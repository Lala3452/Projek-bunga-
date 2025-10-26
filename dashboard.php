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
$user_sql = "SELECT u.*, up.full_name, up.phone, up.address, up.birth_date 
             FROM user u 
             LEFT JOIN user_profiles up ON u.id = up.user_id 
             WHERE u.id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Ambil data transaksi user
$transactions_sql = "SELECT t.*, c.name as category_name 
                     FROM transactions t 
                     JOIN categories c ON t.category_id = c.id 
                     WHERE t.user_id = ? 
                     ORDER BY t.transaction_date DESC 
                     LIMIT 5";
$transactions_stmt = $conn->prepare($transactions_sql);
$transactions_stmt->bind_param("i", $user_id);
$transactions_stmt->execute();
$transactions_result = $transactions_stmt->get_result();
$transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);

// Hitung total pemasukan dan pengeluaran
$income_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'income'";
$income_stmt = $conn->prepare($income_sql);
$income_stmt->bind_param("i", $user_id);
$income_stmt->execute();
$income_result = $income_stmt->get_result();
$total_income = $income_result->fetch_assoc()['total'] ?? 0;

$expense_sql = "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type = 'expense'";
$expense_stmt = $conn->prepare($expense_sql);
$expense_stmt->bind_param("i", $user_id);
$expense_stmt->execute();
$expense_result = $expense_stmt->get_result();
$total_expense = $expense_result->fetch_assoc()['total'] ?? 0;

$balance = $total_income - $total_expense;

// Ambil kategori untuk form
$categories_sql = "SELECT * FROM categories WHERE type IN ('income', 'expense') ORDER BY type, name";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Proses form tambah transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_transaction'])) {
        $type = $_POST['type'];
        $category_id = $_POST['category_id'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $transaction_date = $_POST['transaction_date'];
        
        $insert_sql = "INSERT INTO transactions (user_id, category_id, type, amount, description, transaction_date) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissss", $user_id, $category_id, $type, $amount, $description, $transaction_date);
        
        if ($insert_stmt->execute()) {
            $success_message = "Transaksi berhasil ditambahkan!";
            // Refresh halaman untuk memperbarui data
            header("Location: dashboard.php?success=1");
            exit();
        } else {
            $error_message = "Terjadi kesalahan saat menambahkan transaksi.";
        }
    }
}

// Proses filter laporan
$report_filter = "";
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'all';

if (!empty($start_date) && !empty($end_date)) {
    $report_filter .= " AND t.transaction_date BETWEEN '$start_date' AND '$end_date'";
}

if ($report_type !== 'all') {
    $report_filter .= " AND t.type = '$report_type'";
}

// Ambil data untuk laporan
$report_sql = "SELECT t.*, c.name as category_name 
               FROM transactions t 
               JOIN categories c ON t.category_id = c.id 
               WHERE t.user_id = ? $report_filter
               ORDER BY t.transaction_date DESC";
$report_stmt = $conn->prepare($report_sql);
$report_stmt->bind_param("i", $user_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
$report_transactions = $report_result->fetch_all(MYSQLI_ASSOC);

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
    <title>Financial Management System - Dashboard</title>
    <style>
        :root{--primary:#4361ee;--secondary:#3a0ca3;--success:#4cc9f0;--danger:#f72585;--light:#f8f9fa;--dark:#212529;}
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
        body{background-color:#f5f7fb;color:var(--dark);}
        .container{max-width:1200px;margin:0 auto;padding:20px;}
        header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;padding:20px 0;border-radius:10px;margin-bottom:30px;box-shadow:0 4px 6px rgba(0,0,0,0.1);}
        .header-content{display:flex;justify-content:space-between;align-items:center;padding:0 20px;}
        .user-info{display:flex;align-items:center;gap:15px;}
        .user-avatar{width:40px;height:40px;border-radius:50%;background-color:white;display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:bold;}
        .dashboard{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:30px;}
        .card{background:white;border-radius:10px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
        .balance-card{background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;grid-column:span 3;}
        .card h3{margin-bottom:10px;font-size:16px;opacity:0.8;}
        .card .amount{font-size:28px;font-weight:bold;}
        .income .amount{color:var(--success);}
        .expense .amount{color:var(--danger);}
        .actions{display:flex;gap:10px;margin-bottom:30px;}
        .btn{padding:12px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:600;transition:all 0.3s;}
        .btn-primary{background-color:var(--primary);color:white;}
        .btn-success{background-color:var(--success);color:white;}
        .btn-danger{background-color:var(--danger);color:white;}
        .btn:hover{opacity:0.9;transform:translateY(-2px);}
        table{width:100%;border-collapse:collapse;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.05);}
        th,td{padding:15px;text-align:left;border-bottom:1px solid #eee;}
        th{background-color:var(--light);font-weight:600;}
        .income-badge{background-color:rgba(76,201,240,0.1);color:var(--success);padding:5px 10px;border-radius:20px;font-size:14px;}
        .expense-badge{background-color:rgba(247,37,133,0.1);color:var(--danger);padding:5px 10px;border-radius:20px;font-size:14px;}
        .info-card{background:white;border-radius:10px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;}
        .info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #eee;}
        .info-row:last-child{border-bottom:none;}
        .info-label{font-weight:600;color:#555;}
        .info-value{color:#333;}
        
        /* Modal Styles */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;}
        .modal-content{background:white;border-radius:10px;width:90%;max-width:500px;padding:30px;box-shadow:0 5px 15px rgba(0,0,0,0.2);}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
        .modal-header h2{margin:0;}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999;}
        .close:hover{color:var(--dark);}
        .form-group{margin-bottom:15px;}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px;font-size:16px;}
        .form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;}
        
        /* Report Styles */
        .report-filters{background:white;border-radius:10px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;}
        .filter-row{display:flex;gap:15px;margin-bottom:15px;}
        .filter-group{flex:1;}
        .export-btn{background-color:var(--success);color:white;padding:10px 15px;border:none;border-radius:5px;cursor:pointer;font-weight:600;}
        
        /* Alert Styles */
        .alert{padding:15px;border-radius:5px;margin-bottom:20px;}
        .alert-success{background-color:rgba(76,201,240,0.1);color:var(--success);border:1px solid rgba(76,201,240,0.3);}
        .alert-error{background-color:rgba(247,37,133,0.1);color:var(--danger);border:1px solid rgba(247,37,133,0.3);}
        
        @media(max-width:768px){
            .dashboard{grid-template-columns:1fr;}
            .balance-card{grid-column:span 1;}
            .actions{flex-direction:column;}
            .info-row{flex-direction:column;}
            .filter-row{flex-direction:column;}
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <h1>Financial Management System</h1>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 1)); ?></div>
                    <div>
                        <div id="username-display"><?php echo $user['username']; ?></div>
                        <div style="font-size:12px;opacity:0.8;"><?php echo $user['email']; ?></div>
                    </div>
                    <a href="?logout=true" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </header>

        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success">
                Transaksi berhasil ditambahkan!
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard">
            <div class="card balance-card">
                <h3>Saldo Saat Ini</h3>
                <div class="amount">Rp <?php echo number_format($balance, 0, ',', '.'); ?></div>
            </div>
            <div class="card income">
                <h3>Total Pemasukan</h3>
                <div class="amount">Rp <?php echo number_format($total_income, 0, ',', '.'); ?></div>
            </div>
            <div class="card expense">
                <h3>Total Pengeluaran</h3>
                <div class="amount">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" id="add-income">Tambah Pemasukan</button>
            <button class="btn btn-danger" id="add-expense">Tambah Pengeluaran</button>
            <button class="btn btn-success" id="view-report">Lihat Laporan</button>
        </div>

        <div class="info-card">
            <h3 style="margin-bottom: 15px;">Informasi Akun</h3>
            <div class="info-row">
                <span class="info-label">Username</span>
                <span class="info-value"><?php echo $user['username']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo $user['email']; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Nama Lengkap</span>
                <span class="info-value"><?php echo $user['full_name'] ? $user['full_name'] : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Telepon</span>
                <span class="info-value"><?php echo $user['phone'] ? $user['phone'] : '-'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Role</span>
                <span class="info-value"><?php echo $user['role'] ? ucfirst($user['role']) : 'User'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value"><?php echo $user['is_active'] ? 'Aktif' : 'Nonaktif'; ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Tanggal Bergabung</span>
                <span class="info-value"><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></span>
            </div>
        </div>

        <div class="card">
            <h3>Transaksi Terbaru</h3>
            <table>
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
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></td>
                            <td><?php echo $transaction['category_name']; ?></td>
                            <td><?php echo $transaction['description']; ?></td>
                            <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="<?php echo $transaction['type'] === 'income' ? 'income-badge' : 'expense-badge'; ?>">
                                    <?php echo $transaction['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Belum ada transaksi</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal Tambah Transaksi -->
        <div id="transaction-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Tambah Transaksi</h2>
                    <button class="close">&times;</button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="type" id="transaction-type" value="">
                    <div class="form-group">
                        <label for="category_id">Kategori</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            <!-- Kategori akan diisi oleh JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="amount">Jumlah (Rp)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Deskripsi</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="transaction_date">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-danger" id="cancel-transaction">Batal</button>
                        <button type="submit" class="btn btn-primary" name="add_transaction">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Laporan -->
        <div id="report-modal" class="modal">
            <div class="modal-content" style="max-width: 90%;">
                <div class="modal-header">
                    <h2>Laporan Keuangan</h2>
                    <button class="close">&times;</button>
                </div>
                
                <div class="report-filters">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="start_date">Tanggal Mulai</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="filter-group">
                                <label for="end_date">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="filter-group">
                                <label for="report_type">Tipe Transaksi</label>
                                <select class="form-control" id="report_type" name="report_type">
                                    <option value="all" <?php echo $report_type === 'all' ? 'selected' : ''; ?>>Semua</option>
                                    <option value="income" <?php echo $report_type === 'income' ? 'selected' : ''; ?>>Pemasukan</option>
                                    <option value="expense" <?php echo $report_type === 'expense' ? 'selected' : ''; ?>>Pengeluaran</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                            <button type="button" class="export-btn" id="export-report">Export ke PDF</button>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <table>
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
                            <?php if (count($report_transactions) > 0): ?>
                                <?php foreach ($report_transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></td>
                                    <td><?php echo $transaction['category_name']; ?></td>
                                    <td><?php echo $transaction['description']; ?></td>
                                    <td>Rp <?php echo number_format($transaction['amount'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="<?php echo $transaction['type'] === 'income' ? 'income-badge' : 'expense-badge'; ?>">
                                            <?php echo $transaction['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">Tidak ada transaksi untuk filter yang dipilih</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function(){
        // Data kategori dari PHP
        const categories = <?php echo json_encode($categories); ?>;
        
        // Modal elements
        const transactionModal = document.getElementById('transaction-modal');
        const reportModal = document.getElementById('report-modal');
        const closeButtons = document.querySelectorAll('.close');
        const cancelButton = document.getElementById('cancel-transaction');
        const addIncomeBtn = document.getElementById('add-income');
        const addExpenseBtn = document.getElementById('add-expense');
        const viewReportBtn = document.getElementById('view-report');
        const exportReportBtn = document.getElementById('export-report');
        
        // Form elements
        const modalTitle = document.getElementById('modal-title');
        const transactionType = document.getElementById('transaction-type');
        const categorySelect = document.getElementById('category_id');
        
        // Fungsi untuk membuka modal
        function openModal(modal) {
            modal.style.display = 'flex';
        }
        
        // Fungsi untuk menutup modal
        function closeModal(modal) {
            modal.style.display = 'none';
        }
        
        // Fungsi untuk mengisi kategori berdasarkan tipe transaksi
        function populateCategories(type) {
            categorySelect.innerHTML = '<option value="">Pilih Kategori</option>';
            
            categories.forEach(category => {
                if (category.type === type) {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                }
            });
        }
        
        // Event listeners untuk tombol tambah pemasukan
        addIncomeBtn.addEventListener('click', function() {
            modalTitle.textContent = 'Tambah Pemasukan';
            transactionType.value = 'income';
            populateCategories('income');
            openModal(transactionModal);
        });
        
        // Event listeners untuk tombol tambah pengeluaran
        addExpenseBtn.addEventListener('click', function() {
            modalTitle.textContent = 'Tambah Pengeluaran';
            transactionType.value = 'expense';
            populateCategories('expense');
            openModal(transactionModal);
        });
        
        // Event listeners untuk tombol lihat laporan
        viewReportBtn.addEventListener('click', function() {
            openModal(reportModal);
        });
        
// Di bagian JavaScript dashboard.php, ganti event listener export button:
exportReportBtn.addEventListener('click', function() {
    // Ambil parameter filter
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const reportType = document.getElementById('report_type').value;
    
    // Buat URL export
    let exportUrl = `export_pdf.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`;
    
    // Buka di tab baru
    window.open(exportUrl, '_blank');
});
        
        // Event listeners untuk tombol tutup modal
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.modal');
                closeModal(modal);
            });
        });
        
        // Event listener untuk tombol batal
        cancelButton.addEventListener('click', function() {
            closeModal(transactionModal);
        });
        
        // Tutup modal jika klik di luar konten modal
        window.addEventListener('click', function(event) {
            if (event.target === transactionModal) {
                closeModal(transactionModal);
            }
            if (event.target === reportModal) {
                closeModal(reportModal);
            }
        });
    });
</script>
</body>
</html>

