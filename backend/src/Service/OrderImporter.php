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

            foreach ($rawOrders as $index => $raw) {
                // Savepoint per order: one bad order must not abort the whole batch (assignment rule 2).
                $savepoint = 'order_' . $index;
                $this->em->getConnection()->createSavepoint($savepoint);

                try {
                    $this->importOne($raw, $shop, $calculator, $seen, $results);
                    $this->em->getConnection()->releaseSavepoint($savepoint);
                } catch (\Throwable $e) {
                    $this->em->getConnection()->rollbackSavepoint($savepoint);
                    // Drop half-managed state so the failed row cannot poison the next flush().
                    $this->em->clear();
                    // The shop row is still locked by this transaction; re-attach it.
                    $shop = $this->em->find(Shop::class, $shopId, LockMode::PESSIMISTIC_WRITE);
                    $results[] = new ImportLineResult(
                        $this->peekMarketplaceId($raw),
                        ImportOutcome::Rejected,
                        'persist_failed',
                        null,
                    );
                }
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

    /**
     * Handles one raw order line and appends its ImportLineResult.
     *
     * @param array<string, true> $seen
     * @param list<ImportLineResult> $results
     */
    private function importOne(
        mixed $raw,
        Shop $shop,
        DeliveryCalculator $calculator,
        array &$seen,
        array &$results,
    ): void {
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

                return;
            }

            $order = $this->persistNew($shop, $parsed->order, $delivery['fee']->toDecimalString(), $delivery['urgent']);
            $this->em->persist($order);
            $this->em->flush();
            $results[] = new ImportLineResult($id, ImportOutcome::Created, 'created', $order->getId());

            return;
        }

        if ($decision->outcome === ImportOutcome::Updated && $existing !== null && $decision->newStatus !== null) {
            $existing->setStatus($decision->newStatus);
            $this->em->flush();
            $results[] = new ImportLineResult($id, ImportOutcome::Updated, $decision->reason, $existing->getId());

            return;
        }

        $results[] = new ImportLineResult(
            $id,
            $decision->outcome,
            $decision->reason,
            $existing?->getId(),
        );
    }

    /**
     * Best-effort id for a line that blew up before/while persisting, so the
     * reject line in the response is still traceable to the marketplace order.
     */
    private function peekMarketplaceId(mixed $raw): string
    {
        if (is_array($raw) && isset($raw['id']) && is_scalar($raw['id'])) {
            return trim((string) $raw['id']);
        }

        return '';
    }

    private function persistNew(Shop $shop, ParsedMarketplaceOrder $parsed, string $deliveryFee, bool $isUrgent): ShopOrder
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
        $order->setIsUrgent($isUrgent);
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
