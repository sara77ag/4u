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
    $stmt = $pdo->prepare("UPDATE transactions SET archived = 1, date_archived = date('now') WHERE transaction_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: payments.php");
    exit();
}

   $stmt = $pdo->query("
    SELECT
        tr.transaction_id,
        tr.tenant_id,
        tr.property_id,
        tr.transaction_amount,
        tr.date_of_transaction,
        tr.payment_period,
        tr.next_due,
        tr.status AS transaction_status,
        t.name AS tenant_name,
        p.property_address
    FROM transactions tr
    LEFT JOIN tenants t ON tr.tenant_id = t.tenant_id
    LEFT JOIN properties p ON tr.property_id = p.property_id
    WHERE tr.archived = 0
  ");

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDateTimeValue($value) {
    if (!$value) return '';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $value;
}

function formatDateValue($value) {
    if (!$value) return '';
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js" defer></script>
    <script src="modals.js" defer></script>
    <script src="actionbtn.js" defer></script>
    <style>
    .col-tenant {
        width: 15%;
    }

    .col-property {
        width: 21%;
    }

    .col-amount {
        width: 8%;
    }

    .col-datetime {
        width: 13%;
    }

    .col-period {
        width: 12%;
    }

    .col-nextdue {
        width: 10%;
    }

    .col-status {
        width: 10%;
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
        <h1 class="page-title">Payments</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Status</label>
                        <select id="filterStatus" onchange="filterPayments()">
                            <option value="">All Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="due">Due</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date</label>
                        <input type="date" id="filterDate" onchange="filterPayments()">
                    </div>

                    <div class="filter-group">
                        <label>Period</label>
                        <input type="month" id="filterPeriod" onchange="filterPayments()">
                    </div>

                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                <button class="btn-add blue" onclick="openAddPaymentModal()">
                    <i class="fa-solid fa-plus"></i> Payment
                </button>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <table id="paymentsTable">
                        <thead>
                            <tr>

                                <th class="sortable col-tenant" onclick="sortTable('tenant', this)">
                                    Tenant <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-property" onclick="sortTable('property', this)">
                                    Property <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-amount" onclick="sortTable('amount', this)">
                                    Amount <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-datetime" onclick="sortTable('datetime', this)">
                                    Date/Time <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-period" onclick="sortTable('period', this)">
                                    Period <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-nextdue" onclick="sortTable('nextdue', this)">
                                    Next Due <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable col-status" onclick="sortTable('status', this)">
                                    Status <i class="fa-solid fa-sort sort-icon"></i>
                                </th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($payments as $p):
    $dbStatus = strtolower(trim($p['transaction_status'] ?? ''));
    $status = ($dbStatus === 'completed') ? 'paid' : 'due';

    $statusLabel = 'Due';
    $statusClass = 'status-due';
    $statusIcon = 'fa-clock';

    if ($status === 'paid') {
        $statusLabel = 'Paid';
        $statusClass = 'status-paid';
        $statusIcon = 'fa-circle-check';
    }

    $dateTimeDisplay = formatDateTimeValue($p['date_of_transaction']);
    $nextDueDisplay = formatDateValue($p['next_due']);
?>
    <tr
    data-id="<?= (int)$p['transaction_id'] ?>"
    data-tenant-id="<?= (int)$p['tenant_id'] ?>"
    data-property-id="<?= (int)$p['property_id'] ?>"
    data-tenant="<?= htmlspecialchars($p['tenant_name'] ?? '') ?>"
    data-property="<?= htmlspecialchars($p['property_address'] ?? '') ?>"
    data-amount="<?= (float)$p['transaction_amount'] ?>"
    data-datetime="<?= htmlspecialchars($p['date_of_transaction'] ?? '') ?>"
    data-period="<?= htmlspecialchars($p['payment_period'] ?? '') ?>"
    data-nextdue="<?= htmlspecialchars($p['next_due'] ?? '') ?>"
    data-status="<?= htmlspecialchars($status) ?>"
    data-db-status="<?= htmlspecialchars($dbStatus) ?>"
   >

        <td class="tenant-name"><?= htmlspecialchars($p['tenant_name'] ?? '-') ?></td>
        <td class="property-address"><?= htmlspecialchars($p['property_address'] ?? '-') ?></td>
        <td class="rent-amount">£<?= number_format((float)$p['transaction_amount'], 0) ?></td>
        <td class="payment-datetime"><?= htmlspecialchars($dateTimeDisplay) ?></td>
        <td class="payment-period"><?= htmlspecialchars($p['payment_period'] ?? '-') ?></td>
        <td class="payment-nextdue"><?= htmlspecialchars($nextDueDisplay) ?></td>
        <td>
            <span class="status-badge <?= $statusClass ?>">
                <i class="fa-solid <?= $statusIcon ?>"></i><?= $statusLabel ?>
            </span>
        </td>
        <td>
            <div class="action-buttons">
                <button class="action-btn action-btn-view" title="View" onclick="actionbtn.viewPayment(this)">
                    <i class="fa-solid fa-eye"></i>
                </button>
                
                <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                    <button class="action-btn action-btn-edit" title="Edit" onclick="actionbtn.editPayment(this)">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="archive_id" value="<?= (int)$p['transaction_id'] ?>">
                    <button
                        type="submit"
                        class="action-btn action-btn-delete"
                        title="Archive"
                        onclick="return confirm('Archive this payment?\nPayment ID: <?= (int)$p['transaction_id'] ?>')"
                    >
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


    <div id="viewPaymentModal" class="action-modal-overlay">
    <div class="action-modal-container">
        <div class="action-modal-header">
            <div class="action-header-left">
                <div class="action-modal-header-icon">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <h2 id="paymentTitle">Payment</h2>
            </div>
            <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="action-tabs-nav">
            <button class="tab-btn active" data-tab="payment">
                <i class="fa-solid fa-credit-card"></i> Payment Information
            </button>
            <button class="tab-btn" data-tab="records">
                <i class="fa-solid fa-receipt"></i> Payment Records
            </button>
            <button class="tab-btn" data-tab="files">
                <i class="fa-solid fa-folder"></i> Files
            </button>
            <button class="tab-btn" data-tab="notes">
                <i class="fa-solid fa-sticky-note"></i> Notes
            </button>
        </div>

        <div class="action-tabs-content">
            <!-- Payment Information -->
            <div id="pay-tab-payment" class="tab-content active">
                <div class="tab-header">
                    <i class="fa-solid fa-credit-card"></i>
                    <span>Payment Information</span>
                </div>

                <div class="details-grid">
                    
                    <div class="detail-row">
                        <span class="detail-label">Tenant Name:</span>
                        <span id="pay-payment-tenant" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Property:</span>
                        <span id="pay-payment-property" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount:</span>
                        <span id="pay-payment-amount" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date/Time:</span>
                        <span id="pay-payment-datetime" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Period:</span>
                        <span id="pay-payment-period" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Next Due:</span>
                        <span id="pay-payment-nextdue" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span id="pay-payment-status" class="detail-value">-</span>
                    </div>
                </div>
            </div>

            <!-- Payment Records -->
            <div id="pay-tab-records" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Payment Records</span>
                </div>

                <div class="contant-row-pay">
                    <div class="data-row header">
                        <div class="cell">Amount</div>
                        <div class="cell">Period</div>
                        <div class="cell">Date/Time</div>
                        <div class="cell">Status</div>
                    </div>
                </div>
            </div>

            <!-- Files -->
            <div id="pay-tab-files" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-folder"></i>
                    <span>Files</span>
                </div>

                <div class="files-list">
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Payment Receipt</span>
                        <button class="file-open-btn" onclick="openFile('payment receipt')">Open</button>
                    </div>
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Invoice</span>
                        <button class="file-open-btn" onclick="openFile('invoice')">Open</button>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div id="pay-tab-notes" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-sticky-note"></i>
                    <span>Notes</span>
                </div>

                <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                <div class="notes-input-section">
                    <textarea id="pay-noteTextarea" class="note-textarea" placeholder="Write your note here..."></textarea>
                    <button id="pay-addNoteBtn" class="add-note-btn" onclick="actionbtn.addNote()">
                        <i class="fa-solid fa-plus"></i> Add Note
                    </button>
                </div>
                <?php endif; ?>

                <div data-notes-list class="notes-list"></div>
            </div>
        </div>
    </div>
</div>
        

    <script>
        const months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
        let currentSort = { column: null, direction: 'asc' };

    function parseDateTimeValue(value) {
        if (!value) return 0;
        const normalised = value.replace(' ', 'T');
        const timestamp = new Date(normalised).getTime();
        return isNaN(timestamp) ? 0 : timestamp;
    }

    function parsePeriodValue(value) {
        if (!value) return 0;

        const monthMap = {
            jan: 0,
            january: 0,
            feb: 1,
            february: 1,
            mar: 2,
            march: 2,
            apr: 3,
            april: 3,
            may: 4,
            jun: 5,
            june: 5,
            jul: 6,
            july: 6,
            aug: 7,
            august: 7,
            sep: 8,
            sept: 8,
            september: 8,
            oct: 9,
            october: 9,
            nov: 10,
            november: 10,
            dec: 11,
            december: 11
        };

        const parts = value.trim().split(/\s+/);
        if (parts.length < 2) return 0;

        const month = monthMap[parts[0].toLowerCase()];
        const year = parseInt(parts[1], 10);

        if (month === undefined || isNaN(year)) return 0;

        return new Date(year, month, 1).getTime();
    }


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

        rows.sort((a, b) => {
            let aVal, bVal;

            switch (column) {

                case 'tenant':
                    aVal = (a.dataset.tenant || '').toLowerCase();
                    bVal = (b.dataset.tenant || '').toLowerCase();
                    break;
                case 'property':
                    aVal = (a.dataset.property || '').toLowerCase();
                    bVal = (b.dataset.property || '').toLowerCase();
                    break;
                case 'amount':
                    aVal = parseFloat(a.dataset.amount) || 0;
                    bVal = parseFloat(b.dataset.amount) || 0;
                    break;
                case 'datetime':
                    aVal = parseDateTimeValue(a.dataset.datetime || '');
                    bVal = parseDateTimeValue(b.dataset.datetime || '');
                    break;
                case 'period':
                    aVal = parsePeriodValue(a.dataset.period || '');
                    bVal = parsePeriodValue(b.dataset.period || '');
                    break;
                case 'nextdue':
                    aVal = a.dataset.nextdue || '';
                    bVal = b.dataset.nextdue || '';
                    break;
                case 'status':
                    aVal = (a.dataset.status || '').toLowerCase();
                    bVal = (b.dataset.status || '').toLowerCase();
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
        function filterPayments() {
    const statusFilter = document.getElementById('filterStatus').value;
    const dateTimeFilter = document.getElementById('filterDateTime').value;
    const periodFilter = document.getElementById('filterPeriod').value;
    const rows = document.querySelectorAll('#tableBody tr');

        rows.forEach(row => {
            let show = true;

            if (statusFilter && row.dataset.status !== statusFilter) {
                show = false;
            }

        if (dateTimeFilter) {
            const rowDateTime = parseDateTimeValue(row.dataset.datetime || '');
            const selectedDateTime = new Date(dateTimeFilter).getTime();

            if (!rowDateTime || rowDateTime < selectedDateTime) {
                show = false;
            }
        }

        if (periodFilter) {
            const [year, month] = periodFilter.split('-');
            const formattedFilter = months[parseInt(month, 10) - 1] + " " + year;

                if ((row.dataset.period || '').trim() !== formattedFilter) {
                    show = false;
                }
            }

            row.style.display = show ? '' : 'none';
        });
    }

    function resetFilters() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDate').value = '';
        document.getElementById('filterPeriod').value = '';

        document.querySelectorAll('#tableBody tr').forEach(row => row.style.display = '');

        document.querySelectorAll('table th').forEach(th => {
            th.classList.remove('sorted');
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.classList.remove('asc', 'desc');
            }
        });

    currentSort = { column: null, direction: 'asc' };
    updateAllStatuses();
        }
        
        function updateAllStatuses() {
            const rows = document.querySelectorAll('#tableBody tr');
            const today = new Date();
            today.setHours(0, 0, 0, 0);

        rows.forEach(row => {
            const statusCell = row.querySelector('.status-badge');
            if (!statusCell) return;

            if (row.dataset.status === "paid") {
                statusCell.innerHTML = `<i class="fa-solid fa-circle-check"></i>Paid`;
                statusCell.className = "status-badge status-paid";
                return;
            }

            const dueText = row.dataset.nextdue;
            if (!dueText) return;

            const dueDate = new Date(dueText);
            dueDate.setHours(0, 0, 0, 0);

            let statusKey = "due";
            let statusText = "Due";
            let icon = "fa-clock";

            if (dueDate < today) {
                statusKey = "overdue";
                statusText = "Overdue";
                icon = "fa-triangle-exclamation";
            }

            statusCell.innerHTML = `<i class="fa-solid ${icon}"></i>${statusText}`;
            statusCell.className = `status-badge status-${statusKey}`;
            row.dataset.status = statusKey;
        });
    }

        function viewPayment(btn) {
            const row = btn.closest('tr');
            const id = row.dataset.id;
            alert('View Payment: ' + id);
        }

        function editPayment(btn) {
            const row = btn.closest('tr');
            const id = row.dataset.id;
            alert('Edit Payment: ' + id);
        }

        window.addEventListener('load', () => {
            updateAllStatuses();
        });
    </script>

</body>

</html>