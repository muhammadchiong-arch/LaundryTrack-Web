# LaundryTrack Web

A laundry shop management and order-tracking system that runs **offline on XAMPP**, built with PHP 8, MySQL/MariaDB, and plain HTML, CSS and JS. The workspace follows the Claude Design **"LaundryTrack Web v2"** (`LaundryTrack Web.html`) and the home page follows **"LaundryTrack Landing v7"** (`LaundryTrack Landing v7.html`).

| Role | Can use |
|---|---|
| **Customer** (online account) | Home (current laundry and next drop-off), **Book laundry** (5 steps: service, date and time, details, review, done), My bookings (cancel before the slot), Profile |
| **Walk-in customer** | Track page: order number + last 4 digits of their phone. No account needed. |
| **Staff** | Dashboard, Bookings (confirm, reject, receive laundry, no-show), Schedule, New order, Orders, Customers, Payments |
| **Admin** | Everything staff can do, plus moving an order back a step, voiding payments, Sales totals, Staff accounts, Settings (shop, booking schedule, closed days, services and prices) and the Activity log |

**Bookings:** Pending → Confirmed → Dropped off (the order is created when staff receive and weigh the laundry). Rejected, Cancelled, No-show and Expired close a booking.
**Orders:** Received → Washing → Drying → Folding → Ready for Pickup → Completed, one step at a time, or Cancelled (with a reason; any payment is refunded). An order is completed only when fully paid.

Why the system works this way, and everything the audit changed: [docs/AUDIT.md](docs/AUDIT.md).

## Install on XAMPP (Windows)

1. Install [XAMPP](https://www.apachefriends.org/) with PHP 8.0 or newer. In the XAMPP Control Panel, start **Apache** and **MySQL**.
2. Put this folder in `C:\xampp\htdocs\laundrytrack`. You can clone it there:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/muhammadchiong-arch/LaundryTrack-Web.git laundrytrack
   ```
   Or download the ZIP and rename the extracted folder to `laundrytrack`.
3. Create the database: open <http://localhost/phpmyadmin>, go to **Import**, choose `database/laundrytrack.sql` and click **Import**. This creates the `laundrytrack` database with its tables and the default services.
   - Command-line alternative: `C:\xampp\mysql\bin\mysql -u root < database\laundrytrack.sql`
4. Open <http://localhost/laundrytrack/setup.php> and create the admin account. This page turns itself off once an account exists.
5. Sign in at <http://localhost/laundrytrack/login.php>. Add staff under **Staff**. Under **Settings**, set prices, opening days and hours, slot length and **bookings per slot**, and any holidays.

**Upgrading an earlier install** (before online booking)? Don't re-import the full file. In phpMyAdmin, select the `laundrytrack` database, then **Import** `database/migrations/002_booking.sql`. Your orders, customers and payments stay as they are.

The home page is <http://localhost/laundrytrack/>. Customers create an account there (**Book laundry**) or track a walk-in order at <http://localhost/laundrytrack/track.php>. Staff, admins and customers all sign in at the same page.

**Different MySQL password or port?** Copy `includes/config.local.example.php` to `includes/config.local.php` and change only the values that differ. That file is git-ignored.

**Customers on their own phones:** customers on the shop's Wi-Fi can open `http://<this-PC's-IP>/laundrytrack/track.php` (find the IP with `ipconfig`). You can also leave the track page open on a counter tablet. The app needs no internet: fonts, icons and scripts are all stored locally.

## Motion

- The landing page and the sign-in pages show the animated washer and logo mark from the LaundryTrack designs. Animations pause when they are scrolled out of view.
- After a staff or admin sign-in, a short welcome intro plays ("Hello, Grace" and a loading bar, about 2 seconds), only for the first sign-in of the day on that device. A tap or any key skips it.
- If the device asks for reduced motion, the washer and logo stay still, the intro shortens to a quick fade, and content appears without sliding.

## Daily use

1. **Booked customers:** check **Bookings → Needs review** and confirm (or reject with a reason). When the customer arrives, open the booking and press **Receive laundry**: enter the actual weight (and any payment). That creates the order.
2. **Walk-ins:** **New order**. Find the customer by name or phone, or add them (the app warns if the number already exists). Choose the service, enter the weight and, if they pay now, the payment. Saving creates the order number (e.g. `LAU-1001`) with status **Received**. Print the claim slip, or tell the customer the number.
3. **Move it along:** one tap on **Move to Washing / Drying / Folding / Ready for Pickup** from the order page, the Orders list or the Dashboard board. Every change is timestamped. On Ready for Pickup, **Text customer** opens your phone's SMS app with the message filled in.
4. **Pickup:** **Collect ₱X and mark completed** takes the balance (Cash, or GCash with its reference number) and completes the order in one step. An unpaid order can't be completed.
5. **Mistakes:** an admin can **Undo last step** (logged as a correction) or **Void** a payment with a reason. Anyone can **Cancel order** with a reason; money already paid is recorded as refunded.

## What's inside

```
index.php            Landing page (Landing v7): Book laundry → register, Track an order, Sign in
register.php         Customer sign-up (name, mobile, email, password)
my/                  Customer area: home, 5-step booking, my bookings, profile
track.php            Customer tracking: order no. + last 4 phone digits → status, timeline, order history
login.php, logout.php, setup.php
app/                 Staff and admin pages (each one checks the role)
  bookings.php, booking.php, schedule.php   Booking queue, receive laundry, day schedule
  activity.php       Admin: who did what
  dashboard.php      Admin: today's numbers, order board, recent updates
  orders.php         Filter by status, search, date range, unpaid
  order.php          Details, next status, payments, history, claim slip
  order-new.php      Create order (+ new customer)
  customers.php, customer.php
  transactions.php   Payments by date range, cash/GCash totals, unpaid balance
  staff.php          Admin: add staff, change roles, reset passwords, turn accounts off
  settings.php       Admin: shop details, services and prices
  account.php        Change your own password
  api/customers.php  Customer search used by New order
includes/            Config, database (PDO), auth, helpers, layouts (blocked from the web by .htaccess)
  orders.php         Order rules: allowed status changes, payments, refunds, voids
  bookings.php       Schedule, slot capacity (locked per slot), booking states
assets/              app.css, app.js, landing.css, landing.js, logo, local fonts (Nunito and Figtree, SIL Open Font License)
                     Landing icons are Phosphor Icons (MIT) inlined as SVG by includes/phosphor.php
                     washer.jpg + includes/partials/washer.php: the animated washer (Claude Design "Processing Icon")
                     includes/logo_paths.php + partials/logo_motion.php + js/motion.js: the animated logo mark ("Logo Motion")
database/laundrytrack.sql
```

Tables: `users` (admin/staff), `customers` (walk-ins and online accounts), `services`, `bookings`, `orders`, `order_status_history`, `transactions` (payments, refunds, voids), `closed_dates`, `activity_log`, `settings`, `attempts`.

## Security

- Passwords are stored with `password_hash`. The session ID is renewed when you sign in, and the cookie is HttpOnly and SameSite=Lax.
- Every form has a CSRF token. Sign-out is POST-only.
- Every query is a PDO prepared statement, and every output is HTML-escaped.
- Roles are checked on the server for every page. Staff get *403* on admin pages, and a turned-off account is signed out immediately.
- Sign-in and order tracking are rate-limited: 8 failed sign-ins or 10 failed lookups per 15 minutes per device.
- A customer only sees orders that belong to the phone number they verified.

Bootstrap isn't bundled. The design's own CSS (`assets/css/app.css`) covers the layout and components, so the pages stay light and match the design exactly.
