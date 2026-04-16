<?php

$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// Fetch stats
$occupied = $pdo->query("SELECT COUNT(DISTINCT property_id) FROM contracts WHERE date('now') BETWEEN contract_start AND contract_end ")->fetchColumn();
$totalProperties = $pdo->query("SELECT COUNT(*) FROM properties WHERE archived = 0")->fetchColumn();
$vacant = $totalProperties - $occupied;
$viewings  = $pdo->query("SELECT COUNT(*) FROM viewing_bookings WHERE status = 'scheduled' AND archived = 0")->fetchColumn();
$payments  = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND archived = 0")->fetchColumn();
$late = $pdo->query("
    SELECT COUNT(*) FROM transactions 
    WHERE (status = 'not complete' OR (status = 'pending' AND next_due < date('now')))
    AND archived = 0
")->fetchColumn();
$deadlines = $pdo->query("
    SELECT 
    (
        -- Gas Certificate
        SELECT COUNT(*) FROM properties 
        WHERE gas_cert_due BETWEEN date('now') AND date('now', '+30 days') 
        AND archived = 0
    ) + (
        -- Electric Certificate
        SELECT COUNT(*) FROM properties 
        WHERE electric_cert_due BETWEEN date('now') AND date('now', '+30 days') 
        AND archived = 0
    ) + (
        -- EPC
        SELECT COUNT(*) FROM properties 
        WHERE epc_due_date BETWEEN date('now') AND date('now', '+30 days') 
        AND archived = 0
    ) + (
        -- Landlord ID Expiry
        SELECT COUNT(*) FROM landlords 
        WHERE id_expiry_date BETWEEN date('now') AND date('now', '+30 days') 
        AND archived = 0
    ) + (
        -- Tenant ID Expiry
        SELECT COUNT(*) FROM tenants 
        WHERE id_expiry_date BETWEEN date('now') AND date('now', '+30 days') 
        AND archived = 0
    ) + (
        -- Contract End
        SELECT COUNT(*) FROM contracts 
        WHERE contract_end BETWEEN date('now') AND date('now', '+30 days')
    )
")->fetchColumn();?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - iLet4You</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="navbar.js" ></script>
    <script src="modals.js" ></script>

</head>
<body onload="CallNavbar(); loadModals();">
    
    <div id="navbar-container"></div>

    <main class="main-content">
        <h1 class="page-title">Quick Access</h1>

        <section class="quick-access-section">
            <div class="todo-container">
                <h3>To-Do List</h3>
                <div class="todo-input-group">
                    <input type="text" id="todo-input" class="todo-input" placeholder="Add a new task...">
                    <button class="add-todo-btn" onclick="addTodo()">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <ul class="todo-list" id="todo-list">
                    <li class="empty-todo">No tasks yet. Add one above!</li>
                </ul>
            </div>

            <div class="quick-cards-container">
                <div class="quick-cards-row">
                    <div class="quick-card" onclick="openAddPropertyModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-house"></i></div>
                        <h4 class="quick-card-title">Add Property</h4>
                        <p class="quick-card-desc">Add a new property to the list</p>
                    </div>
                    <div class="quick-card" onclick="openAddLandlordModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-user-tie"></i></div>
                        <h4 class="quick-card-title">Add Landlord</h4>
                        <p class="quick-card-desc">Register a new landlord</p>
                    </div>
                    <div class="quick-card" onclick="openAddTenantModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-user"></i></div>
                        <h4 class="quick-card-title">Add Tenant</h4>
                        <p class="quick-card-desc">Register a new tenant</p>
                    </div>
                    <div class="quick-card" onclick="openAddContractModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-file-contract"></i></div>
                        <h4 class="quick-card-title">New Contract</h4>
                        <p class="quick-card-desc">Create a new rental contract</p>
                    </div>
                </div>

                <div class="quick-cards-row">
                    <div class="quick-card" onclick="openAddPaymentModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-credit-card"></i></div>
                        <h4 class="quick-card-title">New Payment</h4>
                        <p class="quick-card-desc">Register a payment received</p>
                    </div>
                    <div class="quick-card" onclick="openBookViewingModal()">
                        <div class="quick-card-icon"><i class="fa-solid fa-calendar-plus"></i></div>
                        <h4 class="quick-card-title">Book Viewing</h4>
                        <p class="quick-card-desc">Schedule a property viewing</p>
                    </div>
                    <div class="quick-card" onclick="window.location.href='important-dates.php'">
                        <div class="quick-card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                        <h4 class="quick-card-title">Due Dates</h4>
                        <p class="quick-card-desc">Check upcoming deadlines</p>
                    </div>
                    <div class="quick-card" onclick="window.location.href='export.php'">
                        <div class="quick-card-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <h4 class="quick-card-title">Export Report</h4>
                        <p class="quick-card-desc">Export monthly summary</p>
                    </div>
                </div>

                <div class="quick-cards-row">
                    <div class="quick-card" onclick="alert('WhatsApp integration coming soon.')">
                        <div class="quick-card-icon"><i class="fa-brands fa-whatsapp"></i></div>
                        <h4 class="quick-card-title">Open WhatsApp</h4>
                        <p class="quick-card-desc">Send a quick message via WhatsApp</p>
                    </div>
                    <div class="quick-card" onclick="alert('Issue reporting coming soon.')">
                        <div class="quick-card-icon"><i class="fa-solid fa-wrench"></i></div>
                        <h4 class="quick-card-title">Report an Issue</h4>
                        <p class="quick-card-desc">Log a maintenance request</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="statistics-section">
            <h1 class="page-title">Statistics</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Occupied Properties</h4>
                    <div class="stat-number"><?= $occupied ?></div>
                </div>
                <div class="stat-card">
                    <h4>Vacant Properties</h4>
                    <div class="stat-number"><?= $vacant ?></div>
                </div>
                <div class="stat-card">
                    <h4>Upcoming Viewings</h4>
                    <div class="stat-number"><?= $viewings ?></div>
                </div>
                <div class="stat-card">
                    <h4>Upcoming Payments</h4>
                    <div class="stat-number"><?= $payments ?></div>
                </div>
                <div class="stat-card">
                    <h4>Late Payments</h4>
                    <div class="stat-number"><?= $late ?></div>
                </div>
                <div class="stat-card">
                    <h4>Approaching Dates</h4>
                    <div class="stat-number"><?= $deadlines ?></div>
                </div>
            </div>
        </section>
    </main>

    <div id="modals-container"></div>

    <script>
        function addTodo() {
            const input = document.getElementById('todo-input');
            const todoList = document.getElementById('todo-list');
            const taskText = input.value.trim();

            if (taskText) {
                const emptyMsg = todoList.querySelector('.empty-todo');
                if (emptyMsg) emptyMsg.remove();

                const li = document.createElement('li');
                li.className = 'todo-item';
                li.innerHTML = `
                    <input type="checkbox" class="todo-checkbox" onchange="toggleTodo(this)">
                    <span class="todo-text">${taskText}</span>
                    <button class="delete-todo" onclick="deleteTodo(this)">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                `;
                todoList.appendChild(li);
                input.value = '';
            }
        }

        function toggleTodo(checkbox) {
            checkbox.parentElement.classList.toggle('completed');
        }

        function deleteTodo(btn) {
            const todoList = document.getElementById('todo-list');
            btn.parentElement.remove();
            if (todoList.children.length === 0) {
                todoList.innerHTML = '<li class="empty-todo">No tasks yet. Add one above!</li>';
            }
        }

        document.getElementById('todo-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') addTodo();
        });
    </script>
</body>
</html>