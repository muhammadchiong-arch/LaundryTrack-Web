# LaundryTrack system audit (October 2026)

Scope: the whole PHP app (every page, form, table and rule) checked against how a real laundry shop works, followed by fixes. The findings come from reading the code and testing it, not assumptions. The automated end-to-end test (119 checks) covers every flow below.

## 1. What the system was

The app was a counter system. Staff created an order when laundry was dropped off, moved it through Received → Washing → Drying → Folding → Ready for Pickup → Completed, and recorded payments. Customers tracked orders with the order number and the last 4 digits of their phone. There were no customer accounts, no booking and no schedule.

## 2. Problems found

| # | Area | Problem (in the actual code) | Why it matters |
|---|---|---|---|
| 1 | Lifecycle | Admin "Set status" allowed any jump, e.g. **Completed → Received** or Received → Completed | History became meaningless; finished orders could reopen silently |
| 2 | Lifecycle | No way to cancel an order | A wrong or abandoned order could only sit "active" forever or be hidden by marking it Completed, which falsified reports |
| 3 | Payments | An order could be **Completed with money still owed**; only a browser confirm dialog warned | Unpaid laundry left the shop with no record of it |
| 4 | Payments | Admins could **hard-delete** payments, even on completed orders, leaving no trace | Cash could disappear from the books without a record |
| 5 | Payments | No refunds | Cancelling a paid order had no correct money record |
| 6 | Payments | Pickup took two separate actions (record payment, then mark completed) | Extra clicks at the busiest moment |
| 7 | Business flow | No online booking or scheduling, so no way to manage drop-off capacity | This is the core of the requested system |
| 8 | Roles | Staff had no dashboard and landed on a plain list | Staff need "today": what's coming in, what's ready |
| 9 | Security | Raw PHP errors (file paths, SQL) shown on screen with XAMPP's default `display_errors` | Leaks internals |
| 10 | Security | An admin-set password stayed valid forever; staff were never asked to change it | Shared or known passwords |
| 11 | Data | Walk-in customers could be added twice with the same phone number | Split history, wrong "order history" for tracking |
| 12 | Data | No log of who cancelled, refunded, edited a total, or changed staff and settings | Disputes can't be resolved |
| 13 | UX | Status chips pulsed forever on every list | Constant motion that carries no information |
| 14 | UX | The sign-in intro (about 2 s) played on every sign-in | Staff sign in many times a day |
| 15 | UX | The public home page pitched the software to shop owners ("Manage laundry"), but its visitors are the shop's customers | The main customer action (book, track) wasn't the main button |

## 3. Decisions

### Order statuses (kept, plus one)
**Received → Washing → Drying → Folding → Ready for Pickup → Completed**, plus **Cancelled**.
- *Weighing* is not a status: the weight is entered when the laundry is received.
- *Processing* is not a status: Washing, Drying and Folding are the processing steps.
- *Picked up* and *Payment* are not statuses: pickup and the last payment happen together and the result is **Completed**.

Allowed changes (enforced in one function, `transition_order()` in `includes/orders.php`):

| Change | Who | Rule |
|---|---|---|
| Forward one step | Staff, admin | Completed only when the balance is zero |
| Back one step | Admin only | Recorded as "Correction" in the history and activity log |
| Cancel | Staff, admin | Reason required; any money kept is refunded in the same database transaction |
| Anything else (skipping steps, Completed → Received, changing a cancelled order) | Nobody | Refused by the server |

### Booking statuses (new)
**Pending → Confirmed → Dropped off** (an order now exists), with **Rejected**, **Cancelled**, **No-show** and **Expired**.

| Status | How it happens |
|---|---|
| Pending | The customer books |
| Confirmed | Staff confirm (until the end of the booked day) |
| Rejected | Staff, with a reason the customer sees |
| Cancelled | The customer before the slot starts, or staff at any time |
| Dropped off | Staff receive the laundry: weigh it, and the order is created (with an optional payment), all in one form |
| Expired | A pending booking whose day passed without being confirmed or used (automatic) |
| No-show | A confirmed booking whose day passed without a drop-off (automatic; staff can also mark it) |

### Scheduling model: fixed slots with a capacity
- The admin sets open weekdays, opening hours, slot length (30, 60, 90 or 120 min), **bookings per slot**, how many days ahead customers can book, and the shortest notice.
- Holidays are added as closed days.
- Pending and Confirmed bookings hold a place. Walk-ins don't use slots.
- The server refuses past dates, closed days, slots too soon or already started, full slots, and more than 2 active bookings per customer.
- A database lock per slot (`GET_LOCK`) makes "check capacity, then insert" one step, so two customers can't take the last place at the same moment.

Machine-level or kg-level capacity was considered and rejected: it needs data the shop doesn't keep and adds steps for little gain. A per-slot booking limit matches what a counter can actually handle.

### Payment flow (as it really works)
- **No online payment.** The booking says so plainly: pay cash or GCash at the shop.
- Payment can be taken at drop-off (any part) or at pickup. An order can exist unpaid, but it **cannot be completed unpaid**. The last step is one button: "Collect ₱X and mark completed".
- GCash payments need the reference number.
- Mistakes are **voided** (by an admin, with a reason), never deleted. Voided rows stay visible, struck through, and leave every total.
- Payments on completed or cancelled orders are locked.
- Refunds happen when a paid order is cancelled and are subtracted from sales.

### Roles
| | Customer | Staff | Admin |
|---|---|---|---|
| Book, view and cancel own bookings | ✓ | | |
| Dashboard (today) | Own home | ✓ | ✓ plus money totals |
| Bookings: confirm, reject, receive, no-show | | ✓ | ✓ |
| Orders: create, move forward, take payment, cancel | | ✓ | ✓ |
| Move an order back, void a payment | | | ✓ |
| Customers | | ✓ | ✓ plus turning online accounts on or off |
| Staff accounts, settings, schedule, activity log | | | ✓ |

Every page checks the role on the server. A customer opening a staff URL is sent to sign-in, and a staff member opening an admin URL gets a 403. A customer opening someone else's booking gets a 404.

### Database changes
New: `bookings`, `closed_dates`, `activity_log`.

Changed:
- `customers`: email (unique), password, active flag, last sign-in.
- `services`: description.
- `orders`: `booking_id` (unique, so one booking makes at most one order), `cancel_reason`, Cancelled status.
- `transactions`: payment or refund, plus void fields.
- `users`: `must_change_password`.
- `order_status_history`: note.

Every new relationship has a foreign key and an index for its lookups. Existing installs upgrade with `database/migrations/002_booking.sql`. It was tested on a database from the previous version and produces the same structure as a fresh install; running it twice is safe.

### Security
Already in place and re-checked:
- bcrypt passwords
- prepared statements everywhere
- output escaping
- CSRF token on every POST
- session ID renewed at sign-in
- HttpOnly + SameSite cookies
- rate limits on sign-in and tracking
- `includes/` and `database/` blocked from the web

Added:
- error pages that never show internals
- forced password change after an admin creates or resets a staff password
- registration rate limit
- staff can't change a customer's sign-in email
- one-booking-one-order constraint
- a server-side check on every status and money rule (the browser's checks are only for convenience)

There are no file uploads, so there is no upload risk.

### UX and UI
- **Customer booking:** 5 steps with a progress indicator (Service → Date & time → Details → Review → Done).
  - Prices and descriptions are shown on the service cards.
  - Unavailable dates show why ("Closed", "Full", a holiday name), and slots show places left.
  - A live price estimate updates as the customer types a weight.
  - The review answers *what, when, how much, what to bring, how to pay*.
  - The draft is kept on the server, so Back and refresh lose nothing.
- **Customer home:** current laundry with its timeline, next drop-off, a Book button and recent updates.
- **Staff dashboard:** bookings to review, today's drop-offs with a "Receive laundry" button each, the order board, and ready orders.
- **Receiving a booked customer:** one form (weigh → order created → optional payment).
- **Ready for Pickup orders:** a "Text customer" button opens the phone's SMS app with the message filled in. No SMS service is pretended.
- **Labels and errors:** labels sit above inputs, the examples are realistic, and errors name the problem ("That time just filled up. Choose another time.").
- **Phone layouts:** every table becomes a list. The 1440 and 390 px checks found no sideways scrolling.
- **Animation audit:**
  - Kept, because they're motivated:
    - the washer and logo marks (they show "being processed")
    - the status story on the landing page
    - button press feedback
  - Removed or reduced:
    - the infinite chip pulse
    - the sign-in intro after the first one of the day
  - Everything respects reduced motion.
- **Landing:** the main button is now **Book laundry**, the second is **Track an order**, and **Sign in** is in the nav. The design is unchanged.

## 4. Not built (and why)
- **Online payment:** needs a payment provider account and internet; out of scope for an offline XAMPP install. The UI says so.
- **SMS or email notifications:** need a paid gateway. Customers see status changes on their home page, and staff can text from their phone in one tap.
- **Merging duplicate walk-in records into an online account:** a customer who registers gets a new record. Linking old walk-in history safely needs proof of ownership, which isn't designed yet.
- **Password reset by email:** needs mail delivery. Customers and staff ask the shop.
