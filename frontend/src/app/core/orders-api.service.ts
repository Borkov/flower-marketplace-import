import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_BASE_URL } from './api-url.token';

export type ImportPayload = {
  orders: unknown[];
};

export type ImportOutcome = 'created' | 'updated' | 'unchanged' | 'duplicate' | 'rejected';

export type ImportLine = {
  marketplace_id: string;
  outcome: ImportOutcome;
  reason: string;
  order_id: number | null;
};

export type ImportResponse = {
  results: ImportLine[];
  summary: Record<ImportOutcome, number>;
};

export type ShopOrderItem = {
  sku: string;
  name: string;
  qty: number;
  price: string;
};

export type ShopOrder = {
  id: number;
  marketplace_id: string;
  status: string;
  customer_name: string;
  customer_phone: string;
  delivery_type: string;
  district: string | null;
  address: string | null;
  delivery_date: string | null;
  time_from: string | null;
  time_to: string | null;
  is_urgent: boolean;
  urgency_fee: string | null;
  items_sum: string;
  delivery_fee: string;
  grand_total: string;
  marketplace_total: string;
  needs_review: boolean;
  created: string | null;
  items: ShopOrderItem[];
};

export type OrderListResponse = {
  items: ShopOrder[];
  page: number;
  limit: number;
  total: number;
};

@Injectable({ providedIn: 'root' })
export class OrdersApiService {
  private readonly http = inject(HttpClient);
  private readonly base = inject(API_BASE_URL);

  importOrders(shopId: number, payload: ImportPayload): Observable<ImportResponse> {
    return this.http.post<ImportResponse>(`${this.base}/api/shops/${shopId}/orders/import`, payload);
  }

  listOrders(shopId: number, status?: string): Observable<OrderListResponse> {
    const params = status ? { status } : undefined;
    return this.http.get<OrderListResponse>(`${this.base}/api/shops/${shopId}/orders`, { params });
  }
}
