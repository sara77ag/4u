<?php
session_start();

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

$error = "";
$resetMessage = "";
$resetError = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND archived = 0");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

     //if ($user && $password === $user['password']){  //if you are not using password hashing
    if ($user && password_verify($password, $user['password'])) { //if you are using password hashing
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['role']    = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}

// forgot password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_password'])) {
    $email = trim($_POST['reset_email']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND archived = 0");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        //random password 
        $newPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 10);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_date = date('now') WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $user['user_id']]);

        require_once 'mailer.php';
        $sent = sendPasswordResetEmail($user['email'], $user['name'], $user['username'], $newPassword);

        if ($sent) {
            $resetMessage = "A new password has been sent to: " . htmlspecialchars($email);
        } else {
            $resetError = "Failed to send email. Please contact support.";
        }
    } else {
        $resetMessage = "If this email exists, a new password has been sent.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">

</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <i class="fa-solid fa-house"></i>
            </div>

            <h2>Welcome to iLet4You</h2>

            <form method="POST">
                <div class="input-group">
                    <label for="username"><i class="fa-solid fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" placeholder="username" required>
                </div>
                <div class="input-group">
                    <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" placeholder="password" required>
                </div>
                <button type="submit" name="login" class="login-btn">LOGIN</button>
            </form>

            <button class="forgot-link" onclick="openForgotModal()">
                Forgot Password?
            </button>

            <?php if (!empty($error)): ?>
            <p class="error-msg" style="color:red; text-align:center; margin-top:10px;">
                <?= htmlspecialchars($error) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay" id="forgotOverlay">
        <div class="modal-container" style="max-width: 480px;">

            <div class="modal-header">
                <div class="modal-header-title">
                    <div class="modal-header-title-icon">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <h3>Reset Password</h3>
                </div>
                <button class="modal-close-btn" onclick="closeForgotModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body">

                <p class="p-text">Enter your registered email address. We'll send you a new password.</p>

                <form method="POST">
                    <div class="input-group">
                        <label for="reset_email">
                            <i class="fa-solid fa-envelope"></i> Email Address
                        </label>
                        <input type="email" id="reset_email" name="reset_email" placeholder="your@email.com" required>
                    </div>

                    <?php if (!empty($resetMessage)): ?>
                    <div class="reset-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= htmlspecialchars($resetMessage) ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($resetError)): ?>
                    <div class="reset-error">
                        <i class="fa-solid fa-circle-xmark"></i>
                        <?= htmlspecialchars($resetError) ?>
                    </div>
                    <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeForgotModal()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" name="forgot_password" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-paper-plane"></i> Send
                </button>
            </div>
            </form>
        </div>
    </div>

    <script>
    function openForgotModal() {
        document.getElementById('forgotOverlay').classList.add('active');
    }

    function closeForgotModal() {
        document.getElementById('forgotOverlay').classList.remove('active');
    }
    <?php if (!empty($resetMessage) || !empty($resetError)): ?>
    openForgotModal();
    <?php endif; ?>

    // Close modal when clicking outside of it
    document.getElementById('forgotOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeForgotModal();
    });
    </script>
</body>

</html>