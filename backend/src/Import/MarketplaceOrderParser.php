<?php

declare(strict_types=1);

namespace App\Import;

use App\Domain\Money;
use App\Enum\DeliveryType;
use App\Service\PhoneNormalizer;
use App\Service\StatusMapper;
use DateTimeImmutable;

final class MarketplaceOrderParser
{
    /** Column limits from the shop_order / shop_order_item schema. */
    private const MAX_ID = 64;
    private const MAX_NAME = 255;
    private const MAX_PHONE = 16;
    private const MAX_DISTRICT = 32;
    private const MAX_ADDRESS = 512;
    private const MAX_SKU = 64;
    private const MAX_ITEM_NAME = 255;

    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly StatusMapper $statuses,
    ) {
    }

    public function parse(mixed $raw): ParseResult
    {
        if (!is_array($raw)) {
            return ParseResult::reject('', 'invalid_payload', 'Запись заказа должна быть объектом');
        }

        $id = isset($raw['id']) && is_scalar($raw['id']) ? trim((string) $raw['id']) : '';
        if ($id === '') {
            return ParseResult::reject('', 'missing_id', 'Нет идентификатора заказа');
        }

        if (mb_strlen($id) > self::MAX_ID) {
            return ParseResult::reject($id, 'field_too_long', 'Идентификатор заказа длиннее ' . self::MAX_ID . ' символов');
        }

        $statusRaw = isset($raw['status']) && is_scalar($raw['status']) ? (string) $raw['status'] : '';
        $status = $this->statuses->map($statusRaw);
        if ($status === null) {
            return ParseResult::reject($id, 'unknown_status', 'Неизвестный статус: ' . $statusRaw);
        }

        $createdRaw = isset($raw['created_at']) && is_scalar($raw['created_at']) ? (string) $raw['created_at'] : '';
        try {
            $createdAt = new DateTimeImmutable($createdRaw);
        } catch (\Exception) {
            return ParseResult::reject($id, 'invalid_created_at', 'Некорректная дата создания');
        }

        $customer = is_array($raw['customer'] ?? null) ? $raw['customer'] : [];
        $name = isset($customer['name']) && is_scalar($customer['name']) ? trim((string) $customer['name']) : '';
        if ($name === '') {
            return ParseResult::reject($id, 'missing_customer_name', 'Нет имени покупателя');
        }

        if (mb_strlen($name) > self::MAX_NAME) {
            return ParseResult::reject($id, 'field_too_long', 'Имя покупателя длиннее ' . self::MAX_NAME . ' символов');
        }

        $phoneRaw = isset($customer['phone']) && is_scalar($customer['phone']) ? (string) $customer['phone'] : '';
        $phone = $this->phones->normalize($phoneRaw);
        if ($phone === null) {
            return ParseResult::reject($id, 'invalid_phone', 'Телефон нельзя привести к +7XXXXXXXXXX');
        }

        if (mb_strlen($phone) > self::MAX_PHONE) {
            return ParseResult::reject($id, 'field_too_long', 'Телефон длиннее ' . self::MAX_PHONE . ' символов');
        }

        $itemsRaw = $raw['items'] ?? null;
        if (!is_array($itemsRaw) || $itemsRaw === []) {
            return ParseResult::reject($id, 'missing_items', 'Нужна хотя бы одна позиция');
        }

        $items = [];
        $itemsSum = Money::fromKopecks(0);
        foreach ($itemsRaw as $itemRaw) {
            if (!is_array($itemRaw)) {
                return ParseResult::reject($id, 'invalid_item', 'Некорректная позиция заказа');
            }
            $sku = isset($itemRaw['sku']) && is_scalar($itemRaw['sku']) ? (string) $itemRaw['sku'] : '';
            $itemName = isset($itemRaw['name']) && is_scalar($itemRaw['name']) ? (string) $itemRaw['name'] : '';
            $qty = isset($itemRaw['qty']) && is_numeric($itemRaw['qty']) ? (int) $itemRaw['qty'] : 0;
            if ($sku === '' || $itemName === '' || $qty < 1 || !isset($itemRaw['price']) || !is_numeric($itemRaw['price'])) {
                return ParseResult::reject($id, 'invalid_item', 'Позиция без sku/name/qty/price');
            }

            if (mb_strlen($sku) > self::MAX_SKU) {
                return ParseResult::reject($id, 'field_too_long', 'SKU длиннее ' . self::MAX_SKU . ' символов');
            }

            if (mb_strlen($itemName) > self::MAX_ITEM_NAME) {
                return ParseResult::reject($id, 'field_too_long', 'Название позиции длиннее ' . self::MAX_ITEM_NAME . ' символов');
            }

            $price = Money::fromRubles($itemRaw['price']);
            if ($price->kopecks < 0) {
                return ParseResult::reject($id, 'invalid_item', 'Цена позиции отрицательная');
            }
            $price = Money::fromRubles($itemRaw['price']);
            $line = Money::fromKopecks($price->kopecks * $qty);
            $itemsSum = $itemsSum->add($line);
            $items[] = [
                'sku' => $sku,
                'name' => $itemName,
                'qty' => $qty,
                'price' => $price,
            ];
        }

        $marketplaceTotal = isset($raw['total']) && is_numeric($raw['total'])
            ? Money::fromRubles($raw['total'])
            : null;
        if ($marketplaceTotal === null) {
            return ParseResult::reject($id, 'missing_total', 'Нет суммы заказа');
        }

        if ($marketplaceTotal->kopecks < 0) {
            return ParseResult::reject($id, 'invalid_total', 'Сумма заказа отрицательная');
        }

        $needsReview = $marketplaceTotal->kopecks !== $itemsSum->kopecks;

        $delivery = is_array($raw['delivery'] ?? null) ? $raw['delivery'] : [];
        $typeRaw = isset($delivery['type']) && is_scalar($delivery['type']) ? (string) $delivery['type'] : '';
        $type = DeliveryType::tryFrom($typeRaw);
        if ($type === null) {
            return ParseResult::reject($id, 'invalid_delivery_type', 'Тип доставки должен быть delivery или pickup');
        }

        $district = null;
        $address = null;
        $date = null;
        $timeFrom = null;
        $timeTo = null;

        if ($type === DeliveryType::Delivery) {
            $district = isset($delivery['district']) && is_scalar($delivery['district']) ? trim((string) $delivery['district']) : '';
            $address = isset($delivery['address']) && is_scalar($delivery['address']) ? trim((string) $delivery['address']) : '';
            $date = isset($delivery['date']) && is_scalar($delivery['date']) ? trim((string) $delivery['date']) : '';
            $timeFrom = isset($delivery['time_from']) && is_scalar($delivery['time_from']) ? trim((string) $delivery['time_from']) : '';
            $timeTo = isset($delivery['time_to']) && is_scalar($delivery['time_to']) ? trim((string) $delivery['time_to']) : '';
            if ($district === '' || $address === '' || $date === '' || $timeFrom === '' || $timeTo === '') {
                return ParseResult::reject($id, 'missing_delivery_fields', 'Для доставки нужны район, адрес и интервал');
            }

            if (mb_strlen($district) > self::MAX_DISTRICT) {
                return ParseResult::reject($id, 'field_too_long', 'Код района длиннее ' . self::MAX_DISTRICT . ' символов');
            }

            if (mb_strlen($address) > self::MAX_ADDRESS) {
                return ParseResult::reject($id, 'field_too_long', 'Адрес длиннее ' . self::MAX_ADDRESS . ' символов');
            }
        }

        return ParseResult::ok(new ParsedMarketplaceOrder(
            marketplaceId: $id,
            status: $status,
            createdAt: $createdAt,
            customerName: $name,
            phone: $phone,
            deliveryType: $type,
            district: $district === '' ? null : $district,
            address: $address === '' ? null : $address,
            deliveryDate: $date === '' ? null : $date,
            timeFrom: $timeFrom === '' ? null : $timeFrom,
            timeTo: $timeTo === '' ? null : $timeTo,
            items: $items,
            marketplaceTotal: $marketplaceTotal,
            itemsSum: $itemsSum,
            needsReview: $needsReview,
        ));
    }
}
