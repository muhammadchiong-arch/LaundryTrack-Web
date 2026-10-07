# LaundryTrack Web

A laundry shop management and order-tracking system that runs **offline on XAMPP**, built with PHP 8, MySQL/MariaDB, and plain HTML, CSS and JS. The look follows the Claude Design **"LaundryTrack Web v2"** (`LaundryTrack Web.html` in this repo).

| Role | Can use |
|---|---|
| **Admin** | Dashboard, Orders, New order, Customers, Sales, Staff, Settings |
| **Staff** | New order, Orders (update status, record payments), Customers, Transactions |
| **Customer** | Track page: order number + last 4 digits of their phone → status, timeline, details, order history. No account needed. |

Every order follows the same steps, with the same words on every screen:
**Received → Washing → Drying → Folding → Ready for Pickup → Completed**

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
5. Sign in at <http://localhost/laundrytrack/login.php>. Add staff under **Staff**, and set prices under **Settings**.

The customer tracking page is <http://localhost/laundrytrack/>.

**Different MySQL password or port?** Copy `includes/config.local.example.php` to `includes/config.local.php` and change only the values that differ. That file is git-ignored.

**Customers on their own phones:** customers on the shop's Wi-Fi can open `http://<this-PC's-IP>/laundrytrack/` (find the IP with `ipconfig`). You can also leave the track page open on a counter tablet. The app needs no internet: fonts, icons and scripts are all stored locally.

## Daily use

1. **New order:** find the customer by name or phone (or add them), choose the service, enter the weight and, if they pay now, the payment. Saving creates the order number (e.g. `LAU-1001`) and sets the status to **Received**. Print the claim slip, or tell the customer the number.
2. **Move it along:** one tap on **Move to Washing / Drying / Folding / Ready for Pickup**, from the order page, the Orders list or the Dashboard board. Every change is timestamped in the order's history. Admins can also set any status, for example to correct a mistake.
3. **Pickup:** record the balance (Cash or GCash with a reference number), then **Mark completed**. You'll be asked to confirm if a balance is still unpaid.

## What's inside

```
index.php            Customer tracking form (order no. + last 4 digits of phone)
track.php            Customer order status, timeline, order history
login.php, logout.php, setup.php
app/                 Signed-in pages (each one checks the role)
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
assets/              app.css, app.js, logo, local fonts (Nunito and Figtree, SIL Open Font License)
database/laundrytrack.sql
```

Tables: `users` (admin/staff), `customers`, `services`, `orders`, `order_status_history`, `transactions`, `settings`, `attempts`.

## Security

- Passwords are stored with `password_hash`. The session ID is renewed when you sign in, and the cookie is HttpOnly and SameSite=Lax.
- Every form has a CSRF token. Sign-out is POST-only.
- Every query is a PDO prepared statement, and every output is HTML-escaped.
- Roles are checked on the server for every page. Staff get *403* on admin pages, and a turned-off account is signed out immediately.
- Sign-in and order tracking are rate-limited: 8 failed sign-ins or 10 failed lookups per 15 minutes per device.
- A customer only sees orders that belong to the phone number they verified.

Bootstrap isn't bundled. The design's own CSS (`assets/css/app.css`) covers the layout and components, so the pages stay light and match the design exactly.
