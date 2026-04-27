<?php
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// Handle unarchive request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_id'], $_POST['unarchive_type'])) {
    $type = $_POST['unarchive_type'];
    $id   = $_POST['unarchive_id'];

    $tableMap = [
        'properties'      => ['table' => 'properties',      'pk' => 'property_id'],
        'landlords'       => ['table' => 'landlords',       'pk' => 'landlord_id'],
        'tenants'         => ['table' => 'tenants',         'pk' => 'tenant_id'],
        'contracts'       => ['table' => 'contracts',       'pk' => 'contract_id'],
        'transactions'    => ['table' => 'transactions',    'pk' => 'transaction_id'],
        'viewing_bookings'=> ['table' => 'viewing_bookings','pk' => 'booking_id'],
        'users'           => ['table' => 'users',           'pk' => 'user_id'],
        'notes'           => ['table' => 'notes',           'pk' => 'note_id'],
        'files'           => ['table' => 'files',           'pk' => 'file_id'],
    ];

    if (isset($tableMap[$type])) {
        $t   = $tableMap[$type]['table'];
        $pk  = $tableMap[$type]['pk'];
        $stmt = $pdo->prepare("UPDATE $t SET archived = 0, date_archived = NULL WHERE $pk = ?");
        $stmt->execute([$id]);
    }
}
// Handle file restore request (AJAX — checks for path conflict first)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_file_id'])) {
    header('Content-Type: application/json');
    $fileId = (int)$_POST['restore_file_id'];

    $stmt = $pdo->prepare("SELECT file_path FROM files WHERE file_id = ?");
    $stmt->execute([$fileId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'File not found']);
        exit;
    }

    $filePath = $row['file_path'];

    // Check if another active (non-archived) file already has the same path
    $conflict = $pdo->prepare("SELECT file_id FROM files WHERE file_path = ? AND archived = 0 AND file_id != ?");
    $conflict->execute([$filePath, $fileId]);
    $existing = $conflict->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'success'  => false,
            'conflict' => true,
            'message'  => 'Cannot restore this file because another file already exists at the same location. Please delete the existing file first, then try again.',
        ]);
        exit;
    }

    $pdo->prepare("UPDATE files SET archived = 0, date_archived = NULL WHERE file_id = ?")
        ->execute([$fileId]);

    echo json_encode(['success' => true]);
    exit;
}

// Handle permanent delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'], $_POST['delete_type'])) {
    $type = $_POST['delete_type'];
    $id   = $_POST['delete_id'];

    $tableMap = [
        'properties'      => ['table' => 'properties',      'pk' => 'property_id'],
        'landlords'       => ['table' => 'landlords',       'pk' => 'landlord_id'],
        'tenants'         => ['table' => 'tenants',         'pk' => 'tenant_id'],
        'contracts'       => ['table' => 'contracts',       'pk' => 'contract_id'],
        'transactions'    => ['table' => 'transactions',    'pk' => 'transaction_id'],
        'viewing_bookings'=> ['table' => 'viewing_bookings','pk' => 'booking_id'],
        'users'           => ['table' => 'users',           'pk' => 'user_id'],
        'notes'           => ['table' => 'notes',           'pk' => 'note_id'],
        'files'           => ['table' => 'files',           'pk' => 'file_id'],
    ];

    if (isset($tableMap[$type])) {
        $t  = $tableMap[$type]['table'];
        $pk = $tableMap[$type]['pk'];

        // For files: physically delete the file from disk before removing DB record
        if ($type === 'files') {
            $fStmt = $pdo->prepare("SELECT file_path FROM files WHERE file_id = ?");
            $fStmt->execute([$id]);
            $fRow = $fStmt->fetch(PDO::FETCH_ASSOC);
            if ($fRow) {
                $fullPath = __DIR__ . '/' . $fRow['file_path'];
                if (file_exists($fullPath)) unlink($fullPath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM $t WHERE $pk = ?");
        $stmt->execute([$id]);
    }
    header("Location: archive.php");
    exit();
}

// Fetch archived records from each table
$archived = [];
$fiveYearsAgo = date('Y-m-d', strtotime('-5 years'));

// Properties
$rows = $pdo->query("
    SELECT p.property_id AS id,
           p.property_address AS label,
           p.property_type AS sub,
           p.date_archived,
           l.name AS related
    FROM properties p
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    WHERE p.archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'properties', 'id' => $r['id'], 'label' => $r['label'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Landlords
$rows = $pdo->query("
    SELECT landlord_id AS id, name AS label, email AS sub, date_archived, phone AS related
    FROM landlords WHERE archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'landlords', 'id' => $r['id'], 'label' => $r['label'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Tenants
$rows = $pdo->query("
    SELECT tenant_id AS id, name AS label, email AS sub, date_archived, phone AS related
    FROM tenants WHERE archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'tenants', 'id' => $r['id'], 'label' => $r['label'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Transactions
$rows = $pdo->query("
    SELECT transactions.transaction_id AS id,
           properties.property_address AS label,
           transactions.status AS sub,
           transactions.date_archived,
           ('£' || transactions.transaction_amount) AS related
    FROM transactions
    LEFT JOIN properties ON transactions.property_id = properties.property_id
    WHERE transactions.archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'transactions', 'id' => $r['id'], 'label' => $r['label'] ?? 'Transaction #'.$r['id'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Viewing Bookings
$rows = $pdo->query("
    SELECT viewing_bookings.booking_id AS id,
           properties.property_address AS label,
           viewing_bookings.date_time AS sub,
           viewing_bookings.date_archived,
           viewing_bookings.viewer_name AS related
    FROM viewing_bookings
    LEFT JOIN properties ON viewing_bookings.property_id = properties.property_id
    WHERE viewing_bookings.archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'viewing_bookings', 'id' => $r['id'], 'label' => $r['label'] ?? 'Viewing #'.$r['id'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Users
$rows = $pdo->query("
    SELECT user_id AS id, username AS label, email AS sub, date_archived, role AS related
    FROM users WHERE archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'users', 'id' => $r['id'], 'label' => $r['label'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Notes
$rows = $pdo->query("
    SELECT note_id AS id, note_text AS label, type AS sub, date_archived, created_at AS related
    FROM notes WHERE archived = 1
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $archived[] = ['type' => 'notes', 'id' => $r['id'], 'label' => $r['label'],
                   'sub' => $r['sub'], 'related' => $r['related'], 'date' => $r['date_archived']];
}

// Files
try {
    $rows = $pdo->query("
        SELECT f.file_id AS id,
               f.file_name AS label,
               f.file_category AS file_category,
               f.file_type AS file_type,
               f.file_path AS file_path,
               f.date_archived,
               p.property_address AS owner_property,
               t.name             AS owner_tenant,
               l.name             AS owner_landlord,
               u.username         AS owner_user,
               f.payment_id       AS owner_payment_id
        FROM files f
        LEFT JOIN properties p ON f.property_id = p.property_id
        LEFT JOIN tenants     t ON f.tenant_id   = t.tenant_id
        LEFT JOIN landlords   l ON f.landlord_id = l.landlord_id
        LEFT JOIN users       u ON f.user_id     = u.user_id
        WHERE f.archived = 1
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        // Build "linked to" label
        if ($r['owner_property'])      { $ownerType = 'Property'; $ownerName = $r['owner_property']; }
        elseif ($r['owner_tenant'])    { $ownerType = 'Tenant';   $ownerName = $r['owner_tenant'];   }
        elseif ($r['owner_landlord'])  { $ownerType = 'Landlord'; $ownerName = $r['owner_landlord']; }
        elseif ($r['owner_user'])      { $ownerType = 'User';     $ownerName = $r['owner_user'];     }
        elseif ($r['owner_payment_id']){ $ownerType = 'Payment';  $ownerName = 'Payment #' . $r['owner_payment_id']; }
        else                           { $ownerType = '';         $ownerName = 'Unknown'; }

        $catLabel = ucwords(str_replace('_', ' ', $r['file_category']));

        $archived[] = [
            'type'       => 'files',
            'id'         => $r['id'],
            'label'      => $r['label'],
            'sub'        => $catLabel . ($ownerType ? ' — ' . $ownerType . ': ' . $ownerName : ''),
            'related'    => $ownerName,
            'date'       => $r['date_archived'],
            'file_type'  => $r['file_type'],
            'file_path'  => $r['file_path'],
        ];
    }
} catch (Exception $e) {}

// Sort by date_archived DESC (most recent first)
usort($archived, function($a, $b) {
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

// Count overdue records
$overdueCount = 0;
foreach ($archived as $item) {
    if ($item['date'] && $item['date'] < $fiveYearsAgo) $overdueCount++;
}

// Type display config
$typeConfig = [
    'properties'       => ['label' => 'Property',    'icon' => 'fa-house',         'class' => 'type-property'],
    'landlords'        => ['label' => 'Landlord',    'icon' => 'fa-user-tie',      'class' => 'type-landlord'],
    'tenants'          => ['label' => 'Tenant',      'icon' => 'fa-user',          'class' => 'type-tenant'],
    'contracts'        => ['label' => 'Contract',    'icon' => 'fa-file-contract', 'class' => 'type-contract'],
    'transactions'     => ['label' => 'Transaction', 'icon' => 'fa-credit-card',   'class' => 'type-transaction'],
    'viewing_bookings' => ['label' => 'Viewing',     'icon' => 'fa-street-view',   'class' => 'type-viewing'],
    'users'            => ['label' => 'User',        'icon' => 'fa-users',         'class' => 'type-user'],
    'notes'            => ['label' => 'Note',        'icon' => 'fa-sticky-note',   'class' => 'type-note'],
    'files'            => ['label' => 'File',        'icon' => 'fa-file',          'class' => 'type-file'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <style>
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .type-property {
        background: #e8f4fd;
        color: #1a73e8;
    }

    .type-landlord {
        background: #fdf3e8;
        color: #e67e22;
    }

    .type-tenant {
        background: #e8fdf3;
        color: #27ae60;
    }

    .type-contract {
        background: #f3e8fd;
        color: #8e44ad;
    }

    .type-transaction {
        background: #e8fdf8;
        color: #16a085;
    }

    .type-viewing {
        background: #fde8e8;
        color: #c0392b;
    }

    .type-user {
        background: #fdf8e8;
        color: #d4a017;
    }

    .type-note {
        background: #f0f0f0;
        color: #555;
    }

    .type-file {
        background: #edf2ff;
        color: #3b5bdb;
    }

    .file-detail-cat {
        font-weight: 600;
        color: #444;
        font-size: 0.85rem;
    }

    .file-detail-owner {
        color: #777;
        font-size: 0.8rem;
        margin-top: 2px;
    }

    .archive-empty {
        text-align: center;
        padding: 60px 20px;
        color: #aaa;
    }

    .archive-empty i {
        font-size: 3rem;
        margin-bottom: 12px;
        display: block;
    }

    .col-label {
        width: 22%;
    }

    .col-detail {
        width: 16%;
    }

    .col-related {
        width: 16%;
    }

    .col-type {
        width: 12%;
    }

    .col-date {
        width: 14%;
    }

    .col-action {
        width: 20%;
        text-align: center;
    }

    .label-cell {
        font-weight: 500;
    }

    .sub-cell {
        color: #777;
        font-size: 0.88rem;
    }

    /* Overdue row highlight */
    tr.overdue-row {
        background-color: #fff5f5;
    }

    tr.overdue-row td {
        border-left: 3px solid #e74c3c;
    }

    tr.overdue-row td:not(:first-child) {
        border-left: none;
    }

    .overdue-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #fdecea;
        color: #c0392b;
        border: 1px solid #f5c6cb;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 6px;
        white-space: nowrap;
    }

    /* Overdue banner */
    .overdue-banner {
        background: #fdecea;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
    }

    .overdue-banner i {
        font-size: 1.1rem;
    }
    </style>
</head>

<body onload="CallNavbar();">
    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Archive</h1>

        <?php if ($overdueCount > 0): ?>
        <div class="overdue-banner">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>
                <strong><?= $overdueCount ?> record<?= $overdueCount !== 1 ? 's' : '' ?></strong>
                <?= $overdueCount !== 1 ? 'have' : 'has' ?> been archived for over 5 years and should be reviewed for
                permanent deletion.
            </span>
        </div>
        <?php endif; ?>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display:none;">
                    <div class="filter-group">
                        <label>Type</label>
                        <select id="filterType" onchange="applyFilters()">
                            <option value="">All Types</option>
                            <option value="properties">Property</option>
                            <option value="landlords">Landlord</option>
                            <option value="tenants">Tenant</option>
                            <option value="contracts">Contract</option>
                            <option value="transactions">Transaction</option>
                            <option value="viewing_bookings">Viewing</option>
                            <option value="users">User</option>
                            <option value="notes">Note</option>
                            <option value="files">File</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" id="filterSearch" placeholder="Search label..." oninput="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Archived From</label>
                        <input type="date" id="filterFrom" onchange="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Archived To</label>
                        <input type="date" id="filterTo" onchange="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Show</label>
                        <select id="filterOverdue" onchange="applyFilters()">
                            <option value="">All Records</option>
                            <option value="overdue">Overdue Only (5+ years)</option>
                        </select>
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <span id="recordCount" class="record-count"
                    style="color:#888;font-size:.9rem;align-self:center;"></span>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <?php if (empty($archived)): ?>
                    <div class="archive-empty">
                        <i class="fa-solid fa-box-archive"></i>
                        <p>No archived records found.</p>
                    </div>
                    <?php else: ?>
                    <table id="archiveTable">
                        <thead>
                            <tr>
                                <th class="sortable col-label" onclick="sortTable('label', this)">
                                    Name / Label <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-detail">Detail</th>
                                <th class="col-related">Related Info</th>
                                <th class="sortable col-type" onclick="sortTable('type', this)">
                                    Type <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-date" onclick="sortTable('date', this)">
                                    Archived On <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archiveBody">
                            <?php foreach ($archived as $item):
                                $cfg     = $typeConfig[$item['type']] ?? ['label' => $item['type'], 'icon' => 'fa-box', 'class' => ''];
                                $dateF   = $item['date'] ? date('d/m/Y', strtotime($item['date'])) : '-';
                                $overdue = $item['date'] && $item['date'] < $fiveYearsAgo;
                            ?>
                            <tr data-type="<?= htmlspecialchars($item['type']) ?>"
                                data-label="<?= htmlspecialchars(strtolower($item['label'] ?? '')) ?>"
                                data-date="<?= $item['date'] ?? '' ?>" data-overdue="<?= $overdue ? '1' : '0' ?>"
                                <?= $overdue ? 'class="overdue-row"' : '' ?>>

                                <td class="label-cell"><?= htmlspecialchars($item['label'] ?? '-') ?></td>
                                <td class="sub-cell">
                                    <?php if ($item['type'] === 'files' && strpos($item['sub'], ' — ') !== false):
                                        [$catPart, $ownerPart] = explode(' — ', $item['sub'], 2);
                                    ?>
                                    <div class="file-detail-cat"><?= htmlspecialchars($catPart) ?></div>
                                    <div class="file-detail-owner"><?= htmlspecialchars($ownerPart) ?></div>
                                    <?php else: ?>
                                    <?= htmlspecialchars($item['sub'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="sub-cell"><?= htmlspecialchars($item['related'] ?? '-') ?></td>
                                <td>
                                    <span class="type-badge <?= $cfg['class'] ?>">
                                        <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                                        <?= $cfg['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $dateF ?>
                                    <?php if ($overdue): ?>
                                    <span class="overdue-badge">
                                        <i class="fa-solid fa-triangle-exclamation"></i> 5+ yrs
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($item['type'] === 'files'): ?>
                                        <button type="button" class="action-btn action-btn-view" title="View file"
                                            onclick="window.open('<?= htmlspecialchars($item['file_path'] ?? '#') ?>', '_blank')">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <button type="button" class="action-btn action-btn-edit" title="Restore file"
                                            onclick="restoreFile(<?= $item['id'] ?>, this)">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="delete_type" value="files">
                                            <button type="submit" class="action-btn action-btn-delete"
                                                title="Delete permanently"
                                                onclick="return confirm('Permanently delete this file?\nThis cannot be undone.')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="unarchive_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="unarchive_type" value="<?= $item['type'] ?>">
                                            <button type="submit" class="action-btn action-btn-edit" title="Unarchive">
                                                <i class="fa-solid fa-box-archive"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="delete_type" value="<?= $item['type'] ?>">
                                            <button type="submit" class="action-btn action-btn-delete"
                                                title="<?= $overdue ? 'Overdue for deletion — permanently delete' : 'Delete permanently' ?>"
                                                onclick="return confirm('Permanently delete this <?= $cfg['label'] ?>?\nThis cannot be undone.')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    let currentSort = {
        column: null,
        direction: 'asc'
    };

    function updateCount() {
        const visible = document.querySelectorAll('#archiveBody tr:not([style*="display: none"])').length;
        const total = document.querySelectorAll('#archiveBody tr').length;
        const el = document.getElementById('recordCount');
        if (el) el.textContent = visible === total ?
            `${total} record${total !== 1 ? 's' : ''}` :
            `${visible} of ${total} records`;
    }

    document.addEventListener('DOMContentLoaded', updateCount);

    function toggleFilter() {
        const dd = document.getElementById('filterDropdowns');
        dd.style.display = dd.style.display === 'none' ? 'flex' : 'none';
    }

    function applyFilters() {
        const typeVal = document.getElementById('filterType').value;
        const searchVal = document.getElementById('filterSearch').value.toLowerCase();
        const fromVal = document.getElementById('filterFrom').value;
        const toVal = document.getElementById('filterTo').value;
        const overdueVal = document.getElementById('filterOverdue').value;

        document.querySelectorAll('#archiveBody tr').forEach(row => {
            let show = true;
            if (typeVal && row.dataset.type !== typeVal) show = false;
            if (searchVal && !row.dataset.label.includes(searchVal)) show = false;
            if (fromVal && row.dataset.date && row.dataset.date < fromVal) show = false;
            if (toVal && row.dataset.date && row.dataset.date > toVal) show = false;
            if (overdueVal === 'overdue' && row.dataset.overdue !== '1') show = false;
            row.style.display = show ? '' : 'none';
        });

        updateCount();
    }

    function resetFilters() {
        document.getElementById('filterType').value = '';
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterFrom').value = '';
        document.getElementById('filterTo').value = '';
        document.getElementById('filterOverdue').value = '';
        document.querySelectorAll('#archiveBody tr').forEach(r => r.style.display = '');
        document.querySelectorAll('#archiveTable th').forEach(th => {
            th.classList.remove('sorted');
            const ic = th.querySelector('.sort-icon');
            if (ic) ic.classList.remove('asc', 'desc');
        });
        currentSort = {
            column: null,
            direction: 'asc'
        };
        updateCount();
    }

    function sortTable(column, headerEl) {
        const tbody = document.getElementById('archiveBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));

        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }

        document.querySelectorAll('#archiveTable th').forEach(th => {
            th.classList.remove('sorted');
            const ic = th.querySelector('.sort-icon');
            if (ic) ic.classList.remove('asc', 'desc');
        });
        headerEl.classList.add('sorted');
        const icon = headerEl.querySelector('.sort-icon');
        if (icon) icon.classList.add(currentSort.direction);

        rows.sort((a, b) => {
            let aVal, bVal;
            switch (column) {
                case 'label':
                    aVal = a.dataset.label;
                    bVal = b.dataset.label;
                    break;
                case 'type':
                    aVal = a.dataset.type;
                    bVal = b.dataset.type;
                    break;
                case 'date':
                    aVal = a.dataset.date || '';
                    bVal = b.dataset.date || '';
                    break;
                default:
                    return 0;
            }
            if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
            return 0;
        });

        rows.forEach(r => tbody.appendChild(r));
    }

    function restoreFile(fileId, btn) {
        fetch('archive.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'restore_file_id=' + fileId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = btn.closest('tr');
                    if (row) row.remove();
                    updateCount();
                } else if (data.conflict) {
                    alert(data.message);
                } else {
                    alert('Could not restore file: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(() => alert('Network error. Please try again.'));
    }
    </script>
</body>

</html>