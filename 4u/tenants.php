<?php
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

// Fetch all tenants with property info
$stmt = $pdo->query("
    SELECT 
        t.tenant_id,
        t.name,
        t.phone,
        t.email,
        t.contract_start,
        t.contract_end,
        p.property_address
    FROM tenants t
    LEFT JOIN properties p ON t.property_id = p.property_id
    WHERE t.archived = 0
    ORDER BY t.name
");

$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <style>
        .col-name { width: 16%; }
        .col-number { width: 12%; }
        .col-email { width: 22%; }
        .col-address { width: 20%; }
        .col-status { width: 10%; }
        .col-contract { width: 15%; }
        .col-action { width: 10%; text-align: center; }
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
                                <th class="sortable col-name" onclick="sortTable('name', this)">
                                    Name <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-number" onclick="sortTable('number', this)">
                                    Number <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-email" onclick="sortTable('email', this)">
                                    Email <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-address" onclick="sortTable('address', this)">
                                    Address <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-status" onclick="sortTable('status', this)">
                                    Status <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-contract" onclick="sortTable('contract', this)">
                                    Contract <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
<?php foreach ($tenants as $t): 
    $contract = $t['contract_start'] ? $t['contract_start'] . ' to ' . $t['contract_end'] : '-';
?>
    <tr data-name="<?= strtolower($t['name']) ?>" 
        data-number="<?= htmlspecialchars($t['phone']) ?>" 
        data-email="<?= strtolower($t['email']) ?>" 
        data-address="<?= htmlspecialchars($t['property_address'] ?? '') ?>" 
        data-contract="<?= htmlspecialchars($t['contract_start'] ?? '') ?>">
        <td class="tenant-name"><?= htmlspecialchars($t['name']) ?></td>
        <td><?= htmlspecialchars($t['phone']) ?></td>
        <td class="td-email"><?= htmlspecialchars($t['email']) ?></td>
        <td class="tenant-address"><?= htmlspecialchars($t['property_address'] ?? 'No property assigned') ?></td>
        <td>
            <span class="status-badge status-active">
                <i class="fa-solid fa-circle-check"></i> Active
            </span>
        </td>
        <td class="contract-info"><?= htmlspecialchars($contract) ?></td>
        <td>
            <div class="action-buttons">
                <button class="action-btn action-btn-view" title="View" onclick="viewTenant(this)">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <button class="action-btn action-btn-edit" title="Edit" onclick="editTenant(this)">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="archive_id" value="<?= $t['tenant_id'] ?>">
                    <button type="submit" class="action-btn action-btn-delete" title="Archive"
                        onclick="return confirm('Archive this tenant?\n<?= htmlspecialchars($t['name']) ?>')">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
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

    <script>
        let currentSort = {
            column: null,
            direction: 'asc'
        };

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
                
                switch(column) {
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
            
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                let show = true;
                
                if (statusFilter) {
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell && !statusCell.textContent.toLowerCase().includes(statusFilter.toLowerCase())) {
                        show = false;
                    }
                }
                
                if (startFilter || endFilter) {
                    const contractCell = row.querySelector('.contract-info');
                    if (contractCell && contractCell.textContent !== '-') {
                        const [startDate, endDate] = contractCell.textContent.split(' to ');
                        if (startFilter && new Date(startDate) < new Date(startFilter)) show = false;
                        if (endFilter && new Date(endDate) > new Date(endFilter)) show = false;
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
            currentSort = { column: null, direction: 'asc' };
        }

        function updateAllStatuses() {
            const rows = document.querySelectorAll('#tableBody tr');
            const today = new Date();

            rows.forEach(row => {
                const contractCell = row.querySelector('.contract-info');
                const statusCell = row.querySelector('.status-badge');
                if (!contractCell || !statusCell) return;

                const contractText = contractCell.textContent.trim();
                if (contractText === '-') {
                    statusCell.innerHTML = '<i class="fa-solid fa-play-circle"></i> New';
                    statusCell.className = 'status-badge status-new';
                    return;
                }

                const [startStr, endStr] = contractText.split(' to ');
                if (!startStr || !endStr) return;

                const startDate = new Date(startStr.trim());
                const endDate = new Date(endStr.trim());

                let status, statusKey, icon;
                if (today >= startDate && today <= endDate) {
                    status = 'Active';
                    statusKey = 'active';
                    icon = 'fa-circle-check';
                } else if (today > endDate) {
                    status = 'Former';
                    statusKey = 'former';
                    icon = 'fa-clock';
                } else {
                    status = 'New';
                    statusKey = 'new';
                    icon = 'fa-play-circle';
                }

                statusCell.innerHTML = `<i class="fa-solid ${icon}"></i> ${status}`;
                statusCell.className = `status-badge status-${statusKey}`;
            });
        }

        function viewTenant(btn) {
            const row = btn.closest('tr');
            const name = row.querySelector('.tenant-name').textContent;
            alert('View Tenant: ' + name);
        }

        function editTenant(btn) {
            const row = btn.closest('tr');
            const name = row.querySelector('.tenant-name').textContent;
            alert('Edit Tenant: ' + name);
        }

        window.addEventListener('load', () => {
            updateAllStatuses();
        });
    </script>
</body>
</html>