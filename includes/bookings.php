<?php
// Online drop-off bookings and the schedule they reserve.
//
// Scheduling model: fixed drop-off slots (e.g. every 60 min from 08:00 to 18:00) on open days,
// each accepting a set number of bookings. Pending and Confirmed bookings hold a place.
// Walk-ins at the counter don't use slots.
//
// Booking statuses:
//   Pending ──confirm──▶ Confirmed ──drop-off──▶ Dropped off (an order now exists)
//      │                    │
//      ├─reject─▶ Rejected  ├─reject─▶ Rejected
//      ├─cancel─▶ Cancelled ├─cancel─▶ Cancelled   (customer before the slot, or staff)
//      └─(day passed)─▶ Expired      └─(day passed)─▶ No-show
// Every other change is refused.

const BOOKING_HOLDS = ['Pending', 'Confirmed'];
const BOOKING_MAX_ACTIVE = 2; // per customer, so one person can't hold many slots

function schedule(): array
{
    $days = array_values(array_filter(array_map('intval', explode(',', setting('open_days', '1,2,3,4,5,6'))), fn ($d) => $d >= 1 && $d <= 7));
    return [
        'days'     => $days,
        'open'     => preg_match('/^\d{2}:\d{2}$/', setting('open_time')) ? setting('open_time') : '08:00',
        'close'    => preg_match('/^\d{2}:\d{2}$/', setting('close_time')) ? setting('close_time') : '18:00',
        'minutes'  => max(15, min(240, (int) setting('slot_minutes', '60'))),
        'capacity' => max(1, min(50, (int) setting('slot_capacity', '3'))),
        'ahead'    => max(1, min(60, (int) setting('booking_days', '14'))),
        'lead'     => max(0, min(1440, (int) setting('lead_minutes', '60'))),
    ];
}

// Expire pending bookings whose slot has started, and mark confirmed ones from past days as no-shows.
function refresh_bookings(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    q("UPDATE bookings SET status = 'Expired', status_reason = 'Not confirmed or dropped off on the booked day'
        WHERE status = 'Pending' AND slot_date < CURDATE()");
    q("UPDATE bookings SET status = 'No-show', status_reason = 'Laundry was not dropped off'
        WHERE status = 'Confirmed' AND slot_date < CURDATE()");
}

function slot_times(array $s): array
{
    $times = [];
    $t = strtotime('2000-01-01 ' . $s['open']);
    $end = strtotime('2000-01-01 ' . $s['close']);
    while ($t + $s['minutes'] * 60 <= $end) {
        $times[] = date('H:i', $t);
        $t += $s['minutes'] * 60;
    }
    return $times;
}

function closed_reason(string $date, array $s): ?string
{
    if (!in_array((int) date('N', strtotime($date)), $s['days'], true)) {
        return 'Closed';
    }
    $note = q_val('SELECT note FROM closed_dates WHERE closed_on = ?', [$date]);
    return $note !== null ? ($note ?: 'Closed') : null;
}

// Dates a customer can pick from, with why a date is unavailable.
function bookable_dates(): array
{
    $s = schedule();
    $out = [];
    for ($i = 0; $i < $s['ahead']; $i++) {
        $d = date('Y-m-d', strtotime("+$i day"));
        $reason = closed_reason($d, $s);
        if (!$reason) {
            $open = array_filter(availability($d), fn ($x) => $x['ok']);
            if (!$open) $reason = 'Full';
        }
        $out[] = ['date' => $d, 'ok' => !$reason, 'reason' => $reason];
    }
    return $out;
}

// Slots on a date: time, places left, and whether it can be booked now.
function availability(string $date): array
{
    $s = schedule();
    if (closed_reason($date, $s)) return [];
    $used = [];
    foreach (q("SELECT TIME_FORMAT(slot_time, '%H:%i') t, COUNT(*) n FROM bookings WHERE slot_date = ? AND status IN ('Pending','Confirmed') GROUP BY slot_time", [$date]) as $r) {
        $used[$r['t']] = (int) $r['n'];
    }
    $earliest = time() + $s['lead'] * 60;
    $out = [];
    foreach (slot_times($s) as $t) {
        $left = max(0, $s['capacity'] - ($used[$t] ?? 0));
        $past = strtotime("$date $t") < $earliest;
        $out[] = ['time' => $t, 'label' => date('g:i A', strtotime("2000-01-01 $t")), 'left' => $left, 'ok' => !$past && $left > 0, 'why' => $past ? 'Past' : ($left ? '' : 'Full')];
    }
    return $out;
}

function validate_slot(string $date, string $time): ?string
{
    $s = schedule();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) return 'Choose a date.';
    if ($date < date('Y-m-d')) return 'That date has passed. Choose another date.';
    if ($date > date('Y-m-d', strtotime('+' . ($s['ahead'] - 1) . ' day'))) return 'You can book up to ' . $s['ahead'] . ' days ahead.';
    if ($reason = closed_reason($date, $s)) return 'The shop is closed that day' . ($reason !== 'Closed' ? ' (' . $reason . ')' : '') . '. Choose another date.';
    foreach (availability($date) as $slot) {
        if ($slot['time'] === $time) {
            if ($slot['why'] === 'Past') return 'That time is too soon or has passed. Choose a later time.';
            if ($slot['why'] === 'Full') return 'That time just filled up. Choose another time.';
            return null;
        }
    }
    return 'Choose an available time.';
}

/**
 * Reserves a slot. A named lock per slot makes the capacity check and the insert one step,
 * so two customers can't take the last place at the same moment.
 * Returns [booking id, booking no] or an error message.
 */
function create_booking(int $customerId, array $service, string $date, string $time, ?float $estKg, string $notes)
{
    $lock = 'lt_slot_' . $date . '_' . $time;
    if ((int) q_val('SELECT GET_LOCK(?, 5)', [$lock]) !== 1) {
        return 'The schedule is busy. Please try again.';
    }
    try {
        if ($err = validate_slot($date, $time)) return $err;
        $active = (int) q_val("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status IN ('Pending','Confirmed')", [$customerId]);
        if ($active >= BOOKING_MAX_ACTIVE) {
            return 'You already have ' . $active . ' upcoming bookings. Cancel one or wait until you drop them off.';
        }
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO bookings (customer_id, service_id, slot_date, slot_time, est_weight_kg, notes) VALUES (?, ?, ?, ?, ?, ?)',
          [$customerId, $service['id'], $date, $time . ':00', $estKg, $notes ?: null]);
        $id = (int) $pdo->lastInsertId();
        $no = 'BK-' . (1000 + $id);
        q('UPDATE bookings SET booking_no = ? WHERE id = ?', [$no, $id]);
        $pdo->commit();
        return [$id, $no];
    } finally {
        if (db()->inTransaction()) db()->rollBack();
        q('SELECT RELEASE_LOCK(?)', [$lock]);
    }
}

function load_booking(int $id): ?array
{
    return q_one(
        'SELECT b.*, c.name AS customer_name, c.phone, c.email, s.name AS service_name, s.price_per_kg,
                u.name AS handled_by_name, o.id AS order_id, o.order_no, o.status AS order_status
           FROM bookings b JOIN customers c ON c.id = b.customer_id JOIN services s ON s.id = b.service_id
           LEFT JOIN users u ON u.id = b.handled_by LEFT JOIN orders o ON o.booking_id = b.id
          WHERE b.id = ?',
        [$id]
    );
}

function booking_start(array $b): int
{
    return strtotime($b['slot_date'] . ' ' . $b['slot_time']);
}

// Which actions are allowed on a booking right now.
function booking_can(array $b, string $action, string $actor): bool
{
    $st = $b['status'];
    switch ($action) {
        case 'confirm':  return $actor !== 'customer' && $st === 'Pending' && $b['slot_date'] >= date('Y-m-d');
        case 'reject':   return $actor !== 'customer' && in_array($st, BOOKING_HOLDS, true);
        case 'cancel':   return in_array($st, BOOKING_HOLDS, true) && ($actor !== 'customer' || booking_start($b) > time());
        case 'no_show':  return $actor !== 'customer' && $st === 'Confirmed' && booking_start($b) < time();
        case 'drop_off': return $actor !== 'customer' && in_array($st, BOOKING_HOLDS, true);
    }
    return false;
}

// Applies confirm / reject / cancel / no-show. Returns null on success or a message.
function change_booking(array $b, string $action, string $actor, string $reason = '', ?int $userId = null): ?string
{
    $to = ['confirm' => 'Confirmed', 'reject' => 'Rejected', 'cancel' => 'Cancelled', 'no_show' => 'No-show'][$action] ?? null;
    if (!$to || !booking_can($b, $action, $actor)) {
        return 'This booking can no longer be changed that way. It now shows its current status.';
    }
    if ($action === 'reject' && trim($reason) === '') {
        return 'Tell the customer why: enter a reason.';
    }
    $st = q('UPDATE bookings SET status = ?, status_reason = ?, handled_by = ? WHERE id = ? AND status = ?',
            [$to, $reason !== '' ? mb_substr($reason, 0, 255) : null, $userId, $b['id'], $b['status']]);
    if ($st->rowCount() !== 1) {
        return 'Someone else updated this booking first. It now shows its current status.';
    }
    log_activity('booking.' . $action, $b['booking_no'] . ' ' . $b['status'] . ' → ' . $to . ($reason ? ' (' . $reason . ')' : '') . ($actor === 'customer' ? ' by customer' : ''), (int) $b['customer_id']);
    return null;
}

function booking_chip(string $status): string
{
    return '<span class="chip chip-bk-' . e(status_slug($status)) . '">' . e($status) . '</span>';
}

function slot_label(array $b): string
{
    return date('D, M j', strtotime($b['slot_date'])) . ' · ' . date('g:i A', strtotime('2000-01-01 ' . $b['slot_time']));
}

// What the customer should know about their booking right now.
function booking_message(array $b): string
{
    switch ($b['status']) {
        case 'Pending':     return 'The shop will confirm your drop-off time soon.';
        case 'Confirmed':   return 'Bring your laundry to the shop at your drop-off time.';
        case 'Dropped off': return 'Your laundry is with us. Follow the order below.';
        case 'Rejected':    return 'The shop could not take this booking' . ($b['status_reason'] ? ': ' . $b['status_reason'] : '.');
        case 'Cancelled':   return 'This booking was cancelled' . ($b['status_reason'] ? ': ' . $b['status_reason'] : '.');
        case 'No-show':     return 'The laundry was not dropped off on the booked day.';
        case 'Expired':     return 'This booking was not confirmed or used on the booked day. Please book another time.';
    }
    return '';
}
