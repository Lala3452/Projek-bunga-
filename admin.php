<?php
session_start();
include 'config.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Ambil data users dari database
$users = [];
$users_sql = "SELECT u.*, up.full_name, up.phone FROM user u LEFT JOIN user_profiles up ON u.id = up.user_id";
$users_result = $conn->query($users_sql);
if ($users_result) {
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
}

// Ambil data statistik
$total_users = 0;
$total_transactions = 0;
$total_income = 0;
$total_expense = 0;

$total_users_sql = "SELECT COUNT(*) as total FROM user";
$total_users_result = $conn->query($total_users_sql);
if ($total_users_result) {
    $total_users = $total_users_result->fetch_assoc()['total'];
}

$total_transactions_sql = "SELECT COUNT(*) as total FROM transactions";
$total_transactions_result = $conn->query($total_transactions_sql);
if ($total_transactions_result) {
    $total_transactions = $total_transactions_result->fetch_assoc()['total'];
}

$total_income_sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'income'";
$total_income_result = $conn->query($total_income_sql);
if ($total_income_result) {
    $total_income = $total_income_result->fetch_assoc()['total'] ?? 0;
}

$total_expense_sql = "SELECT SUM(amount) as total FROM transactions WHERE type = 'expense'";
$total_expense_result = $conn->query($total_expense_sql);
if ($total_expense_result) {
    $total_expense = $total_expense_result->fetch_assoc()['total'] ?? 0;
}

// Ambil data transaksi (siapkan untuk sections)
$transactions = [];
$transactions_sql = "SELECT t.*, u.username, c.name as category_name 
                    FROM transactions t 
                    JOIN user u ON t.user_id = u.id 
                    JOIN categories c ON t.category_id = c.id 
                    ORDER BY t.transaction_date DESC";
$transactions_result = $conn->query($transactions_sql);
if ($transactions_result) {
    $transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Sistem Manajemen Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ===== VARIABLES ===== */
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --border-radius: 15px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        /* ===== RESET & BASE STYLES ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }

        /* ===== LAYOUT ===== */
        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            transition: var(--transition);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ===== SIDEBAR ===== */
        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .nav-links {
            list-style: none;
            padding: 0 15px;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: var(--transition);
        }

        .nav-links a:hover,
        .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-links i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* ===== HEADER ===== */
        .top-header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* ===== CONTENT ===== */
        .content {
            padding: 30px;
            flex: 1;
        }

        .page-title {
            margin-bottom: 30px;
        }

        .page-title p {
            color: #6c757d;
            margin-top: 5px;
        }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stats-card .amount {
            color: var(--primary);
        }

        .income-card .amount {
            color: var(--success);
        }

        .expense-card .amount {
            color: var(--danger);
        }

        .card h3 {
            margin-bottom: 15px;
            font-size: 16px;
            opacity: 0.8;
            font-weight: 600;
        }

        .card .amount {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-icon {
            font-size: 48px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        /* ===== TABLES ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        th, td {
            padding: 18px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--dark);
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .badge-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }

        .badge-warning {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        /* ===== SEARCH & FILTER ===== */
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            min-width: 150px;
        }

        /* ===== TRENDS ===== */
        .card-trend {
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .trend-up {
            color: #28a745;
        }

        .trend-down {
            color: #dc3545;
        }

        /* ===== QUICK ACTIONS ===== */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .action-icon {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        /* ===== PAGE SECTIONS ===== */
        .page-section {
            display: none;
        }

        .page-section.active {
            display: block;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .nav-links {
                display: flex;
                overflow-x: auto;
            }

            .nav-links li {
                flex-shrink: 0;
            }

            .search-filter {
                flex-direction: column;
            }

            .filter-select,
            .search-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">
                <h2>Admin Panel</h2>
            </div>
            <ul class="nav-links">
                <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-chart-bar"></i> Dashboard</a></li>
                <li><a href="#" class="nav-link" data-page="users"><i class="fas fa-users"></i> Manajemen User</a></li>
                <li><a href="#" class="nav-link" data-page="transactions"><i class="fas fa-money-bill-wave"></i> Transaksi</a></li>
                <li><a href="export_laporan.php" target="_blank" class="nav-link"><i class="fas fa-file-export"></i> Export Laporan</a></li>
                <li><a href="?logout=true" id="logout-btn" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Header -->
            <div class="top-header">
                <h1 id="page-title">Dashboard Admin</h1>
                <div class="user-info">
                    <div class="user-avatar" id="user-avatar">
                        <?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'A'; ?>
                    </div>
                    <div>
                        <div id="admin-name"><?php echo $_SESSION['username'] ?? 'Admin'; ?></div>
                        <div class="user-role"><?php echo $_SESSION['role'] ?? 'admin'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="content">
                <!-- Dashboard Section -->
                <div id="dashboard" class="page-section active">
                    <div class="page-title">
                        <h1>Overview Sistem</h1>
                        <p>Statistik dan ringkasan sistem keuangan</p>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <div class="action-card" data-page="users">
                            <div class="action-icon"><i class="fas fa-users"></i></div>
                            <h3>Kelola User</h3>
                            <p>Manajemen user dan permissions</p>
                        </div>
                        <div class="action-card" data-page="transactions">
                            <div class="action-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <h3>Lihat Transaksi</h3>
                            <p>Monitor semua transaksi sistem</p>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="card-grid">
                        <div class="card stats-card">
                            <div class="card-icon"><i class="fas fa-users"></i></div>
                            <h3>Total Users</h3>
                            <div class="amount"><?php echo $total_users; ?></div>
                            <div class="card-trend trend-up"><i class="fas fa-arrow-up"></i> 12% dari bulan lalu</div>
                        </div>
                        <div class="card income-card">
                            <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <h3>Total Pemasukan</h3>
                            <div class="amount">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></div>
                            <div class="card-trend trend-up"><i class="fas fa-arrow-up"></i> 8% dari bulan lalu</div>
                        </div>
                        <div class="card expense-card">
                            <div class="card-icon"><i class="fas fa-credit-card"></i></div>
                            <h3>Total Pengeluaran</h3>
                            <div class="amount">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></div>
                            <div class="card-trend trend-down"><i class="fas fa-arrow-up"></i> 5% dari bulan lalu</div>
                        </div>
                        <div class="card users-card">
                            <div class="card-icon"><i class="fas fa-exchange-alt"></i></div>
                            <h3>Total Transaksi</h3>
                            <div class="amount"><?php echo $total_transactions; ?></div>
                            <div class="card-trend trend-up"><i class="fas fa-arrow-up"></i> 15% dari bulan lalu</div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="card">
                        <h3>Aktivitas Terbaru</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Aktivitas</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="activity-table">
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></td>
                                    <td>Login ke sistem admin</td>
                                    <td><?php echo date('H:i:s'); ?></td>
                                    <td><span class="badge badge-success">Berhasil</span></td>
                                </tr>
                                <?php 
                                $recent_users = array_slice($users, 0, 3);
                                foreach ($recent_users as $user): 
                                    if ($user['username'] != $_SESSION['username']): 
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td>User terdaftar di sistem</td>
                                    <td><?php echo !empty($user['created_at']) ? date('H:i:s', strtotime($user['created_at'])) : '-'; ?></td>
                                    <td><span class="badge badge-success">Aktif</span></td>
                                </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Users Management Section -->
                <div id="users" class="page-section">
                    <div class="page-title">
                        <h1>Daftar Pengguna</h1>
                        <p>Kelola semua user dalam sistem</p>
                    </div>
                    
                    <!-- Search and Filter -->
                    <div class="search-filter">
                        <div class="search-box">
                            <span class="search-icon"><i class="fas fa-search"></i></span>
                            <input type="text" placeholder="Cari user...">
                        </div>
                        <select class="filter-select">
                            <option>Semua Role</option>
                            <option>User</option>
                            <option>Admin</option>
                        </select>
                        <select class="filter-select">
                            <option>Semua Status</option>
                            <option>Aktif</option>
                            <option>Nonaktif</option>
                        </select>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="card">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Tanggal Bergabung</th>
                                </tr>
                            </thead>
                            <tbody id="users-table">
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo !empty($user['full_name']) ? htmlspecialchars($user['full_name']) : '-'; ?></td>
                                    <td>
                                        <span class="badge <?php echo (isset($user['role']) && $user['role'] == 'admin') ? 'badge-warning' : 'badge-success'; ?>">
                                            <?php echo !empty($user['role']) ? htmlspecialchars($user['role']) : 'user'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo (isset($user['is_active']) && $user['is_active']) ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo (isset($user['is_active']) && $user['is_active']) ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($user['created_at']) ? date('d M Y', strtotime($user['created_at'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Transactions Section -->
                <div id="transactions" class="page-section">
                    <div class="page-title">
                        <div>
                            <h1>Semua Transaksi</h1>
                            <p>Kelola dan monitor semua transaksi sistem</p>
                        </div>
                        <a href="export_laporan.php" target="_blank" class="btn btn-primary"><i class="fas fa-file-export"></i> Export Laporan</a>
                    </div>
                    
                    <!-- Search and Filter -->
                    <div class="search-filter">
                        <div class="search-box">
                            <span class="search-icon"><i class="fas fa-search"></i></span>
                            <input type="text" placeholder="Cari transaksi...">
                        </div>
                        <select class="filter-select">
                            <option>Semua Tipe</option>
                            <option>Pemasukan</option>
                            <option>Pengeluaran</option>
                        </select>
                        <select class="filter-select">
                            <option>Semua User</option>
                            <?php foreach ($users as $user): ?>
                            <option><?php echo htmlspecialchars($user['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary">Terapkan Filter</button>
                    </div>
                    
                    <!-- Transactions Table -->
                    <div class="card">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah</th>
                                    <th>Tipe</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody id="transactions-table">
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($transaction['id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($transaction['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($transaction['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><strong>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo ($transaction['type'] === 'income') ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ($transaction['type'] === 'income') ? 'Pemasukan' : 'Pengeluaran'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($transaction['transaction_date']) ? date('d M Y', strtotime($transaction['transaction_date'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <script>
        // ===== ADMIN PANEL FUNCTIONALITY =====
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const navLinks = document.querySelectorAll('.nav-link');
            const pageSections = document.querySelectorAll('.page-section');
            const actionCards = document.querySelectorAll('.action-card');
            const pageTitle = document.getElementById('page-title');
            
            // Navigation functionality
            function setupNavigation() {
                navLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        // Skip links that open in new tab or are logout links
                        if (this.getAttribute('target') === '_blank' || 
                            this.getAttribute('href')?.includes('logout')) {
                            return;
                        }
                        
                        e.preventDefault();
                        const page = this.getAttribute('data-page');
                        
                        // Update active nav link
                        navLinks.forEach(nav => nav.classList.remove('active'));
                        this.classList.add('active');
                        
                        // Show corresponding page
                        showPage(page);
                        
                        // Update page title
                        updatePageTitle(page);
                    });
                });
            }
            
            // Action cards functionality
            function setupActionCards() {
                actionCards.forEach(card => {
                    card.addEventListener('click', function() {
                        const page = this.getAttribute('data-page');
                        
                        // Update active nav link
                        navLinks.forEach(nav => nav.classList.remove('active'));
                        const navTarget = document.querySelector(`[data-page="${page}"]`);
                        if (navTarget) navTarget.classList.add('active');
                        
                        // Show corresponding page
                        showPage(page);
                        
                        // Update page title
                        updatePageTitle(page);
                    });
                });
            }
            
            // Show specific page
            function showPage(page) {
                pageSections.forEach(section => section.classList.remove('active'));
                const el = document.getElementById(page);
                if (el) el.classList.add('active');
            }
            
            // Update page title based on active page
            function updatePageTitle(page) {
                const titles = {
                    'dashboard': 'Dashboard Admin',
                    'users': 'Manajemen User',
                    'transactions': 'Manajemen Transaksi'
                };
                
                pageTitle.textContent = titles[page] || 'Admin Panel';
            }
            
            // Initialize all functionality
            function init() {
                setupNavigation();
                setupActionCards();
                
                // Add loading state to buttons
                document.querySelectorAll('.btn').forEach(button => {
                    button.addEventListener('click', function() {
                        this.classList.add('loading');
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 1000);
                    });
                });
            }
            
            // Start the application
            init();
        });
    </script>
</body>
</html>
