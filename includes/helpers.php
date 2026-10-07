<?php
const STATUSES = ['Received', 'Washing', 'Drying', 'Folding', 'Ready for Pickup', 'Completed'];

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function flash(string $message, string $kind = 'success'): void
{
    $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<small class="field-error">' . e($errors[$key]) . '</small>' : '';
}

function input(string $key, $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function money($amount): string
{
    return $GLOBALS['config']['currency'] . number_format((float) $amount, 2);
}

function kg($w): string
{
    return rtrim(rtrim(number_format((float) $w, 2, '.', ''), '0'), '.') . ' kg';
}

function fmt_dt(?string $dt, string $format = 'M j, g:i A'): string
{
    return $dt ? date($format, strtotime($dt)) : '';
}

function fmt_when(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return 'Today, ' . date('g:i A', $t);
    }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday, ' . date('g:i A', $t);
    }
    return date('M j, g:i A', $t);
}

function fmt_short(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $t = strtotime($dt);
    return date('Y-m-d', $t) === date('Y-m-d') ? date('g:i A', $t) : date('M j', $t);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini ?: '?';
}

function phone_digits(string $phone): string
{
    $d = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($d, '63') && strlen($d) === 12) {
        $d = '0' . substr($d, 2);
    }
    return $d;
}

function fmt_phone(string $digits): string
{
    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    }
    return $digits;
}

function next_status(string $status): ?string
{
    $i = array_search($status, STATUSES, true);
    return ($i !== false && $i < count(STATUSES) - 1) ? STATUSES[$i + 1] : null;
}

function status_index(string $status): int
{
    $i = array_search($status, STATUSES, true);
    return $i === false ? 0 : $i;
}

function status_slug(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function status_chip(string $status): string
{
    return '<span class="chip chip-' . e(status_slug($status)) . '">' . e($status) . '</span>';
}

// What the customer should know at each status.
function status_message(string $status): string
{
    return [
        'Received'         => 'We have your laundry and it is in line for washing.',
        'Washing'          => 'Your laundry is being washed.',
        'Drying'           => 'Your laundry is in the dryer.',
        'Folding'          => 'Your laundry is being folded and packed.',
        'Ready for Pickup' => 'Your laundry is ready. Please pick it up at the shop.',
        'Completed'        => 'Picked up. Thank you!',
    ][$status] ?? '';
}

function payment_state(float $due, float $paid): array
{
    if ($paid + 0.004 >= $due) {
        return ['paid', 'Paid'];
    }
    return $paid > 0 ? ['partial', 'Partly paid'] : ['unpaid', 'Unpaid'];
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT `key`, `value` FROM settings')->fetchAll() as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function too_many_attempts(string $kind, int $max, int $minutes): bool
{
    q('DELETE FROM attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    return (int) q_val(
        'SELECT COUNT(*) FROM attempts WHERE kind = ? AND ip = ? AND created_at > NOW() - INTERVAL ' . (int) $minutes . ' MINUTE',
        [$kind, client_ip()]
    ) >= $max;
}

function record_attempt(string $kind): void
{
    q('INSERT INTO attempts (kind, ip) VALUES (?, ?)', [$kind, client_ip()]);
}

function icon(string $name, string $class = 'icon'): string
{
    static $paths = [
        'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'basket'   => '<path d="M4.5 9.5h15l-1.5 8.7a2 2 0 0 1-2 1.8H8a2 2 0 0 1-2-1.8L4.5 9.5Z"/><path d="m8.5 9.5 3.5-5 3.5 5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>',
        'cash'     => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.75"/><path d="M6 9.5v5M18 9.5v5"/>',
        'badge'    => '<rect x="4" y="3" width="16" height="18" rx="2.5"/><circle cx="12" cy="10" r="3"/><path d="M8 17a4 4 0 0 1 8 0"/>',
        'sliders'  => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
        'sign-out' => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="m15 8 4 4-4 4M19 12H9"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'collapse' => '<path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/>',
        'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'     => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'printer'  => '<path d="M7 9V3.5h10V9"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v6.5H7z"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8.5-8.5M16 7l2.5 2.5"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

// Records a status change. Returns false if someone else changed the order first.
function change_status(int $orderId, string $from, string $to, int $userId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    $st = q(
        'UPDATE orders SET status = ?, completed_at = ' . ($to === 'Completed' ? 'NOW()' : 'NULL') . ' WHERE id = ? AND status = ?',
        [$to, $orderId, $from]
    );
    if ($st->rowCount() !== 1) {
        $pdo->rollBack();
        return false;
    }
    q('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)', [$orderId, $to, $userId]);
    $pdo->commit();
    return true;
}

// Shared POST handler for the "Move to <next>" buttons on lists and boards.
function handle_advance_post(string $back): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || input('action') !== 'advance') {
        return;
    }
    $order = q_one('SELECT id, order_no, status FROM orders WHERE id = ?', [(int) input('order_id')]);
    $expected = input('from');
    if (!$order || $order['status'] !== $expected || !($next = next_status($expected))) {
        flash('That order was already updated. The list now shows its current status.', 'warning');
    } elseif (change_status((int) $order['id'], $expected, $next, current_user()['id'])) {
        flash($order['order_no'] . ' moved to ' . $next . '.');
    } else {
        flash('That order was already updated. The list now shows its current status.', 'warning');
    }
    redirect($back);
}

function advance_button(array $o, string $class = 'btn btn-sm btn-soft'): string
{
    $next = next_status($o['status']);
    if (!$next) {
        return '';
    }
    $confirm = '';
    if ($next === 'Completed' && isset($o['amount_due'], $o['paid']) && (float) $o['amount_due'] - (float) $o['paid'] > 0.004) {
        $confirm = ' data-confirm="' . e($o['order_no'] . ' still has ' . money((float) $o['amount_due'] - (float) $o['paid']) . ' unpaid. Mark it completed anyway?') . '"';
    }
    return '<form method="post" class="inline-form"' . $confirm . '>' . csrf_field()
        . '<input type="hidden" name="action" value="advance">'
        . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '">'
        . '<input type="hidden" name="from" value="' . e($o['status']) . '">'
        . '<button class="' . e($class) . '" type="submit">' . e($next === 'Completed' ? 'Mark completed' : 'Move to ' . $next) . '</button></form>';
}
