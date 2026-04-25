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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    $stmt = $pdo->prepare("UPDATE properties SET archived = 1, date_archived = date('now') WHERE property_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: properties.php");
    exit();
}

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
        p.landlord_id_expiry_date AS landlord_id_expiry,
        t.tenant_id,
        t.id_expiry_date AS tenant_id_expiry,
        t.name AS tenant_name,
        l.name AS landlord_name,
        c.contract_start,
        c.contract_end
    FROM properties p
    LEFT JOIN landlords l ON p.landlord_id = l.landlord_id
    LEFT JOIN contracts c ON c.contract_id = (
        SELECT c2.contract_id
        FROM contracts c2
        WHERE c2.property_id = p.property_id
        ORDER BY c2.contract_end DESC
        LIMIT 1
    )
    AND date('now') BETWEEN c.contract_start AND c.contract_end    
    LEFT JOIN tenants t ON t.tenant_id = c.tenant_id
    WHERE p.archived = 0
    ORDER BY p.property_address
");

$importantDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDateValue($date) {
    if (empty($date)) {
        return 'N/A';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'N/A';
    }

    return date('d/m/Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Important Dates - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <script src="modals.js"></script>
    <script src="actionbtn.js"></script>
    <style>
    .col-property {
        width: 22%;
    }

    .col-gas {
        width: 11%;
    }

    .col-electric {
        width: 12%;
    }

    .col-epc {
        width: 11%;
    }

    .col-idlandlord {
        width: 12%;
    }

    .col-idtenant {
        width: 11%;
    }

    .col-contract {
        width: 11%;
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
        <h1 class="page-title">Important Dates</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Property</label>
                        <input type="text" id="filterProperty" onchange="filterImportantDates()"
                            placeholder="Enter Property Name">
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <!--<?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?> <button class="btn-add green"
                    onclick="openAddContractModal()">
                    <i class="fa-solid fa-plus"></i>Contract
                    </button>
                    <button class="btn-add blue" onclick="openAddPropertyModal()">
                        <i class="fa-solid fa-plus"></i>Property
                    </button>
                    <?php endif; ?> -->
            </div>


            <div class="table-container">
                <div class="table-wrapper">
                    <table id="importantDatesTable">
                        <thead>
                            <tr>
                                <th class="sortable col-property" onclick="sortTable('property', this)">
                                    Property <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-gas" onclick="sortTable('gas', this)">
                                    Gas <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-electric" onclick="sortTable('electric', this)">
                                    Electric <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-epc" onclick="sortTable('epc', this)">
                                    EPC <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-idlandlord" onclick="sortTable('idlandlord', this)">
                                    Landlord Id Expiry <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-idtenant" onclick="sortTable('idtenant', this)">
                                    Tenant Id Expiry <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-contract" onclick="sortTable('contract', this)">
                                    Contract <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($importantDates as $row): 
                            $status = !empty($row['contract_start']) ? 'occupied' : 'available';
                        ?>
                            <tr data-id="<?= $row['property_id'] ?>"
                                data-address="<?= htmlspecialchars($row['property_address'] ?? '') ?>"
                                data-property="<?= strtolower(htmlspecialchars($row['property_address'] ?? '')) ?>"
                                data-type="<?= htmlspecialchars($row['property_type'] ?? '') ?>"
                                data-status="<?= $status ?>"
                                data-price="<?= htmlspecialchars($row['monthly_rent'] ?? '') ?>"
                                data-landlord="<?= htmlspecialchars($row['landlord_name'] ?? '') ?>"
                                data-tenant="<?= htmlspecialchars($row['tenant_name'] ?? '') ?>"
                                data-contract-start="<?= htmlspecialchars($row['contract_start'] ?? '') ?>"
                                data-contract-end="<?= htmlspecialchars($row['contract_end'] ?? '') ?>"
                                data-rooms="<?= htmlspecialchars($row['room_count'] ?? '') ?>"
                                data-bathrooms="<?= htmlspecialchars($row['bathroom_count'] ?? '') ?>"
                                data-epc="<?= htmlspecialchars($row['epc_rating'] ?? '') ?>"
                                data-pets="<?= isset($row['allowed_pets']) ? $row['allowed_pets'] : '' ?>"
                                data-garden="<?= isset($row['has_garden']) ? $row['has_garden'] : '' ?>"
                                data-council="<?= htmlspecialchars($row['council_band'] ?? '') ?>"
                                data-gas="<?= htmlspecialchars($row['gas_cert_due'] ?? '') ?>"
                                data-electric="<?= htmlspecialchars($row['electric_cert_due'] ?? '') ?>"
                                data-epc-date="<?= htmlspecialchars($row['epc_due_date'] ?? '') ?>"
                                data-landlord-id-expiry="<?= htmlspecialchars($row['landlord_id_expiry'] ?? '') ?>"
                                data-tenant-id="<?= htmlspecialchars($row['tenant_id'] ?? '') ?>"
                                data-tenant-id-expiry="<?= htmlspecialchars($row['tenant_id_expiry'] ?? '') ?>"
                                data-contract="<?= htmlspecialchars($row['contract_end'] ?? '') ?>">
                                <td class="property-name"><?= htmlspecialchars($row['property_address'] ?? 'N/A') ?>
                                </td>
                                <td class="gas-date">
                                    <?= htmlspecialchars(formatDateValue($row['gas_cert_due'] ?? null)) ?></td>
                                <td class="electric-date">
                                    <?= htmlspecialchars(formatDateValue($row['electric_cert_due'] ?? null)) ?></td>
                                <td class="epc-date">
                                    <?= htmlspecialchars(formatDateValue($row['epc_due_date'] ?? null)) ?></td>
                                <td class="landlord-id-ver">
                                    <?= htmlspecialchars(formatDateValue($row['landlord_id_expiry'] ?? null)) ?></td>
                                <td class="tenant-id-ver">
                                    <?= htmlspecialchars(formatDateValue($row['tenant_id_expiry'] ?? null)) ?></td>
                                <td class="contract-enddate">
                                    <?= htmlspecialchars(formatDateValue($row['contract_end'] ?? null)) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" title="View"
                                            onclick="actionbtn.viewImportantDate(this)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                                        <button class="action-btn action-btn-edit" title="Edit"
                                            onclick="actionbtn.editImportantDate(this)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- <?php if ($_SESSION['role'] === 'admin'): ?> <form method="POST"
                                            style="display:inline;">
                                            <input type="hidden" name="archive_id" value="<?= $row['property_id'] ?>">
                                            <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                                onclick="return confirm('Archive this property?\n<?= htmlspecialchars($row['property_address']) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                            </form>
                                            <?php endif; ?> -->
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    </main>

    <div id="modals-container"></div>


    <!-- ============================================ -->
    <!-- Action Buttons: View Property Important Dates -->
    <!-- ============================================ -->
    <div id="viewImportantDatesModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <!-- Header -->
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-house"></i>
                    </div>
                    <h2 id="propertyTitle">-</h2>
                </div>
                <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="dates">
                    <i class="fa-solid fa-calendar"></i> Important Dates
                </button>
                <button class="tab-btn" data-tab="property">
                    <i class="fa-solid fa-house"></i> Property Details
                </button>
                <button class="tab-btn" data-tab="notes">
                    <i class="fa-solid fa-sticky-note"></i> Notes
                </button>
            </div>

            <!-- Tab Content -->
            <div class="action-tabs-content">
                <!-- Important Dates Tab -->
                <div id="i-tab-dates" class="tab-content active">
                    <div class="tab-header">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Important Dates</span>
                    </div>
                    <div class="dates-list">
                        <div class="date-item">
                            <span class="date-label">Landlord's ID Expiry:</span>
                            <span id="i-date-landlord-id" class="date-value">-</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">Tenant's ID Expiry:</span>
                            <span id="i-date-tenant-id" class="date-value">-</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label ">Contract End:</span>
                            <span id="i-date-contract-end" class="date-value">-</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">Gas Certificate Due Date:</span>
                            <span id="i-date-gas" class="date-value">-</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">Electric Certificate Due Date:</span>
                            <span id="i-date-electric" class="date-value">-</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">EPC Due Date:</span>
                            <span id="i-date-epc" class="date-value">-</span>
                        </div>
                    </div>
                </div>
                <!-- Property Tab -->
                <div id="i-tab-property" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-house"></i>
                        <span>Property Details</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Landlord Name:</span>
                            <span id="i-property-landlord" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Type:</span>
                            <span id="i-property-type" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Status:</span>
                            <span id="i-property-status" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Price:</span>
                            <span id="i-property-price" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Tenant Name:</span>
                            <span id="i-property-tenant" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Number of Rooms:</span>
                            <span id="i-property-rooms" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Number of Bathrooms:</span>
                            <span id="i-property-bathrooms" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">EPC Rating:</span>
                            <span id="i-property-epc" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pets Allowed:</span>
                            <span id="i-property-pets" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Garden:</span>
                            <span id="i-property-garden" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Council Band:</span>
                            <span id="i-property-council" class="detail-value">-</span>
                        </div>
                    </div>
                </div>


                <!-- Notes Tab -->
                <div id="i-tab-notes" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-sticky-note"></i>
                        <span>Notes</span>
                    </div>
                    <div class="notes-input-section">
                        <textarea id="i-noteTextarea" class="note-textarea"
                            placeholder="Write your note here..."></textarea>
                        <button id="i-addNoteBtn" class="add-note-btn" onclick="window.actionbtn.addNote()">
                            <i class="fa-solid fa-plus"></i> Add Note
                        </button>
                    </div>
                    <div id="i-notesList" class="notes-list" data-notes-list></div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
    function toggleFilter() {
        const filterToggle = document.getElementById('filterToggle');
        const filterDropdowns = document.getElementById('filterDropdowns');
        filterDropdowns.style.display = filterDropdowns.style.display === 'none' ? 'flex' : 'none';
        filterToggle.classList.toggle('active');
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
                case 'property':
                    aVal = a.dataset.property || '';
                    bVal = b.dataset.property || '';
                    break;
                case 'gas':
                    aVal = a.dataset.gas || '';
                    bVal = b.dataset.gas || '';
                    break;
                case 'electric':
                    aVal = a.dataset.electric || '';
                    bVal = b.dataset.electric || '';
                    break;
                case 'epc':
                    aVal = a.dataset.epcDate || '';
                    bVal = b.dataset.epcDate || '';
                    break;

                case 'idlandlord':
                    aVal = a.dataset.landlordIdExpiry || '';
                    bVal = b.dataset.landlordIdExpiry || '';
                    break;

                case 'idtenant':
                    aVal = a.dataset.tenantIdExpiry || '';
                    bVal = b.dataset.tenantIdExpiry || '';
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

    function filterImportantDates() {
        const propertyFilter = document.getElementById('filterProperty').value.toLowerCase();
        const rows = document.querySelectorAll('#tableBody tr');

        rows.forEach(row => {
            let show = true;

            if (propertyFilter && !row.dataset.property.includes(propertyFilter)) {
                show = false;
            }

            row.style.display = show ? '' : 'none';
        });
    }

    function resetFilters() {
        document.getElementById('filterProperty').value = '';

        const rows = document.querySelectorAll('#tableBody tr');
        rows.forEach(row => row.style.display = '');

        document.querySelectorAll('table th').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) icon.classList.remove('asc', 'desc');
        });
    }

    function showImportantDatesInfo() {
        alert('Important dates are managed through Property, Landlord, and Tenant records.');
    }
    </script>

</body>

</html>
