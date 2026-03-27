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
    $stmt = $pdo->prepare("UPDATE properties SET archived = 1, date_archived = date('now') WHERE property_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: properties.php");
    exit();
}

// Fetch all properties with landlord name and tenant name
$stmt = $pdo->query("
    SELECT 
        p.property_id,
        p.property_address,
        p.property_type,
        p.monthly_rent,
        l.name AS landlord_name,
        t.name AS tenant_name,
        t.contract_start,
        t.contract_end
    FROM properties p
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    LEFT JOIN tenants t ON p.property_id = t.property_id AND t.archived = 0
    WHERE p.archived = 0
");

$properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js" ></script>
    <script src="modals.js" ></script>
    <script src="actionbtn.js"></script>
    <style>
        .col-address { width: 18%; }
        .col-type { width: 8%; }
        .col-landlord { width: 13%; }
        .col-price { width: 9%; }
        .col-tenant { width: 13%; }
        .col-status { width: 11%; }
        .col-contract { width: 14%; }
        .col-action { width: 14%; text-align: center; }
    </style>
</head>
<body onload="CallNavbar(); loadModals();">
    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Properties</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Type</label>
                        <select id="filterType" onchange="filterProperties()">
                            <option value="">All Types</option>
                            <option value="flat">Flat</option>
                            <option value="house">House</option>
                            <option value="apartment">Apartment</option>
                            <option value="bungalow">Bungalow</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus" onchange="filterProperties()">
                            <option value="">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Start</label>
                        <input type="date" id="filterStart" onchange="filterProperties()">
                    </div>
                    <div class="filter-group">
                        <label>End</label>
                        <input type="date" id="filterEnd" onchange="filterProperties()">
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <button class="btn-add green" onclick="openAddContractModal()">
                    <i class="fa-solid fa-plus"></i>Contract
                </button>
                <button class="btn-add blue" onclick="openAddPropertyModal()">
                    <i class="fa-solid fa-plus"></i>Property
                </button>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <table id="propertiesTable">
                        <thead>
                            <tr>
                                <th class="sortable col-address" onclick="sortTable('address', this)">
                                    Address <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-type" onclick="sortTable('type', this)">
                                    Type <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-landlord" onclick="sortTable('landlord', this)">
                                    Landlord <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-price" onclick="sortTable('price', this)">
                                    Price <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-tenant" onclick="sortTable('tenant', this)">
                                    Tenant <i class="fa-solid fa-sort sort-icon"></i>
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
<?php foreach ($properties as $p):
    $status = $p['tenant_name'] ? 'occupied' : 'available';
    $contract = $p['contract_start'] ? $p['contract_start'] . ' to ' . $p['contract_end'] : '-';
    $tenant_name = $p['tenant_name'] ?? '';
    $landlord_name = $p['landlord_name'] ?? '';
    $type = strtolower($p['property_type']);
    $price = $p['monthly_rent'];
?>
    <tr data-type="<?= htmlspecialchars($type) ?>"
        data-status="<?= $status ?>"
        data-price="<?= $price ?>"
        data-landlord="<?= htmlspecialchars($landlord_name) ?>"
        data-tenant="<?= htmlspecialchars($tenant_name) ?>"
        data-contract="<?= htmlspecialchars($p['contract_start'] ?? '') ?>">
        <td class="td-address"><?= htmlspecialchars($p['property_address']) ?></td>
        <td><span class="td-type"><?= htmlspecialchars($p['property_type']) ?></span></td>
        <td class="td-landlord"><?= htmlspecialchars($landlord_name) ?></td>
        <td class="td-rent">£<?= number_format($price, 0) ?></td>
        <td><?php if ($tenant_name): ?>
            <span class="td-tenant"><?= htmlspecialchars($tenant_name) ?></span>
        <?php else: ?>
            <span class="tenant-vacant">Vacant</span>
        <?php endif; ?></td>
        <td>
            <?php if ($status === 'occupied'): ?>
                <span class="status-badge status-occupied">
                    <i class="fa-solid fa-user"></i> Occupied
                </span>
            <?php else: ?>
                <span class="status-badge status-available">
                    <i class="fa-solid fa-check-circle"></i> Available
                </span>
            <?php endif; ?>
        </td>
        <td class="contract-info"><?= htmlspecialchars($contract) ?></td>
        <td>
            <div class="action-buttons">
                <button class="action-btn action-btn-view" title="View" onclick="actionbtn.viewProperty(this)">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <button class="action-btn action-btn-edit" title="Edit" onclick="editProperty(this)">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="archive_id" value="<?= $p['property_id'] ?>">
                    <button type="submit" class="action-btn action-btn-delete" title="Archive"
                        onclick="return confirm('Archive this property?\n<?= htmlspecialchars($p['property_address']) ?>')">
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
                    case 'address':
                        aVal = a.querySelector('.td-address').textContent.toLowerCase();
                        bVal = b.querySelector('.td-address').textContent.toLowerCase();
                        break;
                    case 'type':
                        aVal = a.dataset.type;
                        bVal = b.dataset.type;
                        break;
                    case 'landlord':
                        aVal = a.dataset.landlord.toLowerCase();
                        bVal = b.dataset.landlord.toLowerCase();
                        break;
                    case 'price':
                        aVal = parseInt(a.dataset.price);
                        bVal = parseInt(b.dataset.price);
                        break;
                    case 'tenant':
                        aVal = a.dataset.tenant.toLowerCase();
                        bVal = b.dataset.tenant.toLowerCase();
                        break;
                    case 'status':
                        aVal = a.dataset.status;
                        bVal = b.dataset.status;
                        break;
                    case 'contract':
                        aVal = a.dataset.contract || '';
                        bVal = b.dataset.contract || '';
                        break;
                    default:
                        return 0;
                }
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        function filterProperties() {
            const typeFilter = document.getElementById('filterType').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const startFilter = document.getElementById('filterStart').value;
            const endFilter = document.getElementById('filterEnd').value;

            document.querySelectorAll('#tableBody tr').forEach(row => {
                let show = true;
                if (typeFilter && row.dataset.type !== typeFilter) show = false;
                if (statusFilter && row.dataset.status !== statusFilter) show = false;
                if (startFilter) {
                    const contractCell = row.querySelector('.contract-info');
                    if (contractCell && contractCell.textContent !== '-') {
                        const contractStart = contractCell.textContent.split(' to ')[0];
                        if (contractStart && new Date(contractStart) < new Date(startFilter)) show = false;
                    }
                }
                if (endFilter) {
                    const contractCell = row.querySelector('.contract-info');
                    if (contractCell && contractCell.textContent !== '-') {
                        const contractEnd = contractCell.textContent.split(' to ')[1];
                        if (contractEnd && new Date(contractEnd) > new Date(endFilter)) show = false;
                    }
                }
                row.style.display = show ? '' : 'none';
            });
        }

        function resetFilters() {
            document.getElementById('filterType').value = '';
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

        function viewProperty(btn) {
            const row = btn.closest('tr');
            const address = row.querySelector('.td-address').textContent;
            alert('View Property: ' + address);
        }

        function editProperty(btn) {
            const row = btn.closest('tr');
            const address = row.querySelector('.td-address').textContent;
            alert('Edit Property: ' + address);
        }

        function deleteProperty(btn) {
            const row = btn.closest('tr');
            const address = row.querySelector('.td-address').textContent;
            if (confirm('Are you sure you want to delete property ' + address + '?')) {
                row.remove();
            }
        }
    </script>
</body>
</html>