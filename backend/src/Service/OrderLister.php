<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopOrder;
use App\Enum\OrderStatus;
use App\Exception\InvalidStatusFilterException;
use App\Exception\ShopNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

final class OrderLister
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, limit: int, total: int}
     */
    public function list(int $shopId, ?string $status, int $page, int $limit): array
    {
        $shop = $this->em->find(Shop::class, $shopId);
        if ($shop === null) {
            throw new ShopNotFoundException('Shop not found');
        }

        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        $qb = $this->em->getRepository(ShopOrder::class)->createQueryBuilder('o')
            ->andWhere('o.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('o.id', 'ASC');

        if ($status !== null && $status !== '') {
            $enum = OrderStatus::tryFrom($status);
            if ($enum === null) {
                // Silent fallback to "all orders" would look like a broken filter in the UI.
                throw new InvalidStatusFilterException($status);
            }
            $qb->andWhere('o.status = :status')->setParameter('status', $enum);
        }

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(o.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $orders = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = [];
        /** @var ShopOrder $order */
        foreach ($orders as $order) {
            $items[] = [
                'id' => $order->getId(),
                'marketplace_id' => $order->getMarketplaceId(),
                'status' => $order->getStatus()->value,
                'customer_name' => $order->getCustomerName(),
                'customer_phone' => $order->getCustomerPhone(),
                'delivery_type' => $order->getDeliveryType()->value,
                'district' => $order->getDistrict(),
                'address' => $order->getAddress(),
                'items_sum' => $order->getItemsSum(),
                'delivery_fee' => $order->getDeliveryFee(),
                'grand_total' => $order->getGrandTotal(),
                'marketplace_total' => $order->getMarketplaceTotal(),
                'needs_review' => $order->needsReview(),
            ];
        }

        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }
}
