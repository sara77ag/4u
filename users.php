<?php
session_start();
if (!isset($_SESSION['user_id'])) {
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
    $stmt = $pdo->prepare("UPDATE users SET archived = 1, date_archived = date('now') WHERE user_id = ?");
    $stmt->execute([$_POST['archive_id']]);
    header("Location: users.php");
    exit();
}

// Fetch all users
$stmt = $pdo->query("
    SELECT 
        user_id,
        username,
        name,
        email,
        phone,
        role,
        password_reset_date
    FROM users
    WHERE archived = 0
    ORDER BY name
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle password reset (admin resets for a user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_id'])) {

    $stmt = $pdo->prepare("SELECT username, name, email, password_reset_date FROM users WHERE user_id = ?");
    $stmt->execute([$_POST['reset_password_id']]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($targetUser) {
        $newPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_date = date('now') WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $_POST['reset_password_id']]);

        require_once 'mailer.php';
        $sent = sendPasswordResetEmail($targetUser['email'], $targetUser['name'], $targetUser['username'], $newPassword);

        if ($sent) {
            $_SESSION['flash_success'] = "New password sent to: " . $targetUser['email'];
        } else {
            $_SESSION['flash_error'] = "Password reset but failed to send email to: " . $targetUser['email'];
        }
    }

    header("Location: users.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="tables.css">
    <script src="navbar.js"></script>
    <script src="modals.js"></script>
</head>

<body onload="CallNavbar(); loadModals();">
    <div id="navbar-container"></div>

    <main class="main-content">
        <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="flash-success" style="
        background:#d4edda; color:#155724; border:1px solid #c3e6cb;
        border-radius:8px; padding:12px 20px; margin-bottom:16px;
        display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="flash-error" style="
        background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;
        border-radius:8px; padding:12px 20px; margin-bottom:16px;
        display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-xmark"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
        <h1 class="page-title">Users</h1>

        <div class="main-container">
            <div class="controls-row">
                <button class="btn-filter-toggle" id="filterToggle" onclick="toggleFilter()">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>

                <div class="filter-dropdowns" id="filterDropdowns" style="display: none;">
                    <div class="filter-group">
                        <label>Role</label>
                        <select id="filterRole" onchange="filterUsers()">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="agent">Agent</option>
                            <option value="read_only">Read Only</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sort By</label>
                        <select id="sortBy" onchange="sortUsers()">
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                            <option value="role-asc">Role (A-Z)</option>
                            <option value="role-desc">Role (Z-A)</option>
                        </select>
                    </div>
                    <button class="filter-btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>

                <div class="controls-spacer"></div>

                <button class="btn-add blue" onclick="openAddUserModal()">
                    <i class="fa-solid fa-plus"></i> User
                </button>
            </div>

            <div class="cards-container">
                <div class="cards-grid" id="usersGrid">
                    <?php foreach ($users as $user):
                        $roleDisplay = ucfirst(str_replace('_', ' ', $user['role']));
                        $daysRemainingText = '';
                        $expired = false;

                        if (!empty($user['password_reset_date'])) {
                            $lastReset = new DateTime($user['password_reset_date']);
                            $today = new DateTime();

                            $diffDays = $today->diff($lastReset)->days;

                            if ($diffDays >= 30) {
                                $expired = true;
                                $daysRemainingText = "Expired!";
                            } else {
                                $remaining = 30 - $diffDays;
                                $daysRemainingText = "$remaining days left";
                            }
                        } else {
                            $daysRemainingText = "Never set";
                        }
                        $color = '#7b8288';

                        if ($expired) {
                            $color = '#dc3545';
                        } elseif ($remaining <= 5) {
                            $color = '#fd7e14';
                        }
                    ?>


                    <div class="card" data-name="<?= strtolower($user['name']) ?>"
                        data-role="<?= htmlspecialchars($user['role']) ?>">
                        <div class="card-header">
                            <div class="card-name"><?= htmlspecialchars($user['name']) ?></div>
                        </div>
                        <div class="card-body">
                            <div class="card-detail">
                                <i class="fa-solid fa-user"></i>
                                <span><?= htmlspecialchars($user['username']) ?></span>
                            </div>
                            <div class="card-detail">
                                <i class="fa-solid fa-envelope"></i>
                                <span><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <div class="card-detail">
                                <i class="fa-solid fa-phone"></i>
                                <span><?= htmlspecialchars($user['phone']) ?></span>
                            </div>
                            <span class="blue-badge"><?= htmlspecialchars($roleDisplay) ?></span>

                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; width:100%;">


                                <form method="POST">
                                    <input type="hidden" name="reset_password_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" class="reset-password-link"
                                        style="margin-bottom:10px; border:none;background:none;color:#007bff;cursor:pointer;text-decoration:underline;"
                                        onclick="return confirm('Reset password for <?= htmlspecialchars($user['name']) ?>?')">
                                        Reset Password
                                    </button>
                                </form>
                                <div style="font-size:12px; color: <?= $color ?>; margin-bottom:10px;">
                                    <?= $daysRemainingText ?>
                                </div>
                            </div>

                        </div>
                        <div class="action-buttons">
                            <button class="action-btn action-btn-view" title="View" onclick="viewUser(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>

                            <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                            <button class="action-btn action-btn-edit" title="Edit" onclick="editUser(this)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>

                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="archive_id" value="<?= $user['user_id'] ?>">
                                <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                    onclick="return confirm('Archive this user?\n<?= htmlspecialchars($user['name']) ?>')">
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
    <!-- Action Buttons: View User -->
    <!-- ============================================ -->
    <div id="viewUserModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <!-- Header -->
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-house"></i>
                    </div>
                    <h2 id="username">-</h2>
                </div>
                <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="user">
                    <i class="fa-solid fa-house"></i> User Details
                </button>
            </div>

            <!-- Tab Content -->
            <div class="action-tabs-content">
                <!-- User Tab -->
                <div id="u-tab-user" class="tab-content active">
                    <div class="tab-header">
                        <i class="fa-solid fa-house"></i>
                        <span>User Details</span>
                    </div>
                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Username:</span>
                            <span id="u-users-username" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span id="u-users-name" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone Number:</span>
                            <span id="u-users-phone" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span id="u-users-email" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Password:</span>
                            <span id="u-users-password" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone Reset Date:</span>
                            <span id="u-users-passwordresetdate" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Id Number:</span>
                            <span id="u-users-IdNumber" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Id Expiry Date:</span>
                            <span id="u-users-Idexpirydate" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Role:</span>
                            <span id="u-users-role" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Archived:</span>
                            <span id="u-users-archived" class="status-badge-modal status-available">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date Archived:</span>
                            <span id="u-users-datearchived" class="status-badge-modal status-available">-</span>
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

        function filterUsers() {
            const roleFilter = document.getElementById('filterRole').value;

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                let show = true;

                if (roleFilter && card.dataset.role !== roleFilter) {
                    show = false;
                }

                card.style.display = show ? '' : 'none';
            });
        }

        function sortUsers() {
            const sortValue = document.getElementById('sortBy').value;
            const [column, direction] = sortValue.split('-');
            const cards = Array.from(document.querySelectorAll('.card'));

            cards.sort((a, b) => {
                let aVal, bVal;
                if (column === 'name') {
                    aVal = a.dataset.name.toLowerCase();
                    bVal = b.dataset.name.toLowerCase();
                } else {
                    aVal = a.dataset.role.toLowerCase();
                    bVal = b.dataset.role.toLowerCase();
                }

                if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                return 0;
            });

            const grid = document.getElementById('usersGrid');
            cards.forEach(card => grid.appendChild(card));
        }

        function resetFilters() {
            document.getElementById('filterRole').value = '';
            document.getElementById('sortBy').value = 'name-asc';

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => card.style.display = '');

            sortUsers();
        }


        function editUser(btn) {
            const card = btn.closest('.card');
            const name = card.querySelector('.card-name').textContent.trim();
            alert('Edit User: ' + name);
        }

        function deleteProperty(btn) {
            const row = btn.closest('tr');
            const id = row.querySelector('.card-name').textContent;
            if (confirm('Are you sure you want to delete ' + name + '?')) {
                row.remove();
            }
        }
        </script>
</body>

</html>