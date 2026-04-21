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
    ];

    if (isset($tableMap[$type])) {
        $t   = $tableMap[$type]['table'];
        $pk  = $tableMap[$type]['pk'];
        $stmt = $pdo->prepare("UPDATE $t SET archived = 0, date_archived = NULL WHERE $pk = ?");
        $stmt->execute([$id]);
    }
    header("Location: archive.php");
    exit();
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
    ];

    if (isset($tableMap[$type])) {
        $t  = $tableMap[$type]['table'];
        $pk = $tableMap[$type]['pk'];
        $stmt = $pdo->prepare("DELETE FROM $t WHERE $pk = ?");
        $stmt->execute([$id]);
    }
    header("Location: archive.php");
    exit();
}

// Fetch archived records from each table

$archived = [];

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

// Contracts — skipped: table does not have archived/date_archived columns yet.
// To enable: ALTER TABLE contracts ADD COLUMN archived INTEGER NOT NULL DEFAULT 0;
//            ALTER TABLE contracts ADD COLUMN date_archived TEXT;

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

// Sort by date_archived DESC (most recent first)
usort($archived, function($a, $b) {
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

// Type display config
$typeConfig = [
    'properties'       => ['label' => 'Property',        'icon' => 'fa-house',         'class' => 'type-property'],
    'landlords'        => ['label' => 'Landlord',        'icon' => 'fa-user-tie',      'class' => 'type-landlord'],
    'tenants'          => ['label' => 'Tenant',          'icon' => 'fa-user',          'class' => 'type-tenant'],
    'contracts'        => ['label' => 'Contract',        'icon' => 'fa-file-contract', 'class' => 'type-contract'],
    'transactions'     => ['label' => 'Transaction',     'icon' => 'fa-credit-card',   'class' => 'type-transaction'],
    'viewing_bookings' => ['label' => 'Viewing',         'icon' => 'fa-street-view',   'class' => 'type-viewing'],
    'users'            => ['label' => 'User',            'icon' => 'fa-users',         'class' => 'type-user'],
    'notes'            => ['label' => 'Note',            'icon' => 'fa-sticky-note',   'class' => 'type-note'],
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
        /* ── Type badge colours ─────────────────────────────── */
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
        .type-property    { background: #e8f4fd; color: #1a73e8; }
        .type-landlord    { background: #fdf3e8; color: #e67e22; }
        .type-tenant      { background: #e8fdf3; color: #27ae60; }
        .type-contract    { background: #f3e8fd; color: #8e44ad; }
        .type-transaction { background: #e8fdf8; color: #16a085; }
        .type-viewing     { background: #fde8e8; color: #c0392b; }
        .type-user        { background: #fdf8e8; color: #d4a017; }
        .type-note        { background: #f0f0f0; color: #555; }

        /* ── Archive-specific empty state ───────────────────── */
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

        /* ── Label column ───────────────────────────────────── */
        .col-label   { width: 24%; }
        .col-detail  { width: 18%; }
        .col-related { width: 18%; }
        .col-type    { width: 12%; }
        .col-date    { width: 12%; }
        .col-action  { width: 16%; text-align: center; }

        .label-cell { font-weight: 500; }
        .sub-cell   { color: #777; font-size: 0.88rem; }
    </style>
</head>
<body onload="CallNavbar();">
    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Archive</h1>

        <div class="main-container">
            <!-- Controls row -->
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
                    <button class="filter-btn-reset" onclick="resetFilters()" title="Reset filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <span id="recordCount" class="record-count" style="color:#888;font-size:.9rem;align-self:center;"></span>
            </div>

            <!-- Table -->
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
                                <th class="sortable col-label"  onclick="sortTable('label', this)">
                                    Name / Label <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-detail">Detail</th>
                                <th class="col-related">Related Info</th>
                                <th class="sortable col-type"   onclick="sortTable('type', this)">
                                    Type <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-date"   onclick="sortTable('date', this)">
                                    Archived On <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="archiveBody">
                        <?php foreach ($archived as $item):
                            $cfg   = $typeConfig[$item['type']] ?? ['label' => $item['type'], 'icon' => 'fa-box', 'class' => ''];
                            $dateF = $item['date'] ? date('d/m/Y', strtotime($item['date'])) : '-';
                        ?>
                        <tr data-type="<?= htmlspecialchars($item['type']) ?>"
                            data-label="<?= htmlspecialchars(strtolower($item['label'] ?? '')) ?>"
                            data-date="<?= $item['date'] ?? '' ?>">

                            <td class="label-cell"><?= htmlspecialchars($item['label'] ?? '-') ?></td>
                            <td class="sub-cell"><?= htmlspecialchars($item['sub'] ?? '-') ?></td>
                            <td class="sub-cell"><?= htmlspecialchars($item['related'] ?? '-') ?></td>
                            <td>
                                <span class="type-badge <?= $cfg['class'] ?>">
                                    <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                                    <?= $cfg['label'] ?>
                                </span>
                            </td>
                            <td><?= $dateF ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="unarchive_id"   value="<?= $item['id'] ?>">
                                        <input type="hidden" name="unarchive_type" value="<?= $item['type'] ?>">
                                        <button type="submit" class="action-btn action-btn-edit" title="Unarchive">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_id"   value="<?= $item['id'] ?>">
                                        <input type="hidden" name="delete_type" value="<?= $item['type'] ?>">
                                        <button type="submit" class="action-btn action-btn-delete" title="Delete permanently"
                                            onclick="return confirm('Permanently delete this <?= $cfg['label'] ?>?\nThis cannot be undone.')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
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
    let currentSort = { column: null, direction: 'asc' };

    // Update visible row count
    function updateCount() {
        const visible = document.querySelectorAll('#archiveBody tr:not([style*="display: none"])').length;
        const total   = document.querySelectorAll('#archiveBody tr').length;
        const el = document.getElementById('recordCount');
        if (el) el.textContent = visible === total ? `${total} record${total !== 1 ? 's' : ''}` : `${visible} of ${total} records`;
    }

    // Run on load
    document.addEventListener('DOMContentLoaded', updateCount);

    // Filter toggle
    function toggleFilter() {
        const dd = document.getElementById('filterDropdowns');
        dd.style.display = dd.style.display === 'none' ? 'flex' : 'none';
    }

    // Apply all filters
    function applyFilters() {
        const typeVal   = document.getElementById('filterType').value;
        const searchVal = document.getElementById('filterSearch').value.toLowerCase();
        const fromVal   = document.getElementById('filterFrom').value;
        const toVal     = document.getElementById('filterTo').value;

        document.querySelectorAll('#archiveBody tr').forEach(row => {
            let show = true;

            if (typeVal   && row.dataset.type  !== typeVal)                          show = false;
            if (searchVal && !row.dataset.label.includes(searchVal))                 show = false;
            if (fromVal   && row.dataset.date  && row.dataset.date < fromVal)        show = false;
            if (toVal     && row.dataset.date  && row.dataset.date > toVal)          show = false;

            row.style.display = show ? '' : 'none';
        });

        updateCount();
    }

    // Reset all filters
    function resetFilters() {
        document.getElementById('filterType').value   = '';
        document.getElementById('filterSearch').value = '';
        document.getElementById('filterFrom').value   = '';
        document.getElementById('filterTo').value     = '';
        document.querySelectorAll('#archiveBody tr').forEach(r => r.style.display = '');
        document.querySelectorAll('#archiveTable th').forEach(th => {
            th.classList.remove('sorted');
            const ic = th.querySelector('.sort-icon');
            if (ic) ic.classList.remove('asc', 'desc');
        });
        currentSort = { column: null, direction: 'asc' };
        updateCount();
    }

    // Sort
    function sortTable(column, headerEl) {
        const tbody = document.getElementById('archiveBody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));

        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column    = column;
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
                default: return 0;
            }
            if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return currentSort.direction === 'asc' ?  1 : -1;
            return 0;
        });

        rows.forEach(r => tbody.appendChild(r));
    }
    </script>
</body>
</html>