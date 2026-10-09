<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ShopNotFoundException;
use App\Service\OrderImporter;
use App\Service\OrderLister;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderImporter $importer,
        private readonly OrderLister $lister,
    ) {
    }

    #[Route('/api/shops/{shopId}/orders/import', name: 'api_orders_import', methods: ['POST'])]
    public function import(int $shopId, Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $orders = $payload['orders'] ?? null;
        if (!is_array($orders)) {
            return $this->json(['error' => 'orders_required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->json($this->importer->import($shopId, array_values($orders)));
        } catch (ShopNotFoundException) {
            return $this->json(['error' => 'shop_not_found'], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/shops/{shopId}/orders', name: 'api_orders_list', methods: ['GET'])]
    public function list(int $shopId, Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);

        try {
            return $this->json($this->lister->list(
                $shopId,
                is_string($status) ? $status : null,
                $page,
                $limit,
            ));
        } catch (ShopNotFoundException) {
            return $this->json(['error' => 'shop_not_found'], Response::HTTP_NOT_FOUND);
        }
    }
}
