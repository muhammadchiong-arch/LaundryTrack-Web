<?php
// Laundry orders: creation, the allowed status changes, payments, refunds and voids.
// Every page goes through these functions so the rules are enforced in one place.

const ORDER_CANCELLED = 'Cancelled';

// Money actually kept for an order: payments minus refunds, ignoring voided rows.
function sql_paid(string $orderAlias = 'o'): string
{
    return "(SELECT COALESCE(SUM(IF(t.kind = 'refund', -t.amount, t.amount)), 0) FROM transactions t
             WHERE t.order_id = $orderAlias.id AND t.voided_at IS NULL)";
}

// Same, as a join (alias p.paid) for lists that filter or sort on it.
function sql_paid_join(string $orderAlias = 'o'): string
{
    return "LEFT JOIN (SELECT t.order_id, SUM(IF(t.kind = 'refund', -t.amount, t.amount)) paid FROM transactions t
             WHERE t.voided_at IS NULL GROUP BY t.order_id) p ON p.order_id = $orderAlias.id";
}

function log_activity(string $action, string $detail, ?int $customerId = null): void
{
    $u = current_user();
    q('INSERT INTO activity_log (user_id, customer_id, action, detail) VALUES (?, ?, ?, ?)',
      [$u['id'] ?? null, $customerId, $action, mb_substr($detail, 0, 255)]);
}

function load_order(int $id): ?array
{
    return q_one(
        'SELECT o.*, c.name AS customer_name, c.phone, c.address, s.name AS service_name, u.name AS created_by_name,
                b.booking_no, ' . sql_paid() . ' AS paid
           FROM orders o JOIN customers c ON c.id = o.customer_id JOIN services s ON s.id = o.service_id
           LEFT JOIN users u ON u.id = o.created_by LEFT JOIN bookings b ON b.id = o.booking_id
          WHERE o.id = ?',
        [$id]
    );
}

function order_balance(array $o): float
{
    if ($o['status'] === ORDER_CANCELLED) {
        return 0.0;
    }
    return round(max(0, (float) $o['amount_due'] - (float) $o['paid']), 2);
}

function order_is_open(array $o): bool
{
    return !in_array($o['status'], ['Completed', ORDER_CANCELLED], true);
}

/**
 * Creates an order (status Received) with its number, first history row and optional payment.
 * $d: customer_id, service (row), weight, notes, pay_amount, pay_method, pay_reference, booking_id
 * Returns [order id, order number]. Throws if a linked booking was already used.
 */
function create_order(array $d, int $userId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $due = round($d['weight'] * (float) $d['service']['price_per_kg'], 2);
        q('INSERT INTO orders (customer_id, service_id, booking_id, weight_kg, price_per_kg, amount_due, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          [$d['customer_id'], $d['service']['id'], $d['booking_id'] ?? null, $d['weight'], $d['service']['price_per_kg'], $due, ($d['notes'] ?? '') ?: null, $userId]);
        $orderId = (int) $pdo->lastInsertId();
        $orderNo = 'LAU-' . (1000 + $orderId);
        q('UPDATE orders SET order_no = ? WHERE id = ?', [$orderNo, $orderId]);
        q("INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, 'Received', ?)", [$orderId, $userId]);
        if (!empty($d['booking_id'])) {
            $st = q("UPDATE bookings SET status = 'Dropped off', handled_by = ? WHERE id = ? AND status IN ('Pending','Confirmed')", [$userId, $d['booking_id']]);
            if ($st->rowCount() !== 1) {
                throw new RuntimeException('booking_used');
            }
        }
        if (($d['pay_amount'] ?? 0) > 0) {
            q("INSERT INTO transactions (order_id, kind, amount, method, reference, received_by) VALUES (?, 'payment', ?, ?, ?, ?)",
              [$orderId, $d['pay_amount'], $d['pay_method'], ($d['pay_reference'] ?? '') ?: null, $userId]);
        }
        $pdo->commit();
        return [$orderId, $orderNo];
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

// Validates a payment form. Returns [errors, amount, method, reference].
function validate_payment(array $src, float $max, bool $optional = false): array
{
    $errors = [];
    $raw = trim((string) ($src['amount'] ?? ''));
    $amount = round((float) $raw, 2);
    $method = (string) ($src['method'] ?? 'cash');
    $ref = mb_substr(trim((string) ($src['reference'] ?? '')), 0, 64);
    if ($optional && ($raw === '' || $amount == 0.0)) {
        return [[], 0.0, $method, ''];
    }
    if ($amount <= 0) {
        $errors['amount'] = 'Enter an amount above zero.';
    } elseif ($amount > $max + 0.004) {
        $errors['amount'] = 'That is more than the ' . ($optional ? 'total' : 'balance') . ' of ' . money($max) . '.';
    }
    if (!in_array($method, ['cash', 'gcash'], true)) {
        $errors['method'] = 'Choose cash or GCash.';
    } elseif ($method === 'gcash' && !preg_match('/^\d{6,20}$/', preg_replace('/\s+/', '', $ref))) {
        $errors['reference'] = 'Enter the GCash reference number (digits only).';
    }
    return [$errors, $amount, $method, $method === 'gcash' ? preg_replace('/\s+/', '', $ref) : ''];
}

function record_payment(int $orderId, float $amount, string $method, string $ref, int $userId): void
{
    q("INSERT INTO transactions (order_id, kind, amount, method, reference, received_by) VALUES (?, 'payment', ?, ?, ?, ?)",
      [$orderId, $amount, $method, $ref ?: null, $userId]);
}

/**
 * The single place that changes an order's status.
 * Allowed moves:
 *   forward one step (staff, admin); Completed only when fully paid
 *   back one step (admin only, recorded as a correction)
 *   Cancelled from any open status (staff, admin; reason required; money must be refunded first)
 * Returns null on success or a message explaining why not.
 */
function transition_order(array $o, string $to, array $user, string $note = ''): ?string
{
    $from = $o['status'];
    $i = array_search($from, STATUSES, true);
    $j = array_search($to, STATUSES, true);
    if ($from === ORDER_CANCELLED) {
        return 'This order is cancelled and can no longer change.';
    }
    if ($to === ORDER_CANCELLED) {
        if ($from === 'Completed') return 'A completed order cannot be cancelled.';
        if (trim($note) === '') return 'Enter the reason for cancelling.';
        if ((float) $o['paid'] > 0.004) return 'Refund the ' . money($o['paid']) . ' already paid before cancelling.';
    } elseif ($i === false || $j === false) {
        return 'Unknown status.';
    } elseif ($j === $i + 1) {
        if ($to === 'Completed' && order_balance($o) > 0) {
            return 'Collect the balance of ' . money(order_balance($o)) . ' before marking it completed.';
        }
    } elseif ($j === $i - 1) {
        if ($user['role'] !== 'admin') return 'Only an admin can move an order back a step.';
        $note = $note ?: 'Correction';
    } else {
        return 'Orders move one step at a time.';
    }

    $pdo = db();
    $pdo->beginTransaction();
    $st = q(
        'UPDATE orders SET status = ?, completed_at = ' . ($to === 'Completed' ? 'NOW()' : 'NULL') . ', cancel_reason = ? WHERE id = ? AND status = ?',
        [$to, $to === ORDER_CANCELLED ? mb_substr($note, 0, 255) : null, $o['id'], $from]
    );
    if ($st->rowCount() !== 1) {
        $pdo->rollBack();
        return 'Someone else updated this order first. It now shows the current status.';
    }
    q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)',
      [$o['id'], $to, $note !== '' ? mb_substr($note, 0, 255) : null, $user['id']]);
    $pdo->commit();
    if ($to === ORDER_CANCELLED || $j === $i - 1) {
        log_activity($to === ORDER_CANCELLED ? 'order.cancel' : 'order.step_back', $o['order_no'] . ': ' . $from . ' → ' . $to . ($note ? ' (' . $note . ')' : ''), (int) $o['customer_id']);
    }
    return null;
}

// Cancels an open order. Any money kept is refunded in the same database transaction,
// so an order is never left cancelled-but-paid or refunded-but-open.
function cancel_order(array $o, string $reason, string $refundMethod, string $refundRef, array $user): ?string
{
    if (!order_is_open($o)) return 'A ' . strtolower($o['status']) . ' order cannot be cancelled.';
    if (trim($reason) === '') return 'Enter why the order is cancelled.';
    $paid = round((float) $o['paid'], 2);
    $pdo = db();
    $pdo->beginTransaction();
    $st = q('UPDATE orders SET status = ?, cancel_reason = ?, completed_at = NULL WHERE id = ? AND status = ?',
            [ORDER_CANCELLED, mb_substr($reason, 0, 255), $o['id'], $o['status']]);
    if ($st->rowCount() !== 1) {
        $pdo->rollBack();
        return 'Someone else updated this order first. It now shows the current status.';
    }
    if ($paid > 0.004) {
        q("INSERT INTO transactions (order_id, kind, amount, method, reference, received_by) VALUES (?, 'refund', ?, ?, ?, ?)",
          [$o['id'], $paid, $refundMethod === 'gcash' ? 'gcash' : 'cash', $refundRef ?: null, $user['id']]);
    }
    q('INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)', [$o['id'], ORDER_CANCELLED, mb_substr($reason, 0, 255), $user['id']]);
    $pdo->commit();
    log_activity('order.cancel', $o['order_no'] . ' cancelled from ' . $o['status'] . ' (' . $reason . ')' . ($paid > 0.004 ? ', ' . money($paid) . ' refunded' : ''), (int) $o['customer_id']);
    return null;
}

// Shared handler for the one-tap "Move to <next>" buttons on lists and boards.
function handle_advance_post(string $back): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || input('action') !== 'advance') {
        return;
    }
    $o = q_one('SELECT o.*, ' . sql_paid() . ' AS paid FROM orders o WHERE o.id = ?', [(int) input('order_id')]);
    if (!$o || $o['status'] !== input('from') || !($next = next_status($o['status']))) {
        flash('That order was already updated. The list now shows its current status.', 'warning');
    } elseif ($err = transition_order($o, $next, current_user())) {
        flash($err, 'warning');
    } else {
        flash($o['order_no'] . ' moved to ' . $next . '.');
    }
    redirect($back);
}

// One-tap button for the next step. When only payment stands in the way, it links to the order to collect it.
function advance_button(array $o, string $class = 'btn btn-sm btn-soft'): string
{
    $next = next_status($o['status']);
    if (!$next || $o['status'] === ORDER_CANCELLED) {
        return '';
    }
    if ($next === 'Completed' && isset($o['amount_due'], $o['paid']) && (float) $o['amount_due'] - (float) $o['paid'] > 0.004) {
        return '<a class="' . e($class) . '" href="' . e(url('app/order.php?id=' . (int) $o['id'] . '#pay')) . '">Collect '
            . e(money((float) $o['amount_due'] - (float) $o['paid'])) . '</a>';
    }
    return '<form method="post" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="action" value="advance">'
        . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '">'
        . '<input type="hidden" name="from" value="' . e($o['status']) . '">'
        . '<button class="' . e($class) . '" type="submit">' . e($next === 'Completed' ? 'Mark completed' : 'Move to ' . $next) . '</button></form>';
}
