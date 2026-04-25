<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$pdo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbPath = __DIR__ . '/database.db';
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('DB connection failed: ' . $e->getMessage());
    }

    // TODO: Add
    if (isset($_POST['todo_text'])) {
        $stmt = $pdo->prepare("
            INSERT INTO notes (note_text, created_at, created_by, type, property_id, landlord_id, tenant_id, payment_id, viewing_id, date_id, user_id, archived)
            VALUES (?, datetime('now'), ?, 'todo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)
        ");
        $stmt->execute([trim($_POST['todo_text']), $_SESSION['user_id']]);
        header('Location: dashboard.php');
        exit();
    }

    // TODO: Delete
    if (isset($_POST['delete_todo_id'])) {
        $stmt = $pdo->prepare("
            UPDATE notes SET archived = 1, date_archived = date('now') WHERE note_id = ? AND created_by = ?
        ");
        $stmt->execute([(int)$_POST['delete_todo_id'], $_SESSION['user_id']]);
        header('Location: dashboard.php');
        exit();
    }

    // TODO: Toggle complete
    if (isset($_POST['toggle_todo_id'])) {
        $stmt = $pdo->prepare("UPDATE notes SET completed = ? WHERE note_id = ? AND created_by = ?");
        $stmt->execute([(int)$_POST['toggle_todo_completed'], (int)$_POST['toggle_todo_id'], $_SESSION['user_id']]);
        header('Location: dashboard.php');
        exit();
    }

    // Add Property
    if (isset($_POST['property_address'])) {
        $address = trim($_POST['property_address']);

        $check = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE property_address = ?");
        $check->execute([$address]);
        if ($check->fetchColumn() > 0) {
            header("Location: properties.php?error=duplicate");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO properties (property_address, property_type, monthly_rent, landlord_id,
                                        gas_cert_due, electric_cert_due, epc_due_date, landlord_id_expiry_date,
                                        room_count, bathroom_count, epc_rating, has_garden, allowed_pets,
                                        council_band, archived)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'E', 0, 0, 'D', 0)
            ");
            $stmt->execute([
                $address,
                $_POST['property_type'],
                (float)$_POST['monthly_rent'],
                (int)$_POST['landlord_id'],
                $_POST['gas_cert_date'],
                $_POST['electric_cert_date'],
                $_POST['epc_due_date'],
                $_POST['gas_cert_date']
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                header("Location: properties.php?error=duplicate");
                exit();
            }
            throw $e;
        }

        //Save uploaded property files
        $newPropertyId = (int)$pdo->lastInsertId();

        // Ensure files table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS files (
                file_id       INTEGER PRIMARY KEY AUTOINCREMENT,
                file_name     TEXT NOT NULL,
                file_type     TEXT NOT NULL,
                file_path     TEXT NOT NULL,
                uploaded_at   TEXT NOT NULL,
                file_category TEXT NOT NULL,
                property_id   INTEGER,
                tenant_id     INTEGER,
                landlord_id   INTEGER,
                user_id       INTEGER,
                payment_id    INTEGER,
                archived      INTEGER NOT NULL DEFAULT 0
            )
        ");

        $categoryMap = [
            'gas_file'             => 'gas_cert',
            'electric_file'        => 'electric_cert',
            'epc_file'             => 'epc',
            'ownership_proof_file' => 'ownership_proof',
        ];

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        $folder      = "files/properties/$newPropertyId";
        $fullFolder  = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }

        foreach ($categoryMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
                continue; // optional — skip if not uploaded
            }
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) { continue; }

            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) { continue; }

            $stmt = $pdo->prepare("
                INSERT INTO files (file_name, file_type, file_path, uploaded_at,
                                   file_category, property_id, archived)
                VALUES (?, ?, ?, datetime('now'), ?, ?, 0)
            ");
            $stmt->execute([$filename, strtoupper($ext), $storedPath, $category, $newPropertyId]);
        }

        header('Location: properties.php');
        exit();
    }

    // Add Landlord
    if (isset($_POST['landlord_name'])) {
        $phone = trim($_POST['landlord_phone']);
        $email = trim($_POST['landlord_email']);
        $idNumber = trim($_POST['landlord_id_number']);

        $check = $pdo->prepare("SELECT COUNT(*) FROM landlords WHERE email = ? OR phone = ? OR id_number = ?");
        $check->execute([$email, $phone, $idNumber]);
        if ($check->fetchColumn() > 0) {
            header("Location: landlords.php?error=duplicate");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO landlords (name, phone, address, email, id_type, id_number,
                                       id_expiry_date, number_of_properties, archived)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
            ");
            $stmt->execute([
                trim($_POST['landlord_name']),
                $phone,
                trim($_POST['landlord_address']),
                $email,
                $_POST['landlord_id_type'],
                $idNumber,
                $_POST['landlord_id_expiry']
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                header("Location: landlords.php?error=duplicate");
                exit();
            }
            throw $e;
        }

        $newLandlordId = (int)$pdo->lastInsertId();

        //Save uploaded landlord files 
        $pdo->exec("CREATE TABLE IF NOT EXISTS files (
            file_id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_name TEXT NOT NULL, file_type TEXT NOT NULL,
            file_path TEXT NOT NULL, uploaded_at TEXT NOT NULL,
            file_category TEXT NOT NULL, property_id INTEGER,
            tenant_id INTEGER, landlord_id INTEGER, user_id INTEGER,
            payment_id INTEGER, archived INTEGER NOT NULL DEFAULT 0
        )");
        $landlordCategoryMap = ['landlord_id_file' => 'id_document'];
        $allowedExts = ['pdf','jpg','jpeg','png'];
        $folder     = "files/landlords/$newLandlordId";
        $fullFolder = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }
        foreach ($landlordCategoryMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;
            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) continue;
            $stmt = $pdo->prepare("INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category, landlord_id, archived) VALUES (?, ?, ?, datetime('now'), ?, ?, 0)");
            $stmt->execute([$filename, strtoupper($ext), $storedPath, $category, $newLandlordId]);
        }

        header('Location: landlords.php');
        exit();
    }

    // Add Tenant
    if (isset($_POST['tenant_name'])) {
        $propertyId = (int)$_POST['tenant_property_id'];
        $name = trim($_POST['tenant_name']);
        $phone = trim($_POST['tenant_phone']);
        $email = trim($_POST['tenant_email']);
        $idType = trim($_POST['tenant_id_type'] ?? 'other');
        $idNumber = trim($_POST['tenant_id_number']);
        $idExpiry = $_POST['tenant_id_expiry'];
        $contractStart = $_POST['tenant_contract_start'];
        $contractEnd = $_POST['tenant_contract_end'];
        $paymentFreq = $_POST['tenant_payment_frequency'];
        $rent = (float)$_POST['tenant_rent'];

        $check = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ? OR phone = ? OR id_number = ?");
        $check->execute([$email, $phone, $idNumber]);
        if ($check->fetchColumn() > 0) {
            header("Location: tenants.php?error=duplicate");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, phone, email, id_type, id_number, id_expiry_date, archived)
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$name, $phone, $email, $idType, $idNumber, $idExpiry]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                header("Location: tenants.php?error=duplicate");
                exit();
            }
            throw $e;
        }

        $tenantId = $pdo->lastInsertId();

        $prop = $pdo->prepare("SELECT property_id FROM properties WHERE property_id = ?");
        $prop->execute([$propertyId]);
        $propertyId = $prop->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO contracts (tenant_id, property_id, contract_start, contract_end, rent, frequency)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenantId, $propertyId, $contractStart, $contractEnd, $rent, $paymentFreq]);

        //Save uploaded tenant files
        $tenantCategoryMap = [
            'tenant_contract_file' => 'contract',
            'tenant_id_file'       => 'id_document',
        ];
        $allowedExts = ['pdf','jpg','jpeg','png'];
        $folder     = "files/tenants/$tenantId";
        $fullFolder = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }
        foreach ($tenantCategoryMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;
            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) continue;
            $stmt2 = $pdo->prepare("INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category, tenant_id, archived) VALUES (?, ?, ?, datetime('now'), ?, ?, 0)");
            $stmt2->execute([$filename, strtoupper($ext), $storedPath, $category, $tenantId]);
        }

        header('Location: tenants.php');
        exit();
    }

    // Add Viewing
    if (isset($_POST['viewer_name'])) {
        $stmt = $pdo->prepare("
            INSERT INTO viewing_bookings (property_id, viewer_name, viewer_phone, email,
                                          date_time, user_id, status, archived)
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled', 0)
        ");
        $stmt->execute([
            (int)$_POST['viewing_property_id'],
            trim($_POST['viewer_name']),
            trim($_POST['viewer_phone']),
            trim($_POST['viewer_email']),
            $_POST['viewing_date'] . ' ' . $_POST['viewing_time'],
            1
        ]);
        header('Location: viewing.php');
        exit();
    }

    // Add Payment
    if (isset($_POST['payment_tenant_id'])) {
        $tenantId = (int)$_POST['payment_tenant_id'];
        $propertyId = (int)$_POST['payment_property_id'];
        $amount = (float)$_POST['payment_amount'];
        $paymentPeriod = trim($_POST['payment_period']);
        $paymentMethod = trim($_POST['payment_method']);
        $paymentDate = $_POST['payment_date'];
        $paymentTime = $_POST['payment_time'];

        if (!$tenantId || !$propertyId || !$amount || !$paymentPeriod || !$paymentDate || !$paymentTime) {
            die('Missing required payment fields');
        }

        $dateTime = $paymentDate . ' ' . $paymentTime . ':00';
        $nextDue = date('Y-m-d', strtotime($paymentDate . ' +1 month'));

        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                tenant_id, property_id, transaction_amount, date_of_transaction,
                payment_period, next_due, status, archived
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([
            $tenantId, $propertyId, $amount, $dateTime,
            $paymentPeriod, $nextDue, 'completed'
        ]);

        $newPaymentId = (int)$pdo->lastInsertId();

        //Save uploaded payment files
        $paymentCategoryMap = [
            'payment_receipt_file' => 'payment_receipt',
            'payment_invoice_file' => 'invoice',
        ];
        $allowedExts = ['pdf','jpg','jpeg','png'];
        $folder     = "files/payments/$newPaymentId";
        $fullFolder = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }
        foreach ($paymentCategoryMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;
            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) continue;
            $stmtF = $pdo->prepare("INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category, tenant_id, property_id, payment_id, archived) VALUES (?, ?, ?, datetime('now'), ?, ?, ?, ?, 0)");
            $stmtF->execute([$filename, strtoupper($ext), $storedPath, $category, $tenantId, $propertyId, $newPaymentId]);
        }

        header('Location: payments.php');
        exit();
    }

    // Add User
    if (isset($_POST['user_username'])) {
        $username = trim($_POST['user_username']);
        $email = trim($_POST['user_email']);
        $phone = trim($_POST['user_phone']);
        $idNumber = trim($_POST['user_id_number']);

        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR phone = ? OR id_number = ?");
        $check->execute([$username, $email, $phone, $idNumber]);
        if ($check->fetchColumn() > 0) {
            header("Location: users.php?error=duplicate");
            exit();
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, name, phone, email, password, password_reset_date,
                                  id_number, id_expiry_date, role, archived)
                VALUES (?, ?, ?, ?, ?, date('now'), ?, ?, ?, 0)
            ");
            $stmt->execute([
                $username,
                trim($_POST['user_name']),
                $phone,
                $email,
                password_hash($_POST['user_password'], PASSWORD_DEFAULT),
                $idNumber,
                $_POST['user_id_expiry'],
                $_POST['user_role']
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                header("Location: users.php?error=duplicate");
                exit();
            }
            throw $e;
        }

        $newUserId = (int)$pdo->lastInsertId();

        //Save uploaded user files
        $userCategoryMap = ['user_id_file' => 'id_document'];
        $allowedExts = ['pdf','jpg','jpeg','png'];
        $folder     = "files/users/$newUserId";
        $fullFolder = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }
        foreach ($userCategoryMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;
            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) continue;
            $stmtF = $pdo->prepare("INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category, user_id, archived) VALUES (?, ?, ?, datetime('now'), ?, ?, 0)");
            $stmtF->execute([$filename, strtoupper($ext), $storedPath, $category, $newUserId]);
        }

        header('Location: users.php');
        exit();
    }

    // Add Contract
    if (isset($_POST['contract_property_id'])) {
        $propertyId = (int)$_POST['contract_property_id'];
        $tenantId = (int)$_POST['contract_tenant_id'];
        $start = $_POST['contract_start'];
        $end = $_POST['contract_end'];
        $rent = (float)$_POST['contract_rent'];
        $frequency = $_POST['contract_frequency'];

        if (!$propertyId || !$tenantId || !$start || !$end) {
            die('Missing required fields');
        }

        if ($start >= $end) {
            die('Invalid contract dates');
        }

        $check = $pdo->prepare("
    SELECT 1 FROM contracts
    WHERE property_id = ?
    AND archived = 0
    AND (
        contract_start <= ?
        AND contract_end >= ?
    )
");

$check->execute([
    $propertyId,
    $end,   // الجديد ينتهي
    $start  // الجديد يبدأ
]);

if ($check->fetch()) {
    header("Location: properties.php?contract=conflict");
    exit();
}

        // Get landlord_id from property
        $landlordStmtC = $pdo->prepare("SELECT landlord_id FROM properties WHERE property_id = ?");
        $landlordStmtC->execute([$propertyId]);
        $landlordIdForC = $landlordStmtC->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO contracts (tenant_id, property_id, landlord_id, contract_start, contract_end, rent, frequency)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenantId, $propertyId, $landlordIdForC, $start, $end, $rent, $frequency]);

        $newContractRelatedId = $propertyId; 
        
        //Save uploaded contract files 
        $_cCols = [];
        foreach ($pdo->query("PRAGMA table_info(files)")->fetchAll(PDO::FETCH_ASSOC) as $_cCol) { $_cCols[] = $_cCol['name']; }
        if (!in_array('user_id', $_cCols))    { $pdo->exec("ALTER TABLE files ADD COLUMN user_id INTEGER"); }
        if (!in_array('payment_id', $_cCols)) { $pdo->exec("ALTER TABLE files ADD COLUMN payment_id INTEGER"); }

        $contractFileMap = [
            'contract_file'        => 'contract',
        ];
        $allowedExts = ['pdf','jpg','jpeg','png'];
        $folder      = "files/properties/$propertyId";
        $fullFolder  = __DIR__ . '/' . $folder;
        if (!is_dir($fullFolder)) { mkdir($fullFolder, 0755, true); }

        foreach ($contractFileMap as $inputName => $category) {
            if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) continue;
            $file = $_FILES[$inputName];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts)) continue;
            $filename   = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $fullPath   = $fullFolder . '/' . $filename;
            $storedPath = $folder    . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) continue;
            $stmtCF = $pdo->prepare("INSERT INTO files (file_name, file_type, file_path, uploaded_at, file_category, property_id, tenant_id, landlord_id, archived) VALUES (?, ?, ?, datetime('now'), ?, ?, ?, ?, 0)");
            $stmtCF->execute([$filename, strtoupper($ext), $storedPath, $category, $propertyId, $tenantId, $landlordIdForC]);
        }

        header('Location: properties.php');
        exit();
    }

    exit();
}

$dbPath = __DIR__ . '/database.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}
?>

<?php // Modal: Add Property ?>
<div id="addPropertyModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New Property</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddPropertyModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addPropertyForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Property Address <span class="required">*</span></label>
                    <input type="text" name="property_address" class="form-control" placeholder="Enter Property Address"
                        required>
                </div>
                <div class="form-group">
                    <label>Property Type <span class="required">*</span></label>
                    <select name="property_type" class="form-control" required>
                        <option value="">Select Property Type</option>
                        <option value="Flat">Flat</option>
                        <option value="House">House</option>
                        <option value="Apartment">Apartment</option>
                        <option value="Bungalow">Bungalow</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rent Price <span class="required">*</span></label>
                    <input type="number" name="monthly_rent" class="form-control" placeholder="Enter Rent Amount"
                        required>
                </div>
                <div class="form-group">
                    <label>Landlord <span class="required">*</span></label>
                    <select name="landlord_id" class="form-control" required>
                        <option value="">Select Landlord</option>
                        <?php
                        $landlords = $pdo->query("SELECT landlord_id, name FROM landlords WHERE archived = 0")->fetchAll();
                        foreach ($landlords as $l) {
                            echo '<option value="' . $l['landlord_id'] . '">' . htmlspecialchars($l['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gas Certificate Due <span class="required">*</span></label>
                    <input type="date" name="gas_cert_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Electric Certificate Due <span class="required">*</span></label>
                    <input type="date" name="electric_cert_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>EPC Due Date <span class="required">*</span></label>
                    <input type="date" name="epc_due_date" class="form-control" required>
                </div>

                <!--Document uploads -->
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>Property Documents <span
                            style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">


                        <!-- Gas -->
                        <div class="doc-slot" id="slot-gas">
                            <input type="file" name="gas_file" id="inp-gas" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('gas', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-gas').click()">
                                <i class="fa-solid fa-fire-flame-curved doc-slot-icon" style="color:#e53e3e;"></i>
                                <span class="doc-slot-label">Gas Safety Certificate</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-gas" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-gas"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('gas')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Electrical-->
                        <div class="doc-slot" id="slot-electric">
                            <input type="file" name="electric_file" id="inp-electric" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('electric', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-electric').click()">
                                <i class="fa-solid fa-bolt doc-slot-icon" style="color:#d69e2e;"></i>
                                <span class="doc-slot-label">Electrical Certificate</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-electric" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-electric"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('electric')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- EPC -->
                        <div class="doc-slot" id="slot-epc">
                            <input type="file" name="epc_file" id="inp-epc" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('epc', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-epc').click()">
                                <i class="fa-solid fa-leaf doc-slot-icon" style="color:#38a169;"></i>
                                <span class="doc-slot-label">EPC Certificate</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-epc" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-epc"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('epc')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Ownership Proof -->
                        <div class="doc-slot" id="slot-ownership-proof">
                            <input type="file" name="ownership_proof_file" id="inp-ownership-proof"
                                class="doc-file-input" accept=".pdf,.jpg,.jpeg,.png"
                                onchange="previewDocSlot('ownership-proof', this)">
                            <div class="doc-slot-inner"
                                onclick="document.getElementById('inp-ownership-proof').click()">
                                <i class="fa-solid fa-file-shield doc-slot-icon" style="color:#805ad5;"></i>
                                <span class="doc-slot-label">Ownership Proof</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-ownership-proof" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-ownership-proof"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('ownership-proof')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddPropertyForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Add Landlord ?>
<div id="addLandlordModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New Landlord</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddLandlordModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addLandlordForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="landlord_name" class="form-control"
                        placeholder="Enter Landlord's Full Name" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="landlord_phone" class="form-control"
                        placeholder="Enter Landlord's Phone Number" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="landlord_email" class="form-control" placeholder="Enter Landlord's Email"
                        required>
                </div>
                <div class="form-group">
                    <label>Address <span class="required">*</span></label>
                    <input type="text" name="landlord_address" class="form-control"
                        placeholder="Enter Landlord's Address" required>
                </div>
                <div class="form-group">
                    <label>ID Type <span class="required">*</span></label>
                    <select name="landlord_id_type" class="form-control" required>
                        <option value="">Select ID Type</option>
                        <option value="passport">Passport</option>
                        <option value="driving_license">Driving License</option>
                        <option value="national_id">National ID</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID Number <span class="required">*</span></label>
                    <input type="text" name="landlord_id_number" class="form-control"
                        placeholder="Enter Landlord's ID Number" required>
                </div>
                <div class="form-group">
                    <label>ID Expiry Date <span class="required">*</span></label>
                    <input type="date" name="landlord_id_expiry" class="form-control" required>
                </div>
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>Landlord Documents <span
                            style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">

                        <!-- Copy of ID / Passport -->
                        <div class="doc-slot" id="slot-landlord-id">
                            <input type="file" name="landlord_id_file" id="inp-landlord-id" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('landlord-id', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-landlord-id').click()">
                                <i class="fa-solid fa-id-card doc-slot-icon" style="color:#3182ce;"></i>
                                <span class="doc-slot-label">Copy of ID / Passport</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-landlord-id" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-landlord-id"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('landlord-id')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddLandlordForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Add Tenant ?>
<div id="addTenantModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New Tenant</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddTenantModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addTenantForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Property <span class="required">*</span></label>
                    <select name="tenant_property_id" class="form-control" required>
                        <option value="">Select Property</option>
                        <?php
                        $properties = $pdo->query("SELECT property_id, property_address FROM properties WHERE archived = 0")->fetchAll();
                        foreach ($properties as $prop) {
                            echo '<option value="' . $prop['property_id'] . '">' . htmlspecialchars($prop['property_address']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="tenant_name" class="form-control" placeholder="Enter Tenant's Full Name"
                        required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="tenant_phone" class="form-control" placeholder="Enter Tenant's Phone Number"
                        required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="tenant_email" class="form-control" placeholder="Enter Tenant's Email"
                        required>
                </div>
                <div class="form-group">
                    <label>ID Type <span class="required">*</span></label>
                    <select name="tenant_id_type" class="form-control" required>
                        <option value="">Select ID Type</option>
                        <option value="passport">Passport</option>
                        <option value="driving_license">Driving License</option>
                        <option value="national_id">National ID</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID Number <span class="required">*</span></label>
                    <input type="text" name="tenant_id_number" class="form-control"
                        placeholder="Enter Tenant's ID Number" required>
                </div>
                <div class="form-group">
                    <label>ID Expiry Date <span class="required">*</span></label>
                    <input type="date" name="tenant_id_expiry" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Rent <span class="required">*</span></label>
                    <input type="number" name="tenant_rent" class="form-control" placeholder="Enter Monthly Rent Amount"
                        required>
                </div>
                <div class="form-group">
                    <label>Contract Start <span class="required">*</span></label>
                    <input type="date" name="tenant_contract_start" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Contract End <span class="required">*</span></label>
                    <input type="date" name="tenant_contract_end" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Payment Frequency <span class="required">*</span></label>
                    <select name="tenant_payment_frequency" class="form-control" required>
                        <option value="">Select Frequency</option>
                        <option value="weekly">Weekly</option>
                        <option value="fortnightly">Fortnightly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>Tenant Documents <span
                            style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">

                        <!-- Tenancy Contract -->
                        <div class="doc-slot" id="slot-tenant-contract">
                            <input type="file" name="tenant_contract_file" id="inp-tenant-contract"
                                class="doc-file-input" accept=".pdf,.jpg,.jpeg,.png"
                                onchange="previewDocSlot('tenant-contract', this)">
                            <div class="doc-slot-inner"
                                onclick="document.getElementById('inp-tenant-contract').click()">
                                <i class="fa-solid fa-file-contract doc-slot-icon"></i>
                                <span class="doc-slot-label">Tenancy Contract</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-tenant-contract" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-tenant-contract"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('tenant-contract')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Copy of ID / Passport -->
                        <div class="doc-slot" id="slot-tenant-id">
                            <input type="file" name="tenant_id_file" id="inp-tenant-id" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('tenant-id', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-tenant-id').click()">
                                <i class="fa-solid fa-id-card doc-slot-icon" style="color:#3182ce;"></i>
                                <span class="doc-slot-label">Copy of ID / Passport</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-tenant-id" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-tenant-id"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('tenant-id')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddTenantForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Book Viewing ?>
<div id="bookViewingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Book New Viewing</h3>
            </div>
            <button class="modal-close-btn" onclick="closeBookViewingModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="bookViewingForm" class="modal-body" method="POST" action="modals.php">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Property <span class="required">*</span></label>
                    <select name="viewing_property_id" class="form-control" required>
                        <option value="">Select Property</option>
                        <?php
                        foreach ($properties as $prop) {
                            echo '<option value="' . $prop['property_id'] . '">' . htmlspecialchars($prop['property_address']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="viewer_name" class="form-control" placeholder="Enter Viewer Full Name"
                        required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="viewer_phone" class="form-control" placeholder="Enter Viewer's Phone Number"
                        required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="viewer_email" class="form-control" placeholder="Enter Viewer's Email"
                        required>
                </div>
                <div class="form-group">
                    <label>Viewing Date <span class="required">*</span></label>
                    <input type="date" name="viewing_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Viewing Time <span class="required">*</span></label>
                    <input type="time" name="viewing_time" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetBookViewingForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Book
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Upload File ?>
<div id="uploadFileModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-upload"></i></div>
                <h3>Upload File</h3>
            </div>
            <button class="modal-close-btn" onclick="closeUploadFileModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="uploadFileForm" class="modal-body" method="POST" action="upload.php" enctype="multipart/form-data">
            <input type="hidden" name="redirect" value="dashboard.php">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>File <span class="required">*</span></label>
                    <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="file_category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="gas_cert">Gas Certificate</option>
                        <option value="electric_cert">Electric Certificate</option>
                        <option value="epc">EPC</option>
                        <option value="id_document">ID Document</option>
                        <option value="contract">Contract</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Link to Property</label>
                    <select name="property_id" class="form-control">
                        <option value="">None</option>
                        <?php
                        foreach ($properties as $prop) {
                            echo '<option value="' . $prop['property_id'] . '">' . htmlspecialchars($prop['property_address']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Link to Landlord</label>
                    <select name="landlord_id" class="form-control">
                        <option value="">None</option>
                        <?php
                        foreach ($landlords as $l) {
                            echo '<option value="' . $l['landlord_id'] . '">' . htmlspecialchars($l['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Link to Tenant</label>
                    <select name="tenant_id" class="form-control">
                        <option value="">None</option>
                        <?php
                        $tenants = $pdo->query("SELECT tenant_id, name FROM tenants WHERE archived = 0")->fetchAll();
                        foreach ($tenants as $t) {
                            echo '<option value="' . $t['tenant_id'] . '">' . htmlspecialchars($t['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetUploadFileForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Add Payment ?>
<div id="addPaymentModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New Payment</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddPaymentModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addPaymentForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Tenant <span class="required">*</span></label>
                    <select name="payment_tenant_id" class="form-control" required>
                        <option value="">Select Tenant</option>
                        <?php
                        foreach ($tenants as $t) {
                            echo '<option value="' . $t['tenant_id'] . '">' . htmlspecialchars($t['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Property <span class="required">*</span></label>
                    <select name="payment_property_id" class="form-control" required>
                        <option value="">Select Property</option>
                        <?php
                        foreach ($properties as $prop) {
                            echo '<option value="' . $prop['property_id'] . '">' . htmlspecialchars($prop['property_address']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" name="payment_amount" class="form-control"
                        placeholder="Enter Rent Amount Received" required>
                </div>
                <div class="form-group">
                    <label>Period Covered <span class="required">*</span></label>
                    <input type="text" name="payment_period" class="form-control" placeholder="e.g. Mar - Apr 2025"
                        required>
                </div>
                <div class="form-group">
                    <label>Payment Method <span class="required">*</span></label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">Select Payment Method</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card Payment</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Date <span class="required">*</span></label>
                    <input type="date" name="payment_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Payment Time <span class="required">*</span></label>
                    <input type="time" name="payment_time" class="form-control" required>
                </div>
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>Payment Documents <span
                            style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">

                        <!-- Payment Receipt -->
                        <div class="doc-slot" id="slot-payment-receipt">
                            <input type="file" name="payment_receipt_file" id="inp-payment-receipt"
                                class="doc-file-input" accept=".pdf,.jpg,.jpeg,.png"
                                onchange="previewDocSlot('payment-receipt', this)">
                            <div class="doc-slot-inner"
                                onclick="document.getElementById('inp-payment-receipt').click()">
                                <i class="fa-solid fa-receipt doc-slot-icon" style="color:#38a169;"></i>
                                <span class="doc-slot-label">Payment Receipt</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-payment-receipt" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-payment-receipt"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('payment-receipt')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Invoice -->
                        <div class="doc-slot" id="slot-payment-invoice">
                            <input type="file" name="payment_invoice_file" id="inp-payment-invoice"
                                class="doc-file-input" accept=".pdf,.jpg,.jpeg,.png"
                                onchange="previewDocSlot('payment-invoice', this)">
                            <div class="doc-slot-inner"
                                onclick="document.getElementById('inp-payment-invoice').click()">
                                <i class="fa-solid fa-file-invoice doc-slot-icon" style="color:#d69e2e;"></i>
                                <span class="doc-slot-label">Invoice</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-payment-invoice" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-payment-invoice"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('payment-invoice')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddPaymentForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Add Contract ?>
<div id="addContractModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New Contract</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddContractModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addContractForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Property <span class="required">*</span></label>
                    <select name="contract_property_id" class="form-control" required>
                        <option value="">Select Property</option>
                        <?php
                        foreach ($properties as $prop) {
                            echo '<option value="' . $prop['property_id'] . '">' . htmlspecialchars($prop['property_address']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Landlord <span class="required">*</span></label>
                    <select name="contract_landlord_id" class="form-control" required>
                        <option value="">Select Landlord</option>
                        <?php
                        foreach ($landlords as $l) {
                            echo '<option value="' . $l['landlord_id'] . '">' . htmlspecialchars($l['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tenant <span class="required">*</span></label>
                    <select name="contract_tenant_id" class="form-control" required>
                        <option value="">Select Tenant</option>
                        <?php
                        foreach ($tenants as $t) {
                            echo '<option value="' . $t['tenant_id'] . '">' . htmlspecialchars($t['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rent <span class="required">*</span></label>
                    <input type="number" name="contract_rent" class="form-control" placeholder="Enter Rent Amount"
                        required>
                </div>
                <div class="form-group">
                    <label>Payment Frequency <span class="required">*</span></label>
                    <select name="contract_frequency" class="form-control" required>
                        <option value="">Select Frequency</option>
                        <option value="weekly">Weekly</option>
                        <option value="fortnightly">Fortnightly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contract Start Date <span class="required">*</span></label>
                    <input type="date" name="contract_start" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Contract End Date <span class="required">*</span></label>
                    <input type="date" name="contract_end" class="form-control" required>
                </div>
                <!-- Document uploads -->
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>Contract Documents
                        <span style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">

                        <!-- Tenancy Contract -->
                        <div class="doc-slot" id="slot-contract-file">
                            <input type="file" name="contract_file" id="inp-contract-file" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('contract-file', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-contract-file').click()">
                                <i class="fa-solid fa-file-contract doc-slot-icon"></i>
                                <span class="doc-slot-label">Tenancy Contract</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-contract-file" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-contract-file"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('contract-file')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddContractForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Create
                </button>
            </div>
        </form>
    </div>
</div>

<?php // Modal: Add User ?>
<div id="addUserModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-plus"></i></div>
                <h3>Add New User</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddUserModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addUserForm" class="modal-body" method="POST" action="modals.php" enctype="multipart/form-data">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="user_username" class="form-control" placeholder="Enter Username" required>
                </div>
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="user_name" class="form-control" placeholder="Enter User's Full Name"
                        required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="user_email" class="form-control" placeholder="Enter User's Email"
                        required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="user_phone" class="form-control" placeholder="Enter Phone Number" required>
                </div>
                <div class="form-group">
                    <label>ID Number <span class="required">*</span></label>
                    <input type="text" name="user_id_number" class="form-control" placeholder="Enter ID Number"
                        required>
                </div>
                <div class="form-group">
                    <label>ID Expiry <span class="required">*</span></label>
                    <input type="date" name="user_id_expiry" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="user_role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="agent">Agent</option>
                        <option value="read_only">Read Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <span class="required">*</span></label>
                    <input type="password" name="user_password" class="form-control" placeholder="Enter Password"
                        required>
                </div>
                <div class="form-group form-group--full">
                    <label style="font-weight:600; color:#1a365d; font-size:13px; margin-bottom:10px; display:block;">
                        <i class="fa-solid fa-folder-open" style="margin-right:6px;"></i>User Documents <span
                            style="color:#a0aec0; font-weight:400;">(optional — only PDF, JPG, PNG)</span>
                    </label>
                    <div class="doc-upload-grid">

                        <!-- Copy of ID / Passport -->
                        <div class="doc-slot" id="slot-user-id">
                            <input type="file" name="user_id_file" id="inp-user-id" class="doc-file-input"
                                accept=".pdf,.jpg,.jpeg,.png" onchange="previewDocSlot('user-id', this)">
                            <div class="doc-slot-inner" onclick="document.getElementById('inp-user-id').click()">
                                <i class="fa-solid fa-id-card doc-slot-icon" style="color:#3182ce;"></i>
                                <span class="doc-slot-label">Copy of ID / Passport</span>
                                <span class="doc-slot-hint">Click to upload</span>
                            </div>
                            <div class="doc-slot-preview" id="prev-user-id" style="display:none;">
                                <i class="fa-solid fa-file-pdf"></i>
                                <span class="doc-prev-name" id="prevname-user-id"></span>
                                <button type="button" class="doc-remove-btn" onclick="clearDocSlot('user-id')">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="resetAddUserForm()">
                    <i class="fa-solid fa-xmark"></i> Reset
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </form>
    </div>
</div>