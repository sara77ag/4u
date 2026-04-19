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
    $stmt = $pdo->prepare("UPDATE landlords SET archived = 1, date_archived = date('now') WHERE landlord_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: landlords.php");
    exit();
}

// Fetch all landlords
$stmt = $pdo->query("
    SELECT 
        landlord_id,
        name,
        phone,
        email,
        address,
        number_of_properties
    FROM landlords
    WHERE archived = 0
    ORDER BY name
");

$landlords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlords - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <script src="modals.js"></script>
    <script src="actionbtn.js" ></script>
</head>
<body onload="CallNavbar(); loadModals();">
    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Landlords</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Properties</label>
                        <select id="filterProperties" onchange="filterLandlords()">
                            <option value="">All Counts</option>
                            <option value="0">0 Properties</option>
                            <option value="1-5">1-5 Properties</option>
                            <option value="6+">6+ Properties</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select id="sortBy" onchange="sortLandlords()">
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="properties-asc">Properties (Low-High)</option>
                            <option value="properties-desc">Properties (High-Low)</option>
                        </select>
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <button class="btn-add blue" onclick="openAddLandlordModal()">
                    <i class="fa-solid fa-plus"></i> Landlord
                </button>
            </div>

            <div class="cards-container">
                <div class="cards-grid" id="landlordsGrid">
<?php foreach ($landlords as $landlord): 
    $numProps = (int)$landlord['number_of_properties'];
    $propText = $numProps === 1 ? '1 Property' : $numProps . ' Properties';
?>
                    <div class="card" data-name="<?= strtolower($landlord['name']) ?>" data-properties="<?= $numProps ?>">
                        <div class="card-header">
                            <div class="card-name"><?= htmlspecialchars($landlord['name']) ?></div>
                        </div>
                        <div class="card-body">
                            <div class="card-detail">
                                <i class="fa-solid fa-phone"></i>
                                <span><?= htmlspecialchars($landlord['phone']) ?></span>
                            </div>
                            <div class="card-detail">
                                <i class="fa-solid fa-envelope"></i>
                                <span><?= htmlspecialchars($landlord['email']) ?></span>
                            </div>
                            <div class="card-detail">
                                <i class="fa-solid fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($landlord['address']) ?></span>
                            </div>
                            <div class="blue-badge"><?= htmlspecialchars($propText) ?></div>
                        </div>
                        <div class="action-buttons">
                            <button class="action-btn action-btn-view" title="View" onclick="actionbtn.viewLandlord(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                           
                            <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                                <button class="action-btn action-btn-edit" title="Edit" onclick="editLandlord(this)">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="archive_id" value="<?= $landlord['landlord_id'] ?>">
                                    <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                        onclick="return confirm('Archive this landlord?\n<?= htmlspecialchars($landlord['name']) ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?> 
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="modals-container"></div>

    
<!-- ============================================ -->
<!-- Action Buttons Modal: View Landlord -->
<!-- ============================================ -->
<div id="viewLandlordModal" class="action-modal-overlay">
    <div class="action-modal-container">
        <!-- Header -->
        <div class="action-modal-header">
            <div class="action-header-left">
                <div class="action-modal-header-icon">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <h2 id="landlordName">Landlord Name</h2>
            </div>
            <button class="action-modal-close" onclick="actionbtn.closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="action-tabs-nav">
            <button class="tab-btn active" data-tab="landlord">
                <i class="fa-solid fa-user-tie"></i> Landlord Information
            </button>
            <button class="tab-btn" data-tab="properties">
                <i class="fa-solid fa-house"></i> Owned Properties
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
            <!-- Landlord Tab -->
            <div id="l-tab-landlord" class="tab-content active">
                <div class="tab-header">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>Landlord Information</span>
                </div>
              <div class="details-grid">
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span id="l-landlord-name" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span id="l-landlord-phone" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span id="l-landlord-email" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ID Type:</span>
                        <span id="l-landlord-idtype" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ID Number:</span>
                        <span id="l-landlord-idnumber" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">ID Expiry:</span>
                        <span id="l-landlord-idexpiry" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span id="l-landlord-address" class="detail-value">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Preferred Contact Method:</span>
                        <span id="l-landlord-contact" class="detail-value">-</span>
                    </div>
                </div>
            </div>

            <!-- Properties Tab -->
            <div id="l-tab-properties" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-house"></i>
                    <span>Owned Properties</span>
                    <span class="count-prperties">3</span>
                </div>
       
            <!-- Properties List  -->
            <div class="contant-row-p">
                <!-- Header Row -->
                <div class="data-row header">
                    <div class="cell">Address</div>
                    <div class="cell">Type</div>
                    <div class="cell">Status</div>
                    <div class="cell">Tenant</div>
                    <div class="cell">Rent</div>
                </div>

                <!-- Data Row 1 -->
                <div class="data-row">
                    <div class="cell">221B Baker Street</div>
                    <div class="cell" class="td-type">Flat</div>
                    <div class="cell"><span class="status-badge status-occupied">Occupied</span></div>
                    <div class="cell">John Smith</div>
                    <div class="cell"><span class="rent">£1,200</span></div>
                </div>

                <!-- Data Row 2 -->
                <div class="data-row">
                    <div class="cell">Oxford Road 10</div>
                    <div class="cell" class="td-type">House</div>
                    <div class="cell"><span class="status-badge status-available">Vacant</span></div>
                    <div class="cell"><span class="no-tenant">-</span></div>
                    <div class="cell"><span class="rent">£950</span></div>
                </div>

                <!-- Data Row 3 -->
                <div class="data-row">
                    <div class="cell">Green Lane 5</div>
                    <div class="cell" >Flat</div>
                    <div class="cell"><span class="status-badge status-occupied">Occupied</span></div>
                    <div class="cell">Sarah Ali</div>
                    <div class="cell"><span class="rent">£800</span></div>
                </div>
         </div>
            </div>
            
            <!-- Files Tab -->
            <div id="l-tab-files" class="tab-content">
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
                        <span class="file-name">Copy of ID / Passport</span>
                        <button class="file-open-btn" onclick="openFile('gas')">Open</button>
                    </div>
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">Ownership Proof</span>
                        <button class="file-open-btn" onclick="openFile('electrical')">Open</button>
                    </div>
                </div>
            </div>

            <!-- Notes Tab -->
            <div id="l-tab-notes" class="tab-content">
                <div class="tab-header">
                    <i class="fa-solid fa-sticky-note"></i>
                    <span>Notes</span>
                </div>
                <div class="notes-input-section">
                    <textarea id="l-noteTextarea" class="note-textarea" placeholder="Write your note here..."></textarea>
                    <button id="l-addNoteBtn" class="add-note-btn" onclick="actionbtn.addNote()">
                        <i class="fa-solid fa-plus"></i> Add Note
                    </button>
                </div>
                <div id="l-note-notesList" class="notes-list">
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

        function filterLandlords() {
            const propsFilter = document.getElementById('filterProperties').value;

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                let show = true;

                if (propsFilter) {
                    const numProps = parseInt(card.dataset.properties);
                    if (propsFilter === '0' && numProps !== 0) show = false;
                    if (propsFilter === '1-5' && (numProps < 1 || numProps > 5)) show = false;
                    if (propsFilter === '6+' && numProps < 6) show = false;
                }

                card.style.display = show ? '' : 'none';
            });
        }

        function sortLandlords() {
            const sortValue = document.getElementById('sortBy').value;
            const [column, direction] = sortValue.split('-');
            const cards = Array.from(document.querySelectorAll('.card'));

            cards.sort((a, b) => {
                let aVal, bVal;
                if (column === 'name') {
                    aVal = a.dataset.name.toLowerCase();
                    bVal = b.dataset.name.toLowerCase();
                } else {
                    aVal = parseInt(a.dataset.properties);
                    bVal = parseInt(a.dataset.properties);
                }

                if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            const grid = document.getElementById('landlordsGrid');
            cards.forEach(card => grid.appendChild(card));
        }

        function resetFilters() {
            document.getElementById('filterProperties').value = '';
            document.getElementById('sortBy').value = 'name-asc';

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => card.style.display = '');
        }

        function editLandlord(btn) {
            const card = btn.closest('.card');
            const name = card.querySelector('.card-name').textContent.trim();
            alert('Edit Landlord: ' + name);
        }
    </script>
</body>
</html>