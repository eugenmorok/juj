# RPG Arena

Браузерная RPG-арена сущностей на Laravel.

Проект реализуется по зафиксированным документам:

- `../outputs/rpg-arena-technical-spec.md`
- `../outputs/rpg-arena-implementation-plan.md`

## Стек MVP

- Laravel 13
- Blade
- Alpine.js
- Tailwind CSS
- Vite
- PostgreSQL как целевая БД
- SQLite допустим для локального быстрого запуска

## Локальный запуск

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Для разработки frontend:

```bash
npm run dev
```

И в отдельном терминале:

```bash
php artisan serve
```

## PostgreSQL

Для локальной или серверной PostgreSQL-конфигурации можно взять шаблон:

```bash
copy .env.pgsql.example .env
```

Затем заполнить:

```env
DB_DATABASE=rpg_arena
DB_USERNAME=rpg_arena
DB_PASSWORD=
```

После настройки БД:

```bash
php artisan key:generate
php artisan migrate
```

## Этапы

Текущий этап: `Этап 0. Подготовка проекта`.

Ближайшие задачи:

- зафиксировать базовый Laravel-проект в Git;
- проверить сборку Vite;
- проверить `php artisan`;
- перейти к спринту 1: auth, роли и базовая навигация.
