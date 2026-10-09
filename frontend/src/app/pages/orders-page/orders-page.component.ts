import { JsonPipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { OrdersApiService } from '../../core/orders-api.service';

const DEMO_SHOP_ID = 1;

@Component({
  selector: 'app-orders-page',
  standalone: true,
  imports: [JsonPipe],
  templateUrl: './orders-page.component.html',
  styleUrl: './orders-page.component.scss',
})
export class OrdersPageComponent {
  private readonly http = inject(HttpClient);
  private readonly ordersApi = inject(OrdersApiService);

  readonly loading = signal(false);
  readonly importResult = signal<unknown | null>(null);
  readonly error = signal<string | null>(null);

  loadFixtureAndImport(): void {
    this.error.set(null);
    this.loading.set(true);

    this.http.get<{ orders: unknown[] }>('/assets/marketplace-orders.json').subscribe({
      next: (fixture) => {
        this.ordersApi.importOrders(DEMO_SHOP_ID, fixture).subscribe({
          next: (result) => {
            this.importResult.set(result);
            this.loading.set(false);
          },
          error: () => {
            this.error.set(
              'Импорт пока не реализован на бэкенде (ожидается POST /api/shops/1/orders/import).',
            );
            this.loading.set(false);
          },
        });
      },
      error: () => {
        this.error.set('Не удалось прочитать assets/marketplace-orders.json');
        this.loading.set(false);
      },
    });
  }
}
