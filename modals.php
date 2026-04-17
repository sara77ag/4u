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

    // ── TODO: Add ──────────────────────────────────────────────
    if (isset($_POST['todo_text'])) {
        $stmt = $pdo->prepare("
            INSERT INTO notes (note_text, created_at, created_by, type, property_id, landlord_id, tenant_id, payment_id, viewing_id, date_id, user_id, archived)
            VALUES (?, datetime('now'), 'system', 'todo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)
        ");
        $stmt->execute([trim($_POST['todo_text'])]);
        header('Location: dashboard.php');
        exit();
    }

    // ── TODO: Delete ───────────────────────────────────────────
    if (isset($_POST['delete_todo_id'])) {
        $stmt = $pdo->prepare("
            UPDATE notes SET archived = 1, date_archived = date('now') WHERE note_id = ?
        ");
        $stmt->execute([(int)$_POST['delete_todo_id']]);
        header('Location: dashboard.php');
        exit();
    }

    // ── Add Property ───────────────────────────────────────────
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

    // ── Edit Property ──────────────────────────────────────────
    if (isset($_POST['edit_property_id'])) {
        $stmt = $pdo->prepare("
            UPDATE properties
            SET property_address = ?, property_type = ?, monthly_rent = ?,
                gas_cert_due = ?, electric_cert_due = ?, epc_due_date = ?,
                room_count = ?, bathroom_count = ?, epc_rating = ?,
                has_garden = ?, allowed_pets = ?, council_band = ?
            WHERE property_id = ?
        ");
        $stmt->execute([
            trim($_POST['edit_property_address']),
            $_POST['edit_property_type'],
            (float)$_POST['edit_property_price'],
            $_POST['edit_property_gas_cert'],
            $_POST['edit_property_electric_cert'],
            $_POST['edit_property_epc_due'],
            (int)$_POST['edit_property_rooms'],
            (int)$_POST['edit_property_bathrooms'],
            $_POST['edit_property_epc_rating'],
            (int)$_POST['edit_property_garden'],
            (int)$_POST['edit_property_pets'],
            $_POST['edit_property_council'],
            (int)$_POST['edit_property_id']
        ]);
        header('Location: properties.php');
        exit();
    }

    // ── Add Landlord ───────────────────────────────────────────
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
// Handle tenant submission
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

    // 1. Insert tenant
    $stmt = $pdo->prepare("
        INSERT INTO tenants (name, phone, email, id_type, id_number, id_expiry_date, archived)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $propertyId,
        $name,
        $phone,
        $email,
        $idType,
        $idNumber,
        $idExpiry
    ]);

    $prop = $pdo->prepare("SELECT landlord_id FROM properties WHERE property_id = ?");
    $prop->execute([$propertyId]);
    $landlordId = $prop->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO contracts (tenant_id, property_id, landlord_id, contract_start, contract_end, rent, frequency, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$tenantId, $propertyId, $landlordId, $contractStart, $contractEnd, $rent, $paymentFreq]);

    header('Location: tenants.php');
    exit();
}

    // ── Add Viewing ────────────────────────────────────────────
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

    // ── Edit Viewing ───────────────────────────────────────────
    if (isset($_POST['edit_viewing_id'])) {
        $stmt = $pdo->prepare("
            UPDATE viewing_bookings
            SET viewer_name = ?, viewer_phone = ?, email = ?,
                date_time = ?, status = ?
            WHERE booking_id = ?
        ");
        $stmt->execute([
            trim($_POST['edit_viewing_name']),
            trim($_POST['edit_viewing_phone']),
            trim($_POST['edit_viewing_email']),
            $_POST['edit_viewing_date'] . ' ' . $_POST['edit_viewing_time'],
            $_POST['edit_viewing_status'],
            (int)$_POST['edit_viewing_id']
        ]);
        header('Location: viewing.php');
        exit();
    }

    // ── Add User ───────────────────────────────────────────────
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

    // ── Edit User ──────────────────────────────────────────────
    if (isset($_POST['edit_user_id'])) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET username = ?, name = ?, phone = ?, email = ?,
                role = ?, id_number = ?, id_expiry_date = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            trim($_POST['edit_user_username']),
            trim($_POST['edit_user_name']),
            trim($_POST['edit_user_phone']),
            trim($_POST['edit_user_email']),
            $_POST['edit_user_role'],
            trim($_POST['edit_user_idnumber']),
            $_POST['edit_user_idexpiry'],
            (int)$_POST['edit_user_id']
        ]);
        header('Location: users.php');
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
<!-- Modal: View Property -->
<!-- ============================================ -->
<div id="viewPropertyModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>View Property</h3>
            </div>
            <button class="modal-close-btn" onclick="closeViewPropertyModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-form-grid">
                <div class="form-group"><label>Property ID</label><div class="form-control" id="viewProperty_id"></div></div>
                <div class="form-group"><label>Address</label><div class="form-control" id="viewProperty_address"></div></div>
                <div class="form-group"><label>Type</label><div class="form-control" id="viewProperty_type"></div></div>
                <div class="form-group"><label>Landlord</label><div class="form-control" id="viewProperty_landlord"></div></div>
                <div class="form-group"><label>Monthly Rent</label><div class="form-control" id="viewProperty_price"></div></div>
                <div class="form-group"><label>Tenant</label><div class="form-control" id="viewProperty_tenant"></div></div>
                <div class="form-group"><label>Status</label><div class="form-control" id="viewProperty_status"></div></div>
                <div class="form-group"><label>Contract Start</label><div class="form-control" id="viewProperty_contractStart"></div></div>
                <div class="form-group"><label>Contract End</label><div class="form-control" id="viewProperty_contractEnd"></div></div>
                <div class="form-group"><label>Gas Cert Due</label><div class="form-control" id="viewProperty_gasCert"></div></div>
                <div class="form-group"><label>Electric Cert Due</label><div class="form-control" id="viewProperty_electricCert"></div></div>
                <div class="form-group"><label>EPC Due</label><div class="form-control" id="viewProperty_epcDue"></div></div>
                <div class="form-group"><label>Rooms</label><div class="form-control" id="viewProperty_rooms"></div></div>
                <div class="form-group"><label>Bathrooms</label><div class="form-control" id="viewProperty_bathrooms"></div></div>
                <div class="form-group"><label>EPC Rating</label><div class="form-control" id="viewProperty_epcRating"></div></div>
                <div class="form-group"><label>Garden</label><div class="form-control" id="viewProperty_garden"></div></div>
                <div class="form-group"><label>Pets Allowed</label><div class="form-control" id="viewProperty_pets"></div></div>
                <div class="form-group"><label>Council Band</label><div class="form-control" id="viewProperty_council"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeViewPropertyModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- Modal: Edit Property -->
<!-- ============================================ -->
<div id="editPropertyModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-pen"></i></div>
                <h3>Edit Property</h3>
            </div>
            <button class="modal-close-btn" onclick="closeEditPropertyModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editPropertyForm" class="modal-body" method="POST" action="modals.php">
            <input type="hidden" name="edit_property_id" id="editProperty_id">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Address <span class="required">*</span></label>
                    <input type="text" name="edit_property_address" id="editProperty_address" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Type <span class="required">*</span></label>
                    <select name="edit_property_type" id="editProperty_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="flat">Flat</option>
                        <option value="house">House</option>
                        <option value="apartment">Apartment</option>
                        <option value="bungalow">Bungalow</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monthly Rent <span class="required">*</span></label>
                    <input type="number" name="edit_property_price" id="editProperty_price" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Gas Cert Due</label>
                    <input type="date" name="edit_property_gas_cert" id="editProperty_gasCert" class="form-control">
                </div>
                <div class="form-group">
                    <label>Electric Cert Due</label>
                    <input type="date" name="edit_property_electric_cert" id="editProperty_electricCert" class="form-control">
                </div>
                <div class="form-group">
                    <label>EPC Due</label>
                    <input type="date" name="edit_property_epc_due" id="editProperty_epcDue" class="form-control">
                </div>
                <div class="form-group">
                    <label>Rooms</label>
                    <input type="number" name="edit_property_rooms" id="editProperty_rooms" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>Bathrooms</label>
                    <input type="number" name="edit_property_bathrooms" id="editProperty_bathrooms" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label>EPC Rating</label>
                    <select name="edit_property_epc_rating" id="editProperty_epcRating" class="form-control">
                        <option value="">Select Rating</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                        <option value="F">F</option>
                        <option value="G">G</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Garden</label>
                    <select name="edit_property_garden" id="editProperty_garden" class="form-control">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pets Allowed</label>
                    <select name="edit_property_pets" id="editProperty_pets" class="form-control">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Council Band</label>
                    <select name="edit_property_council" id="editProperty_council" class="form-control">
                        <option value="">Select Band</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                        <option value="F">F</option>
                        <option value="G">G</option>
                        <option value="H">H</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeEditPropertyModal()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save
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
                    name="landlord_name" class="form-control" placeholder="Enter Landlord's Full Name" required>
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
<!-- Modal: View Landlord -->
<!-- ============================================ -->
<div id="viewLandlordModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>View Landlord</h3>
            </div>
            <button class="modal-close-btn" onclick="closeViewLandlordModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-form-grid">
                <div class="form-group"><label>Landlord ID</label><div class="form-control" id="viewLandlord_id"></div></div>
                <div class="form-group"><label>Full Name</label><div class="form-control" id="viewLandlord_name"></div></div>
                <div class="form-group"><label>Phone</label><div class="form-control" id="viewLandlord_phone"></div></div>
                <div class="form-group"><label>Email</label><div class="form-control" id="viewLandlord_email"></div></div>
                <div class="form-group"><label>Address</label><div class="form-control" id="viewLandlord_address"></div></div>
                <div class="form-group"><label>Number of Properties</label><div class="form-control" id="viewLandlord_properties"></div></div>
                <div class="form-group"><label>ID Type</label><div class="form-control" id="viewLandlord_idtype"></div></div>
                <div class="form-group"><label>ID Number</label><div class="form-control" id="viewLandlord_idnumber"></div></div>
                <div class="form-group"><label>ID Expiry</label><div class="form-control" id="viewLandlord_idexpiry"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeViewLandlordModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- Modal: Edit Landlord -->
<!-- ============================================ -->
<div id="editLandlordModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-pen"></i></div>
                <h3>Edit Landlord</h3>
            </div>
            <button class="modal-close-btn" onclick="closeEditLandlordModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editLandlordForm" class="modal-body" method="POST" action="modals.php">
            <input type="hidden" name="edit_landlord_id" id="editLandlord_id">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="edit_landlord_name" id="editLandlord_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="edit_landlord_phone" id="editLandlord_phone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="edit_landlord_email" id="editLandlord_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Address <span class="required">*</span></label>
                    <input type="text" name="edit_landlord_address" id="editLandlord_address" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>ID Type</label>
                    <select name="edit_landlord_idtype" id="editLandlord_idtype" class="form-control">
                        <option value="">Select ID Type</option>
                        <option value="passport">Passport</option>
                        <option value="driving_license">Driving License</option>
                        <option value="national_id">National ID</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" name="edit_landlord_idnumber" id="editLandlord_idnumber" class="form-control">
                </div>
                <div class="form-group">
                    <label>ID Expiry</label>
                    <input type="date" name="edit_landlord_idexpiry" id="editLandlord_idexpiry" class="form-control">
                </div>
                <div class="form-group">
                    <label>Number of Properties</label>
                    <input type="number" name="edit_landlord_properties" id="editLandlord_properties" class="form-control" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeEditLandlordModal()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save
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

<!-- ============================================ -->
<!-- Modal: View Viewing -->
<!-- ============================================ -->
<div id="viewViewingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>View Viewing</h3>
            </div>
            <button class="modal-close-btn" onclick="closeViewViewingModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-form-grid">
                <div class="form-group"><label>Booking ID</label><div class="form-control" id="viewViewing_id"></div></div>
                <div class="form-group"><label>Full Name</label><div class="form-control" id="viewViewing_name"></div></div>
                <div class="form-group"><label>Phone</label><div class="form-control" id="viewViewing_phone"></div></div>
                <div class="form-group"><label>Email</label><div class="form-control" id="viewViewing_email"></div></div>
                <div class="form-group"><label>Property</label><div class="form-control" id="viewViewing_property"></div></div>
                <div class="form-group"><label>Date / Time</label><div class="form-control" id="viewViewing_datetime"></div></div>
                <div class="form-group"><label>Agent</label><div class="form-control" id="viewViewing_agent"></div></div>
                <div class="form-group"><label>Status</label><div class="form-control" id="viewViewing_status"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeViewViewingModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- Modal: Edit Viewing -->
<!-- ============================================ -->
<div id="editViewingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-pen"></i></div>
                <h3>Edit Viewing</h3>
            </div>
            <button class="modal-close-btn" onclick="closeEditViewingModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editViewingForm" class="modal-body" method="POST" action="modals.php">
            <input type="hidden" name="edit_viewing_id" id="editViewing_id">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="edit_viewing_name" id="editViewing_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="edit_viewing_phone" id="editViewing_phone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="edit_viewing_email" id="editViewing_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="edit_viewing_date" id="editViewing_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Time <span class="required">*</span></label>
                    <input type="time" name="edit_viewing_time" id="editViewing_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="edit_viewing_status" id="editViewing_status" class="form-control" required>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeEditViewingModal()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- Modal: View Important Date -->
<!-- ============================================ -->
<div id="viewDateModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <h3>Important Dates</h3>
            </div>
            <button class="modal-close-btn" onclick="closeViewDateModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-form-grid">
                <div class="form-group"><label>Property</label><div class="form-control" id="viewDate_property"></div></div>
                <div class="form-group"><label>Gas Cert Due</label><div class="form-control" id="viewDate_gas"></div></div>
                <div class="form-group"><label>Electric Cert Due</label><div class="form-control" id="viewDate_electric"></div></div>
                <div class="form-group"><label>EPC Due</label><div class="form-control" id="viewDate_epc"></div></div>
                <div class="form-group"><label>Landlord ID Expiry</label><div class="form-control" id="viewDate_idlandlord"></div></div>
                <div class="form-group"><label>Tenant ID Expiry</label><div class="form-control" id="viewDate_idtenant"></div></div>
                <div class="form-group"><label>Contract End</label><div class="form-control" id="viewDate_contract"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeViewDateModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
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
                        $properties = $pdo->query("SELECT property_id, property_address FROM properties WHERE archived = 0")->fetchAll();
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
                        $landlords = $pdo->query("SELECT landlord_id, name FROM landlords WHERE archived = 0")->fetchAll();
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
                        $tenants = $pdo->query("SELECT tenant_id, name FROM tenants WHERE archived = 0")->fetchAll();
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
                        $tenants = $tenants ?? $pdo->query("SELECT tenant_id, name FROM tenants WHERE archived = 0")->fetchAll();
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

<!-- ============================================ -->
<!-- Modal: View User -->
<!-- ============================================ -->
<div id="viewUserModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-eye"></i></div>
                <h3>View User</h3>
            </div>
            <button class="modal-close-btn" onclick="closeViewUserModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-form-grid">
                <div class="form-group"><label>User ID</label><div class="form-control" id="viewUser_id"></div></div>
                <div class="form-group"><label>Username</label><div class="form-control" id="viewUser_username"></div></div>
                <div class="form-group"><label>Full Name</label><div class="form-control" id="viewUser_name"></div></div>
                <div class="form-group"><label>Phone</label><div class="form-control" id="viewUser_phone"></div></div>
                <div class="form-group"><label>Email</label><div class="form-control" id="viewUser_email"></div></div>
                <div class="form-group"><label>Role</label><div class="form-control" id="viewUser_role"></div></div>
                <div class="form-group"><label>ID Number</label><div class="form-control" id="viewUser_idnumber"></div></div>
                <div class="form-group"><label>ID Expiry</label><div class="form-control" id="viewUser_idexpiry"></div></div>
                <div class="form-group"><label>Password Reset Date</label><div class="form-control" id="viewUser_pwreset"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeViewUserModal()">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- Modal: Edit User -->
<!-- ============================================ -->
<div id="editUserModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-header-title">
                <div class="modal-header-title-icon"><i class="fa-solid fa-pen"></i></div>
                <h3>Edit User</h3>
            </div>
            <button class="modal-close-btn" onclick="closeEditUserModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editUserForm" class="modal-body" method="POST" action="modals.php">
            <input type="hidden" name="edit_user_id" id="editUser_id">
            <div class="modal-form-grid">
                <div class="form-group">
                    <label>Username <span class="required">*</span></label>
                    <input type="text" name="edit_user_username" id="editUser_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="edit_user_name" id="editUser_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="tel" name="edit_user_phone" id="editUser_phone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="edit_user_email" id="editUser_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select name="edit_user_role" id="editUser_role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="agent">Agent</option>
                        <option value="read_only">Read Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" name="edit_user_idnumber" id="editUser_idnumber" class="form-control">
                </div>
                <div class="form-group">
                    <label>ID Expiry</label>
                    <input type="date" name="edit_user_idexpiry" id="editUser_idexpiry" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-reset" onclick="closeEditUserModal()">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="modal-btn modal-btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>