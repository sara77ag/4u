<?php
// Only connect to database when actually needed
$pdo = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbPath = __DIR__ . '/database.db';
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('DB connection failed: ' . $e->getMessage());
    }

    // Handle property submission
    if (isset($_POST['property_address'])) {
        $address = trim($_POST['property_address']);
        $type = $_POST['property_type'];
        $landlordId = (int)$_POST['landlord_id'];
        $rent = (float)$_POST['monthly_rent'];
        $gasCert = $_POST['gas_cert_date'];
        $electricCert = $_POST['electric_cert_date'];
        $epcDue = $_POST['epc_due_date'];
        
        $stmt = $pdo->prepare("
            INSERT INTO properties (property_address, property_type, monthly_rent, landlord_id, 
                                    gas_cert_due, electric_cert_due, epc_due_date, landlord_id_expiry_date,
                                    room_count, bathroom_count, epc_rating, has_garden, allowed_pets, 
                                    council_band, archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 'E', 0, 0, 'D', 0)
        ");
        
        $stmt->execute([$address, $type, $rent, $landlordId, $gasCert, $electricCert, $epcDue, $gasCert]);
        
        header('Location: properties.php');
        exit();
    }

    // Handle landlord submission
    if (isset($_POST['landlord_name'])) {
        $name = trim($_POST['landlord_name']);
        $phone = trim($_POST['landlord_phone']);
        $address = trim($_POST['landlord_address']);
        $email = trim($_POST['landlord_email']);
        $idType = $_POST['landlord_id_type'];
        $idNumber = trim($_POST['landlord_id_number']);
        $idExpiry = $_POST['landlord_id_expiry'];
        
        $stmt = $pdo->prepare("
            INSERT INTO landlords (name, phone, address, email, id_type, id_number, 
                                   id_expiry_date, number_of_properties, archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)
        ");
        
        $stmt->execute([$name, $phone, $address, $email, $idType, $idNumber, $idExpiry]);
        
        header('Location: landlords.php');
        exit();
    }

    // Handle tenant submission
    if (isset($_POST['tenant_name'])) {
        $propertyId = (int)$_POST['tenant_property_id'];
        $name = trim($_POST['tenant_name']);
        $phone = trim($_POST['tenant_phone']);
        $email = trim($_POST['tenant_email']);
        $idNumber = trim($_POST['tenant_id_number']);
        $idExpiry = $_POST['tenant_id_expiry'];
        $contractStart = $_POST['tenant_contract_start'];
        $contractEnd = $_POST['tenant_contract_end'];
        $paymentFreq = $_POST['tenant_payment_frequency'];
        
        $stmt = $pdo->prepare("
            INSERT INTO tenants (property_id, name, phone, email, id_number, id_expiry_date,
                                contract_start, contract_end, payment_frequency, archived)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        
        $stmt->execute([$propertyId, $name, $phone, $email, $idNumber, $idExpiry, 
                        $contractStart, $contractEnd, $paymentFreq]);
        
        header('Location: tenants.php');
        exit();
    }

    // Handle viewing submission
    if (isset($_POST['viewer_name'])) {
        $propertyId = (int)$_POST['viewing_property_id'];
        $viewerName = trim($_POST['viewer_name']);
        $viewerPhone = trim($_POST['viewer_phone']);
        $viewerEmail = trim($_POST['viewer_email']);
        $dateTime = $_POST['viewing_date'] . ' ' . $_POST['viewing_time'];
        $userId = 1;
        
        $stmt = $pdo->prepare("
            INSERT INTO viewing_bookings (property_id, viewer_name, viewer_phone, email,
                                          date_time, user_id, status, archived)
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled', 0)
        ");
        
        $stmt->execute([$propertyId, $viewerName, $viewerPhone, $viewerEmail, $dateTime, $userId]);
        
        header('Location: viewing.php');
        exit();
    }

    // Handle user submission
    if (isset($_POST['user_username'])) {
        $username = trim($_POST['user_username']);
        $name = trim($_POST['user_name']);
        $phone = trim($_POST['user_phone']);
        $email = trim($_POST['user_email']);
        $password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
        $role = $_POST['user_role'];
        $idNumber = trim($_POST['user_id_number']);
        $idExpiry = $_POST['user_id_expiry'];
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, name, phone, email, password, password_reset_date,
                              id_number, id_expiry_date, role, archived)
            VALUES (?, ?, ?, ?, ?, date('now'), ?, ?, ?, 0)
        ");
        
        $stmt->execute([$username, $name, $phone, $email, $password, $idNumber, $idExpiry, $role]);
        
        header('Location: users.php');
        exit();
    }
    exit();
}

// For GET requests (AJAX loading), connect to show dropdowns
$dbPath = __DIR__ . '/database.db';
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}
?>

<div id="addPropertyModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
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
                        <option value="flat">Flat</option>
                        <option value="house">House</option>
                        <option value="apartment">Apartment</option>
                        <option value="bungalow">Bungalow</option>
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

<div id="addLandlordModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
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
                        <option value="">Select Landlord's ID Type</option>
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

<div id="addTenantModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
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
                    <label>ID Number <span class="required">*</span></label>
                    <input type="text" name="tenant_id_number" class="form-control" placeholder="Enter Tenant's ID Number" required>
                </div>
                <div class="form-group">
                    <label>ID Expiry Date <span class="required">*</span></label>
                    <input type="date" name="tenant_id_expiry" class="form-control" required>
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

<div id="bookViewingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
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
                        $properties = $pdo->query("SELECT property_id, property_address FROM properties WHERE archived = 0")->fetchAll();
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

<div id="addPaymentModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <h3>Add New Payment</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddPaymentModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addPaymentForm" class="modal-body" onsubmit="event.preventDefault();">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Tenant <span class="required">*</span></label>
                    <input type="text" class="form-control" placeholder="Payment tracking not yet implemented" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeAddPaymentModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </form>
    </div>
</div>

<div id="addContractModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <h3>Add New Contract</h3>
            </div>
            <button class="modal-close-btn" onclick="closeAddContractModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="addContractForm" class="modal-body" onsubmit="event.preventDefault();">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Note <span class="required">*</span></label>
                    <input type="text" class="form-control" placeholder="Contracts are added via the Tenant form" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeAddContractModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </form>
    </div>
</div>

<div id="addUserModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
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