<?php
session_start();
include 'config.php';

// Cek apakah user sudah login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Proses filter laporan
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';
$user_filter = isset($_GET['user_id']) ? $_GET['user_id'] : '';

// Build query dengan filter
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "t.transaction_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
}

if ($report_type !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $report_type;
    $param_types .= "s";
}

if (!empty($user_filter)) {
    $where_conditions[] = "t.user_id = ?";
    $params[] = $user_filter;
    $param_types .= "i";
}

$where_clause = "";
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Ambil data transaksi untuk laporan
$transactions_sql = "SELECT t.*, u.username, c.name as category_name 
                    FROM transactions t 
                    JOIN user u ON t.user_id = u.id 
                    JOIN categories c ON t.category_id = c.id 
                    $where_clause
                    ORDER BY t.transaction_date DESC";

$transactions_stmt = $conn->prepare($transactions_sql);
if (!empty($params)) {
    $transactions_stmt->bind_param($param_types, ...$params);
}
$transactions_stmt->execute();
$transactions_result = $transactions_stmt->get_result();
$transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);

// Hitung total pemasukan dan pengeluaran
$total_income = 0;
$total_expense = 0;

foreach ($transactions as $transaction) {
    if ($transaction['type'] === 'income') {
        $total_income += $transaction['amount'];
    } else {
        $total_expense += $transaction['amount'];
    }
}

$balance = $total_income - $total_expense;

// Ambil data semua users untuk filter
$users_sql = "SELECT id, username FROM user";
$users_result = $conn->query($users_sql);
$all_users = $users_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Laporan Keuangan</title>
    <style>
        /* Reset dan base styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            padding: 20px;
            color: #000;
            font-size: 12px;
            line-height: 1.4;
            background: white;
        }
        
        /* Header styling */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
            text-transform: uppercase;
        }
        
        .header .subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .header .date-info {
            font-size: 14px;
            color: #888;
        }
        
        /* Info section styling */
        .info-section {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #4361ee;
        }
        
        .info-section h2 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 120px;
        }
        
        /* Summary cards styling */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .summary-card.income {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            border-left: 4px solid #4caf50;
        }
        
        .summary-card.expense {
            background: linear-gradient(135deg, #ffe8e8, #ffcdd2);
            border-left: 4px solid #f44336;
        }
        
        .summary-card.balance {
            background: linear-gradient(135deg, #e8f4fd, #bbdefb);
            border-left: 4px solid #2196f3;
        }
        
        .summary-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #555;
            text-transform: uppercase;
        }
        
        .summary-card .amount {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        /* Table styling */
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: auto;
        }
        
        .transaction-table th {
            background: #333;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-size: 12px;
            border: 1px solid #444;
            text-transform: uppercase;
        }
        
        .transaction-table td {
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 11px;
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        .transaction-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .transaction-table tr:hover {
            background: #f1f1f1;
        }
        
        .type-income {
            color: #4caf50;
            font-weight: bold;
        }
        
        .type-expense {
            color: #f44336;
            font-weight: bold;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: bold;
        }
        
        /* Footer styling */
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        /* Print-specific styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 15px;
                font-size: 11px;
            }
            
            .header {
                margin-bottom: 20px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .summary-card .amount {
                font-size: 18px;
            }
            
            .transaction-table th,
            .transaction-table td {
                padding: 8px 6px;
                font-size: 10px;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            /* Ensure tables don't break across pages */
            table, tr, td, th {
                page-break-inside: avoid;
            }
        }
        
        /* Screen-specific styles */
        @media screen {
            .no-print {
                text-align: center;
                margin: 20px 0;
                padding: 20px;
                background: #f5f5f5;
                border-radius: 8px;
                border: 1px solid #ddd;
            }
            
            .print-btn {
                background: #2196f3;
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: bold;
                margin: 0 10px;
                transition: all 0.3s;
                display: inline-block;
                text-decoration: none;
            }
            
            .print-btn:hover {
                background: #1976d2;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
            
            .print-btn.danger {
                background: #f44336;
            }
            
            .print-btn.danger:hover {
                background: #d32f2f;
            }
            
            body {
                max-width: 1000px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
        }
        
        /* Filter section styling */
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
        }
        
        .filter-section h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 12px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <!-- Filter Section (Hanya tampil di browser) -->
    <div class="no-print">
        <div class="filter-section">
            <h3>Filter Laporan</h3>
            <form method="GET" action="export_laporan.php" class="filter-form">
                <div class="form-group">
                    <label for="start_date">Tanggal Mulai</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Tanggal Selesai</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="form-group">
                    <label for="report_type">Tipe Transaksi</label>
                    <select id="report_type" name="report_type">
                        <option value="all" <?php echo $report_type === 'all' ? 'selected' : ''; ?>>Semua Transaksi</option>
                        <option value="income" <?php echo $report_type === 'income' ? 'selected' : ''; ?>>Pemasukan</option>
                        <option value="expense" <?php echo $report_type === 'expense' ? 'selected' : ''; ?>>Pengeluaran</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="user_id">User</label>
                    <select id="user_id" name="user_id">
                        <option value="">Semua User</option>
                        <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo $user['username']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="print-btn">Terapkan Filter</button>
                    <button type="button" class="print-btn" onclick="window.print()">Cetak / Export PDF</button>
                    <a href="admin.php" class="print-btn" style="text-decoration: none; text-align: center;">Kembali ke Admin</a>
                    <button type="button" class="print-btn danger" onclick="window.close()">Tutup</button>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <p>Preview Laporan Keuangan. Klik tombol "Cetak / Export PDF" untuk mencetak atau menyimpan sebagai PDF.</p>
        </div>
    </div>

    <!-- Header Laporan -->
    <div class="header">
        <h1>LAPORAN KEUANGAN</h1>
        <div class="subtitle">Financial Management System - Admin Panel</div>
        <div class="date-info">Dibuat pada: <?php echo date('d F Y H:i:s'); ?></div>
    </div>

    <!-- Informasi Filter -->
    <div class="info-section">
        <h2>INFORMASI LAPORAN</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Periode Laporan:</span>
                <?php 
                if (!empty($start_date) && !empty($end_date)) {
                    echo date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
                } else {
                    echo 'Semua Periode';
                }
                ?>
            </div>
            <div class="info-item">
                <span class="info-label">Tipe Transaksi:</span>
                <?php 
                if ($report_type === 'all') {
                    echo 'Semua Transaksi';
                } elseif ($report_type === 'income') {
                    echo 'Pemasukan Saja';
                } else {
                    echo 'Pengeluaran Saja';
                }
                ?>
            </div>
            <div class="info-item">
                <span class="info-label">User:</span>
                <?php 
                if (!empty($user_filter)) {
                    $user_name = '';
                    foreach ($all_users as $user) {
                        if ($user['id'] == $user_filter) {
                            $user_name = $user['username'];
                            break;
                        }
                    }
                    echo $user_name;
                } else {
                    echo 'Semua User';
                }
                ?>
            </div>
            <div class="info-item">
                <span class="info-label">Total Transaksi:</span>
                <?php echo count($transactions); ?> transaksi
            </div>
        </div>
    </div>

    <!-- Ringkasan Keuangan -->
    <div class="summary-cards">
        <div class="summary-card income">
            <h3>Total Pemasukan</h3>
            <div class="amount">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card expense">
            <h3>Total Pengeluaran</h3>
            <div class="amount">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card balance">
            <h3>Saldo</h3>
            <div class="amount">Rp <?php echo number_format($balance, 0, ',', '.'); ?></div>
        </div>
    </div>

    <!-- Daftar Transaksi -->
    <div class="info-section">
        <h2>DAFTAR TRANSAKSI</h2>
        
        <?php if (count($transactions) > 0): ?>
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th width="100">Tanggal</th>
                        <th width="120">User</th>
                        <th width="120">Kategori</th>
                        <th>Deskripsi</th>
                        <th width="120">Jumlah</th>
                        <th width="100">Tipe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td>#<?php echo $transaction['id']; ?></td>
                        <td><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></td>
                        <td><strong><?php echo $transaction['username']; ?></strong></td>
                        <td><?php echo $transaction['category_name']; ?></td>
                        <td><?php echo $transaction['description']; ?></td>
                        <td class="amount-cell">Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                        <td class="<?php echo $transaction['type'] === 'income' ? 'type-income' : 'type-expense'; ?>">
                            <?php echo $transaction['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 30px; color: #666; font-style: italic;">
                Tidak ada data transaksi untuk filter yang dipilih.
            </p>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        Laporan ini dibuat secara otomatis oleh Financial Management System.<br>
        <?php echo date('d F Y H:i:s'); ?> | Halaman 1 of 1
    </div>

    <script>
        // Auto-print ketika parameter print=true
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            window.print();
        }
        
        // Handle after print event
        window.onafterprint = function() {
            // Opsional: tutup window setelah print
            // window.close();
        };
        
        // Set default dates jika tidak ada filter
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            if (!startDate.value) {
                // Set default ke awal bulan ini
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                startDate.value = firstDay.toISOString().split('T')[0];
            }
            
            if (!endDate.value) {
                // Set default ke hari ini
                endDate.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>