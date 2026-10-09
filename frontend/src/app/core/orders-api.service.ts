import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_BASE_URL } from './api-url.token';

export type ImportPayload = {
  orders: unknown[];
};

@Injectable({ providedIn: 'root' })
export class OrdersApiService {
  private readonly http = inject(HttpClient);
  private readonly base = inject(API_BASE_URL);

  importOrders(shopId: number, payload: ImportPayload): Observable<unknown> {
    return this.http.post(`${this.base}/api/shops/${shopId}/orders/import`, payload);
  }

  listOrders(shopId: number, status?: string): Observable<unknown> {
    const params = status ? { status } : undefined;
    return this.http.get(`${this.base}/api/shops/${shopId}/orders`, { params });
  }
}
