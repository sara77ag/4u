<?php
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
            VALUES (?, datetime('now'), 'system', 'todo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)
        ");
        $stmt->execute([trim($_POST['todo_text'])]);
        header('Location: dashboard.php');
        exit();
    }

    // TODO: Delete
    if (isset($_POST['delete_todo_id'])) {
        $stmt = $pdo->prepare("
            UPDATE notes SET archived = 1, date_archived = date('now') WHERE note_id = ?
        ");
        $stmt->execute([(int)$_POST['delete_todo_id']]);
        header('Location: dashboard.php');
        exit();
    }

    // Add Property
    if (isset($_POST['property_address'])) {
        $stmt = $pdo->prepare("
            INSERT INTO properties (property_address, property_type, monthly_rent, landlord_id,
                                    gas_cert_due, electric_cert_due, epc_due_date, landlord_id_expiry_date,
                                    room_count, bathroom_count, epc_rating, has_garden, allowed_pets,
                                    council_band, archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'E', 0, 0, 'D', 0)
        ");
        $stmt->execute([
            trim($_POST['property_address']),
            $_POST['property_type'],
            (float)$_POST['monthly_rent'],
            (int)$_POST['landlord_id'],
            $_POST['gas_cert_date'],
            $_POST['electric_cert_date'],
            $_POST['epc_due_date'],
            $_POST['gas_cert_date']
        ]);
        header('Location: properties.php');
        exit();
    }

    // Add Landlord
    if (isset($_POST['landlord_name'])) {
        $stmt = $pdo->prepare("
            INSERT INTO landlords (name, phone, address, email, id_type, id_number,
                                   id_expiry_date, number_of_properties, archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
        ");
        $stmt->execute([
            trim($_POST['landlord_name']),
            trim($_POST['landlord_phone']),
            trim($_POST['landlord_address']),
            trim($_POST['landlord_email']),
            $_POST['landlord_id_type'],
            trim($_POST['landlord_id_number']),
            $_POST['landlord_id_expiry']
        ]);
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

        $stmt = $pdo->prepare("
            INSERT INTO tenants (name, phone, email, id_type, id_number, id_expiry_date, archived)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$name, $phone, $email, $idType, $idNumber, $idExpiry]);
        $tenantId = $pdo->lastInsertId();

        $prop = $pdo->prepare("SELECT property_id FROM properties WHERE property_id = ?");
        $prop->execute([$propertyId]);
        $propertyId = $prop->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO contracts (tenant_id, property_id, contract_start, contract_end, rent, frequency)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenantId, $propertyId, $contractStart, $contractEnd, $rent, $paymentFreq]);

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

    // Add User
    if (isset($_POST['user_username'])) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, name, phone, email, password, password_reset_date,
                              id_number, id_expiry_date, role, archived)
            VALUES (?, ?, ?, ?, ?, date('now'), ?, ?, ?, 0)
        ");
        $stmt->execute([
            trim($_POST['user_username']),
            trim($_POST['user_name']),
            trim($_POST['user_phone']),
            trim($_POST['user_email']),
            password_hash($_POST['user_password'], PASSWORD_DEFAULT),
            trim($_POST['user_id_number']),
            $_POST['user_id_expiry'],
            $_POST['user_role']
        ]);
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

    // Basic validation
    if (!$propertyId || !$tenantId || !$start || !$end) {
        die('Missing required fields');
    }

    if ($start >= $end) {
        die('Invalid contract dates');
    }

    // Check conflict
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM contracts
        WHERE property_id = ?
        AND (
            (? <= contract_end) AND (? >= contract_start)
        )
    ");
    $check->execute([$propertyId, $start, $end]);

    if ($check->fetchColumn() > 0) {
        header("Location: properties.php?contract=conflict&msg=overlap");
        exit();
    }

    // Insert contract
    $stmt = $pdo->prepare("
        INSERT INTO contracts (tenant_id, property_id, contract_start, contract_end, rent, frequency)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tenantId,
        $propertyId,
        $start,
        $end,
        $rent,
        $frequency
    ]);

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

<!-- ============================================ -->
<!-- Modal: Add Property -->
<!-- ============================================ -->
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
        <form id="addPropertyForm" class="modal-body" method="POST" action="modals.php">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Property Address <span class="required">*</span></label>
                    <input type="text" name="property_address" class="form-control" placeholder="Enter Property Address" required>
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
                    <input type="number" name="monthly_rent" class="form-control" placeholder="Enter Rent Amount" required>
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

<!-- ============================================ -->
<!-- Modal: Add Landlord -->
<!-- ============================================ -->
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
        <form id="addLandlordForm" class="modal-body" method="POST" action="modals.php">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="landlord_name" class="form-control" placeholder="Enter Landlord's Full Name" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="landlord_phone" class="form-control" placeholder="Enter Landlord's Phone Number" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="landlord_email" class="form-control" placeholder="Enter Landlord's Email" required>
                </div>
                <div class="form-group">
                    <label>Address <span class="required">*</span></label>
                    <input type="text" name="landlord_address" class="form-control" placeholder="Enter Landlord's Address" required>
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
                    <input type="text" name="landlord_id_number" class="form-control" placeholder="Enter Landlord's ID Number" required>
                </div>
                <div class="form-group">
                    <label>ID Expiry Date <span class="required">*</span></label>
                    <input type="date" name="landlord_id_expiry" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Number of Properties</label>
                    <input type="number" name="landlord_number_of_properties" class="form-control" placeholder="0" min="0" value="0">
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

<!-- ============================================ -->
<!-- Modal: Add Tenant -->
<!-- ============================================ -->
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
        <form id="addTenantForm" class="modal-body" method="POST" action="modals.php">
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
                    <input type="text" name="tenant_name" class="form-control" placeholder="Enter Tenant's Full Name" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="tenant_phone" class="form-control" placeholder="Enter Tenant's Phone Number" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="tenant_email" class="form-control" placeholder="Enter Tenant's Email" required>
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
                    <input type="text" name="tenant_id_number" class="form-control" placeholder="Enter Tenant's ID Number" required>
                </div>
                <div class="form-group">
                    <label>ID Expiry Date <span class="required">*</span></label>
                    <input type="date" name="tenant_id_expiry" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Rent <span class="required">*</span></label>
                    <input type="number" name="tenant_rent" class="form-control" placeholder="Enter Monthly Rent Amount" required>
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

<!-- ============================================ -->
<!-- Modal: Book Viewing -->
<!-- ============================================ -->
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
                    <input type="text" name="viewer_name" class="form-control" placeholder="Enter Viewer Full Name" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="viewer_phone" class="form-control" placeholder="Enter Viewer's Phone Number" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="viewer_email" class="form-control" placeholder="Enter Viewer's Email" required>
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
<!-- ============================================ -->
<!-- Modal: Upload File -->
<!-- ============================================ -->
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

<!-- ============================================ -->
<!-- Modal: Add Payment -->
<!-- ============================================ -->
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
                    <input type="number" name="payment_amount" class="form-control" placeholder="Enter Rent Amount Received" required>
                </div>
                <div class="form-group">
                    <label>Period Covered <span class="required">*</span></label>
                    <input type="text" name="payment_period" class="form-control" placeholder="e.g. Mar - Apr 2025" required>
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
                <div class="form-group">
                    <label>Upload Files</label>
                    <input type="file" id="paymentFileInput" name="payment_files[]" class="file-input" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="file-upload-group" onclick="document.getElementById('paymentFileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Upload Payment Proof & other files</p>
                    </div>
                    <div id="paymentFileCount" class="file-count"></div>
                    <div id="paymentFileList"></div>
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

<!-- ============================================ -->
<!-- Modal: Add Contract -->
<!-- ============================================ -->
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
                    <input type="number" name="contract_rent" class="form-control" placeholder="Enter Rent Amount" required>
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
                <div class="form-group">
                    <label>Upload Files</label>
                    <input type="file" id="contractFileInput" name="contract_files[]" class="file-input" multiple accept=".pdf,.jpg,.jpeg,.png">
                    <div class="file-upload-group" onclick="document.getElementById('contractFileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Upload Contract & other files</p>
                    </div>
                    <div id="contractFileCount" class="file-count"></div>
                    <div id="contractFileList"></div>
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

<!-- ============================================ -->
<!-- Modal: Add User -->
<!-- ============================================ -->
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
        <form id="addUserForm" class="modal-body" method="POST" action="modals.php">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="user_username" class="form-control" placeholder="Enter Username" required>
                </div>
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="user_name" class="form-control" placeholder="Enter User's Full Name" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="user_email" class="form-control" placeholder="Enter User's Email" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="user_phone" class="form-control" placeholder="Enter Phone Number" required>
                </div>
                <div class="form-group">
                    <label>ID Number <span class="required">*</span></label>
                    <input type="text" name="user_id_number" class="form-control" placeholder="Enter ID Number" required>
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
                    <input type="password" name="user_password" class="form-control" placeholder="Enter Password" required>
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