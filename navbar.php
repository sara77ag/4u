<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$dbPath = __DIR__ . '/database.db';
$currentUser = null;

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT username, name, phone, email, role
        FROM users
        WHERE user_id = ? AND archived = 0
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $currentUser = null;
}

$role = $_SESSION['role'] ?? '';
?>
<!-- Header -->
<header class="main-header">
    <div class="header-left">
        <h1 class="company-name">iLet4You</h1>
    </div>
    <div class="header-center">
        <div class="search-bar">
            <input type="text" placeholder="Search by name, address, or postcode" class="search-input">
            <button class="search-btn">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </div>
    </div>
    <div class="header-right">
        <div class="user-avatar-container">
            <div class="user-avatar">
                <i class="fa-regular fa-user"></i>
            </div>
            <div class="user-dropdown">
                <a href="#" onclick="openProfileModal(); return false;">
                    <i class="fa-regular fa-user"></i> View Profile</a>
                <?php if ($role === 'admin'): ?>
                <a href="archive.php"><i class="fa-solid fa-box-archive"></i> View Archived</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- Sidebar Navbar -->
<nav class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="properties.php">
                <i class="fa-solid fa-house"></i>
                <span>Properties</span>
            </a>
        </li>
        <li>
            <a href="landlords.php">
                <i class="fa-solid fa-user-tie"></i>
                <span>Landlords</span>
            </a>
        </li>
        <li>
            <a href="tenants.php">
                <i class="fa-solid fa-user"></i>
                <span>Tenants</span>
            </a>
        </li>
        <li>
            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>
        </li>
        <li>
            <a href="viewing.php">
                <i class="fa-solid fa-street-view"></i>
                <span>Viewing</span>
            </a>
        </li>
        <li>
            <a href="important-dates.php">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Important Dates</span>
            </a>
        </li>
        <?php if ($role === 'admin'): ?>
        <li>
            <a href="users.php">
                <i class="fa-solid fa-users"></i>
                <span>Users</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

<div id="profileModal" class="modal-overlay" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal-container" style="max-width: 480px;">

        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-regular fa-user"></i>
                </div>
                <h3>My Profile</h3>
            </div>
            <button class="modal-close-btn" onclick="closeProfileModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">

            <div style="text-align: center; margin-bottom: 24px;">
                <div style="
                    width: 72px; height: 72px; border-radius: 50%;
                    background: linear-gradient(135deg, #1a365d, #3182ce);
                    display: inline-flex; align-items: center; justify-content: center;
                    margin-bottom: 10px;
                ">
                    <i class="fa-regular fa-user" style="font-size: 28px; color: #fff;"></i>
                </div>
                <div>
                    <?php
                    $roleLabels = [
                        'admin'     => ['label' => 'Administrator', 'color' => '#1a65a5', 'bg' => '#ebf8ff'],
                        'agent'     => ['label' => 'Agent',         'color' => '#276749', 'bg' => '#f0fff4'],
                        'read_only' => ['label' => 'Read Only',     'color' => '#6b46c1', 'bg' => '#faf5ff'],
                    ];
                    $r = $roleLabels[$role] ?? ['label' => ucfirst($role), 'color' => '#a0aec0', 'bg' => '#f7fafc'];
                    ?>
                    <span style="
                        background: <?= $r['bg'] ?>; color: <?= $r['color'] ?>;
                        padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
                    ">
                        <?= htmlspecialchars($r['label']) ?>
                    </span>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Username</label>
                        <div class="profile-field">
                            <i class="fa-solid fa-at"></i>
                            <?= htmlspecialchars($currentUser['username'] ?? '—') ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <div class="profile-field">
                            <i class="fa-solid fa-user"></i>
                            <?= htmlspecialchars($currentUser['name'] ?? '—') ?>
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Phone</label>
                        <div class="profile-field">
                            <i class="fa-solid fa-phone"></i>
                            <?= htmlspecialchars($currentUser['phone'] ?? '—') ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <div class="profile-field">
                            <i class="fa-solid fa-envelope"></i>
                            <?= htmlspecialchars($currentUser['email'] ?? '—') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="modal-btn modal-btn-reset" onclick="closeProfileModal()">
                <i class="fa-solid fa-xmark"></i> Close
            </button>
        </div>

    </div>
</div>

<style>
.profile-field {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #f7fafc;
    border-radius: 8px;
    font-size: 14px;
    color: #2d3748;
    border: 2px solid #f7fafc;
    min-height: 42px;
}
.profile-field i {
    color: #3182ce;
    font-size: 13px;
    flex-shrink: 0;
}
</style>

<script>
function openProfileModal() {
    document.getElementById('profileModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeProfileModal() {
    document.getElementById('profileModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}
</script>
