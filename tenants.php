<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// Handle archive request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    $stmt = $pdo->prepare("UPDATE tenants SET archived = 1, date_archived = date('now') WHERE tenant_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: tenants.php");
    exit();
}

$query = "SELECT 
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
        LEFT JOIN landlords l ON l.landlord_id = p.landlord_id
        WHERE t.archived = 0";

$tenants = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
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
    .col-name {
        width: 16%;
    }

    .col-number {
        width: 12%;
    }

    .col-email {
        width: 22%;
    }

    .col-address {
        width: 20%;
    }

    .col-status {
        width: 10%;
    }

    .col-contract {
        width: 15%;
    }

    .col-action {
        width: 10%;
        text-align: center;
    }
    </style>
</head>

<body onload="CallNavbar(); loadModals();">

    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Tenants</h1>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
        <div class="flash-error" style="
            background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;
            border-radius:8px; padding:12px 20px; margin-bottom:16px;
            display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-xmark"></i>
            A tenant with that email, phone number, or ID number already exists.
        </div>
        <?php endif; ?>

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

                <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                <button class="btn-add blue" onclick="openAddTenantModal()">
                    <i class="fa-solid fa-plus"></i> Tenant
                </button>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <table id="tenantsTable">
                        <thead>
                            <tr>
                                <th class="sortable col-name" onclick="sortTable('name', this)">Name <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-number" onclick="sortTable('number', this)">Number <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-email" onclick="sortTable('email', this)">Email <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-address" onclick="sortTable('address', this)">Address <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-status" onclick="sortTable('status', this)">Status <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-contract" onclick="sortTable('contract', this)">Contract <i
                                        class="fa-solid fa-sort"></i></th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($tenants as $t): ?>
                            <tr data-tenant-id="<?= $t['tenant_id'] ?>" data-name="<?= htmlspecialchars($t['name']) ?>"
                                data-phone="<?= htmlspecialchars($t['phone']) ?>"
                                data-email="<?= htmlspecialchars($t['email']) ?>"
                                data-address="<?= htmlspecialchars($t['property_address'] ?? 'Unassigned') ?>"
                                data-idtype="<?= htmlspecialchars($t['id_type']) ?>"
                                data-idnumber="<?= htmlspecialchars($t['id_number']) ?>"
                                data-idexpiry="<?= htmlspecialchars($t['id_expiry_date']) ?>"
                                data-contact="<?= htmlspecialchars($t['contact_method'] ?? '') ?>"
                                data-contract-start="<?= htmlspecialchars($t['contract_start']) ?>"
                                data-contract-end="<?= htmlspecialchars($t['contract_end']) ?>"
                                data-frequency="<?= htmlspecialchars($t['frequency']) ?>"
                                data-landlord="<?= htmlspecialchars($t['landlord_name'] ?? 'N/A') ?>"
                                data-rent="<?= htmlspecialchars($t['rent'] ?? 0) ?>">

                                <td class="tenant-name"><?= htmlspecialchars($t['name']) ?></td>
                                <td><?= htmlspecialchars($t['phone']) ?></td>
                                <td class="td-email"><?= htmlspecialchars($t['email']) ?></td>
                                <td class="tenant-address">
                                    <?= htmlspecialchars($t['property_address'] ?? 'Unassigned') ?></td>
                                <td><span class="status-badge"></span></td>
                                <td class="contract-info">
                                    <?php if (!empty($t['contract_start']) && !empty($t['contract_end'])): ?>
                                    <?= date('d/m/Y', strtotime($t['contract_start'])) ?>
                                    to
                                    <?= date('d/m/Y', strtotime($t['contract_end'])) ?>
                                    <?php else: ?>
                                    No Contract
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" onclick="actionbtn.viewTenant(this)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                                        <button class="action-btn action-btn-edit" onclick="actionbtn.editTenant(this)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="archive_id" value="<?= $t['tenant_id'] ?>">
                                            <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                                onclick="openArchiveModal(<?= $t['tenant_id'] ?>, '<?= htmlspecialchars($t['name']) ?>'); return false;">
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

    <?php // Action Buttons Modal: View Tenant ?>
    <div id="viewTenantModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <h2 id="tenantName">Tenant Name</h2>
                </div>
                <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="tenant">
                    <i class="fa-solid fa-user"></i> Tenant Information
                </button>
                <button class="tab-btn" data-tab="tenancyHistory">
                    <i class="fa-solid fa-address-book"></i>Tenancy History
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

                <?php // Tenant Tab ?>
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
                            <span class="detail-label">ID Type:</span>
                            <span id="t-tenant-idtype" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID Number:</span>
                            <span id="t-tenant-idnumber" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID Expiry:</span>
                            <span id="l-tenant-idexpiry" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Address:</span>
                            <span id="l-tenant-address" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Preferred Contact Method:</span>
                            <span id="t-tenant-contact" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <?php // Tenancy History Tab ?>
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
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                        </div>
                        <div class="data-row">
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                        </div>
                        <div class="data-row">
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell">-</div>
                            <div class="cell"> - </div>
                            <div class="cell">-</div>
                        </div>
                    </div>
                </div>

                <?php // Current Tenancy Tab ?>
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
                            <span class="detail-label">Status:</span>
                            <span id="t-currentTenancy-status" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Rent:</span>
                            <span id="t-currentTenancy-rent" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <?php // Files Tab ?>
                <div id="t-tab-files" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-folder"></i>
                        <span>Files</span>
                    </div>
                    <div class="files-list" id="t-files-list">
                    </div>
                </div>

                <?php // Notes Tab ?>
                <div id="t-tab-notes" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-sticky-note"></i>
                        <span>Notes</span>
                    </div>
                    <div class="notes-input-section">
                        <textarea id="t-noteTextarea" class="note-textarea"
                            placeholder="Write your note here..."></textarea>
                        <button id="t-addNoteBtn" class="add-note-btn" onclick="actionbtn.addNote()">
                            <i class="fa-solid fa-plus"></i> Add Note
                        </button>
                    </div>
                    <div data-notes-list class="notes-list"></div>
                </div>

            </div>
        </div>
    </div>

    <?php // Archive Confirm Modal ?>
    <div id="archiveModal" class="action-modal-overlay">
        <div class="action-modal-container" style="max-width: 480px;">
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <h3>Archive Tenant</h3>
                </div>
                <button class="modal-close-btn" onclick="closeArchiveModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="delete-body">
                <p class="p-text"> Are you sure you want to archive this tenant? </p>
                <p id="archiveTenantName" class="delete-entity"></p>
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-delete" onclick="confirmArchive()">
                        Yes, Archive
                    </button>
                </div>
                <form id="archiveForm" method="POST" style="display:none;">
                    <input type="hidden" name="archive_id" id="archive_id">
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleFilter() {
        const dropdown = document.getElementById('filterDropdowns');
        dropdown.style.display = dropdown.style.display === 'none' ? 'flex' : 'none';
    }

    function sortTable(column, headerElement) {
        const tbody = document.getElementById('tableBody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }

        document.querySelectorAll('table th').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.classList.remove('asc', 'desc');
        });

        headerElement.classList.add('sorted');
        const icon = headerElement.querySelector('.sort-icon');
        if (icon) icon.classList.add(currentSort.direction);

        rows.sort((a, b) => {
            let aVal, bVal;
            switch (column) {
                case 'name':
                    aVal = a.querySelector('.tenant-name').textContent.toLowerCase();
                    bVal = b.querySelector('.tenant-name').textContent.toLowerCase();
                    break;
                case 'number':
                    aVal = a.cells[1].textContent.toLowerCase();
                    bVal = b.cells[1].textContent.toLowerCase();
                    break;
                case 'email':
                    aVal = a.querySelector('.td-email').textContent.toLowerCase();
                    bVal = b.querySelector('.td-email').textContent.toLowerCase();
                    break;
                case 'address':
                    aVal = a.querySelector('.tenant-address').textContent.toLowerCase();
                    bVal = b.querySelector('.tenant-address').textContent.toLowerCase();
                    break;
                case 'status':
                    aVal = a.querySelector('.status-badge').textContent.toLowerCase();
                    bVal = b.querySelector('.status-badge').textContent.toLowerCase();
                    break;
                case 'contract':
                    aVal = a.querySelector('.contract-info').textContent.toLowerCase();
                    bVal = b.querySelector('.contract-info').textContent.toLowerCase();
                    break;
                default:
                    return 0;
            }
            if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
            return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
        updateAllStatuses();
    }

    function filterTenants() {
        const statusFilter = document.getElementById('filterStatus').value;
        const startFilter = document.getElementById('filterStart').value;
        const endFilter = document.getElementById('filterEnd').value;

        document.querySelectorAll('#tableBody tr').forEach(row => {
            let show = true;

            if (statusFilter) {
                const statusCell = row.querySelector('.status-badge');
                if (statusCell && !statusCell.textContent.toLowerCase().includes(statusFilter.toLowerCase())) {
                    show = false;
                }
            }

            if (startFilter || endFilter) {
                const contractStart = row.dataset.contractStart;
                const contractEnd = row.dataset.contractEnd;

                if (contractStart && contractEnd) {
                    const cStart = new Date(contractStart);
                    const cEnd = new Date(contractEnd);
                    const fStart = startFilter ? new Date(startFilter) : null;
                    const fEnd = endFilter ? new Date(endFilter) : null;

                    if (fStart && !fEnd) show = (cStart <= fStart && cEnd >= fStart);
                    else if (!fStart && fEnd) show = (cStart <= fEnd && cEnd >= fEnd);
                    else if (fStart && fEnd) show = (cStart <= fEnd && cEnd >= fStart);
                }
            }

            row.style.display = show ? '' : 'none';
        });
    }

    function resetFilters() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterStart').value = '';
        document.getElementById('filterEnd').value = '';
        document.querySelectorAll('#tableBody tr').forEach(row => row.style.display = '');
        document.querySelectorAll('table th').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.classList.remove('asc', 'desc');
        });
        currentSort = {
            column: null,
            direction: 'asc'
        };
    }

    function updateAllStatuses() {
        const today = new Date();
        document.querySelectorAll('#tableBody tr').forEach(row => {
            const statusCell = row.querySelector('.status-badge');
            const startRaw = row.dataset.contractStart;
            const endRaw = row.dataset.contractEnd;

            if (!startRaw || !endRaw) {
                statusCell.innerHTML = '<i class="fa-solid fa-user-plus"></i> New';
                statusCell.className = 'status-badge status-new';
                row.dataset.status = 'new';
                return;
            }

            const startDate = new Date(startRaw);
            const endDate = new Date(endRaw);

            if (isNaN(startDate) || isNaN(endDate)) {
                statusCell.innerHTML = '<i class="fa-solid fa-question"></i> Unknown';
                statusCell.className = 'status-badge status-unknown';
                row.dataset.status = 'unknown';
                return;
            }

            let status, key, icon;
            if (today >= startDate && today <= endDate) {
                status = 'Active';
                key = 'active';
                icon = 'fa-circle-check';
            } else if (today > endDate) {
                status = 'Former';
                key = 'former';
                icon = 'fa-clock';
            } else {
                status = 'New';
                key = 'new';
                icon = 'fa-user-plus';
            }

            statusCell.innerHTML = `<i class="fa-solid ${icon}"></i> ${status}`;
            statusCell.className = `status-badge status-${key}`;
            row.dataset.status = key;
        });
    }

    let selectedTenantId = null;

    function openArchiveModal(id, address) {
        selectedTenantId = id;
        document.getElementById('archiveTenantName').innerText = address;
        document.getElementById('archiveModal').style.display = 'flex';
    }

    function closeArchiveModal() {
        document.getElementById('archiveModal').style.display = 'none';
        selectedTenantId = null;
    }

    function confirmArchive() {
        document.getElementById('archive_id').value = selectedTenantId;
        document.getElementById('archiveForm').submit();
    }

    window.addEventListener('DOMContentLoaded', () => {
        updateAllStatuses();

        const params = new URLSearchParams(window.location.search);
        const tenantId = params.get('id');
        const shouldOpen = params.get('open');

        if (tenantId && shouldOpen === '1') {
            const row = document.querySelector(`tr[data-tenant-id="${tenantId}"]`);
            if (row) {
                const btn = row.querySelector('.action-btn-view');
                if (btn) actionbtn.viewTenant(btn);
            }
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });
    </script>
</body>

</html>