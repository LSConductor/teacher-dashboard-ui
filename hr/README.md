# Living School casual HR

Laravel app for casual lists, clear-to-teach, induction, contracts, and director contact. It does not change the primary program builder on the staff portal. Catalyst (report writer, markbook, rubrics, scope and sequence, outcome audits) is named in `config/modules.php` and switched off.

Local sign-in after seeding, password `living-demo`:

- `hr@living.school`
- `conductor@living.school`
- `director@living.school`
- `office@living.school`
- `mentor@living.school`
- `sam.green@example.com`

```bash
cd hr
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

On DigitalOcean, use managed Postgres in Sydney (`syd1`) and set `DB_CONNECTION=pgsql` in the environment. Keep the database on the private network. Change the demo password before any shared use.

Bookings store a link and id for a program that still lives on the primary portal. This app does not read or write that builder.
