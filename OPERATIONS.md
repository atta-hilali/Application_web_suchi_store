# Miya Sushi — orders and restaurant settings

This release uses Laravel 12, React 18, Vite 8, TND with three decimals and Africa/Tunis time. Use PHP 8.2+ with maintained security patches and Node 22.12+ or Node 24 for builds/tests. Laravel 12's security support runs through 24 February 2027; plan its next framework upgrade before then.

## First setup

1. Install from the lockfiles: `composer install` and `npm ci`.
2. Configure `.env`, including `APP_TIMEZONE=Africa/Tunis`, the database, application URL and cookie settings. Generate an application key only for a new installation.
3. Run `php artisan migrate`. For a new demonstration installation only, run `php artisan db:seed`. Do not rerun all seeders on an existing client installation: the demo admin/product seeders retain their original behavior.
4. Run `php artisan storage:link` and `npm run build`.
5. Sign in as an administrator, open **Admin Panel → Paramètres**, and review the operating rules.

The demo administrator and public login hint are intentionally unchanged at the owner's request. Replace the demo credentials before making a client deployment public. Online payments and password recovery are outside this release's agreed scope.

## Home background image/video

Open **Admin Panel → Fond d’accueil**. Choose Image or Vidéo, then reuse the saved media, upload a file, or enter a direct HTTPS media link. Preview it and click **Publier ce fond**. Images accept JPG/PNG/WebP up to 8 MiB; videos accept MP4/WebM up to 30 MiB. MP4 with H.264 and a short landscape clip is recommended. YouTube page links are not supported.

The image and video slots are saved separately, so switching to an image does not remove the saved video. Replacing a slot removes only its previous uploaded file after the database update succeeds. Uploaded media live in `storage/app/public/hero/`; persist/back up that directory and ensure the storage symlink works. Set PHP `upload_max_filesize` to at least 30M and `post_max_size`/web-server body limits above 30M (40M recommended) on the target host.

The homepage video loops silently and fills the existing background. Visitors can pause it; reduced-motion preferences and video loading failures use the saved image instead. The initial video is a replaceable sushi-preparation stock sample; provenance and license links are recorded in `public/videos/README.md`. Publish client-approved media before delivery.

Verified for this media addition: 34 backend tests (191 assertions), 16 frontend tests, the production build and ten live HTTP media checks passed. The live checks uploaded the bundled H.264 MP4, verified its serving and size, switched to the saved image and back to video, and checked unauthenticated access denial.

## Restaurant rules

The admin settings screen controls accepting orders, opening days, opening/closing times, holiday dates, delivery availability, allowed postcodes, delivery charge and minimum order. These settings are stored in the database; the restaurant settings seeder preserves operator changes.

Initial defaults are daily 11:30–22:30 in Tunis time, zero delivery fee, zero minimum and delivery disabled until the administrator enters approved four-digit postcodes and enables it. This avoids inventing a service area. Collection is available during opening hours. An overnight period such as 20:00–02:00 is supported; opening days refer to the day that the shift starts. A closed date prevents orders throughout that calendar date.

New orders store TND amounts with three decimals (millimes). Existing product numbers are retained as TND prices; no exchange-rate conversion is performed. Approve the actual menu prices before launch. Pre-existing orders are marked EUR so historical amounts are not silently relabeled or mixed with TND revenue. New TND sales reporting excludes those historical EUR orders.

Existing historical database timestamps should be reviewed against the previous deployment's timezone before importing real historical data. The new application, customer displays and operating-hour calculations use Africa/Tunis.

## Receiving orders

Keep the application open and signed in on the restaurant's operating device. Orders and dashboard counts refresh every 10 seconds after a successful response; they also refresh when the window regains focus. Requests do not overlap. Customers receive the same automatic refresh for acceptance/refusal status.

The administrator sees new-order messages and the browser-tab title shows the pending count. Orders continue to refresh automatically in the background. The refresh/time status strip and sound controls have been removed at the owner's request.

These are browser visual alerts, not SMS/email or notifications when the browser is closed. Browser sleep/background throttling, offline devices and expired sessions can delay updates. Keep the restaurant's device awake and connected. Reconnect or sign in again when necessary. A 10-second polling interval is not a guaranteed delivery time when the browser/device is suspended.

## Checkout and retry behavior

The cart obtains a server-calculated quote containing product prices, subtotal, delivery charge and final total before enabling confirmation. It refreshes the quote every 30 seconds while open. A signed quote is tied to the customer, items, delivery mode/postcode and prices, and expires after ten minutes. Changed prices or fees require a new quote and customer confirmation; forged frontend totals cannot change the charged order amount.

The server checks opening/holiday rules, approved delivery postcodes, minimum order, product availability, contact telephone and delivery address. Limits are 99 units per product, 50 distinct products, and 999,999.999 TND per order. Admin product prices allow up to 99,999.999 TND with no more than three decimals.

Each submission has a UUID retry reference. The frontend reuses it for a retry of the same payload after a network error. The server locks that customer's submissions and enforces a unique `(user_id, idempotency_key)` database constraint. The same reference returns the existing order (HTTP 200); a new order returns HTTP 201. Reusing a reference for a different payload returns HTTP 409. Previously processed orders cannot be overwritten by a competing accept/refuse action.

API integrations must first call `POST /api/orders/quote`, then submit `quote_token`, `idempotency_key`, `contact_phone` and the agreed basket data to `POST /api/orders`. Delivery additionally needs `delivery_postcode` and `delivery_address`. Fetch current operating rules from `GET /api/restaurant-settings`. Only admins may update them at `PUT /api/admin/restaurant-settings`.

Login is limited to five attempts per account/IP per minute and 30 per IP per minute. Registration is limited to ten per IP per hour, quotes to 60 per customer per minute and order submissions to ten per customer per minute. Configure trusted proxies correctly on the production host so rate limiting sees the intended client address.

## Updating an existing deployment

Back up the database, uploaded files and configuration. Deploy the source and committed lockfiles, install backend dependencies with `composer install --no-dev --optimize-autoloader`, build assets with `npm ci && npm run build` in the build environment, set `APP_TIMEZONE=Africa/Tunis`, and run `php artisan migrate --force`. Do not use `migrate:fresh` or blanket demo seeding on client data. The migration widens money fields to three decimals and adds order currency/retry/contact fields. Its rollback keeps widened money columns to avoid truncating millimes.

Serve only the `public/` directory over HTTPS, persist uploads, configure session/CSRF domains, and rebuild Laravel caches after the environment changes. Test the migration and full checkout on the target MySQL/MariaDB staging server before production. The local automated database suite uses SQLite, which does not exercise all MySQL locking/DDL behavior.

## Checks

Verified locally on 10 November 2026: 28 backend tests (144 assertions), 11 frontend tests, 24 HTTP checks against the running server, production asset build, PHP formatting checks and Composer validation all passed. Composer and npm reported zero known dependency vulnerabilities after updating the lockfiles. The local database migration also completed successfully. These results use SQLite and do not replace target-server MySQL and device acceptance testing.

```bash
php artisan test
npm test
npm run build
composer validate --no-check-publish
composer audit
npm audit
```

The backend suite covers signed quotes, fee/price changes, millime precision, delivery/opening rules, retries, rate limits and admin access. Frontend tests cover automatic refresh, connection recovery, new-order detection, stale quote rejection, totals, settings submission and TND/Tunis formatting. Desktop/mobile browser acceptance on the actual restaurant device remains part of deployment acceptance.

Framework upgrade references: [Laravel 12 upgrade guide](https://laravel.com/docs/12.x/upgrade), [Laravel support policy](https://laravel.com/docs/12.x/releases).
