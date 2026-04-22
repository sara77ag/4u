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
        SELECT username, name, phone, email, role, password_reset_date
        FROM users
        WHERE user_id = ? AND archived = 0
        LIMIT 1
    ");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $currentUser = null;
}

$passwordResetDate = $currentUser['password_reset_date'] ?? null;

$daysRemaining = 30;
$progressPercent = 100;

if ($passwordResetDate) {
    $lastReset = new DateTime($passwordResetDate);
    $today = new DateTime();

    $interval = $lastReset->diff($today);
    $daysPassed = (int)$interval->format('%a');

    if ($today < $lastReset) {
        $daysPassed = 0;
    }

    $daysPassed = min($daysPassed, 30);

    $daysRemaining = max(30 - $daysPassed, 0);
    $progressPercent = ($daysRemaining / 30) * 100;
}

$barColor = "#38a169";
$hintClass = "";

if ($daysRemaining <= 0) {
    $barColor = "#e53e3e";
    $hintClass = "danger";
} elseif ($daysRemaining <= 5) {
    $barColor = "#ed8936";
    $hintClass = "warning";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_self_password'])) {

    $userId = $_SESSION['user_id'];

    $newPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, password_reset_date = date('now') 
        WHERE user_id = ?
    ");
    $stmt->execute([$hashedPassword, $userId]);

    require_once 'mailer.php';

    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        sendPasswordResetEmail($u['email'], $u['name'], $newPassword);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$role = $_SESSION['role'] ?? '';
?>
<!-- Header -->
<header class="main-header">
    <div class="header-left">
        <h1 class="company-name">iLet4You</h1>
    </div>
    <div class="header-center">
        <div class="search-bar" id="searchBarWrapper">
            <input type="text" placeholder="Search by name, address, email, or number" class="search-input"
                id="globalSearch">
            <button class="search-btn" id="searchBtn">
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
        <?php if ($role === 'admin'): ?>
        <li>
            <a href="archive.php">
                <i class="fa-solid fa-box-archive"></i>
                <span>Archive</span>
            </a>
        </li>
        <?php endif; ?>
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


            <div class="password-progress-wrapper">
                <div class="progress-label">
                    Password Expiry
                </div>
            </div>

            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: <?= $progressPercent ?>%; background: <?= $barColor ?>;">
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; width:100%;">

                <div class="progress-hint <?= $hintClass ?>">
                    <?php if ($daysRemaining <= 0): ?>
                    Your password has expired. Change it now.
                    <?php else: ?>
                    You have <?= $daysRemaining ?> day<?= $daysRemaining == 1 ? '' : 's' ?> remaining
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="reset_self_password" value="1">

                    <button type="submit" class="reset-password">
                        Reset Password
                    </button>
                </form>

            </div>

        </div>
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

.password-progress-wrapper {
    margin-top: 20px;
}

.progress-label {
    font-size: 13px;
    margin-bottom: 6px;
    color: #4a5568;
    font-weight: 600;
}

.progress-bar-bg {
    width: 100%;
    height: 10px;
    background: #edf2f7;
    border-radius: 20px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    width: 100%;
    background: #38a169;
    border-radius: 20px;
    transition: width 0.5s ease, background 0.3s ease;
}

.progress-hint {
    font-size: 12px;
    margin-top: 6px;
    color: #718096;
}

.progress-hint.warning {
    color: #dd6b20;
    font-weight: 600;
}

.progress-hint.danger {
    color: #e53e3e;
    font-weight: bold;
}

.reset-password {
    border: none;
    background: none;
    color: #007bff;
    cursor: pointer;
    text-decoration: underline;
    font-size: 12px;
}


.reset-password:hover {
    color: #315d93;
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