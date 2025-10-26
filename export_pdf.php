<?php
session_start();
include 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Ambil data user
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT u.*, up.full_name FROM user u 
             LEFT JOIN user_profiles up ON u.id = up.user_id 
             WHERE u.id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Proses filter laporan dengan aman
$report_filter = " AND t.user_id = ?";
$params = [$user_id];
$param_types = "i";

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

if (!empty($start_date) && !empty($end_date)) {
    $report_filter .= " AND t.transaction_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
}

if ($report_type !== 'all') {
    $report_filter .= " AND t.type = ?";
    $params[] = $report_type;
    $param_types .= "s";
}

// Ambil data untuk laporan
$report_sql = "SELECT t.transaction_date, c.name as category_name, t.description, 
                      t.amount, t.type
               FROM transactions t 
               JOIN categories c ON t.category_id = c.id 
               WHERE 1=1 $report_filter
               ORDER BY t.transaction_date DESC";
$report_stmt = $conn->prepare($report_sql);
$report_stmt->bind_param($param_types, ...$params);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
$report_transactions = $report_result->fetch_all(MYSQLI_ASSOC);

// Hitung total pemasukan dan pengeluaran berdasarkan filter
$income_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income'";
if (!empty($start_date) && !empty($end_date)) {
    $income_sql .= " AND transaction_date BETWEEN ? AND ?";
}
if ($report_type === 'expense') {
    $income_sql .= " AND 1=0"; // Tidak menampilkan income jika filter hanya expense
}

$income_stmt = $conn->prepare($income_sql);
if (!empty($start_date) && !empty($end_date)) {
    $income_stmt->bind_param("iss", $user_id, $start_date, $end_date);
} else {
    $income_stmt->bind_param("i", $user_id);
}
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$total_income = $income_result->fetch_assoc()['total'] ?? 0;

$expense_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense'";
if (!empty($start_date) && !empty($end_date)) {
    $expense_sql .= " AND transaction_date BETWEEN ? AND ?";
}
if ($report_type === 'income') {
    $expense_sql .= " AND 1=0"; // Tidak menampilkan expense jika filter hanya income
}

$expense_stmt = $conn->prepare($expense_sql);
if (!empty($start_date) && !empty($end_date)) {
    $expense_stmt->bind_param("iss", $user_id, $start_date, $end_date);
} else {
    $expense_stmt->bind_param("i", $user_id);
}
$expense_stmt->execute();
$expense_result = $expense_stmt->get_result();
$total_expense = $expense_result->fetch_assoc()['total'] ?? 0;

$balance = $total_income - $total_expense;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - <?php echo date('d M Y'); ?></title>
    <style>
        /* CSS untuk cetak/PDF */
        @media print {
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
            }
            
            .no-print {
                display: none !important;
            }
            
            .header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #333;
            }
            
            .header h1 {
                font-size: 20px;
                margin-bottom: 5px;
                color: #333;
            }
            
            .header .subtitle {
                font-size: 14px;
                color: #666;
            }
            
            .info-section {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            
            .info-section h2 {
                font-size: 16px;
                margin-bottom: 10px;
                color: #333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 5px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .info-item {
                margin-bottom: 5px;
            }
            
            .info-label {
                font-weight: bold;
                color: #555;
            }
            
            .summary-cards {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 10px;
                margin-bottom: 20px;
            }
            
            .summary-card {
                padding: 15px;
                border-radius: 5px;
                text-align: center;
                border: 1px solid #ddd;
            }
            
            .summary-card.income {
                background: #e8f5e8;
                border-left: 4px solid #4caf50;
            }
            
            .summary-card.expense {
                background: #ffe8e8;
                border-left: 4px solid #f44336;
            }
            
            .summary-card.balance {
                background: #e8f4fd;
                border-left: 4px solid #2196f3;
            }
            
            .summary-card h3 {
                font-size: 14px;
                margin-bottom: 8px;
                color: #555;
            }
            
            .summary-card .amount {
                font-size: 16px;
                font-weight: bold;
                color: #333;
            }
            
            .transaction-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            
            .transaction-table th {
                background: #333;
                color: white;
                padding: 10px;
                text-align: left;
                font-size: 12px;
                border: 1px solid #444;
            }
            
            .transaction-table td {
                padding: 8px 10px;
                border: 1px solid #ddd;
                font-size: 11px;
            }
            
            .transaction-table tr:nth-child(even) {
                background: #f9f9f9;
            }
            
            .type-income {
                color: #4caf50;
                font-weight: bold;
            }
            
            .type-expense {
                color: #f44336;
                font-weight: bold;
            }
            
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            /* Memastikan tidak ada pemotongan baris dalam tabel */
            table, tr, td, th {
                page-break-inside: avoid;
            }
        }
        
        /* CSS untuk tampilan di browser (opsional) */
        @media screen {
            body {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            .no-print {
                text-align: center;
                margin: 20px 0;
                padding: 15px;
                background: #f5f5f5;
                border-radius: 5px;
            }
            
            .print-btn {
                background: #2196f3;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-btn:hover {
                background: #1976d2;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <p>Preview Laporan Keuangan. Klik tombol di bawah untuk mencetak atau export ke PDF.</p>
        <button class="print-btn" onclick="window.print()">Cetak / Export PDF</button>
        <button class="print-btn" onclick="window.close()" style="background: #f44336;">Tutup</button>
    </div>

    <div class="header">
        <h1>LAPORAN KEUANGAN</h1>
        <div class="subtitle">Financial Management System</div>
        <div class="subtitle">Dibuat pada: <?php echo date('d F Y H:i'); ?></div>
    </div>

    <div class="info-section">
        <h2>Informasi Pengguna</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nama:</span> 
                <?php echo $user['full_name'] ? $user['full_name'] : $user['username']; ?>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span> 
                <?php echo $user['email']; ?>
            </div>
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
        </div>
    </div>

    <div class="summary-cards">
        <div class="summary-card income">
            <h3>TOTAL PEMASUKAN</h3>
            <div class="amount">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card expense">
            <h3>TOTAL PENGELUARAN</h3>
            <div class="amount">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card balance">
            <h3>SALDO</h3>
            <div class="amount">Rp <?php echo number_format($balance, 0, ',', '.'); ?></div>
        </div>
    </div>

    <div class="info-section">
        <h2>Daftar Transaksi (<?php echo count($report_transactions); ?> transaksi)</h2>
        
        <?php if (count($report_transactions) > 0): ?>
            <table class="transaction-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Jumlah</th>
                        <th>Tipe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></td>
                        <td><?php echo $transaction['category_name']; ?></td>
                        <td><?php echo $transaction['description']; ?></td>
                        <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                        <td class="<?php echo $transaction['type'] === 'income' ? 'type-income' : 'type-expense'; ?>">
                            <?php echo $transaction['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #666;">
                Tidak ada transaksi untuk periode yang dipilih.
            </p>
        <?php endif; ?>
    </div>

    <div class="footer">
        Laporan ini dibuat secara otomatis oleh Financial Management System.<br>
        <?php echo date('d F Y H:i:s'); ?> | Halaman 1
    </div>

    <script>
        // Auto-print ketika halaman dimuat (opsional)
        window.onload = function() {
            // Opsional: auto print setelah delay singkat
            // setTimeout(function() {
            //     window.print();
            // }, 1000);
        };
        
        // Handle after print event
        window.onafterprint = function() {
            // Opsional: tutup window setelah print
            // window.close();
        };
    </script>
</body>
</html>