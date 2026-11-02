<?php
session_start();
require_once 'connect.php';

// Simple Records UI (dev/admin) — lists account balances and transactions.
// Features: filter by country_code, phone_number, tx_type, date range; CSV export; pagination.

// Read filters from GET
$country = $_GET['country_code'] ?? '';
$phone = $_GET['phone_number'] ?? '';
$tx_type = $_GET['tx_type'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$export = isset($_GET['export']) ? $_GET['export'] : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// Helper to bind parameters dynamically
function buildWhere(&$params, &$types) {
    $w = [];
    if (!empty($params['country_code'])) { $w[] = "country_code = ?"; $types .= 's'; }
    if (!empty($params['phone_number'])) { $w[] = "phone_number = ?"; $types .= 's'; }
    if (!empty($params['tx_type'])) { $w[] = "tx_type = ?"; $types .= 's'; }
    if (!empty($params['from'])) { $w[] = "created_at >= ?"; $types .= 's'; }
    if (!empty($params['to'])) { $w[] = "created_at <= ?"; $types .= 's'; }
    return $w ? 'WHERE ' . implode(' AND ', $w) : '';
}

$filters = ['country_code'=>$country,'phone_number'=>$phone,'tx_type'=>$tx_type,'from'=>$from,'to'=>$to];

// If exporting CSV, we'll stream transactions only (balances export also possible)
if ($export === 'csv') {
    // Build where and params
    $types = '';
    $where = buildWhere($filters, $types);

    $sql = "SELECT id,country_code,phone_number,tx_type,counterparty,amount,currency,direction,balance_before,balance_after,note,created_at FROM transactions " . $where . " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // bind params if any
        $bindParams = [];
        if (!empty($country)) $bindParams[] = $country;
        if (!empty($phone)) $bindParams[] = $phone;
        if (!empty($tx_type)) $bindParams[] = $tx_type;
        if (!empty($from)) $bindParams[] = $from;
        if (!empty($to)) $bindParams[] = $to;
        if ($bindParams) {
            $stmt->bind_param($types, ...$bindParams);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transactions_export.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','country_code','phone_number','tx_type','counterparty','amount','currency','direction','balance_before','balance_after','note','created_at']);
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [$row['id'],$row['country_code'],$row['phone_number'],$row['tx_type'],$row['counterparty'],$row['amount'],$row['currency'],$row['direction'],$row['balance_before'],$row['balance_after'],$row['note'],$row['created_at']]);
        }
        fclose($out);
        exit;
    } else {
        die('Failed to prepare export query: ' . $conn->error);
    }
}

// Balance list (simple)
$balances = [];
{
    $sql = "SELECT country_code,phone_number,balance,updated_at FROM account_balances";
    $conds = [];
    $params = [];
    $types = '';
    if (!empty($country)) { $conds[] = 'country_code = ?'; $params[] = $country; $types .= 's'; }
    if (!empty($phone)) { $conds[] = 'phone_number = ?'; $params[] = $phone; $types .= 's'; }
    if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
    $sql .= ' ORDER BY updated_at DESC LIMIT 500';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $balances[] = $r;
        $stmt->close();
    }
}

// Transactions with pagination
$txs = [];
$total = 0;
{
    $types = '';
    $where = buildWhere($filters, $types);
    // count
    $countSql = "SELECT COUNT(*) AS cnt FROM transactions " . $where;
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        $bindParams = [];
        if (!empty($country)) $bindParams[] = $country;
        if (!empty($phone)) $bindParams[] = $phone;
        if (!empty($tx_type)) $bindParams[] = $tx_type;
        if (!empty($from)) $bindParams[] = $from;
        if (!empty($to)) $bindParams[] = $to;
        if ($bindParams) $countStmt->bind_param($types, ...$bindParams);
        $countStmt->execute();
        $cres = $countStmt->get_result()->fetch_assoc();
        $total = intval($cres['cnt'] ?? 0);
        $countStmt->close();
    }

    $offset = ($page-1)*$perPage;
    $sql = "SELECT id,country_code,phone_number,tx_type,counterparty,amount,currency,direction,balance_before,balance_after,note,created_at FROM transactions " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Build bind params
        $bindParams = [];
        if (!empty($country)) $bindParams[] = $country;
        if (!empty($phone)) $bindParams[] = $phone;
        if (!empty($tx_type)) $bindParams[] = $tx_type;
        if (!empty($from)) $bindParams[] = $from;
        if (!empty($to)) $bindParams[] = $to;
        // types for the above + ii for limit/offset
        $typesAll = $types . 'ii';
        $bindParams[] = $perPage;
        $bindParams[] = $offset;
        if ($bindParams) $stmt->bind_param($typesAll, ...$bindParams);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $txs[] = $r;
        $stmt->close();
    }
}

// Helper to build query string with preserved filters
function qs($overrides=[]) {
    $q = array_merge($_GET, $overrides);
    return http_build_query($q);
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Records — PaySecure (dev)</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:18px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:8px;font-size:13px} th{background:#f5f7fb}</style>
</head><body>
<h2>PaySecure — Records (dev)</h2>
<form method="get" style="margin-bottom:12px">
  <label>Country: <input name="country_code" value="<?= htmlspecialchars($country) ?>"></label>
  <label>Phone: <input name="phone_number" value="<?= htmlspecialchars($phone) ?>"></label>
  <label>Type: <input name="tx_type" value="<?= htmlspecialchars($tx_type) ?>"></label>
  <label>From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></label>
  <label>To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"></label>
  <button type="submit">Filter</button>
  <a href="?<?= qs(['export'=>'csv']) ?>">Export CSV</a>
</form>

<h3>Balances</h3>
<?php if (empty($balances)): ?>
  <div>No balances found.</div>
<?php else: ?>
  <table>
    <tr><th>Country</th><th>Phone</th><th>Balance</th><th>Updated At</th></tr>
    <?php foreach ($balances as $b): ?>
      <tr>
        <td><?= htmlspecialchars($b['country_code']) ?></td>
        <td><?= htmlspecialchars($b['phone_number']) ?></td>
        <td>₹<?= number_format($b['balance'],2) ?></td>
        <td><?= htmlspecialchars($b['updated_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>

<h3 style="margin-top:18px">Transactions (<?= $total ?>)</h3>
<?php if (empty($txs)): ?>
  <div>No transactions found for the filters.</div>
<?php else: ?>
  <table>
    <tr><th>ID</th><th>Date</th><th>Country</th><th>Phone</th><th>Type</th><th>Counterparty</th><th>Amount</th><th>Before</th><th>After</th><th>Note</th></tr>
    <?php foreach ($txs as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['id']) ?></td>
        <td><?= htmlspecialchars($t['created_at']) ?></td>
        <td><?= htmlspecialchars($t['country_code']) ?></td>
        <td><?= htmlspecialchars($t['phone_number']) ?></td>
        <td><?= htmlspecialchars($t['tx_type']) ?></td>
        <td><?= htmlspecialchars($t['counterparty']) ?></td>
        <td><?= htmlspecialchars($t['direction'] === 'out' ? '-' : '') ?>₹<?= number_format($t['amount'],2) ?></td>
        <td>₹<?= is_null($t['balance_before']) ? '-' : number_format($t['balance_before'],2) ?></td>
        <td>₹<?= is_null($t['balance_after']) ? '-' : number_format($t['balance_after'],2) ?></td>
        <td><?= htmlspecialchars($t['note']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div style="margin-top:12px">
    <?php
      $last = max(1, ceil($total / $perPage));
      if ($page > 1) echo '<a href="?'.qs(['page'=>$page-1]).'">&larr; Prev</a> ';
      echo " Page $page of $last ";
      if ($page < $last) echo ' <a href="?'.qs(['page'=>$page+1]).'">Next &rarr;</a>';
    ?>
  </div>
<?php endif; ?>

</body></html>
