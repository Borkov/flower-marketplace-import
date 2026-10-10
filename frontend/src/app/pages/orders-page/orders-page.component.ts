import { HttpClient } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import {
  ImportLine,
  ImportResponse,
  OrdersApiService,
  ShopOrder,
} from '../../core/orders-api.service';

const DEMO_SHOP_ID = 1;

const STATUS_FILTERS = [
  { value: '', label: 'Все' },
  { value: 'new', label: 'Новые' },
  { value: 'accepted', label: 'Принятые' },
  { value: 'delivering', label: 'В доставке' },
  { value: 'delivered', label: 'Доставленные' },
  { value: 'cancelled', label: 'Отменённые' },
] as const;

const STATUS_LABEL: Record<ShopOrder['status'], string> = {
  new: 'Новый',
  accepted: 'Принят',
  delivering: 'В доставке',
  delivered: 'Доставлен',
  cancelled: 'Отменён',
};

const OUTCOME_LABEL: Record<string, string> = {
  created: 'Создан',
  updated: 'Обновлён',
  unchanged: 'Без изменений',
  duplicate: 'Дубль',
  rejected: 'Отклонён',
};

@Component({
  selector: 'app-orders-page',
  standalone: true,
  templateUrl: './orders-page.component.html',
  styleUrl: './orders-page.component.scss',
})
export class OrdersPageComponent implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly ordersApi = inject(OrdersApiService);

  readonly shopId = DEMO_SHOP_ID;
  readonly statusFilters = STATUS_FILTERS;
  readonly outcomeLabel = OUTCOME_LABEL;
  readonly statusLabel = STATUS_LABEL;

  readonly importing = signal(false);
  readonly loadingList = signal(false);
  readonly importResult = signal<ImportResponse | null>(null);
  readonly orders = signal<ShopOrder[]>([]);
  readonly statusFilter = signal('');
  readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.reloadOrders();
  }

  setFilter(status: string): void {
    this.statusFilter.set(status);
    this.reloadOrders();
  }

  loadFixtureAndImport(): void {
    this.error.set(null);
    this.importing.set(true);

    this.http.get<{ orders: unknown[] }>('/assets/marketplace-orders.json').subscribe({
      next: (fixture) => {
        this.ordersApi.importOrders(DEMO_SHOP_ID, fixture).subscribe({
          next: (result) => {
            this.importResult.set(result);
            this.importing.set(false);
            this.reloadOrders();
          },
          error: () => {
            this.error.set('Не удалось импортировать заказы. Проверьте, что API запущен.');
            this.importing.set(false);
          },
        });
      },
      error: () => {
        this.error.set('Не удалось прочитать assets/marketplace-orders.json');
        this.importing.set(false);
      },
    });
  }

  reasonText(line: ImportLine): string {
    const map: Record<string, string> = {
      created: 'Заказ сохранён',
      duplicate_in_batch: 'Повтор id в этой пачке',
      unknown_district: 'Район без тарифа',
      invalid_phone: 'Телефон нельзя нормализовать',
      unknown_status: 'Неизвестный статус',
      status_unchanged: 'Заказ уже был с таким статусом',
      final_status_locked: 'Финальный статус не откатывается',
      status_updated: 'Обновлён только статус',
      missing_items: 'Нет позиций',
      missing_delivery_fields: 'Нет данных доставки',
      field_too_long: 'Слишком длинное поле',
      persist_failed: 'Не удалось сохранить заказ',
    };
    return map[line.reason] ?? line.reason;
  }

  private reloadOrders(): void {
    this.loadingList.set(true);
    const status = this.statusFilter();
    this.ordersApi.listOrders(DEMO_SHOP_ID, status || undefined).subscribe({
      next: (response) => {
        this.orders.set(response.items);
        this.loadingList.set(false);
      },
      error: () => {
        // Without this the list silently reads as "no orders yet".
        this.orders.set([]);
        this.error.set('Не удалось загрузить список заказов. Проверьте, что API запущен.');
        this.loadingList.set(false);
      },
    });
  }
}
