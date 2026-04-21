<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit();
}

$stmt = $pdo->prepare("
    SELECT name, username, email, phone
    FROM users
    WHERE user_id = ? AND archived = 0
");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'name' => '',
    'username' => '',
    'email' => '',
    'phone' => ''
];

$role = $_SESSION['role'] ?? '';
$roleDisplay = $role ? ucwords(str_replace('_', ' ', $role)) : 'User';
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
                <a href="#" id="viewProfileBtn"><i class="fa-regular fa-user"></i> View Profile</a>
                <?php if ($role === 'admin'): ?>
                <a href="archive.php"><i class="fa-solid fa-box-archive"></i> View Archived</a>
                <?php endif; ?>
                <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<div id="profileModal" class="profile-modal-overlay" aria-hidden="true">
    <div class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
        <div class="profile-modal-header">
            <div class="profile-modal-title-wrap">
                <div class="profile-modal-title-icon">
                    <i class="fa-regular fa-user"></i>
                </div>
                <h2 id="profileModalTitle">My Profile</h2>
            </div>
            <button type="button" class="profile-modal-close" id="closeProfileModal" aria-label="Close profile modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="profile-modal-body">
            <div class="profile-hero">
                <div class="profile-hero-avatar">
                    <i class="fa-regular fa-user"></i>
                </div>
                <span class="profile-role-badge"><?= htmlspecialchars($roleDisplay) ?></span>
            </div>
            <div class="profile-details-grid">
                <div class="profile-field">
                    <span class="profile-detail-label">Username</span>
                    <div class="profile-detail-box">
                        <i class="fa-solid fa-at"></i>
                        <span class="profile-detail-value"><?= htmlspecialchars($currentUser['username'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="profile-field">
                    <span class="profile-detail-label">Full Name</span>
                    <div class="profile-detail-box">
                        <i class="fa-solid fa-user"></i>
                        <span class="profile-detail-value"><?= htmlspecialchars($currentUser['name'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="profile-field">
                    <span class="profile-detail-label">Phone</span>
                    <div class="profile-detail-box">
                        <i class="fa-solid fa-phone"></i>
                        <span class="profile-detail-value"><?= htmlspecialchars($currentUser['phone'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="profile-field">
                    <span class="profile-detail-label">Email</span>
                    <div class="profile-detail-box">
                        <i class="fa-solid fa-envelope"></i>
                        <span class="profile-detail-value"><?= htmlspecialchars($currentUser['email'] ?: '-') ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="profile-modal-footer">
            <button type="button" class="profile-close-btn" id="profileFooterClose">
                <i class="fa-solid fa-xmark"></i>
                <span>Close</span>
            </button>
        </div>
    </div>
</div>

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
