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
                            <button class="action-btn action-btn-view" title="View" onclick="viewLandlord(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="action-btn action-btn-edit" title="Edit" onclick="editLandlord(this)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="archive_id" value="<?= $landlord['landlord_id'] ?>">
                                <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                    onclick="return confirm('Archive this landlord?\n<?= htmlspecialchars($landlord['name']) ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="modals-container"></div>

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

        function viewLandlord(btn) {
            const card = btn.closest('.card');
            const name = card.querySelector('.card-name').textContent.trim();
            alert('View Landlord: ' + name);
        }

        function editLandlord(btn) {
            const card = btn.closest('.card');
            const name = card.querySelector('.card-name').textContent.trim();
            alert('Edit Landlord: ' + name);
        }
    </script>
</body>
</html>