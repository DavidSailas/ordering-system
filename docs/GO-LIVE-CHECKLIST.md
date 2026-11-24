# Go-live checklist

Work through this top to bottom. Run `php artisan canteen:preflight` at the end; it checks the technical items for you.

## 1. Settings (`.env`)

- [ ] Start from `.env.production.example`.
- [ ] `APP_ENV=production` and **`APP_DEBUG=false`** (with debug on, an error page shows code and settings).
- [ ] `APP_KEY` generated once (`php artisan key:generate --show`) and kept secret.
- [ ] `APP_URL` starts with `https://`, and `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`.
- [ ] **`APP_TIMEZONE=Asia/Manila`** (otherwise all times are 8 hours behind).
- [ ] A real database (MySQL/PostgreSQL), and real email (`MAIL_MAILER=smtp`) so sign-up codes arrive.
- [ ] The real `.env` is **never** committed or sent around (it holds passwords). If it was shared, change those passwords.

## 2. Accounts

- [ ] Delete the demo accounts (`admin@example.com`, `cashier@example.com`, `owner.*@example.com`, `runner.*@example.com`, `student@example.com`) or give them strong passwords. `php artisan canteen:preflight` fails while any still uses `password`.
- [ ] Create the real overall administrator, then each canteen's owner, cashiers and runners.
- [ ] Do **not** run `db:seed` or `MockDataSeeder` on the live database (it replaces all canteens, dishes and orders).

## 3. School list and ID tap

- [ ] **Admin → School IDs:** delete the 7 sample people and import the real list (students and employees).
- [ ] Decide: ID **optional** (name is filled in when tapped) or **required** (Order security → School ID at checkout). Don't make it required until the list is loaded.
- [ ] Kiosk tablets: connect a barcode/QR/NFC reader that "types" the ID; test it once with a real ID card.

## 4. Money and delivery

- [ ] Each canteen: GCash / Maya number and name (Admin → Settings), and a test payment.
- [ ] **Admin → Delivery & fees:** check building names, floors, base fee, fee per floor, and which floor the canteen is on.
- [ ] Runners know that only **paid** delivery orders appear on their screen.

## 5. Scheduled task (important)

- [ ] The server runs, every minute: `php artisan schedule:run`. This cancels unpaid orders on time. (Without it, they are still cancelled whenever a cashier board or "My orders" page is open, but not exactly on time.)

## 6. Test it like a customer

- [ ] Pickup order with cash, pay at the counter, kitchen, done.
- [ ] Delivery order with GCash, cashier verifies, runner sees it.
- [ ] An unpaid order cancels itself; the missed order shows on the cashier's board.
- [ ] A paused ID is refused, and the administrator can lift the pause.
- [ ] `php artisan test` passes on the machine you deploy from.

## 7. Backups

- [ ] The database is backed up automatically, and you have restored a backup at least once.
