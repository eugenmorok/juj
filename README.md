# RPG Arena

Браузерная RPG-арена сущностей на Laravel. Игрок регистрируется, создает сущность с характеристиками SPECIAL, покупает предметы, переносит их между инвентарями, экипирует сущность и запускает бой на арене против другого игрока или стартового бота.

Проект ведется по документам:

- `../outputs/rpg-arena-technical-spec.md`
- `../outputs/rpg-arena-implementation-plan.md`

## MVP

В текущем MVP есть:

- регистрация, вход и выход;
- роли игрока и администратора;
- админка Filament для справочников, предметов, навыков, ботов и баланса;
- типы и виды сущностей со стартовыми SPECIAL;
- создание сущности с распределением 100 очков;
- навыки, покупка навыков за очки развития;
- общий инвентарь игрока и инвентари сущностей;
- применение зелий и расходников из инвентаря;
- 10 слотов экипировки;
- предметы с редкостью: обычный, редкий, элитный, уникальный;
- магазин предметов, услуг и расширения инвентаря;
- арена, боевой движок, лог боя, награды, повышение уровня;
- псевдо-игроки для матчмейкинга, если реальных игроков мало;
- тесты основных игровых сценариев.

## Stack

- PHP 8.4
- Laravel 13
- Blade
- Tailwind CSS
- Vite
- Filament
- PostgreSQL для тестового и production-окружения
- SQLite можно использовать только для быстрого локального эксперимента

## Local Setup

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Для frontend-разработки:

```bash
npm run dev
```

Во втором терминале:

```bash
php artisan serve
```

После `migrate --seed` будут созданы:

- стартовый каталог типов и видов сущностей;
- стартовые навыки;
- 10 слотов экипировки;
- стартовые предметы магазина;
- стартовые боты для арены;
- администратор, если задан `ADMIN_PASSWORD`.

## PostgreSQL

Шаблон для PostgreSQL:

```bash
copy .env.pgsql.example .env
```

Заполнить:

```env
DB_DATABASE=rpg_arena
DB_USERNAME=rpg_arena
DB_PASSWORD=
```

Затем:

```bash
php artisan key:generate
php artisan migrate --seed
```

## Production Env

Для production используй отдельный шаблон:

```bash
copy .env.production.example .env
```

Обязательно заполнить:

```env
APP_KEY=
APP_URL=
DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

Ключ приложения:

```bash
php artisan key:generate
```

Важно: `ADMIN_PASSWORD` должен быть заполнен до первого production seed. Если пароль пустой, production seeder не создаст администратора.

## Deploy Checklist

На сервере после получения кода:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Очередь:

```bash
php artisan queue:work --tries=3
```

Планировщик:

```bash
* * * * * cd /var/www/rpg-arena && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test
vendor/bin/pint --dirty
npm run build
```

Текущие feature-тесты покрывают регистрацию, создание сущности, бюджет 100 очков, магазин, инвентарь, расходники, экипировку, бой, награды, повышение уровня, ботов, админ-доступ и сквозной MVP-цикл.

## Release Docs

- [Manual test checklist](docs/manual-test-checklist.md)
- [MVP limitations](docs/mvp-limitations.md)
- [Next improvements](docs/next-improvements.md)

## Default Admin

Локально, если `ADMIN_PASSWORD` пустой, используется пароль `password`.

В production пустой `ADMIN_PASSWORD` запрещает автосоздание администратора. Это сделано специально, чтобы не выкатывать публичный сервер с дефолтным паролем.
