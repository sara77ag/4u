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
        p.room_count,
        p.bathroom_count,
        p.epc_rating,
        p.has_garden,
        p.allowed_pets,
        p.council_band,
        p.gas_cert_due,
        p.electric_cert_due,
        p.epc_due_date,
        p.landlord_id_expiry_date,
        l.name AS landlord_name,
        t.name AS tenant_name,
        t.contract_start,
        t.contract_end,
        t.payment_frequency,
        t.id_expiry_date
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
    <script src="actionbtn.js" ></script>
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
                    $rooms = $p['room_count'] ?? 0;
                    $bathrooms = $p['bathroom_count'] ?? 0;
                    $epc = $p['epc_rating'] ?? '';
                    $garden = $p['has_garden'] ?? 0;
                    $pets = $p['allowed_pets'] ?? 0;
                    $council = $p['council_band'] ?? '';
                    $frequency = $p['payment_frequency'] ?? '';
                ?>
            <tr 
             data-id="<?= $p['property_id'] ?>"
             data-type="<?= htmlspecialchars($type) ?>"
             data-status="<?= $status ?>"
             data-price="<?= $price ?>"
             data-landlord="<?= htmlspecialchars($landlord_name) ?>"
             data-tenant="<?= htmlspecialchars($tenant_name) ?>"
             data-contract-start="<?= $p['contract_start'] ?>"
             data-contract-end="<?= $p['contract_end'] ?>"
             data-frequency="<?= htmlspecialchars($p['payment_frequency'] ?? '') ?>"
             data-rooms="<?= $p['room_count'] ?>"
             data-bathrooms="<?= $p['bathroom_count'] ?>"
             data-epc="<?= htmlspecialchars($p['epc_rating']) ?>"
             data-pets="<?= $p['allowed_pets'] ?>"
             data-garden="<?= $p['has_garden'] ?>"
             data-council="<?= htmlspecialchars($p['council_band']) ?>"
             data-gas="<?= $p['gas_cert_due'] ?>"
             data-electric="<?= $p['electric_cert_due'] ?>"
             data-epc-date="<?= $p['epc_due_date'] ?>"
             data-landlord-id-expiry="<?= $p['landlord_id_expiry_date'] ?>"
             data-tenant-id-expiry="<?= $p['id_expiry_date'] ?? '' ?>"
            >
        <td class="td-address"><?= htmlspecialchars($p['property_address']) ?></td>
        <td><span class="td-type"><?= htmlspecialchars($p['property_type']) ?></span></td>
        <td class="td-landlord"><?= htmlspecialchars($landlord_name) ?></td>
        <td class="rent">£<?= number_format($price, 0) ?></td>
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

<!-- ============================================ -->
<!-- Action Buttons: View Property -->
<!-- ============================================ -->
<div id="viewPropertyModal" class="action-modal-overlay">
    <div class="action-modal-container">
        <!-- Header -->
        <div class="action-modal-header">
            <div class="action-header-left">
                <div class="action-modal-header-icon">
                    <i class="fa-solid fa-house"></i>
                </div>
                <h2 id="propertyTitle">-</h2>
            </div>
            <button class="action-modal-close" onclick="actionbtn.closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="action-tabs-nav">
            <button class="tab-btn active" data-tab="property">
                <i class="fa-solid fa-house"></i> Property Details
            </button>
            <button class="tab-btn" data-tab="contract">
                <i class="fa-solid fa-file-contract"></i> Contract Agreement
            </button>
            <button class="tab-btn" data-tab="dates">
                <i class="fa-solid fa-calendar"></i> Important Dates
            </button>
            <button class="tab-btn" data-tab="files">
                <i class="fa-solid fa-folder"></i> Files
            </button>
            <button class="tab-btn" data-tab="notes">
                <i class="fa-solid fa-sticky-note"></i> Notes
            </button>
        </div>

        <!-- Tab Content -->
        <div class="action-tabs-content">
            <!-- Property Tab -->
            <div id="p-tab-property" class="tab-content active">
                <div class="tab-header">
                    <i class="fa-solid fa-house"></i>
                    <span>Property Details</span>
                </div>
              <div class="details-grid">
                    <div class="detail-row">
                        <span class="detail-label">Landlord Name:</span>
                        <span id="p-property-landlord" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Property Type:</span>
                        <span id="p-property-type" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Property Status:</span>
                        <span id="p-property-status" class="status-badge status-available">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tenant Name:</span>
                        <span id="p-property-tenant" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Rooms:</span>
                        <span id="p-property-rooms" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Number of Bathrooms:</span>
                        <span id="p-property-bathrooms" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">EPC Rating:</span>
                        <span id="p-property-epc" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Pets Allowed:</span>
                        <span id="p-property-pets" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Garden:</span>
                        <span id="p-property-garden" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Council Band:</span>
                        <span id="p-property-council" class="detail-value">-</span>
                    </div>
                </div>
            </div>

            <!-- Contract Tab -->
            <div id="p-tab-contract" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-file-contract"></i>
                    <span>Contract Agreement</span>
                </div>
                <div class="details-grid">
                    <div class="detail-row">
                        <span class="detail-label">Landlord Name:</span>
                        <span id="p-contract-landlord" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Tenant Name:</span>
                        <span id="p-contract-tenant" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Rent Amount:</span>
                        <span id="p-contract-rent" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Frequency:</span>
                        <span id="p-contract-frequency" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Due Date:</span>
                        <span id="p-contract-duedate" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contract Start:</span>
                        <span id="p-contract-start" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contract End:</span>
                        <span id="p-contract-end" class="detail-value">-</span>
                    </div>
                </div>
            </div>

            <!-- Important Dates Tab -->
            <div id="p-tab-dates" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-calendar"></i>
                    <span>Important Dates</span>
                </div>
                <div class="dates-list">
                    <div class="date-item">
                        <span class="date-label">Landlord's ID Expiry:</span>
                        <span  id="p-date-landlord-id" class="date-value">-</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">Tenant's ID Expiry:</span>
                        <span  id="p-date-tenant-id" class="date-value">-</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">Contract End:</span>
                        <span id="p-date-contract-end" class="date-value">-</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">Gas Certificate Due Date:</span>
                        <span id="p-date-gas" class="date-value">-</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">Electric Certificate Due Date:</span>
                        <span id="p-date-electric" class="date-value">-</span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">EPC Due Date:</span>
                        <span id="p-date-epc" class="date-value">-</span>
                    </div>
                </div>
            </div>

            <!-- Files Tab -->
            <div id="p-tab-files" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-folder"></i>
                    <span>Files</span>
                </div>
                <div class="files-list">
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Tenancy Contract</span>
                        <button class="file-open-btn" onclick="openFile('tenancy')">Open</button>
                    </div>
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Gas Safety Certificate</span>
                        <button class="file-open-btn" onclick="openFile('gas')">Open</button>
                    </div>
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Electrical Installation Certificate</span>
                        <button class="file-open-btn" onclick="openFile('electrical')">Open</button>
                    </div>
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">EPC Certificate</span>
                        <button class="file-open-btn" onclick="openFile('epc')">Open</button>
                    </div>
                </div>
            </div>

            <!-- Notes Tab -->
            <div id="p-tab-notes" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-sticky-note"></i>
                    <span>Notes</span>
                </div>
                <div class="notes-input-section">
                    <textarea id="p-noteTextarea" class="note-textarea" placeholder="Write your note here..."></textarea>
                    <button id="p-addNoteBtn" class="add-note-btn" onclick="window.actionbtn.addNote()">
                        <i class="fa-solid fa-plus"></i> Add Note
                    </button>
                </div>
                <div id="p-notesList" class="notes-list">
                </div>
            </div>
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

        function editProperty(btn) {
            const row = btn.closest('tr');
            const address = row.querySelector('.td-address').textContent;
            alert('Edit Property: ' + address);
        }

        function deleteProperty(btn) {
            const row = btn.closest('tr');
            const id = row.querySelector('.property-id').textContent;
            if (confirm('Are you sure you want to delete property ' + id + '?')) {
                row.remove();
            }
        }
    </script>
</body>
</html>