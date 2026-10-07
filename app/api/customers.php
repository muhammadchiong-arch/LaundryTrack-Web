<?php
// Customer search for the New order form. Returns JSON.
require __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Signed out']);
    exit;
}

$term = input('q');
$rows = [];
if (mb_strlen($term) >= 2) {
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $digits = preg_replace('/\D+/', '', $term);
    $sql = 'SELECT c.id, c.name, c.phone, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders
              FROM customers c WHERE c.name LIKE ?';
    $params = [$like];
    if (strlen($digits) >= 3) {
        $sql .= ' OR c.phone LIKE ?';
        $params[] = '%' . $digits . '%';
    }
    $sql .= ' ORDER BY c.name LIMIT 8';
    foreach (q($sql, $params) as $r) {
        $rows[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'phone' => fmt_phone($r['phone']),
                   'initials' => initials($r['name']), 'orders' => (int) $r['orders']];
    }
}
echo json_encode($rows);
