<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';

$dbPath = __DIR__ . '/database.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// ── Properties ────────────────────────────────────────────────────────────────
$properties = $pdo->query("
    SELECT p.property_id, p.property_address, p.property_type,
           p.monthly_rent,
           l.name  AS landlord_name,
           t.name  AS tenant_name,
           c.contract_start, c.contract_end, c.frequency AS payment_frequency,
           p.gas_cert_due, p.electric_cert_due, p.epc_due_date,
           p.room_count, p.bathroom_count, p.epc_rating,
           CASE WHEN p.has_garden  = 1 THEN 'Yes' ELSE 'No' END AS has_garden,
           CASE WHEN p.allowed_pets= 1 THEN 'Yes' ELSE 'No' END AS allowed_pets,
           p.council_band
    FROM   properties p
    LEFT JOIN landlords l  ON p.landlord_id = l.landlord_id
    LEFT JOIN contracts c  ON p.property_id = c.property_id
                           AND date('now') BETWEEN c.contract_start AND c.contract_end
    LEFT JOIN tenants   t  ON c.tenant_id   = t.tenant_id
    WHERE  p.archived = 0
    ORDER  BY p.property_address
")->fetchAll(PDO::FETCH_ASSOC);

// ── Landlords (admin only) ────────────────────────────────────────────────────
$landlords = [];
if ($isAdmin) {
    $landlords = $pdo->query("
        SELECT landlord_id, name, phone, email, address,
               id_type, id_number, id_expiry_date, number_of_properties
        FROM   landlords
        WHERE  archived = 0
        ORDER  BY name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Tenants ───────────────────────────────────────────────────────────────────
$tenantQuery = $isAdmin
    ? "SELECT t.tenant_id, t.name, t.phone, t.email,
              t.id_type, t.id_number, t.id_expiry_date,
              c.contract_start, c.contract_end, c.frequency AS payment_frequency,
              p.property_address
       FROM   tenants t
       LEFT JOIN contracts   c ON t.tenant_id   = c.tenant_id
                               AND date('now') BETWEEN c.contract_start AND c.contract_end
       LEFT JOIN properties  p ON c.property_id = p.property_id
       WHERE  t.archived = 0
       ORDER  BY t.name"
    : "SELECT t.tenant_id, t.name, t.phone, t.email,
              c.contract_start, c.contract_end, c.frequency AS payment_frequency,
              p.property_address
       FROM   tenants t
       LEFT JOIN contracts   c ON t.tenant_id   = c.tenant_id
                               AND date('now') BETWEEN c.contract_start AND c.contract_end
       LEFT JOIN properties  p ON c.property_id = p.property_id
       WHERE  t.archived = 0
       ORDER  BY t.name";

$tenants = $pdo->query($tenantQuery)->fetchAll(PDO::FETCH_ASSOC);

// ── Transactions ──────────────────────────────────────────────────────────────
$transactions = $pdo->query("
    SELECT tr.transaction_id,
           tr.transaction_amount, tr.date_of_transaction,
           tr.payment_period, tr.next_due, tr.status,
           t.name  AS tenant_name,
           p.property_address
    FROM   transactions tr
    LEFT JOIN tenants     t ON tr.tenant_id  = t.tenant_id
    LEFT JOIN properties  p ON tr.property_id= p.property_id
    WHERE  tr.archived = 0
    ORDER  BY tr.date_of_transaction DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Viewings ──────────────────────────────────────────────────────────────────
$viewings = $pdo->query("
    SELECT vb.booking_id, vb.viewer_name, vb.viewer_phone, vb.email,
           vb.date_time, vb.status,
           p.property_address,
           u.name AS agent_name
    FROM   viewing_bookings vb
    LEFT JOIN properties p ON vb.property_id = p.property_id
    LEFT JOIN users      u ON vb.user_id     = u.user_id
    WHERE  vb.archived = 0
    ORDER  BY vb.date_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Output ────────────────────────────────────────────────────────────────────
$filename = 'iLet4You_Export_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Helper: wrap a value in a <td> with optional inline style
function td($val, $style = ''): string {
    $val = htmlspecialchars((string)($val ?? ''), ENT_QUOTES);
    return $style ? "<td style=\"$style\">$val</td>" : "<td>$val</td>";
}

function money($v): string { return '£' . number_format((float)$v, 2); }

// Status badge colour
function statusColour(string $status): string {
    return match(strtolower($status)) {
        'paid', 'complete', 'confirmed' => '#d4edda',
        'pending'                       => '#fff3cd',
        'not complete', 'cancelled'     => '#f8d7da',
        'scheduled'                     => '#cce5ff',
        default                         => '#ffffff',
    };
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml><x:ExcelWorkbook><x:ExcelWorksheets>
  <x:ExcelWorksheet><x:Name>Properties</x:Name><x:WorksheetOptions><x:Selected/></x:WorksheetOptions></x:ExcelWorksheet>
  <?php if ($isAdmin): ?><x:ExcelWorksheet><x:Name>Landlords</x:Name></x:ExcelWorksheet><?php endif; ?>
  <x:ExcelWorksheet><x:Name>Tenants</x:Name></x:ExcelWorksheet>
  <x:ExcelWorksheet><x:Name>Transactions</x:Name></x:ExcelWorksheet>
  <x:ExcelWorksheet><x:Name>Viewings</x:Name></x:ExcelWorksheet>
</x:ExcelWorksheets></x:ExcelWorkbook></xml>
<![endif]-->
<style>
  body        { font-family: Arial, sans-serif; font-size: 11pt; }
  table       { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
  th          { background-color: #1a3c5e; color: #ffffff; font-weight: bold;
                padding: 8px 12px; text-align: left; border: 1px solid #0d2840;
                white-space: nowrap; }
  td          { padding: 6px 12px; border: 1px solid #d0d0d0; vertical-align: middle; }
  tr:nth-child(even) td { background-color: #f2f6fb; }
  tr:hover td { background-color: #e8f0fe; }
  .section-title { font-size: 14pt; font-weight: bold; color: #1a3c5e;
                   background: #dce8f5; padding: 8px 12px;
                   border-left: 5px solid #1a3c5e; margin: 20px 0 6px 0; }
  .export-header { font-size: 18pt; font-weight: bold; color: #1a3c5e;
                   padding-bottom: 4px; }
  .export-sub    { font-size: 10pt; color: #666; margin-bottom: 20px; }
  .vacant  { color: #c0392b; font-style: italic; }
  .badge   { padding: 2px 8px; border-radius: 3px; font-size: 10pt; }
</style>
</head>
<body>

<div class="export-header">iLet4You — Full Export</div>
<div class="export-sub">Generated: <?= date('d/m/Y H:i') ?> <?= $isAdmin ? '· Admin View' : '· Standard View' ?></div>

<!-- ══ PROPERTIES ══ -->
<div class="section-title">🏠 Properties</div>
<table>
  <thead>
    <tr>
      <th>#</th><th>Address</th><th>Type</th><th>Monthly Rent</th>
      <th>Landlord</th><th>Tenant</th><th>Contract Start</th><th>Contract End</th>
      <th>Payment</th><th>Gas Cert</th><th>Electric Cert</th><th>EPC Due</th>
      <th>Rooms</th><th>Baths</th><th>EPC</th><th>Garden</th><th>Pets</th><th>Council Band</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($properties as $i => $r): ?>
    <tr>
      <?= td($r['property_id']) ?>
      <?= td($r['property_address'], 'font-weight:bold') ?>
      <?= td($r['property_type']) ?>
      <?= td(money($r['monthly_rent']), 'font-weight:bold; color:#1a3c5e') ?>
      <?= td($r['landlord_name']) ?>
      <td style="<?= empty($r['tenant_name']) ? 'color:#c0392b;font-style:italic' : '' ?>">
        <?= htmlspecialchars($r['tenant_name'] ?? 'Vacant') ?>
      </td>
      <?= td($r['contract_start']) ?>
      <?= td($r['contract_end']) ?>
      <?= td($r['payment_frequency']) ?>
      <?= td($r['gas_cert_due']) ?>
      <?= td($r['electric_cert_due']) ?>
      <?= td($r['epc_due_date']) ?>
      <?= td($r['room_count']) ?>
      <?= td($r['bathroom_count']) ?>
      <?= td($r['epc_rating']) ?>
      <?= td($r['has_garden']) ?>
      <?= td($r['allowed_pets']) ?>
      <?= td($r['council_band']) ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ══ LANDLORDS (admin only) ══ -->
<?php if ($isAdmin): ?>
<div class="section-title">👔 Landlords</div>
<table>
  <thead>
    <tr>
      <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th>
      <th>ID Type</th><th>ID Number</th><th>ID Expiry</th><th>Properties</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($landlords as $r): ?>
    <tr>
      <?= td($r['landlord_id']) ?>
      <?= td($r['name'], 'font-weight:bold') ?>
      <?= td($r['phone']) ?>
      <?= td($r['email']) ?>
      <?= td($r['address']) ?>
      <?= td($r['id_type']) ?>
      <?= td($r['id_number']) ?>
      <?= td($r['id_expiry_date']) ?>
      <?= td($r['number_of_properties']) ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<!-- ══ TENANTS ══ -->
<div class="section-title">👤 Tenants</div>
<table>
  <thead>
    <tr>
      <th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Property</th>
      <?php if ($isAdmin): ?><th>ID Type</th><th>ID Number</th><th>ID Expiry</th><?php endif; ?>
      <th>Contract Start</th><th>Contract End</th><th>Payment</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tenants as $r): ?>
    <tr>
      <?= td($r['tenant_id']) ?>
      <?= td($r['name'], 'font-weight:bold') ?>
      <?= td($r['phone']) ?>
      <?= td($r['email']) ?>
      <?= td($r['property_address'] ?? '—') ?>
      <?php if ($isAdmin): ?>
        <?= td($r['id_type']) ?>
        <?= td($r['id_number']) ?>
        <?= td($r['id_expiry_date']) ?>
      <?php endif; ?>
      <?= td($r['contract_start'] ?? '') ?>
      <?= td($r['contract_end']   ?? '') ?>
      <?= td($r['payment_frequency'] ?? '') ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- ══ TRANSACTIONS ══ -->
<div class="section-title">💳 Transactions</div>
<table>
  <thead>
    <tr>
      <th>#</th><th>Tenant</th><th>Property</th><th>Amount</th>
      <th>Date</th><th>Period</th><th>Next Due</th><th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($transactions as $r): ?>
    <tr>
      <?= td($r['transaction_id']) ?>
      <?= td($r['tenant_name']) ?>
      <?= td($r['property_address']) ?>
      <?= td(money($r['transaction_amount']), 'font-weight:bold; color:#1a3c5e') ?>
      <?= td($r['date_of_transaction']) ?>
      <?= td($r['payment_period']) ?>
      <?= td($r['next_due']) ?>
      <td>
        <span class="badge" style="background:<?= statusColour($r['status'] ?? '') ?>">
          <?= htmlspecialchars(ucfirst($r['status'] ?? '')) ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>


<div class="section-title">Viewings</div>
<table>
  <thead>
    <tr>
      <th>#</th><th>Viewer</th><th>Phone</th><th>Email</th>
      <th>Property</th><th>Date / Time</th><th>Status</th><th>Agent</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($viewings as $r): ?>
    <tr>
      <?= td($r['booking_id']) ?>
      <?= td($r['viewer_name'], 'font-weight:bold') ?>
      <?= td($r['viewer_phone']) ?>
      <?= td($r['email']) ?>
      <?= td($r['property_address']) ?>
      <?= td($r['date_time']) ?>
      <td>
        <span class="badge" style="background:<?= statusColour($r['status'] ?? '') ?>">
          <?= htmlspecialchars(ucfirst($r['status'] ?? '')) ?>
        </span>
      </td>
      <?= td($r['agent_name'] ?? '—') ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body>
</html>