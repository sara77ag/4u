<?php
// Database connection
$dbPath = __DIR__ . '/database.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// Fetch stats
$occupied  = $pdo->query("SELECT COUNT(*) FROM tenants WHERE archived = 0")->fetchColumn();
$vacant    = $pdo->query("SELECT COUNT(*) FROM properties WHERE archived = 0")->fetchColumn();
$viewings  = $pdo->query("SELECT COUNT(*) FROM viewing_bookings WHERE status = 'scheduled' AND archived = 0")->fetchColumn();
$payments  = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND archived = 0")->fetchColumn();
$late      = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'not complete' AND archived = 0")->fetchColumn();
$deadlines = $pdo->query("SELECT COUNT(*) FROM tenants WHERE contract_end BETWEEN date('now') AND date('now', '+30 days') AND archived = 0")->fetchColumn();
?>

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
    <!-- Navbar Container -->
    <div id="navbar-container"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Title -->
        <h1 class="page-title">Quick Access</h1>

        <!-- Quick Access Section -->
        <section class="quick-access-section">
            <!-- To-Do List -->
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

            <!-- Quick Action Cards -->
            <div class="quick-cards-container">
                <!-- Row 1 -->
                <div class="quick-cards-row">
                    <div class="quick-card" onclick="openAddPropertyModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-house"></i>
                        </div>
                        <h4 class="quick-card-title">Add Property</h4>
                        <p class="quick-card-desc">Add a new property to the list</p>
                    </div>
                    <div class="quick-card" onclick="openAddLandlordModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <h4 class="quick-card-title">Add Landlord</h4>
                        <p class="quick-card-desc">Register a new landlord</p>
                    </div>
                    <div class="quick-card" onclick="openAddTenantModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <h4 class="quick-card-title">Add Tenant</h4>
                        <p class="quick-card-desc">Register a new <br> tenant</p>
                    </div>
                    <div class="quick-card" onclick="openAddContractModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-file-contract"></i>
                        </div>
                        <h4 class="quick-card-title">New Contract</h4>
                        <p class="quick-card-desc">Create a new rental contract</p>
                    </div>
                </div> 

                <!-- Row 2 -->
                <div class="quick-cards-row">
                    <div class="quick-card" onclick="openAddPaymentModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                        <h4 class="quick-card-title">New Payment</h4>
                        <p class="quick-card-desc">Register a payment received</p>
                    </div>
                    <div class="quick-card" onclick="openBookViewingModal()">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-calendar-plus"></i>
                        </div>
                        <h4 class="quick-card-title">Book Viewing</h4>
                        <p class="quick-card-desc">Schedule a property viewing</p>
                    </div>
                    <div class="quick-card">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <h4 class="quick-card-title"> Due Dates</h4>
                        <p class="quick-card-desc">Check upcoming deadlines</p>
                    </div>
                    <div class="quick-card">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-chart-line"></i>
                        </div>
                        <h4 class="quick-card-title">Export Report</h4>
                        <p class="quick-card-desc">Export monthly summary</p>
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="quick-cards-row">
                    <div class="quick-card">
                        <div class="quick-card-icon">
                            <i class="fa-brands fa-whatsapp"></i>
                        </div>
                        <h4 class="quick-card-title">Open WhatsApp</h4>
                        <p class="quick-card-desc">Send a quick message via WhatsApp</p>
                    </div>
                    <div class="quick-card">
                        <div class="quick-card-icon">
                            <i class="fa-solid fa-wrench"></i>
                        </div>
                        <h4 class="quick-card-title">Report an Issue</h4>
                        <p class="quick-card-desc">Log a maintenance request</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistics Section -->
        <section class="statistics-section">
            <h1 class="page-title" >Statistics</h1>
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

    <!-- Modals Container - Loaded dynamically -->
    <div id="modals-container"></div>

    <script>
        // To-Do List Functionality
        function addTodo() {
            const input = document.getElementById('todo-input');
            const todoList = document.getElementById('todo-list');
            const taskText = input.value.trim();
            
            if (taskText) {
                // Remove empty message if exists
                const emptyMsg = todoList.querySelector('.empty-todo');
                if (emptyMsg) {
                    emptyMsg.remove();
                }
                
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
            const todoItem = checkbox.parentElement;
            todoItem.classList.toggle('completed');
        }

        function deleteTodo(btn) {
            const todoItem = btn.parentElement;
            const todoList = document.getElementById('todo-list');
            todoItem.remove();
            
            // Show empty message if no tasks
            if (todoList.children.length === 0) {
                todoList.innerHTML = '<li class="empty-todo">No tasks yet. Add one above!</li>';
            }
        }

        // Allow adding task with Enter key
        document.getElementById('todo-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addTodo();
            }
        });

    </script>
</body>
</html>

