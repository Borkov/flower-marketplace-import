# Flower marketplace import (тестовое задание)

Мини-модуль: импорт заказов маркетплейса (Symfony + MySQL + Angular), расчёт доставки, мобильный UI.

Подробные решения и допущения — в [DECISIONS.md](./DECISIONS.md).

## Требования

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Linux engine)
- Порты **8080** (API), **4200** (UI), **3306** (MySQL, опционально с хоста)

Node/PHP/Composer на хосте **не нужны** — всё в контейнерах.

## Быстрый старт

```bash
cd flower-marketplace-import
docker compose up --build
```

Первый запуск может занять несколько минут: `composer install` в контейнере `php`, `npm install` в `frontend`.

| URL | Назначение |
|-----|------------|
| http://localhost:4200 | Angular UI |
| http://localhost:8080/api/health | Проверка API |

Остановка: `Ctrl+C`, затем при необходимости `docker compose down`.

## Структура

```
backend/          Symfony 7.2, PHP 8.2
frontend/         Angular 19 (standalone, signals на следующих этапах)
docker/           nginx, php entrypoint, MySQL seed тарифов
DECISIONS.md      журнал решений (главный артеfact для review)
```

## Тесты

```bash
docker compose exec php vendor/bin/phpunit
```

## Что уже работает

- [x] Docker Compose (mysql, php-fpm, nginx, frontend)
- [x] Импорт фикстуры, расчёт доставки, список заказов и фильтр по статусу
- [x] Идемпотентность: UNIQUE `(shop_id, marketplace_id)` + `SELECT ... FOR UPDATE` по магазину
- [x] Один плохой заказ не роняет пачку: per-order savepoint + валидация длин в парсере
- [x] Unit-тесты правил фикстуры (телефон, статусы, доставка, дубль в пачке)

## API

| Метод | Путь | Назначение |
|-------|------|------------|
| GET | `/api/health` | проверка живости |
| POST | `/api/shops/{shopId}/orders/import` | импорт пачки, ответ — `results[]` + `summary` |
| GET | `/api/shops/{shopId}/orders?status=&page=&limit=` | список с фильтром по статусу |

`outcome` в ответе импорта: `created`, `updated`, `unchanged`, `duplicate`, `rejected`.
Неизвестный `status` в фильтре → `400 unknown_status_filter`.

## Разработка без Docker (опционально)

Если установлены PHP 8.2+ и Composer локально:

```bash
cd backend
composer install
# DATABASE_URL на localhost:3306 при поднятом только mysql-сервисе
```

Фронт: Node 22+, `cd frontend && npm install && npm start` (proxy `/api` → `http://nginx:80`, то есть прокси рассчитан на сеть Docker Compose).
