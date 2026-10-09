<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopOrder;
use App\Entity\ShopOrderItem;
use App\Enum\ImportOutcome;
use App\Exception\ShopNotFoundException;
use App\Import\ImportDecisionMaker;
use App\Import\ImportLineResult;
use App\Import\MarketplaceOrderParser;
use App\Import\ParsedMarketplaceOrder;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class OrderImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MarketplaceOrderParser $parser,
        private readonly ImportDecisionMaker $decisions,
        private readonly TariffCatalog $tariffs,
    ) {
    }

    /**
     * @param list<mixed> $rawOrders
     *
     * @return array{results: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function import(int $shopId, array $rawOrders): array
    {
        $this->em->beginTransaction();
        try {
            $shop = $this->em->find(Shop::class, $shopId, LockMode::PESSIMISTIC_WRITE);
            if ($shop === null) {
                throw new ShopNotFoundException('Shop not found');
            }

            $calculator = $this->tariffs->calculator();
            $seen = [];
            $results = [];

            foreach ($rawOrders as $raw) {
                $parsed = $this->parser->parse($raw);
                $id = $parsed->marketplaceId;
                $existing = $id === ''
                    ? null
                    : $this->em->getRepository(ShopOrder::class)->findOneBy([
                        'shop' => $shop,
                        'marketplaceId' => $id,
                    ]);

                $decision = $this->decisions->decide(
                    seenInBatch: isset($seen[$id]) && $id !== '',
                    existingStatus: $existing?->getStatus(),
                    parsed: $parsed,
                );

                if ($id !== '') {
                    $seen[$id] = true;
                }

                if ($decision->outcome === ImportOutcome::Created && $parsed->order !== null) {
                    $delivery = $calculator->calculate(
                        $parsed->order->deliveryType,
                        $parsed->order->district,
                        $parsed->order->itemsSum,
                        $parsed->order->createdAt,
                        $parsed->order->deliveryDate,
                        $parsed->order->timeFrom,
                    );
                    if (isset($delivery['error'])) {
                        $results[] = new ImportLineResult(
                            $id,
                            ImportOutcome::Rejected,
                            (string) $delivery['error'],
                            null,
                        );
                        continue;
                    }

                    $order = $this->persistNew($shop, $parsed->order, $delivery['fee']->toDecimalString());
                    $this->em->persist($order);
                    $this->em->flush();
                    $results[] = new ImportLineResult($id, ImportOutcome::Created, 'created', $order->getId());
                    continue;
                }

                if ($decision->outcome === ImportOutcome::Updated && $existing !== null && $decision->newStatus !== null) {
                    $existing->setStatus($decision->newStatus);
                    $this->em->flush();
                    $results[] = new ImportLineResult($id, ImportOutcome::Updated, $decision->reason, $existing->getId());
                    continue;
                }

                $results[] = new ImportLineResult(
                    $id,
                    $decision->outcome,
                    $decision->reason,
                    $existing?->getId(),
                );
            }

            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'duplicate' => 0,
            'rejected' => 0,
        ];
        $payload = [];
        foreach ($results as $line) {
            ++$summary[$line->outcome->value];
            $payload[] = $line->toArray();
        }

        return [
            'results' => $payload,
            'summary' => $summary,
        ];
    }

    private function persistNew(Shop $shop, ParsedMarketplaceOrder $parsed, string $deliveryFee): ShopOrder
    {
        $order = new ShopOrder();
        $order->setShop($shop);
        $order->setMarketplaceId($parsed->marketplaceId);
        $order->setStatus($parsed->status);
        $order->setCustomerName($parsed->customerName);
        $order->setCustomerPhone($parsed->phone);
        $order->setDeliveryType($parsed->deliveryType);
        $order->setDistrict($parsed->district);
        $order->setAddress($parsed->address);
        $order->setDeliveryDate($parsed->deliveryDate);
        $order->setTimeFrom($parsed->timeFrom);
        $order->setTimeTo($parsed->timeTo);
        $order->setMarketplaceCreatedAt($parsed->createdAt);
        $order->setMarketplaceTotal($parsed->marketplaceTotal->toDecimalString());
        $order->setItemsSum($parsed->itemsSum->toDecimalString());
        $order->setDeliveryFee($deliveryFee);
        $grand = $parsed->itemsSum->add(\App\Domain\Money::fromRubles($deliveryFee));
        $order->setGrandTotal($grand->toDecimalString());
        $order->setNeedsReview($parsed->needsReview);

        foreach ($parsed->items as $itemData) {
            $item = new ShopOrderItem();
            $item->setSku($itemData['sku']);
            $item->setName($itemData['name']);
            $item->setQty($itemData['qty']);
            $item->setPrice($itemData['price']->toDecimalString());
            $order->addItem($item);
        }

        return $order;
    }
}
