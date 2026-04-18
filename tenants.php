<?php
session_start();
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    $stmt = $pdo->prepare("UPDATE tenants SET archived = 1, date_archived = date('now') WHERE tenant_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: tenants.php");
    exit();
}

$tenants = $pdo->query("
    SELECT
        t.*,
        c.contract_start,
        c.contract_end,
        c.rent,
        c.frequency,
        p.property_address,
        l.name AS landlord_name
    FROM tenants t
    LEFT JOIN contracts c ON c.tenant_id = t.tenant_id AND c.contract_id = (
        SELECT contract_id FROM contracts
        WHERE tenant_id = t.tenant_id
        ORDER BY contract_end DESC LIMIT 1
    )
    LEFT JOIN properties p ON c.property_id = p.property_id
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    WHERE t.archived = 0
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <script src="modals.js"></script>
    <script src="actionbtn.js"></script>
    <style>
        .col-name     { width: 16%; }
        .col-number   { width: 12%; }
        .col-email    { width: 22%; }
        .col-address  { width: 20%; }
        .col-status   { width: 10%; }
        .col-contract { width: 15%; }
        .col-action   { width: 10%; text-align: center; }
    </style>
</head>
<body onload="CallNavbar(); loadModals();">

    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Tenants</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus" onchange="filterTenants()">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="former">Former</option>
                            <option value="new">New</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Start</label>
                        <input type="date" id="filterStart" onchange="filterTenants()">
                    </div>
                    <div class="filter-group">
                        <label>End</label>
                        <input type="date" id="filterEnd" onchange="filterTenants()">
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <button class="btn-add blue" onclick="openAddTenantModal()">
                    <i class="fa-solid fa-plus"></i> Tenant
                </button>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <table id="tenantsTable">
                        <thead>
                            <tr>
                                <th class="sortable col-name"     onclick="sortTable('name', this)">Name <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-number"   onclick="sortTable('number', this)">Number <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-email"    onclick="sortTable('email', this)">Email <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-address"  onclick="sortTable('address', this)">Address <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-status"   onclick="sortTable('status', this)">Status <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-contract" onclick="sortTable('contract', this)">Contract <i class="fa-solid fa-sort"></i></th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($tenants as $t): ?>
                            <tr
                                data-tenant-id="<?= $t['tenant_id'] ?>"
                                data-name="<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>"
                                data-phone="<?= htmlspecialchars($t['phone'], ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($t['email'], ENT_QUOTES) ?>"
                                data-address="<?= htmlspecialchars($t['property_address'] ?? 'Unassigned', ENT_QUOTES) ?>"
                                data-idnumber="<?= htmlspecialchars($t['id_number'] ?? '', ENT_QUOTES) ?>"
                                data-idexpiry="<?= htmlspecialchars($t['id_expiry_date'] ?? '', ENT_QUOTES) ?>"
                                data-contract-start="<?= htmlspecialchars($t['contract_start'] ?? '', ENT_QUOTES) ?>"
                                data-contract-end="<?= htmlspecialchars($t['contract_end'] ?? '', ENT_QUOTES) ?>"
                                data-frequency="<?= htmlspecialchars($t['frequency'] ?? '', ENT_QUOTES) ?>"
                                data-landlord="<?= htmlspecialchars($t['landlord_name'] ?? 'N/A', ENT_QUOTES) ?>"
                                data-rent="<?= htmlspecialchars($t['rent'] ?? '0', ENT_QUOTES) ?>"
                                data-status="">
                                <td class="tenant-name"><?= htmlspecialchars($t['name']) ?></td>
                                <td class="tenant-phone"><?= htmlspecialchars($t['phone']) ?></td>
                                <td class="td-email"><?= htmlspecialchars($t['email']) ?></td>
                                <td class="tenant-address"><?= htmlspecialchars($t['property_address'] ?? 'Unassigned') ?></td>
                                <td><span class="status-badge"></span></td>
                                <td class="contract-info">
                                    <?php if (!empty($t['contract_start']) && !empty($t['contract_end'])): ?>
                                        <?= date('d/m/Y', strtotime($t['contract_start'])) ?> to <?= date('d/m/Y', strtotime($t['contract_end'])) ?>
                                    <?php else: ?>
                                        No Contract
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" onclick="actionbtn.viewTenant(this)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'read_only'): ?>
                                            <button class="action-btn action-btn-edit" onclick="editTenant(this)">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Archive <?= htmlspecialchars(addslashes($t['name'])) ?>?')">
                                                <input type="hidden" name="archive_id" value="<?= $t['tenant_id'] ?>">
                                                <button type="submit" class="action-btn action-btn-delete">
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
                </div>
            </div>
        </div>
    </main>

    <div id="modals-container"></div>

    <!-- View Tenant Modal -->
    <div id="viewTenantModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h2 id="tenantName">Tenant Name</h2>
                </div>
                <button class="action-modal-close" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="tenant">
                    <i class="fa-solid fa-user"></i> Tenant Information
                </button>
                <button class="tab-btn" data-tab="tenancyHistory">
                    <i class="fa-solid fa-address-book"></i> Tenancy History
                </button>
                <button class="tab-btn" data-tab="currentTenancy">
                    <i class="fa-solid fa-house"></i> Current Tenancy
                </button>
                <button class="tab-btn" data-tab="files">
                    <i class="fa-solid fa-folder"></i> Files
                </button>
                <button class="tab-btn" data-tab="notes">
                    <i class="fa-solid fa-sticky-note"></i> Notes
                </button>
            </div>

            <div class="action-tabs-content">

                <!-- Tenant Info Tab -->
                <div id="t-tab-tenant" class="tab-content active">
                    <div class="tab-header">
                        <i class="fa-solid fa-user"></i>
                        <span>Tenant Information</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span id="t-tenant-name" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone:</span>
                            <span id="t-tenant-phone" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span id="t-tenant-email" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID Number:</span>
                            <span id="t-tenant-idnumber" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID Expiry:</span>
                            <span id="t-tenant-idexpiry" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Address:</span>
                            <span id="t-tenant-address" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <!-- Tenancy History Tab -->
                <div id="t-tab-tenancyHistory" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-address-book"></i>
                        <span>Tenancy History</span>
                    </div>
                    <div class="contant-row-t">
                        <div class="data-row header">
                            <div class="cell">Address</div>
                            <div class="cell">Landlord</div>
                            <div class="cell">Move in</div>
                            <div class="cell">Move out</div>
                            <div class="cell">Status</div>
                        </div>
                        <div class="data-row">
                            <div class="cell">221B Baker Street</div>
                            <div class="cell">Alex</div>
                            <div class="cell">01/01/2023</div>
                            <div class="cell">01/01/2024</div>
                            <div class="cell"><span class="status-badge status-previous">Previous</span></div>
                        </div>
                        <div class="data-row">
                            <div class="cell">Green Lane 5</div>
                            <div class="cell">Alex</div>
                            <div class="cell">01/01/2025</div>
                            <div class="cell">-</div>
                            <div class="cell"><span class="status-badge status-active">Current</span></div>
                        </div>
                    </div>
                </div>

                <!-- Current Tenancy Tab -->
                <div id="t-tab-currentTenancy" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-house"></i>
                        <span>Current Tenancy</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Address:</span>
                            <span id="t-currentTenancy-address" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Landlord:</span>
                            <span id="t-currentTenancy-landlord" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contract Start:</span>
                            <span id="t-currentTenancy-contractStart" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contract End:</span>
                            <span id="t-currentTenancy-contractEnd" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Frequency:</span>
                            <span id="t-currentTenancy-frequency" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Rent:</span>
                            <span id="t-currentTenancy-rent" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <!-- Files Tab -->
                <div id="t-tab-files" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-folder"></i>
                        <span>Files</span>
                    </div>
                    <div class="files-list" id="t-files-list">
                        <p>No files uploaded yet.</p>
                    </div>
                </div>

                <!-- Notes Tab -->
                <div id="t-tab-notes" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-sticky-note"></i>
                        <span>Notes</span>
                    </div>
                    <div class="notes-input-section">
                        <textarea id="t-noteTextarea" class="note-textarea" placeholder="Write your note here..."></textarea>
                        <button id="t-addNoteBtn" class="add-note-btn" onclick="actionbtn.addNote()">
                            <i class="fa-solid fa-plus"></i> Add Note
                        </button>
                    </div>
                    <div id="t-note-notesList" class="notes-list"></div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ─── Status Logic ─────────────────────────────────────────────────────
        function updateAllStatuses() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            document.querySelectorAll('#tableBody tr').forEach(row => {
                const badge    = row.querySelector('.status-badge');
                const startRaw = row.dataset.contractStart;
                const endRaw   = row.dataset.contractEnd;

                if (!startRaw || !endRaw) {
                    badge.innerHTML    = '<i class="fa-solid fa-user-plus"></i> New';
                    badge.className    = 'status-badge status-new';
                    row.dataset.status = 'new';
                    return;
                }

                const startDate = new Date(startRaw);
                const endDate   = new Date(endRaw);

                if (isNaN(startDate) || isNaN(endDate)) {
                    badge.innerHTML    = '<i class="fa-solid fa-question"></i> Unknown';
                    badge.className    = 'status-badge status-unknown';
                    row.dataset.status = 'unknown';
                    return;
                }

                let label, key, icon;
                if (today >= startDate && today <= endDate) {
                    label = 'Active'; key = 'active'; icon = 'fa-circle-check';
                } else if (today > endDate) {
                    label = 'Former'; key = 'former'; icon = 'fa-clock';
                } else {
                    label = 'New';    key = 'new';    icon = 'fa-user-plus';
                }

                badge.innerHTML    = `<i class="fa-solid ${icon}"></i> ${label}`;
                badge.className    = `status-badge status-${key}`;
                row.dataset.status = key;
            });
        }

        // ─── Filter ───────────────────────────────────────────────────────────
        function toggleFilter() {
            const dd = document.getElementById('filterDropdowns');
            dd.style.display = dd.style.display === 'none' ? 'flex' : 'none';
        }

        function filterTenants() {
            const statusFilter = document.getElementById('filterStatus').value;
            const startFilter  = document.getElementById('filterStart').value;
            const endFilter    = document.getElementById('filterEnd').value;

            document.querySelectorAll('#tableBody tr').forEach(row => {
                let show = true;
                if (statusFilter && row.dataset.status !== statusFilter) show = false;
                if (startFilter && row.dataset.contractStart && row.dataset.contractStart < startFilter) show = false;
                if (endFilter   && row.dataset.contractEnd   && row.dataset.contractEnd   > endFilter)   show = false;
                row.style.display = show ? '' : 'none';
            });
        }

        function resetFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterStart').value  = '';
            document.getElementById('filterEnd').value    = '';
            document.querySelectorAll('#tableBody tr').forEach(row => row.style.display = '');
            currentSort = { column: null, direction: 'asc' };
            document.querySelectorAll('#tenantsTable thead th').forEach(th => {
                th.classList.remove('sorted');
                const icon = th.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-sort';
            });
        }

        // ─── Sort ─────────────────────────────────────────────────────────────
        function sortTable(column, headerElement) {
            const tbody = document.getElementById('tableBody');
            const rows  = Array.from(tbody.querySelectorAll('tr'));

            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column    = column;
                currentSort.direction = 'asc';
            }

            // Reset all header icons
            document.querySelectorAll('#tenantsTable thead th').forEach(th => {
                th.classList.remove('sorted');
                const icon = th.querySelector('i');
                if (icon) icon.className = 'fa-solid fa-sort';
            });

            // Set active header icon
            headerElement.classList.add('sorted');
            const activeIcon = headerElement.querySelector('i');
            if (activeIcon) {
                activeIcon.className = currentSort.direction === 'asc'
                    ? 'fa-solid fa-sort-up'
                    : 'fa-solid fa-sort-down';
            }

            rows.sort((a, b) => {
                let aVal = '', bVal = '';
                switch (column) {
                    case 'name':
                        aVal = a.querySelector('.tenant-name')?.textContent.trim().toLowerCase()    ?? '';
                        bVal = b.querySelector('.tenant-name')?.textContent.trim().toLowerCase()    ?? '';
                        break;
                    case 'number':
                        aVal = a.querySelector('.tenant-phone')?.textContent.trim().toLowerCase()   ?? '';
                        bVal = b.querySelector('.tenant-phone')?.textContent.trim().toLowerCase()   ?? '';
                        break;
                    case 'email':
                        aVal = a.querySelector('.td-email')?.textContent.trim().toLowerCase()       ?? '';
                        bVal = b.querySelector('.td-email')?.textContent.trim().toLowerCase()       ?? '';
                        break;
                    case 'address':
                        aVal = a.querySelector('.tenant-address')?.textContent.trim().toLowerCase() ?? '';
                        bVal = b.querySelector('.tenant-address')?.textContent.trim().toLowerCase() ?? '';
                        break;
                    case 'status':
                        aVal = a.dataset.status ?? '';
                        bVal = b.dataset.status ?? '';
                        break;
                    case 'contract':
                        aVal = a.dataset.contractStart ?? '';
                        bVal = b.dataset.contractStart ?? '';
                        break;
                }
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 :  1;
                if (aVal > bVal) return currentSort.direction === 'asc' ?  1 : -1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
            filterTenants();
        }

        // ─── Row click → open view modal ─────────────────────────────────────
        document.getElementById('tableBody').addEventListener('click', function(e) {
            if (e.target.closest('.action-btn') || e.target.closest('form')) return;
            const row = e.target.closest('tr');
            if (!row) return;
            const viewBtn = row.querySelector('.action-btn-view');
            if (viewBtn) viewBtn.click();
        });

        // ─── Edit (placeholder) ───────────────────────────────────────────────
        function editTenant(btn) {
            const row = btn.closest('tr');
            alert('Edit Tenant: ' + row.dataset.name);
        }

        // ─── Init ─────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', updateAllStatuses);
    </script>
</body>
</html>