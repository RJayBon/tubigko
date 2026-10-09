<?php
/**
 * TubigKo — data access layer.
 */
declare(strict_types=1);

function load_gallons(): array
{
    $rows = db_query('SELECT * FROM gallons ORDER BY name ASC');
    return array_map(static function ($g) {
        return [
            'db_id'  => (int)$g['id'],
            'id'     => $g['code'],
            'name'   => $g['name'],
            'type'   => $g['water_type'],
            'size'   => $g['gallon_size'],
            'price'  => (float)$g['price_per_gallon'],
            'stock'  => (int)$g['stock'],
            'desc'   => $g['description'],
            'status' => $g['status'],
        ];
    }, $rows);
}

function load_customers(): array
{
    $rows = db_query(
        "SELECT u.*, COALESCE(o.order_count, 0) AS order_count
         FROM users u
         LEFT JOIN (
             SELECT customer_id, COUNT(*) AS order_count
             FROM deliveries GROUP BY customer_id
         ) o ON o.customer_id = u.id
         WHERE u.role = 'customer'
         ORDER BY u.created_at DESC"
    );
    return array_map(static function ($c) {
        return [
            'db_id'   => (int)$c['id'],
            'id'      => sprintf('CUST-%04d', $c['id']),
            'name'    => $c['full_name'],
            'phone'   => $c['phone'] ?? '',
            'email'   => $c['email'],
            'address' => $c['address'] ?? '',
            'joined'  => substr((string)$c['created_at'], 0, 10),
            'orders'  => (int)$c['order_count'],
            'status'  => ucfirst((string)$c['status']),
        ];
    }, $rows);
}

function load_deliveries(?int $customerId = null): array
{
    $sql = "SELECT d.*, u.full_name AS customer_name,
                   COALESCE(GROUP_CONCAT(
                       CONCAT(di.quantity, ' x ', di.gallon_name)
                       ORDER BY di.id SEPARATOR ', '
                   ), '') AS items_label
            FROM deliveries d
            JOIN users u ON u.id = d.customer_id
            LEFT JOIN delivery_items di ON di.delivery_id = d.id";
    $params = [];
    if ($customerId !== null) {
        $sql .= ' WHERE d.customer_id = ?';
        $params[] = $customerId;
    }
    $sql .= ' GROUP BY d.id ORDER BY d.created_at DESC';

    $rows = db_query($sql, $params);

    return array_map(static function ($d) {
        return [
            'db_id'       => (int)$d['id'],
            'id'          => $d['order_code'],
            'customer'    => $d['customer_name'],
            'customer_id' => (int)$d['customer_id'],
            'address'     => $d['delivery_address'],
            'items'       => $d['items_label'] !== '' ? $d['items_label'] : '—',
            'rider'       => $d['rider'] ?: 'Not yet assigned',
            'scheduled'   => date('Y-m-d', strtotime((string)$d['delivery_date'])) . ' ' . $d['delivery_time'],
            'notes'       => $d['notes'],
            'total'       => (float)$d['total_amount'],
            'raw_status'  => $d['status'],
            'status'      => delivery_group_label($d['status']),
            'stage'       => delivery_stage_label($d['status']),
        ];
    }, $rows);
}

function load_payment_methods(bool $enabledOnly = false): array
{
    $sql = 'SELECT * FROM payment_methods';
    if ($enabledOnly) $sql .= " WHERE status = 'enabled'";
    $sql .= ' ORDER BY id ASC';

    return array_map(static function ($m) {
        return [
            'db_id'    => (int)$m['id'],
            'id'       => $m['code'],
            'method'   => $m['method'],
            'provider' => $m['provider'] ?? '',
            'fee'      => (float)$m['fee'],
            'status'   => ucfirst((string)$m['status']),
        ];
    }, db_query($sql));
}

function load_payments(?int $customerId = null): array
{
    $sql = "SELECT p.*, u.full_name AS customer_name, d.order_code
            FROM payments p
            JOIN users u ON u.id = p.customer_id
            JOIN deliveries d ON d.id = p.delivery_id";
    $params = [];
    if ($customerId !== null) {
        $sql .= ' WHERE p.customer_id = ?';
        $params[] = $customerId;
    }
    $sql .= ' ORDER BY p.created_at DESC';

    return array_map(static function ($p) {
        return [
            'db_id'       => (int)$p['id'],
            'id'          => $p['payment_code'],
            'customer'    => $p['customer_name'],
            'customer_id' => (int)$p['customer_id'],
            'order'       => $p['order_code'],
            'delivery_id' => (int)$p['delivery_id'],
            'method'      => $p['payment_method'],
            'reference'   => $p['reference_number'],
            'amount'      => (float)$p['amount'],
            'date'        => substr((string)($p['payment_date'] ?? $p['created_at']), 0, 10),
            'raw_status'  => $p['status'],
            'status'      => ucfirst((string)$p['status']),
        ];
    }, db_query($sql, $params));
}

function load_notifications(?int $userId = null): array
{
    if ($userId !== null) {
        $sql = 'SELECT * FROM notifications
                WHERE user_id = ? OR user_id IS NULL
                ORDER BY created_at DESC';
        $params = [$userId];
    } else {
        $sql = 'SELECT * FROM notifications ORDER BY created_at DESC';
        $params = [];
    }
    $rows = db_query($sql, $params);

    foreach ($rows as $k => $n) {
        $isRead = (bool)$n['is_read'];
        if ($userId !== null && $n['user_id'] === null) {
            $r = db_one(
                'SELECT 1 AS c FROM notification_reads WHERE notification_id = ? AND user_id = ? LIMIT 1',
                [(int)$n['id'], $userId]
            );
            if ($r) $isRead = true;
        }
        $rows[$k] = [
            'db_id'    => (int)$n['id'],
            'id'       => $n['notif_code'],
            'user_id'  => $n['user_id'] !== null ? (int)$n['user_id'] : null,
            'title'    => $n['title'],
            'message'  => $n['message'],
            'audience' => $n['audience'],
            'date'     => $n['created_at'],
            'type'     => $n['type'],
            'read'     => $isRead,
        ];
    }
    return $rows;
}

function count_unread_notifications(int $userId): int
{
    $row = db_one(
        'SELECT COUNT(*) AS c FROM notifications n
         WHERE (n.user_id = ? AND n.is_read = 0)
            OR (n.user_id IS NULL AND NOT EXISTS (
                 SELECT 1 FROM notification_reads r
                 WHERE r.notification_id = n.id AND r.user_id = ?
               ))',
        [$userId, $userId]
    );
    return (int)($row['c'] ?? 0);
}

function count_notifications_since(int $lastSeenId): int
{
    $row = db_one('SELECT COUNT(*) AS c FROM notifications WHERE id > ?', [$lastSeenId]);
    return (int)($row['c'] ?? 0);
}

function notifications_max_id(): int
{
    $row = db_one('SELECT MAX(id) AS m FROM notifications');
    return (int)($row['m'] ?? 0);
}

function next_code(string $prefix, int $pad = 3): string
{
    $pdo = db();
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        db_exec_or_fail('INSERT IGNORE INTO code_sequences (prefix, last_number) VALUES (?, 0)', [$prefix]);
        $row = db_one('SELECT last_number FROM code_sequences WHERE prefix = ? FOR UPDATE', [$prefix]);
        $next = ((int)($row['last_number'] ?? 0)) + 1;
        db_exec_or_fail('UPDATE code_sequences SET last_number = ? WHERE prefix = ?', [$next, $prefix]);
        if ($ownTx) $pdo->commit();
        return $prefix . '-' . str_pad((string)$next, $pad, '0', STR_PAD_LEFT);
    } catch (Throwable $ex) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

function create_gallon(array $in, ?string &$error = null): bool
{
    try {
        $code = next_code('G', 3);
        return db_exec(
            'INSERT INTO gallons (code, name, water_type, gallon_size, price_per_gallon, stock, description)
             VALUES (?,?,?,?,?,?,?)',
            [$code, $in['name'], $in['type'], $in['size'], $in['price'], $in['stock'], $in['description']],
            $error
        );
    } catch (Throwable $ex) {
        app_log('error', 'create_gallon failed: ' . $ex->getMessage());
        $error = 'Could not create gallon.';
        return false;
    }
}

function update_customer_status(int $id, string $status, ?string &$error = null): bool
{
    if (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
        return false;
    }
    return db_exec(
        'UPDATE users SET status = ? WHERE id = ? AND role = "customer"',
        [$status, $id], $error
    );
}

function delete_customer(int $id, ?string &$error = null): bool
{
    $active = db_one(
        "SELECT COUNT(*) AS c FROM deliveries
         WHERE customer_id = ? AND status NOT IN ('delivered','cancelled')",
        [$id]
    );
    if ($active && (int)$active['c'] > 0) {
        $error = 'This customer has active deliveries. Deactivate the account instead.';
        return false;
    }
    return db_exec('DELETE FROM users WHERE id = ? AND role = "customer"', [$id], $error);
}

function delivery_transitions(): array
{
    return [
        'pending'          => ['confirmed', 'cancelled'],
        'confirmed'        => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered'        => [],
        'cancelled'        => [],
    ];
}

function update_delivery_status(int $id, string $status, ?string $rider = null, ?string &$error = null): bool
{
    $current = db_one('SELECT status FROM deliveries WHERE id = ?', [$id]);
    if (!$current) {
        $error = 'Delivery not found.';
        return false;
    }
    $allowed = delivery_transitions()[$current['status']] ?? [];
    if (!in_array($status, $allowed, true)) {
        $error = 'Cannot move from "' . $current['status'] . '" to "' . $status . '".';
        return false;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        if ($rider !== null) {
            db_exec_or_fail(
                'UPDATE deliveries SET status = ?, rider = ? WHERE id = ?',
                [$status, $rider === '' ? null : $rider, $id]
            );
        } else {
            db_exec_or_fail('UPDATE deliveries SET status = ? WHERE id = ?', [$status, $id]);
        }

        if ($status === 'cancelled') {
            $items = db_query('SELECT gallon_id, quantity FROM delivery_items WHERE delivery_id = ?', [$id]);
            foreach ($items as $it) {
                db_exec_or_fail(
                    'UPDATE gallons SET stock = stock + ? WHERE id = ?',
                    [(int)$it['quantity'], (int)$it['gallon_id']]
                );
            }
        }

        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        app_log('error', 'update_delivery_status failed: ' . $ex->getMessage());
        $error = 'Could not update delivery status.';
        return false;
    }
}

function update_payment_status(int $id, string $status, ?string &$error = null): bool
{
    if (!in_array($status, ['paid', 'failed', 'pending', 'refunded'], true)) {
        $error = 'Invalid payment status.';
        return false;
    }
    $paymentDate = $status === 'paid' ? date('Y-m-d H:i:s') : null;
    return db_exec(
        'UPDATE payments SET status = ?, payment_date = COALESCE(?, payment_date) WHERE id = ?',
        [$status, $paymentDate, $id], $error
    );
}

function create_notification(
    ?int $userId, string $audience, string $title, string $message,
    string $type, ?string &$error = null
): bool {
    try {
        $code = next_code('N', 3);
    } catch (Throwable $ex) {
        app_log('error', 'next_code(N) failed: ' . $ex->getMessage());
        $error = 'Could not allocate notification code.';
        return false;
    }
    return db_exec(
        'INSERT INTO notifications (notif_code, user_id, audience, title, message, type)
         VALUES (?,?,?,?,?,?)',
        [$code, $userId, $audience, $title, $message, $type], $error
    );
}

function mark_notifications_read(int $userId, ?int $singleId = null): void
{
    if ($singleId !== null) {
        $n = db_one('SELECT id, user_id FROM notifications WHERE id = ?', [$singleId]);
        if (!$n) return;
        if ($n['user_id'] === null) {
            db_exec('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)',
                [$singleId, $userId], $e);
        } else {
            db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
                [$singleId, $userId], $e);
        }
        return;
    }

    db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId], $e);
    $broadcasts = db_query('SELECT id FROM notifications WHERE user_id IS NULL');
    foreach ($broadcasts as $b) {
        db_exec('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)',
            [(int)$b['id'], $userId], $e2);
    }
}

function admin_mark_notifications_seen(int $adminId, ?int $upToId = null): void
{
    $max = $upToId ?? notifications_max_id();
    $_SESSION['admin_notif_last_seen'] = $max;
}

function admin_unread_count(): int
{
    $cursor = (int)($_SESSION['admin_notif_last_seen'] ?? 0);
    return count_notifications_since($cursor);
}

function create_order(
    int $customerId,
    string $address,
    string $date,
    string $time,
    ?string $notes,
    array $cartItems,
    string $paymentMethod,
    ?string $reference,
    ?string &$error = null
): ?string {
    if (empty($cartItems)) {
        $error = 'Your cart is empty. Please select at least one gallon.';
        return null;
    }

    $selected = null;
    foreach (load_payment_methods(true) as $m) {
        if ($m['method'] === $paymentMethod) { $selected = $m; break; }
    }
    if (!$selected) {
        $error = 'Please choose a valid payment method.';
        return null;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $lines    = [];
        $subtotal = 0.0;
        foreach ($cartItems as $item) {
            $gallonId = (int)($item['id'] ?? 0);
            $qty      = max(1, (int)($item['qty'] ?? 1));
            if ($gallonId <= 0) continue;

            $gallon = db_one('SELECT * FROM gallons WHERE id = ? FOR UPDATE', [$gallonId]);
            if (!$gallon) continue;
            if ($gallon['status'] !== 'available') {
                $pdo->rollBack();
                $error = $gallon['name'] . ' is not available right now.';
                return null;
            }
            if ((int)$gallon['stock'] < $qty) {
                $pdo->rollBack();
                $error = 'Not enough stock for ' . $gallon['name'] . ' (only ' . $gallon['stock'] . ' left).';
                return null;
            }

            $lineTotal = $qty * (float)$gallon['price_per_gallon'];
            $subtotal += $lineTotal;
            $lines[] = [
                'gallon_id'   => (int)$gallon['id'],
                'gallon_name' => $gallon['name'],
                'qty'         => $qty,
                'price'       => (float)$gallon['price_per_gallon'],
                'line_total'  => $lineTotal,
            ];
        }

        if (empty($lines)) {
            $pdo->rollBack();
            $error = 'None of the selected gallons could be found.';
            return null;
        }

        $total = $subtotal + (float)$selected['fee'];

        $orderCode = next_code('ORD', 4);
        db_exec_or_fail(
            'INSERT INTO deliveries
                (order_code, customer_id, delivery_address, delivery_date, delivery_time,
                 notes, total_amount, status)
             VALUES (?,?,?,?,?,?,?,?)',
            [$orderCode, $customerId, $address, $date, $time, $notes, $total, 'pending']
        );
        $deliveryId = (int)$pdo->lastInsertId();

        foreach ($lines as $l) {
            db_exec_or_fail(
                'INSERT INTO delivery_items
                    (delivery_id, gallon_id, gallon_name, quantity, price_per_gallon, total_amount)
                 VALUES (?,?,?,?,?,?)',
                [$deliveryId, $l['gallon_id'], $l['gallon_name'], $l['qty'], $l['price'], $l['line_total']]
            );
            db_exec_or_fail(
                'UPDATE gallons SET stock = stock - ? WHERE id = ?',
                [$l['qty'], $l['gallon_id']]
            );
        }

        $paymentCode = next_code('PAY', 4);
        db_exec_or_fail(
            'INSERT INTO payments
                (payment_code, customer_id, delivery_id, amount, payment_method, reference_number, status)
             VALUES (?,?,?,?,?,?,?)',
            [$paymentCode, $customerId, $deliveryId, $total, $paymentMethod, $reference, 'pending']
        );

        $notifCode = next_code('N', 3);
        db_exec_or_fail(
            'INSERT INTO notifications (notif_code, user_id, audience, title, message, type)
             VALUES (?,?,?,?,?,?)',
            [$notifCode, $customerId, 'Customer', 'Delivery request submitted',
             "Your order $orderCode was submitted and is being prepared.", 'Delivery']
        );

        $pdo->commit();
        return $orderCode;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        app_log('error', 'create_order failed: ' . $ex->getMessage());
        $error = 'Could not submit your order. Please try again.';
        return null;
    }
}