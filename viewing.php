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
    $stmt = $pdo->prepare("UPDATE viewing_bookings SET archived = 1, date_archived = date('now') WHERE booking_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: viewing.php");
    exit();
}

// Fetch all viewings
$stmt = $pdo->query("
    SELECT 
        vb.booking_id,
        vb.viewer_name,
        vb.viewer_phone,
        vb.email,
        vb.date_time,
        vb.status,
        p.property_address,
        u.name as agent_name
    FROM viewing_bookings vb
    LEFT JOIN properties p ON vb.property_id = p.property_id
    LEFT JOIN users u ON vb.user_id = u.user_id
    WHERE vb.archived = 0
    ORDER BY vb.date_time DESC
");

$viewings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewing - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <script src="modals.js"></script>
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

    .col-property {
        width: 20%;
    }

    .col-datetime {
        width: 10%;
    }

    .col-agent {
        width: 15%;
    }

    .col-status {
        width: 5%;
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
        <h1 class="page-title">Viewing</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus" onchange="filterViewings()">
                            <option value="">All Statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="no_show">No Show</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date/Time</label>
                        <input type="datetime-local" id="filterDateTime" onchange="filterViewings()">
                    </div>
                    <div class="filter-group">
                        <label>Agent</label>
                        <input type="text" id="filterAgent" onchange="filterViewings()">
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                <button class="btn-add blue" onclick="openBookViewingModal()">
                    <i class="fa-solid fa-plus"></i> Viewing
                </button>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <table id="viewingsTable">
                        <thead>
                            <tr>
                                <th class="sortable col-name" onclick="sortTable('name', this)">
                                    Name <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-number" onclick="sortTable('number', this)">
                                    Phone Number <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-email" onclick="sortTable('email', this)">
                                    Email <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-property" onclick="sortTable('property', this)">
                                    Property <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-datetime" onclick="sortTable('datetime', this)">
                                    Date/Time <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-agent" onclick="sortTable('agent', this)">
                                    Agent <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-status" onclick="sortTable('status', this)">
                                    Status <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($viewings as $v): 
    $statusClass = 'status-' . str_replace('_', '', $v['status']);
    $statusDisplay = ucfirst(str_replace('_', ' ', $v['status']));
    $statusIcon = match($v['status']) {
        'scheduled' => 'fa-calendar',
        'completed' => 'fa-circle-check',
        'cancelled' => 'fa-circle-xmark',
        'no_show' => 'fa-triangle-exclamation',
        default => 'fa-calendar'
    };
?>
                            <tr data-name="<?= strtolower($v['viewer_name']) ?>"
                                data-status="<?= htmlspecialchars($v['status']) ?>"
                                data-datetime="<?= htmlspecialchars($v['date_time']) ?>">
                                <td class="viewer-name"><?= htmlspecialchars($v['viewer_name']) ?></td>
                                <td><?= htmlspecialchars($v['viewer_phone']) ?></td>
                                <td class="viewer-email"><?= htmlspecialchars($v['email']) ?></td>
                                <td class="viewing-property"><?= htmlspecialchars($v['property_address'] ?? 'N/A') ?>
                                </td>
                                <td class="viewing-datetime"><?= htmlspecialchars($v['date_time']) ?></td>
                                <td class="agent"><?= htmlspecialchars($v['agent_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <i class="fa-solid <?= $statusIcon ?>"></i>
                                        <?= $statusDisplay ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-notes" title="View"
                                            onclick="actionbtn.Viewingnote(this)">
                                            <i class="far fa-sticky-note"></i>
                                        </button>

                                        <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                                        <button class="action-btn action-btn-edit" title="Edit"
                                            onclick="editViewing(this)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="archive_id" value="<?= $v['booking_id'] ?>">
                                            <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                                onclick="return confirm('Archive this viewing?\n<?= htmlspecialchars($v['viewer_name']) ?>')">
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

    <!-- ============================================ -->
    <!-- Action Buttons: View Viewings -->
    <!-- ============================================ -->
    <div id="viewViewingModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <!-- Header -->
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-house"></i>
                    </div>
                    <h2 id="viewier-name">-</h2>
                </div>
                <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="viewing">
                    <i class="fa-solid fa-house"></i> Viewing Details
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
                <!-- Viewing Tab -->
                <div id="v-tab-viewing" class="tab-content active">
                    <div class="tab-header">
                        <i class="fa-solid fa-house"></i>
                        <span>Viewing Details</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Viewer Name</span>
                            <span id="v-viewing-viewer" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Viewer Phone Number</span>
                            <span id="v-viewing-phone" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Viewing Status</span>
                            <span id="v-viewing-status" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Viewer Date/Time</span>
                            <span id="v-viewing-datetime" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Staff ID</span>
                            <span id="v-viewing-userid" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Viewer Email</span>
                            <span id="v-viewing-email" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Archived</span>
                            <span id="v-viewing-archived" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date Archived</span>
                            <span id="v-viewing-datearchived" class="detail-value">-</span>
                        </div>
                    </div>
                </div>
                <!-- Property Tab -->
                <div id="v-tab-property" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-house"></i>
                        <span>Property Details</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Landlord Name:</span>
                            <span id="v-property-landlord" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Type:</span>
                            <span id="v-property-type" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Status:</span>
                            <span id="v-property-status" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Property Price:</span>
                            <span id="v-property-price" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Tenant Name:</span>
                            <span id="v-property-tenant" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Number of Rooms:</span>
                            <span id="v-property-rooms" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Number of Bathrooms:</span>
                            <span id="v-property-bathrooms" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">EPC Rating:</span>
                            <span id="v-property-epc" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Pets Allowed:</span>
                            <span id="v-property-pets" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Garden:</span>
                            <span id="v-property-garden" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Council Band:</span>
                            <span id="v-property-council" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <!-- Notes Tab -->
                <div id="v-tab-notes" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-sticky-note"></i>
                        <span>Notes</span>
                    </div>
                    <div class="notes-input-section">
                        <textarea id="v-noteTextarea" class="note-textarea"
                            placeholder="Write your note here..."></textarea>
                        <button id="v-addNoteBtn" class="add-note-btn" onclick="window.actionbtn.addNote()">
                            <i class="fa-solid fa-plus"></i> Add Note
                        </button>
                    </div>
                    <div id="v-notesList" class="notes-list">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let currentSort = {
        column: null,
        direction: 'asc'
    };

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
                case 'name':
                    aVal = a.querySelector('.viewer-name').textContent.toLowerCase();
                    bVal = b.querySelector('.viewer-name').textContent.toLowerCase();
                    break;
                case 'number':
                    aVal = a.cells[1].textContent.toLowerCase();
                    bVal = b.cells[1].textContent.toLowerCase();
                    break;
                case 'email':
                    aVal = a.querySelector('.viewer-email').textContent.toLowerCase();
                    bVal = b.querySelector('.viewer-email').textContent.toLowerCase();
                    break;
                case 'property':
                    aVal = a.querySelector('.viewing-property').textContent.toLowerCase();
                    bVal = b.querySelector('.viewing-property').textContent.toLowerCase();
                    break;
                case 'datetime':
                    aVal = new Date(a.querySelector('.viewing-datetime').textContent).getTime();
                    bVal = new Date(b.querySelector('.viewing-datetime').textContent).getTime();
                    break;
                case 'agent':
                    aVal = a.querySelector('.agent').textContent.toLowerCase();
                    bVal = b.querySelector('.agent').textContent.toLowerCase();
                    break;
                case 'status':
                    aVal = a.querySelector('.status-badge').textContent.toLowerCase();
                    bVal = b.querySelector('.status-badge').textContent.toLowerCase();
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

    function filterViewings() {
        const statusFilter = document.getElementById('filterStatus').value;
        const datetimeFilter = document.getElementById('filterDateTime').value;
        const agentFilter = document.getElementById('filterAgent').value.toLowerCase();

        const rows = document.querySelectorAll('#tableBody tr');

        rows.forEach(row => {
            let show = true;

            if (statusFilter && row.dataset.status !== statusFilter) {
                show = false;
            }

            if (datetimeFilter) {
                const viewingDate = new Date(row.dataset.datetime);
                const filterDate = new Date(datetimeFilter);
                if (viewingDate < filterDate) {
                    show = false;
                }
            }

            if (agentFilter) {
                const agentCell = row.querySelector('.agent');
                if (agentCell && !agentCell.textContent.toLowerCase().includes(agentFilter)) {
                    show = false;
                }
            }

            row.style.display = show ? '' : 'none';
        });
    }

    function resetFilters() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateTime').value = '';
        document.getElementById('filterAgent').value = '';

        const rows = document.querySelectorAll('#tableBody tr');
        rows.forEach(row => row.style.display = '');

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

    function viewViewing(btn) {
        const row = btn.closest('tr');
        const name = row.querySelector('.viewer-name').textContent;
        alert('View Viewer: ' + name);
    }

    function editViewing(btn) {
        const row = btn.closest('tr');
        const name = row.querySelector('.viewer-name').textContent;
        alert('Edit Viewer: ' + name);
    }

    // to open the view modal directly from search results
    window.addEventListener('load', () => {

        const params = new URLSearchParams(window.location.search);
        const viewingId = params.get('id');
        const shouldOpen = params.get('open');

        if (viewingId && shouldOpen) {
            const row = document.querySelector(`tr[data-viewing-id="${viewingId}"]`);

            if (row) {
                actionbtn.viewViewing({
                    closest: () => row
                });
            }
        }
    });
    </script>

</body>

</html>