<?php
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

$properties = $pdo->query("
    SELECT p.property_id, p.property_address, p.property_type, p.monthly_rent,
           l.name AS landlord_name, t.name AS tenant_name,
           c.contract_start, c.contract_end, c.frequency AS payment_frequency,
           p.gas_cert_due, p.electric_cert_due, p.epc_due_date,
           p.room_count, p.bathroom_count, p.epc_rating,
           CASE WHEN p.has_garden = 1 THEN 'Yes' ELSE 'No' END AS has_garden,
           CASE WHEN p.allowed_pets = 1 THEN 'Yes' ELSE 'No' END AS allowed_pets,
           p.council_band
    FROM properties p
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    LEFT JOIN contracts c ON p.property_id = c.property_id AND date('now') BETWEEN c.contract_start AND c.contract_end
    LEFT JOIN tenants t ON c.tenant_id = t.tenant_id
    WHERE p.archived = 0
    ORDER BY p.property_address
")->fetchAll(PDO::FETCH_ASSOC);

$landlords = $pdo->query("
    SELECT landlord_id, name, phone, email, address,
           id_type, id_number, id_expiry_date, number_of_properties
    FROM landlords
    WHERE archived = 0
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$tenants = $pdo->query("
    SELECT t.tenant_id, t.name, t.phone, t.email,
           t.id_type, t.id_number, t.id_expiry_date,
           c.contract_start, c.contract_end, c.frequency AS payment_frequency,
           p.property_address
    FROM tenants t
    LEFT JOIN contracts c ON t.tenant_id = c.tenant_id AND date('now') BETWEEN c.contract_start AND c.contract_end
    LEFT JOIN properties p ON c.property_id = p.property_id
    WHERE t.archived = 0
    ORDER BY t.name
")->fetchAll(PDO::FETCH_ASSOC);

$transactions = $pdo->query("
    SELECT tr.transaction_id, tr.transaction_amount, tr.date_of_transaction,
           tr.payment_period, tr.next_due, tr.status,
           t.name AS tenant_name,
           p.property_address
    FROM transactions tr
    LEFT JOIN tenants t ON tr.tenant_id = t.tenant_id
    LEFT JOIN properties p ON tr.property_id = p.property_id
    WHERE tr.archived = 0
    ORDER BY tr.date_of_transaction DESC
")->fetchAll(PDO::FETCH_ASSOC);

$viewings = $pdo->query("
    SELECT vb.booking_id, vb.viewer_name, vb.viewer_phone, vb.email,
           vb.date_time, vb.status,
           p.property_address,
           u.name AS agent_name
    FROM viewing_bookings vb
    LEFT JOIN properties p ON vb.property_id = p.property_id
    LEFT JOIN users u ON vb.user_id = u.user_id
    WHERE vb.archived = 0
    ORDER BY vb.date_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

$filename = 'iLet4You_Export_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

function csvRow(array $cells): string {
    return implode(',', array_map(function($cell) {
        $cell = str_replace(["\n", "\r"], ' ', $cell ?? '');
        return '"' . str_replace('"', '""', $cell) . '"';
    }, $cells)) . "\n";
}

// Header
echo csvRow(['iLet4You Export — ' . date('d/m/Y')]);
echo "\n";

// Properties
echo csvRow(['PROPERTIES']);
echo csvRow([
    'ID', 'Address', 'Type', 'Monthly Rent', 'Landlord', 'Tenant',
    'Contract Start', 'Contract End', 'Payment Frequency',
    'Gas Cert Due', 'Electric Cert Due', 'EPC Due',
    'Rooms', 'Bathrooms', 'EPC Rating', 'Garden', 'Pets', 'Council Band'
]);
foreach ($properties as $r) {
    echo csvRow([
        $r['property_id'], $r['property_address'], $r['property_type'],
        '£' . number_format($r['monthly_rent'], 2),
        $r['landlord_name'], $r['tenant_name'] ?? 'Vacant',
        $r['contract_start'] ?? '', $r['contract_end'] ?? '', $r['payment_frequency'] ?? '',
        $r['gas_cert_due'], $r['electric_cert_due'], $r['epc_due_date'],
        $r['room_count'], $r['bathroom_count'], $r['epc_rating'],
        $r['has_garden'], $r['allowed_pets'], $r['council_band']
    ]);
}

echo "\n";

// Landlords
echo csvRow(['LANDLORDS']);
echo csvRow([
    'ID', 'Name', 'Phone', 'Email', 'Address',
    'ID Type', 'ID Number', 'ID Expiry', 'Number of Properties'
]);
foreach ($landlords as $r) {
    echo csvRow([
        $r['landlord_id'], $r['name'], $r['phone'], $r['email'], $r['address'],
        $r['id_type'], $r['id_number'], $r['id_expiry_date'], $r['number_of_properties']
    ]);
}

echo "\n";

// Tenants
echo csvRow(['TENANTS']);
echo csvRow([
    'ID', 'Name', 'Phone', 'Email', 'Property',
    'ID Type', 'ID Number', 'ID Expiry',
    'Contract Start', 'Contract End', 'Payment Frequency'
]);
foreach ($tenants as $r) {
    echo csvRow([
        $r['tenant_id'], $r['name'], $r['phone'], $r['email'], $r['property_address'] ?? '',
        $r['id_type'], $r['id_number'], $r['id_expiry_date'],
        $r['contract_start'] ?? '', $r['contract_end'] ?? '', $r['payment_frequency'] ?? ''
    ]);
}

echo "\n";

// Transactions
echo csvRow(['TRANSACTIONS']);
echo csvRow([
    'ID', 'Tenant', 'Property', 'Amount', 'Date',
    'Period Covered', 'Next Due', 'Status'
]);
foreach ($transactions as $r) {
    echo csvRow([
        $r['transaction_id'], $r['tenant_name'], $r['property_address'],
        '£' . number_format($r['transaction_amount'], 2),
        $r['date_of_transaction'], $r['payment_period'],
        $r['next_due'], $r['status']
    ]);
}

echo "\n";

// Viewings
echo csvRow(['VIEWINGS']);
echo csvRow([
    'ID', 'Viewer Name', 'Phone', 'Email',
    'Property', 'Date/Time', 'Status', 'Agent'
]);
foreach ($viewings as $r) {
    echo csvRow([
        $r['booking_id'], $r['viewer_name'], $r['viewer_phone'], $r['email'],
        $r['property_address'], $r['date_time'], $r['status'], $r['agent_name'] ?? ''
    ]);
}