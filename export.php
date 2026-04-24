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

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

$year  = max(2000, min(2099, $year));
$month = max(1,    min(12,   $month));

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$monthLabel = date('F Y', strtotime($monthStart));

function money($v): string { return '£' . number_format((float)$v, 2); }

function statusColour(string $s): string {
    return match(strtolower($s)) {
        'paid', 'complete', 'confirmed' => '#1e7e34',
        'pending'                       => '#856404',
        'not complete', 'cancelled'     => '#721c24',
        'scheduled'                     => '#004085',
        default                         => '#555',
    };
}
function statusBg(string $s): string {
    return match(strtolower($s)) {
        'paid', 'complete', 'confirmed' => '#d4edda',
        'pending'                       => '#fff3cd',
        'not complete', 'cancelled'     => '#f8d7da',
        'scheduled'                     => '#cce5ff',
        default                         => '#f0f0f0',
    };
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }

$properties = $pdo->prepare("
    SELECT p.property_id, p.property_address, p.property_type,
           p.monthly_rent,
           l.name AS landlord_name,
           t.name AS tenant_name,
           c.contract_start, c.contract_end,
           p.gas_cert_due, p.electric_cert_due, p.epc_due_date,
           p.room_count, p.epc_rating
    FROM   properties p
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    LEFT JOIN contracts c ON p.property_id = c.property_id
                          AND c.contract_start <= :me
                          AND c.contract_end   >= :ms
    LEFT JOIN tenants   t ON c.tenant_id = t.tenant_id
    WHERE  p.archived = 0
    ORDER  BY p.property_address
");
$properties->execute([':ms' => $monthStart, ':me' => $monthEnd]);
$properties = $properties->fetchAll(PDO::FETCH_ASSOC);

$transactions = $pdo->prepare("
    SELECT tr.transaction_id,
           tr.transaction_amount, tr.date_of_transaction,
           tr.payment_period, tr.next_due, tr.status,
           t.name  AS tenant_name,
           p.property_address
    FROM   transactions tr
    LEFT JOIN tenants    t ON tr.tenant_id   = t.tenant_id
    LEFT JOIN properties p ON tr.property_id = p.property_id
    WHERE  tr.archived = 0
      AND  tr.date_of_transaction BETWEEN :ms AND :me
    ORDER  BY tr.date_of_transaction
");
$transactions->execute([':ms' => $monthStart, ':me' => $monthEnd]);
$transactions = $transactions->fetchAll(PDO::FETCH_ASSOC);

$viewings = $pdo->prepare("
    SELECT vb.booking_id, vb.viewer_name, vb.viewer_phone, vb.email,
           vb.date_time, vb.status,
           p.property_address,
           u.name AS agent_name
    FROM   viewing_bookings vb
    LEFT JOIN properties p ON vb.property_id = p.property_id
    LEFT JOIN users      u ON vb.user_id     = u.user_id
    WHERE  vb.archived = 0
      AND  date(vb.date_time) BETWEEN :ms AND :me
    ORDER  BY vb.date_time
");
$viewings->execute([':ms' => $monthStart, ':me' => $monthEnd]);
$viewings = $viewings->fetchAll(PDO::FETCH_ASSOC);

$certWarn = $pdo->prepare("
    SELECT property_address,
           gas_cert_due, electric_cert_due, epc_due_date
    FROM   properties
    WHERE  archived = 0
      AND (
            gas_cert_due      BETWEEN :me AND date(:me, '+60 days')
         OR electric_cert_due BETWEEN :me AND date(:me, '+60 days')
         OR epc_due_date      BETWEEN :me AND date(:me, '+60 days')
      )
    ORDER  BY property_address
");
$certWarn->execute([':me' => $monthEnd]);
$certWarn = $certWarn->fetchAll(PDO::FETCH_ASSOC);

$totalProperties = count($properties);
$vacantCount     = count(array_filter($properties, fn($p) => empty($p['tenant_name'])));
$occupiedCount   = $totalProperties - $vacantCount;

$expectedRent = array_sum(array_column(
    array_filter($properties, fn($p) => !empty($p['tenant_name'])),
    'monthly_rent'
));

$collectedRent = array_sum(array_map(
    fn($tr) => strtolower($tr['status']) === 'paid' ? (float)$tr['transaction_amount'] : 0,
    $transactions
));

$outstandingRent = array_sum(array_map(
    fn($tr) => strtolower($tr['status']) === 'pending' ? (float)$tr['transaction_amount'] : 0,
    $transactions
));

$totalViewings    = count($viewings);
$confirmedViewings = count(array_filter($viewings, fn($v) => strtolower($v['status']) === 'confirmed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Monthly Report — <?= e($monthLabel) ?> — iLet4You</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            background: #f4f6f9;
        }

        .page-wrap {
            max-width: 900px;
            margin: 30px auto;
            background: #fff;
            padding: 40px 48px;
            box-shadow: 0 2px 16px rgba(0,0,0,.12);
            border-radius: 4px;
        }

        .controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }
        .controls label { font-size: 9.5pt; font-weight: bold; color: #333; }
        .controls select {
            padding: 5px 10px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 9.5pt;
        }
        .btn {
            padding: 6px 18px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 9.5pt;
            font-weight: bold;
        }
        .btn-primary   { background: #1a3c5e; color: #fff; }
        .btn-secondary { background: #fff; color: #1a3c5e; border: 1px solid #1a3c5e; }
        .btn:hover     { opacity: .88; }

        .report-header {
            border-bottom: 3px solid #1a3c5e;
            padding-bottom: 14px;
            margin-bottom: 24px;
        }
        .report-header h1 {
            font-size: 20pt;
            color: #1a3c5e;
            letter-spacing: -.3px;
        }
        .report-header .meta {
            font-size: 8.5pt;
            color: #666;
            margin-top: 4px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }
        .card {
            background: #f2f6fb;
            border: 1px solid #d0dcea;
            border-radius: 4px;
            padding: 12px 14px;
            text-align: center;
        }
        .card .card-val {
            font-size: 14pt;
            font-weight: bold;
            color: #1a3c5e;
        }
        .card .card-val.green  { color: #1e7e34; }
        .card .card-val.red    { color: #c0392b; }
        .card .card-val.amber  { color: #856404; }
        .card .card-lbl {
            font-size: 7.5pt;
            color: #555;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1a3c5e;
            background: #dce8f5;
            padding: 6px 12px;
            border-left: 5px solid #1a3c5e;
            margin: 28px 0 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 4px;
        }
        thead th {
            background: #1a3c5e;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
            white-space: nowrap;
            font-size: 8pt;
        }
        tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #f7fafd; }

        .vacant   { color: #c0392b; font-style: italic; }
        .bold     { font-weight: bold; }
        .blue     { color: #1a3c5e; }
        .muted    { color: #888; }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
        }

        .warn-row td { background: #fff8e1 !important; }

        .empty { padding: 10px 8px; color: #888; font-style: italic; font-size: 8.5pt; }

        .report-footer {
            border-top: 1px solid #ddd;
            margin-top: 36px;
            padding-top: 10px;
            font-size: 7.5pt;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { background: #fff; font-size: 9pt; }

            .page-wrap {
                max-width: 100%;
                margin: 0;
                padding: 10mm 12mm;
                box-shadow: none;
                border-radius: 0;
            }

            .controls { display: none; }

            .summary-grid { grid-template-columns: repeat(5, 1fr); }

            table { font-size: 7.5pt; page-break-inside: auto; }
            thead { display: table-header-group; }
            tr    { page-break-inside: avoid; }

            .section-title { page-break-after: avoid; }

            .pb-before { page-break-before: always; }
        }

        @page {
            size: A4 portrait;
            margin: 15mm 12mm;
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <form method="get" class="controls no-print">
        <label for="month">Month</label>
        <select name="month" id="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                <?= date('F', mktime(0,0,0,$m,1)) ?>
            </option>
            <?php endfor; ?>
        </select>

        <label for="year">Year</label>
        <select name="year" id="year">
            <?php for ($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit" class="btn btn-primary">View Report</button>
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print / Save as PDF</button>
    </form>

    <div class="report-header">
        <h1>iLet4You — Monthly Report</h1>
        <div class="meta">
            Period: <strong><?= e($monthLabel) ?></strong>
            &nbsp;·&nbsp; Generated: <?= date('d/m/Y H:i') ?>
            &nbsp;·&nbsp; <?= $isAdmin ? 'Admin View' : 'Standard View' ?>
        </div>
    </div>

    <div class="summary-grid">
        <div class="card">
            <div class="card-val"><?= $totalProperties ?></div>
            <div class="card-lbl">Total Properties</div>
        </div>
        <div class="card">
            <div class="card-val"><?= $occupiedCount ?></div>
            <div class="card-lbl">Occupied</div>
        </div>
        <div class="card">
            <div class="card-val <?= $vacantCount > 0 ? 'red' : '' ?>"><?= $vacantCount ?></div>
            <div class="card-lbl">Vacant</div>
        </div>
        <div class="card">
            <div class="card-val green"><?= money($collectedRent) ?></div>
            <div class="card-lbl">Rent Collected</div>
        </div>
        <div class="card">
            <div class="card-val <?= $outstandingRent > 0 ? 'amber' : '' ?>"><?= money($outstandingRent) ?></div>
            <div class="card-lbl">Outstanding</div>
        </div>
    </div>

    <div class="summary-grid" style="grid-template-columns: repeat(3,1fr); margin-bottom: 0;">
        <div class="card">
            <div class="card-val blue"><?= money($expectedRent) ?></div>
            <div class="card-lbl">Expected Rent</div>
        </div>
        <div class="card">
            <div class="card-val"><?= $totalViewings ?></div>
            <div class="card-lbl">Viewings Scheduled</div>
        </div>
        <div class="card">
            <div class="card-val"><?= $confirmedViewings ?></div>
            <div class="card-lbl">Viewings Confirmed</div>
        </div>
    </div>

    <div class="section-title">Properties Active in <?= e($monthLabel) ?></div>
    <?php if (empty($properties)): ?>
        <p class="empty">No active properties found for this period.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Address</th>
                <th>Type</th>
                <th>Monthly Rent</th>
                <th>Landlord</th>
                <th>Tenant</th>
                <th>Contract Start</th>
                <th>Contract End</th>
                <th>Rooms</th>
                <th>EPC</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($properties as $r): ?>
            <tr>
                <td class="bold"><?= e($r['property_address']) ?></td>
                <td><?= e($r['property_type']) ?></td>
                <td class="bold blue"><?= money($r['monthly_rent']) ?></td>
                <td><?= e($r['landlord_name']) ?></td>
                <td <?= empty($r['tenant_name']) ? 'class="vacant"' : '' ?>>
                    <?= e($r['tenant_name'] ?? 'Vacant') ?>
                </td>
                <td><?= e($r['contract_start']) ?></td>
                <td><?= e($r['contract_end']) ?></td>
                <td><?= e($r['room_count']) ?></td>
                <td><?= e($r['epc_rating']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="section-title pb-before">Transactions — <?= e($monthLabel) ?></div>
    <?php if (empty($transactions)): ?>
        <p class="empty">No transactions recorded for this period.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tenant</th>
                <th>Property</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Period</th>
                <th>Next Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $r): ?>
            <tr>
                <td class="muted"><?= e($r['transaction_id']) ?></td>
                <td class="bold"><?= e($r['tenant_name']) ?></td>
                <td><?= e($r['property_address']) ?></td>
                <td class="bold blue"><?= money($r['transaction_amount']) ?></td>
                <td><?= e($r['date_of_transaction']) ?></td>
                <td><?= e($r['payment_period']) ?></td>
                <td><?= e($r['next_due']) ?></td>
                <td>
                    <span class="badge"
                          style="background:<?= statusBg($r['status'] ?? '') ?>;
                                 color:<?= statusColour($r['status'] ?? '') ?>">
                        <?= e(ucfirst($r['status'] ?? '')) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table style="margin-top:2px;">
        <tbody>
            <tr>
                <td style="text-align:right; padding:5px 8px; font-weight:bold; width:68%; border:none;">
                    Total Collected:
                </td>
                <td style="font-weight:bold; color:#1e7e34; border-bottom:1px solid #e0e0e0;">
                    <?= money($collectedRent) ?>
                </td>
            </tr>
            <tr>
                <td style="text-align:right; padding:5px 8px; font-weight:bold; border:none;">
                    Total Outstanding:
                </td>
                <td style="font-weight:bold; color:#856404; border-bottom:1px solid #e0e0e0;">
                    <?= money($outstandingRent) ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="section-title">Viewings — <?= e($monthLabel) ?></div>
    <?php if (empty($viewings)): ?>
        <p class="empty">No viewings recorded for this period.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Viewer</th>
                <th>Phone</th>
                <th>Property</th>
                <th>Date / Time</th>
                <th>Status</th>
                <th>Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($viewings as $r): ?>
            <tr>
                <td class="bold"><?= e($r['viewer_name']) ?></td>
                <td><?= e($r['viewer_phone']) ?></td>
                <td><?= e($r['property_address']) ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($r['date_time']))) ?></td>
                <td>
                    <span class="badge"
                          style="background:<?= statusBg($r['status'] ?? '') ?>;
                                 color:<?= statusColour($r['status'] ?? '') ?>">
                        <?= e(ucfirst($r['status'] ?? '')) ?>
                    </span>
                </td>
                <td><?= e($r['agent_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($certWarn)): ?>
    <div class="section-title" style="background:#fff3cd; border-color:#856404; color:#856404;">
        Compliance Alerts — Certs Expiring Within 60 Days
    </div>
    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Gas Cert Due</th>
                <th>Electric Cert Due</th>
                <th>EPC Due</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($certWarn as $r): ?>
            <tr class="warn-row">
                <td class="bold"><?= e($r['property_address']) ?></td>
                <td><?= e($r['gas_cert_due']      ?? '—') ?></td>
                <td><?= e($r['electric_cert_due'] ?? '—') ?></td>
                <td><?= e($r['epc_due_date']      ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="report-footer">
        <span>iLet4You Property Management</span>
        <span><?= e($monthLabel) ?> Monthly Report</span>
        <span>Generated <?= date('d/m/Y H:i') ?></span>
    </div>

</div>
</body>
</html>