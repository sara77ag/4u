<?php
session_start();

/*
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
*/

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

$query  = "SELECT t.*, p.property_address
        FROM tenants t
        LEFT JOIN properties p on t.property_id = p.property_id
        WHERE t.archived = 0";

$tenants = $pdo -> query($query)->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="navbar.js" ></script>
    <script src="modals.js" ></script>
    <style>
        /* Column specific widths */
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
 
    <!-- Navbar Container -->
    <div id="navbar-container"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Title -->
        <h1 class="page-title">Tenants</h1>

        <div class="main-container">
            <!-- Main Controls Row -->
            <div class="controls-row">
                <!-- Filter Toggle Button -->
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <!-- Filter Dropdowns -->
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

                <!-- Spacer to push button to right -->
                <div class="controls-spacer"></div>

                <!-- Action Button -->
                <button class="btn-add blue" onclick="openAddTenantModal()">
                    <i class="fa-solid fa-plus"></i> Tenant
                </button>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-wrapper">
                    <table id="tenantsTable">
                        <thead>
                            <tr>
                                <th class="sortable col-name" onclick="sortTable('name', this)">Name <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-number" onclick="sortTable('number', this)">Number <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-email" onclick="sortTable('email', this)">Email <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-address" onclick="sortTable('address', this)">Address <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-status" onclick="sortTable('status', this)">Status <i class="fa-solid fa-sort"></i></th>
                                <th class="sortable col-contract" onclick="sortTable('contract', this)">Contract <i class="fa-solid fa-sort"></i></th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($tenants as $t): ?>
                            <tr data-name="<?= strtolower($t['name']) ?>" 
                                data-status="" 
                                data-contract="<?= $t['contract_start'] ?>">
                                <td class="tenant-name"><?= htmlspecialchars($t['name']) ?></td>
                                <td><?= htmlspecialchars($t['phone']) ?></td>
                                <td class="td-email"><?= htmlspecialchars($t['email']) ?></td>
                                <td class="tenant-address"><?= htmlspecialchars($t['property_address'] ?? 'Unassigned') ?></td>
                                <td><span class="status-badge"></span></td>
                                <td class="contract-info">
                                    <?= date('m/d/Y', strtotime($t['contract_start'])) ?> to <?= date('m/d/Y', strtotime($t['contract_end'])) ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" onclick="viewTenant(this)"><i class="fa-solid fa-eye"></i></button>
                                        
                                        <?php if ($_SESSION['role'] !== 'read_only'): ?>
                                            <button class="action-btn action-btn-edit" onclick="editTenant(this)"><i class="fa-solid fa-pen"></i></button>
                                            
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Archive <?= htmlspecialchars($t['name']) ?>?')">
                                                <input type="hidden" name="archive_id" value="<?= $t['tenant_id'] ?>">
                                                <button type="submit" class="action-btn action-btn-delete"><i class="fa-solid fa-trash"></i></button>
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

        <!-- Modals Container -->
    <div id="modals-container"></div>

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
                if (icon) {
                    icon.classList.remove('asc', 'desc');
                }
            });
            
            headerElement.classList.add('sorted');
            const icon = headerElement.querySelector('.sort-icon');
            if (icon) {
                icon.classList.add(currentSort.direction);
            }
            
            // Sort rows
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
            
            // Update statuses after sorting
            updateAllStatuses();
        }

        // Filter Tenants
        function filterTenants() {
            const statusFilter = document.getElementById('filterStatus').value;
            const startFilter = document.getElementById('filterStart').value;
            const endFilter = document.getElementById('filterEnd').value;
            
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                let show = true;
                
                // Status filter
                if (statusFilter) {
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell && statusCell.textContent.toLowerCase().includes(statusFilter.toLowerCase()) === false) {
                        show = false;
                    }
                }
                
                // Contract date filters
                if (startFilter || endFilter) {
                    const contractCell = row.querySelector('.contract-info');
                    if (contractCell && contractCell.textContent !== '-') {
                        const [startDate, endDate] = contractCell.textContent.split(' to ');
                        if (startFilter && new Date(startDate) < new Date(startFilter)) {
                            show = false;
                        }
                        if (endFilter && new Date(endDate) > new Date(endFilter)) {
                            show = false;
                        }
                    }
                }
                
                row.style.display = show ? '' : 'none';
            });
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterStart').value = '';
            document.getElementById('filterEnd').value = '';
            
            const rows = document.querySelectorAll('#tableBody tr');
            rows.forEach(row => row.style.display = '');
            
            document.querySelectorAll('table th').forEach(th => {
                th.classList.remove('sorted');
                const icon = th.querySelector('.sort-icon');
                if (icon) {
                    icon.classList.remove('asc', 'desc');
                }
            });
            currentSort = { column: null, direction: 'asc' };
        }

        // Update all tenant statuses dynamically based on contract dates
        function updateAllStatuses() {
            const rows = document.querySelectorAll('#tableBody tr');
            const today = new Date();

            rows.forEach(row => {
                const contractCell = row.querySelector('.contract-info');
                const statusCell = row.querySelector('.status-badge');
                if (!contractCell || !statusCell) return;

                const contractText = contractCell.textContent.trim();
                if (contractText === '-' || contractText === '') {
                    // No contract -> New
                    statusCell.innerHTML = '<i class="fa-solid fa-play-circle"></i> New';
                    statusCell.className = 'status-badge status-new';
                    row.dataset.status = 'new';
                    return;
                }

                const [startStr, endStr] = contractText.split(' to ');
                if (!startStr || !endStr) return;

                // Parse MM/DD/YYYY -> Date (assume current year if needed, but samples have full year)
                const parseDate = (dateStr) => {
                    const [month, day, year] = dateStr.split('/').map(Number);
                    return new Date(year, month - 1, day); // JS months 0-indexed
                };

                const startDate = parseDate(startStr.trim());
                const endDate = parseDate(endStr.trim());

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
                    status = 'new';
                    statusKey = 'new';
                    icon = 'fa-play-circle';
                }

                statusCell.innerHTML = `<i class="fa-solid ${icon}"></i> ${status}`;
                statusCell.className = `status-badge status-${statusKey}`;
                row.dataset.status = statusKey;
            });
        }

        // Tenant action functions
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

        function deleteTenant(btn) {
            const row = btn.closest('tr');
            const name = row.querySelector('.tenant-name').textContent;
            if (confirm('Are you sure you want to delete tenant ' + name + '?')) {
                row.remove();
            }
        }

        // Initialise statuses on load
        window.addEventListener('load', () => {
            updateAllStatuses();
        });
    </script>
</body>
</html>

