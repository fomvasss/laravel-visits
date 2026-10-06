# Laravel Visits

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-visits.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-visits)

Self-hosted аналітика для Laravel у твоїй власній БД: трекінг відвідувачів, сесій і переглядів сторінок, гео/девайс/бот-детекція, атрибуція кампаній, конверсії, прив'язані до твоїх Eloquent-моделей, щоденні rollup-и та вбудований дашборд з картою активності.

[English](README.md) · Документація (англійською): **https://fomvasss.github.io/laravel-visits/**

![Dashboard](docs/images/dashboard.gif)

- **Visitor → Session → Event** — стабільна ідентичність, сесії, перегляди й кастомні дії замість однієї плоскої таблиці
- **Асинхронно** — у запиті лише визначається visitor id; гео, детекція девайса й записи в БД — у job на черзі
- **Три точки входу** — автоматичні перегляди (middleware), JSON-ендпоінт для JS beacon і мобільних застосунків, `Visits::track()` з PHP
- **Ідентичність** — cookie, `X-Visitor-Id` від клієнта, повторне з'єднання залогінених юзерів на Bearer-token API, злиття при логіні або через `Visits::identify()`
- **Атрибуція** — UTM/`ref` first touch і last touch, click ID рекламних платформ, пошукові запити
- **Дашборд** — Overview з картою сесій, Campaigns, Sessions, Visitors, Live, Whoami

## Вимоги

- PHP ^8.3
- Laravel ^12 | ^13
- Воркер черги і scheduler

## Встановлення

```bash
composer require fomvasss/laravel-visits
php artisan migrate

php artisan vendor:publish --tag=visits-config # за потреби
php artisan vendor:publish --tag=visits-assets # за потреби, JS beacon
```

> **Безпека:** дашборд на `/visits` за замовчуванням відкритий усім. Перед деплоєм задай `dashboard.middleware`, напр. `['web', 'auth']`.

## Швидкий старт

Кожен `GET`-запит через middleware-групу `web` уже трекається; дашборд — на `/visits`.

```php
use Fomvasss\Visits\Facades\Visits;

Visits::track('order.placed', $order, ['amount' => $order->total]);

// вебхук оплати: cookie немає, відвідувача беремо з попередньої події
Visits::track('order.paid', $order, inheritFrom: 'order.placed');
```

Модель `$order` має використовувати трейт `Fomvasss\Visits\Concerns\HasVisits`.

## Документація

Повна документація — англійською: **https://fomvasss.github.io/laravel-visits/** (ті самі сторінки лежать у [docs/](docs/index.md)).

- [Встановлення](docs/installation.md) · [Конфігурація](docs/configuration.md) · [Як працює трекінг](docs/usage/how-it-works.md)
- [Production checklist](docs/guides/production.md) · [Інтеграція клієнтів](docs/guides/client-integration.md) · [Безпека](docs/guides/security.md)
- [Оновлення](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## Ліцензія

MIT — див. [LICENSE](LICENSE.md).

## Підтримка

Якщо пакет тобі корисний, можеш підтримати його розвиток:

[![Monobank](https://img.shields.io/badge/Donate-Monobank-black)](https://send.monobank.ua/jar/5xsqtHvVrY)
[![Ko-Fi](https://img.shields.io/badge/Donate-Ko--fi-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/fomvasss)
[![USDT TRC20](https://img.shields.io/badge/Donate-USDT%20TRC20-26A17B?logo=tether&logoColor=white)](https://link.trustwallet.com/send?coin=195&address=THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf&token_id=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t)

> USDT TRC20: `THLgp6DxiAtbNHvgnKV56vk1L38UuUagKf`
