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
        user_id, username, name, email, phone, role, password,
        password_reset_date, id_number, id_expiry_date, archived, date_archived
    FROM users
    WHERE archived = 0
    ORDER BY name
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle password reset
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
    <script src="actionbtn.js"></script>
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

        <?php if (isset($_GET['error']) && $_GET['error'] === 'duplicate'): ?>
        <div class="flash-error" style="
            background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;
            border-radius:8px; padding:12px 20px; margin-bottom:16px;
            display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-circle-xmark"></i>
            A user with that username, email, phone number, or ID number already exists.
        </div>
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
                    ?>
                    <div class="card"
                        data-userid="<?= $user['user_id'] ?>"
                        data-username="<?= htmlspecialchars($user['username']) ?>"
                        data-name="<?= htmlspecialchars($user['name']) ?>"
                        data-phone="<?= htmlspecialchars($user['phone']) ?>"
                        data-email="<?= htmlspecialchars($user['email']) ?>"
                        data-password="<?= htmlspecialchars($user['password']) ?>"
                        data-passwordresetdate="<?= htmlspecialchars($user['password_reset_date']) ?>"
                        data-idnumber="<?= htmlspecialchars($user['id_number']) ?>"
                        data-idexpirydate="<?= htmlspecialchars($user['id_expiry_date']) ?>"
                        data-role="<?= htmlspecialchars($user['role']) ?>"
                        data-archived="<?= htmlspecialchars($user['archived']) ?>"
                        data-datearchived="<?= htmlspecialchars($user['date_archived']) ?>">

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
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="reset_password_id" value="<?= $user['user_id'] ?>">
                                <button type="submit" class="reset-password-link" style="border:none;background:none;color:#007bff;cursor:pointer;text-decoration:underline;"
                                    onclick="return confirm('Reset password for <?= htmlspecialchars($user['name']) ?>?')">
                                    Reset Password
                                </button>
                            </form>
                        </div>

                        <div class="action-buttons">
                            <button class="action-btn action-btn-view" title="View" onclick="actionbtn.viewUser(this)">
                                <i class="fa-solid fa-eye"></i>
                            </button>

                            <?php if (in_array($_SESSION['role'], ['admin', 'agent'])): ?>
                            <button class="action-btn action-btn-edit" title="Edit" onclick="actionbtn.editUser(this)">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php endif; ?>

                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="archive_id" value="<?= $user['user_id'] ?>">
                                <button type="submit" class="action-btn action-btn-delete" title="Archive"
                                    onclick="openArchiveModal(
                                    <?= (int)$user['user_id'] ?>,
                                    '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>'
                                ); return false;">
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

    <?php // Action Buttons: View User ?>
    <div id="viewUserModal" class="action-modal-overlay">
        <div class="action-modal-container">
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <h2 id="username">User Name</h2>
                </div>

                <button class="modal-close-btn" onclick="actionbtn.closeModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="action-tabs-nav">
                <button class="tab-btn active" data-tab="user">
                    <i class="fa-solid fa-user"></i> User Details
                </button>
                <button class="tab-btn" data-tab="files">
                    <i class="fa-solid fa-folder"></i> Files
                </button>
            </div>

            <div class="action-tabs-content">
                <div id="u-tab-user" class="tab-content active">
                    <div class="tab-header">
                        <i class="fa-solid fa-user"></i>
                        <span>User Details</span>
                    </div>

                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">Username:</span>
                            <span id="u-user-username" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span id="u-user-name" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone Number:</span>
                            <span id="u-user-phone" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span id="u-user-email" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Role:</span>
                            <span id="u-user-role" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Archived:</span>
                            <span id="u-user-archived" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date Archived:</span>
                            <span id="u-user-datearchived" class="detail-value">-</span>
                        </div>
                    </div>
                </div>

                <div id="u-tab-files" class="tab-content">
                    <div class="tab-header">
                        <i class="fa-solid fa-folder"></i>
                        <span>Files</span>
                    </div>

                    <div class="details-grid">
                        <div class="detail-row">
                            <span class="detail-label">ID Number:</span>
                            <span id="u-user-IdNumber" class="detail-value">-</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">ID Expiry Date:</span>
                            <span id="u-user-Idexpirydate" class="detail-value">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="archiveModal" class="action-modal-overlay">
        <div class="action-modal-container" style="max-width: 480px;">
            <div class="action-modal-header">
                <div class="action-header-left">
                    <div class="action-modal-header-icon">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <h3>Archive User</h3>
                </div>
                <button class="modal-close-btn" onclick="closeArchiveModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="delete-body">
                <p class="p-text">Are you sure you want to archive this user?</p>
                <p id="archiveUserName" class="delete-entity"></p>

                <div class="modal-footer">
                    <button class="modal-btn modal-btn-delete" onclick="confirmArchive()">
                        Yes, Archive
                    </button>
                </div>

                <form id="archiveForm" method="POST" style="display:none;">
                    <input type="hidden" name="archive_id" id="archive_id">
                </form>
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
        document.querySelectorAll('.card').forEach(card => {
            card.style.display = (!roleFilter || card.dataset.role === roleFilter) ? '' : 'none';
        });
    }

    function sortUsers() {
        const sortValue = document.getElementById('sortBy').value;
        const [column, direction] = sortValue.split('-');
        const cards = Array.from(document.querySelectorAll('.card'));

        cards.sort((a, b) => {
            let aVal = column === 'name' ? a.dataset.name.toLowerCase() : a.dataset.role.toLowerCase();
            let bVal = column === 'name' ? b.dataset.name.toLowerCase() : b.dataset.role.toLowerCase();
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
        document.querySelectorAll('.card').forEach(card => card.style.display = '');
        sortUsers();
    }

    let selectedUserId = null;

    function openArchiveModal(id, userName) {
        selectedUserId = id;
        document.getElementById('archiveUserName').innerText = userName;
        document.getElementById('archiveModal').style.display = 'flex';
    }

    function closeArchiveModal() {
        document.getElementById('archiveModal').style.display = 'none';
        selectedUserId = null;
    }

    function confirmArchive() {
        document.getElementById('archive_id').value = selectedUserId;
        document.getElementById('archiveForm').submit();
    }
    </script>
</body>

</html>