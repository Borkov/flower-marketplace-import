<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DeliveryType;
use App\Enum\OrderStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
#[ORM\UniqueConstraint(name: 'uniq_shop_marketplace_order', columns: ['shop_id', 'marketplace_id'])]
class ShopOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Shop $shop;

    #[ORM\Column(length: 64)]
    private string $marketplaceId;

    #[ORM\Column(type: 'string', enumType: OrderStatus::class, length: 32)]
    private OrderStatus $status;

    #[ORM\Column(length: 255)]
    private string $customerName;

    #[ORM\Column(length: 16)]
    private string $customerPhone;

    #[ORM\Column(type: 'string', enumType: DeliveryType::class, length: 16)]
    private DeliveryType $deliveryType;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $district = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $deliveryDate = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $timeFrom = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $timeTo = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $marketplaceCreatedAt;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $marketplaceTotal;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $itemsSum;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $deliveryFee;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $grandTotal;

    #[ORM\Column]
    private bool $needsReview = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, ShopOrderItem> */
    #[ORM\OneToMany(targetEntity: ShopOrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function setShop(Shop $shop): void
    {
        $this->shop = $shop;
    }

    public function getMarketplaceId(): string
    {
        return $this->marketplaceId;
    }

    public function setMarketplaceId(string $marketplaceId): void
    {
        $this->marketplaceId = $marketplaceId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): void
    {
        $this->customerName = $customerName;
    }

    public function getCustomerPhone(): string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): void
    {
        $this->customerPhone = $customerPhone;
    }

    public function getDeliveryType(): DeliveryType
    {
        return $this->deliveryType;
    }

    public function setDeliveryType(DeliveryType $deliveryType): void
    {
        $this->deliveryType = $deliveryType;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): void
    {
        $this->district = $district;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getDeliveryDate(): ?string
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?string $deliveryDate): void
    {
        $this->deliveryDate = $deliveryDate;
    }

    public function getTimeFrom(): ?string
    {
        return $this->timeFrom;
    }

    public function setTimeFrom(?string $timeFrom): void
    {
        $this->timeFrom = $timeFrom;
    }

    public function getTimeTo(): ?string
    {
        return $this->timeTo;
    }

    public function setTimeTo(?string $timeTo): void
    {
        $this->timeTo = $timeTo;
    }

    public function getMarketplaceCreatedAt(): DateTimeImmutable
    {
        return $this->marketplaceCreatedAt;
    }

    public function setMarketplaceCreatedAt(DateTimeImmutable $marketplaceCreatedAt): void
    {
        $this->marketplaceCreatedAt = $marketplaceCreatedAt;
    }

    public function getMarketplaceTotal(): string
    {
        return $this->marketplaceTotal;
    }

    public function setMarketplaceTotal(string $marketplaceTotal): void
    {
        $this->marketplaceTotal = $marketplaceTotal;
    }

    public function getItemsSum(): string
    {
        return $this->itemsSum;
    }

    public function setItemsSum(string $itemsSum): void
    {
        $this->itemsSum = $itemsSum;
    }

    public function getDeliveryFee(): string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(string $deliveryFee): void
    {
        $this->deliveryFee = $deliveryFee;
    }

    public function getGrandTotal(): string
    {
        return $this->grandTotal;
    }

    public function setGrandTotal(string $grandTotal): void
    {
        $this->grandTotal = $grandTotal;
    }

    public function needsReview(): bool
    {
        return $this->needsReview;
    }

    public function setNeedsReview(bool $needsReview): void
    {
        $this->needsReview = $needsReview;
    }

    /** @return Collection<int, ShopOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ShopOrderItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
    }
}
