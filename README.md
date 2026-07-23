# Handy Herdsman

Cattle AI, herd management, and ranch services — Valley Mills, TX.
A single Laravel app that is three things at once: a marketing/SEO site, a
protocol-aware booking system, and a light herd CRM/records portal.

See [`docs/handy-herdsman-spec.md`](docs/handy-herdsman-spec.md) — **the source
of truth** — and [`docs/handy-herdsman-issues.md`](docs/handy-herdsman-issues.md)
for the milestone/issue plan.

## Stack

- **Laravel 13** + **Vue starter kit** (Inertia 3, Composition API, TypeScript, Tailwind, shadcn-vue)
- **PostgreSQL**
- **Spatie laravel-permission** (teams mode — each client team is a tenant, `team_id` scopes data)
- **Laravel Cashier + Stripe** (stored payment method charged on confirm; no Stripe invoicing; no tax)
- **Queue + scheduler** for reminders — sent.dm (SMS) and Resend (email) behind notification channels
- **Laravel Cloud** hosting

## Guiding principles

- **Laravel-first, custom-last** — always prefer an official Laravel package.
- **Config over code** — prices, services, service-area rules, gestation values, and availability are editable data, never hardcoded.
- **Mobile-first, not mobile-responsive** — clients and Jeff are on phones in the field.

## M0 — Foundation (this repo, as of the initial commit)

- Laravel 13 + Vue starter kit, PostgreSQL, Inertia 3 build verified.
- Spatie teams-mode auth; roles seeded: `staff`, `client_owner`, `client_member`, `vet`.
- Laravel Cashier installed; **Team is the Billable entity** (billing per client account).
- **Full data model** (spec §6) — all tables migrated; soft deletes throughout.
- **Editable config** (spec §7): `rate_config`, `services`, `service_area_rules`,
  `gestation_config`, `availability_rules`, `blackout_dates` — all seeded.
- Protocol timing constants in `config/protocol.php`; reminder defaults in `config/reminders.php`.

Everything after M0 is tracked as GitHub issues (M1–M11) for the coding agent.

## Local setup

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
# configure DB_* for your local Postgres (database: handy_herdsman)
php artisan migrate:fresh --seed
npm run dev   # or: composer run dev
```
