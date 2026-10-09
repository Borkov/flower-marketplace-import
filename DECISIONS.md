# DECISIONS.md — журнал решений

> Тестовое задание: Senior Fullstack (Growth). Репозиторий: `flower-marketplace-import`.  
> Документ живой: дополняем по мере реализации. Сейчас зафиксированы **scaffold + план + допущения по фикстуре**.

---

## 1. Как запустить

См. [README.md](./README.md). Кратко:

```bash
docker compose up --build
```

- UI: http://localhost:4200  
- API health: http://localhost:8080/api/health  

**Примечание:** на первом `up` контейнер `php` выполняет `composer install`; `frontend` — `npm install`. Нужен запущенный Docker Desktop.

---

## 2. Ключевые решения

### 2.1. Архитектура репозитория

| Решение | Альтернативы | Почему так |
|---------|--------------|------------|
| Monorepo: `backend/` + `frontend/` + `docker/` | Два репозитория, submodules | Одна инструкция для проверяющих, один `docker compose up` |
| Demo shop `id = 1` без авторизации | JWT, multi-tenant UI | В ТЗ auth не нужен; shop_id в URL оставляем для совместимости с будущим multi-shop |
| Тарифы районов в MySQL (seed + таблица) | JSON в коде, env | Легко менять пороги без деплоя; правила срочности — в коде (`DeliveryCalculator`) |
| Доставка считается **при импорте** и сохраняется в заказе | Пересчёт при каждом GET | Предсказуемый список, меньше сюрпризов при смене тарифов; при повторном импорте меняем только статус — **доставку не пересчитываем** (см. 2.6) |
| Деньги: `DECIMAL(10,2)` в рублях | Копейки в INT | Проще читать в SQL и в UI; для теста достаточно |
| Время: `DateTimeImmutable` UTC в БД, вход ISO8601 с offset | Всё в MSK строками | Symfony + MySQL; сравнение для срочности в одной TZ |

### 2.2. Идempotентность и дубли

| Решение | Альтернативы | Почему так |
|---------|--------------|------------|
| Уникальный ключ `(shop_id, marketplace_id)` | Только проверка в коде | Гарантия на уровне БД |
| Импорт одной HTTP-транзакции на пачку: per-order savepoint или отдельные flush без abort всей транзакции | Одна большая транзакция all-or-nothing | ТЗ: один плохой заказ не роняет пачку |
| Дубль **внутри пачки**: первое вхождение обрабатывается, второе → outcome `duplicate` | Отклонять оба | Явное правило из ТЗ |
| Параллельные импорты: `SELECT ... FOR UPDATE` по shop или advisory lock на время импорта | Только UNIQUE без обработки | Второй запрос ждёт или получает тот же результат; опишем в коде и тесте |
| UI: disable кнопки + опциональный заголовок `Idempotency-Key` | Только бэкенд | Снижает двойной клик; ключ — запасной уровень |

**Что увидит второй параллельный запрос (план):** после commit первого — те же заказы `unchanged` / `updated`; во время commit — блокировка или короткое ожидание, без второго insert.

### 2.3. API (план)

```
POST /api/shops/{shopId}/orders/import
Body: { "orders": [ ... ] }  // как в фикстуре

GET  /api/shops/{shopId}/orders?status=new&page=1&limit=20
```

**Ответ импорта (план):**

```json
{
  "results": [
    {
      "marketplace_id": "MP-1001",
      "outcome": "created|updated|unchanged|duplicate|rejected",
      "reason": "human readable or code",
      "order_id": 42
    }
  ],
  "summary": { "created": 0, "updated": 0, "unchanged": 0, "duplicate": 0, "rejected": 0 }
}
```

Коды отказа (план): `invalid_phone`, `unknown_status`, `missing_field`, `unknown_district`, `duplicate_in_batch`, …

Пагинация: limit/offset или page/limit — **page/limit** для простоты UI.

### 2.4. Тесты (план)

| Уровень | Объём | Обоснование |
|---------|-------|-------------|
| Unit (PHPUnit) | PhoneNormalizer, StatusMapper, DeliveryCalculator, duplicate-in-batch | Быстро, покрывает «сломается незаметно» |
| Integration | 1× полный импорт фикстуры + 1× повторный импорт | Проверка UNIQUE и unchanged |
| E2E | Не планируем в первой версии | Дорого; мобильный UI проверим вручную |

**Что сломается первым (гипотеза):** гонка двух импортов; срочность (+200 ₽) при неверной интерпретации `time_from` vs `created_at`; тихое изменение суммы при повторном импорте, если случайно пересчитать items.

### 2.5. Фронтенд

| Решение | Почему |
|---------|--------|
| Standalone components, signals для loading/error/list | Контекст работодателя |
| Mobile-first: одна колонка, sticky CTA «Загрузить заказы» | Большинство пользователей с телефона |
| Результат импорта — список карточек по `marketplace_id`, не широкая таблица | Читаемость на узком экране |
| Фикстура только в `assets/`, кнопка отправляет её на API | Требование ТЗ |

### 2.6. Повторный импорт существующего заказа

- Меняется **только** поле статуса (если не финальный `delivered` / `cancelled`).
- Финальные статусы **не откатываем**; если маркетплейс прислал более «ранний» статус — игнорируем, outcome `unchanged`, reason `final_status_locked`.
- Поля customer, delivery, items, суммы, `needs_review`, delivery_fee **не обновляем** при повторном импорте (допущение: маркетплейс в этой интеграции шлёт апдейты статуса). Если бы обновлялись адрес/состав — нужна отдельная политика; для теста фиксируем минимализм.

---

## 3. Что сознательно не делаем

| Не делаем | Риск |
|-----------|------|
| Авторизация | Любой с URL может импортировать в shop 1 — для prod нужен auth |
| CI/CD, деплой | Ручная проверка через Docker |
| Очереди/async import | Большие пачки блокируют HTTP — для 11 заказов OK |
| Админка тарифов | Тарифы в seed; новый район = миграция/SQL |
| i18n | UI на русском, код/коды ошибок на английском |
| E2E (Playwright/Cypress) | Регресс UI вручную |

---

## 4. Допущения и разбор фикстуры (11 записей в пачке)

Обозначения outcome: **created**, **updated**, **unchanged**, **duplicate**, **rejected**.

### 4.1. Общие правила трактовки

1. **Срочность +200 ₽:** сравниваем начало слота доставки `delivery.date + delivery.time_from` (Europe/Moscow) с `created_at + 2 hours`. Если слот **строго раньше** этого момента — срочность. (Если слот ровно на границе 2 ч — **не** срочный.)
2. **Бесплатная доставка:** обнуляет только **базу** района; срочность применяется поверх.
3. **Pickup:** доставка 0; район/адрес/интервал не требуем.
4. **Delivery:** обязательны district, address, date, time_from, time_to.
5. **Сумма для тарифов и `needs_review`:** `sum(items.qty * items.price)`; при расхождении с `total` — сохраняем заказ, `needs_review = true`, в расчётах используем сумму позиций.
6. **Телефон:** нормализация к `+7XXXXXXXXXX` (10 цифр после 7); иначе rejected `invalid_phone`.

### 4.2. Построчно по ID

#### MP-1001 (1-е вхождение, index 0)

- Валидный NEW, delivery centre, items 3200 = total.
- Доставка: 3200 < 5000 → base 300; слот 2026-10-10 14:00 vs created 2026-10-08 09:00 + 2h — не срочно.
- **Первый импорт:** created, delivery_fee = 300, grand = 3500.

#### MP-1002

- ACCEPTED → `accepted`; phone `89001112233` → `+79001112233`.
- north, items 2×2500 = 5000 = total; 5000 < 7000 → base 400.
- Слот 2026-10-10 12:00, created 2026-10-10 11:00 + 2h = 13:00 → 12:00 < 13:00 → **срочно +200**.
- delivery_fee = 600, grand = 5600.
- **created.**

#### MP-1003

- centre, items 6400 ≥ 5000 → base 0; не срочно.
- **created**, delivery 0, grand 6400.

#### MP-1004

- pickup → delivery 0.
- Phone `8 900 444 33 22` → `+79004443322`.
- **created.**

#### MP-1005

- DONE → `delivered`; south, items 2800, total OK; 2800 < 7000 → base 400, не срочно (слот на следующий день после created).
- **created** (исторический заказ в первой загрузке).

#### MP-1006

- district `zarechye` нет в тарифах.
- **rejected**, reason/code `unknown_district` (остальные в пачке сохраняются).

#### MP-1007

- phone `123` не нормализуется.
- **rejected** `invalid_phone`.

#### MP-1008

- status `PACKING` не в маппинге.
- **rejected** `unknown_status`.

#### MP-1009

- suburb: base 700, free threshold нет; items 3600 ≠ total 3000 → **created** + `needs_review`.
- delivery_fee = 700, grand = 3600 + 700 = 4300 (items sum для расчётов).

#### MP-1001 (2-е вхождение, index 9)

- Тот же id в той же пачке.
- **duplicate** (первое уже обработано в этой же request).

#### MP-1010

- CANCELED → `cancelled`; centre, 2000, delivery base 300 (2000 < 5000), не срочно.
- **created**, grand 2300.

### 4.3. Ожидаемый summary первого импорта (9 сохранённых)

| Outcome | IDs |
|---------|-----|
| created | MP-1001 (1st), MP-1002 … MP-1005, MP-1009, MP-1010 |
| duplicate | MP-1001 (2nd) |
| rejected | MP-1006, MP-1007, MP-1008 |

**created count:** 7 уникальных заказов в БД (1001, 1002, 1003, 1004, 1005, 1009, 1010).

### 4.4. Второй полный импорт той же фикстуры

- Уже существующие с тем же статусом → **unchanged** (кроме duplicate in batch для второго MP-1001 → **duplicate** снова).
- Если в JSON сменить статус NEW → ACCEPTED для существующего нефинального → **updated** (только status).

---

## 5. Учёт времени

| Этап | Часы (оценка) | Факт |
|------|---------------|------|
| Scaffold: Docker, Symfony/Angular каркас, фикстура, DECISIONS черновик | 2–3 | _заполнить_ |
| Схема БД + сущности | 2–3 | |
| Домен импорта + доставка | 5–8 | |
| API + CORS | 2–3 | |
| Unit/integration тесты | 2–4 | |
| UI: список, фильтр, результат импорта | 4–6 | |
| Прогон фикстуры, правки, README | 1–2 | |
| **Итого (план)** | **~18–29** | |

---

## 6. Использование AI

| Область | Как используем | Что проверяем руками |
|---------|----------------|----------------------|
| Scaffold Docker/Symfony/Angular | Cursor: генерация compose, конфигов, DECISIONS | Запуск `docker compose up`, health, proxy |
| Доменная логика (далее) | Черновики сервисов | PHPUnit, разбор фикстуры построчно |
| UI (далее) | Разметка mobile-first | Реальный viewport / DevTools |

**Переделывали / ожидаем переделки:** nginx `SCRIPT_FILENAME`, line endings в `entrypoint.sh`, согласование «не пересчитывать доставку при update» с кодом импорта.

---

## 7. Открытые вопросы (если спросят employer — ответ по умолчанию)

1. **Несколько магазинов:** API с `shopId`, в demo только `1`.
2. **Обновление items при re-import:** не делаем; только status.
3. **Итог в UI:** показываем `items_sum`, `delivery_fee`, `grand_total`; marketplace `total` — справочно если `needs_review`.

---

## 8. Что сделано в коде

- [x] Entities + UNIQUE `(shop_id, marketplace_id)`
- [x] PhoneNormalizer, StatusMapper, DeliveryCalculator, OrderImporter
- [x] `POST /api/shops/{id}/orders/import` и `GET /api/shops/{id}/orders`
- [x] Angular: результат импорта, список, фильтр статуса
- [x] PHPUnit: MP-1002 срочность, MP-1009 review, дубль MP-1001

Параллельные импорты сериализуются `SELECT ... FOR UPDATE` по строке магазина: второй запрос ждёт и видит уже сохранённые заказы (`unchanged` / `updated`).
