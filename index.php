<?php
session_start();
include 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        // Proses Login
        $username = clean_input($_POST['username']);
        $password = md5(clean_input($_POST['password'])); // MD5 hash
        
        $sql = "SELECT * FROM user WHERE username = ? AND password = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'] ? $user['role'] : 'user';
            
            if ($_SESSION['role'] == 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Username atau password salah!";
        }
    } elseif (isset($_POST['register'])) {
        // Proses Registrasi
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $password = md5(clean_input($_POST['password'])); // MD5 hash
        
        // Cek apakah username atau email sudah ada
        $check_sql = "SELECT id FROM user WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username atau email sudah terdaftar!";
        } else {
            $insert_sql = "INSERT INTO user (username, email, password, role, is_active) VALUES (?, ?, ?, 'user', 1)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sss", $username, $email, $password);
            
            if ($insert_stmt->execute()) {
                // Buat profil user
                $user_id = $insert_stmt->insert_id;
                $profile_sql = "INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)";
                $profile_stmt = $conn->prepare($profile_sql);
                $profile_stmt->bind_param("is", $user_id, $username);
                $profile_stmt->execute();
                
                $success = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan saat registrasi: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management System - Login</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color:#f5f5f5; display:flex; justify-content:center; align-items:center; min-height:100vh; padding:20px; }
        .container { display:flex; max-width:900px; width:100%; background-color:white; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.1); overflow:hidden; }
        .welcome-section { flex:1; background:linear-gradient(135deg, #0a3c64 0%, #0d6e9bcb 100%); color:white; padding:40px; display:flex; flex-direction:column; justify-content:center; }
        .welcome-section h1 { font-size:32px; margin-bottom:15px; }
        .welcome-section p { font-size:16px; opacity:0.9; line-height:1.5; }
        .form-section { flex:1; padding:40px; }
        .form-container { max-width:350px; margin:0 auto; }
        .form-title { font-size:24px; margin-bottom:30px; color:#333; text-align:center; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:500; color:#555; }
        .form-group input { width:100%; padding:12px 15px; border:1px solid #ddd; border-radius:5px; font-size:16px; transition:border-color 0.3s; }
        .form-group input:focus { border-color:#f59619; outline:none; }
        .password-input { position:relative; }
        .password-input input { padding-right:40px; }
        .password-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#777; }
        .btn { width:100%; padding:12px; background:linear-gradient(135deg, #f7a815 0%, #df7512ef 100%); color:white; border:none; border-radius:5px; font-size:16px; font-weight:600; cursor:pointer; transition:opacity 0.3s; }
        .btn:hover { opacity:0.9; }
        .link-text { text-align:center; margin-top:20px; color:#555; }
        .link-text a { color:#6a11cb; text-decoration:none; font-weight:500; }
        .link-text a:hover { text-decoration:underline; }
        .alert { padding:12px; border-radius:5px; margin-bottom:20px; text-align:center; }
        .alert-success { background-color:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-danger { background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        @media (max-width:768px){ .container{ flex-direction:column; } .welcome-section{ padding:30px; text-align:center; } .form-section{ padding:30px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <h1>WELCOME BACK!</h1>
            <p>Kelola keuangan Anda dengan mudah dan efisien</p>
        </div>
        <div class="form-section">
            <div class="form-container">
                <!-- Alert Message -->
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form id="login-form" class="form" method="POST" action="">
                    <h2 class="form-title">Login</h2>
                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <input type="text" id="login-username" name="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="password-input">
                            <input type="password" id="login-password" name="password" placeholder="Masukkan password" required>
                            <button type="button" class="password-toggle">👁️</button>
                        </div>
                    </div>
                    <button type="submit" name="login" class="btn">Login</button>
                    <p class="link-text">Don't have an account? <a href="#" id="go-to-signup">Sign Up</a></p>
                </form>

                <!-- Sign Up Form -->
                <form id="signup-form" class="form" method="POST" action="" style="display:none;">
                    <h2 class="form-title">Sign Up</h2>
                    <div class="form-group">
                        <label for="signup-username">Username</label>
                        <input type="text" id="signup-username" name="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label for="signup-email">Email</label>
                        <input type="email" id="signup-email" name="email" placeholder="@gmail.com" required>
                    </div>
                    <div class="form-group">
                        <label for="signup-password">Password</label>
                        <div class="password-input">
                            <input type="password" id="signup-password" name="password" placeholder="Masukkan password" required>
                            <button type="button" class="password-toggle">👁️</button>
                        </div>
                    </div>
                    <button type="submit" name="register" class="btn">Sign Up</button>
                    <p class="link-text">Already have an account? <a href="#" id="go-to-login">Login</a></p>
                </form>
            </div>
        </div>
    </div>

<script>
    // Toggle antara login & signup
    document.getElementById('go-to-signup').addEventListener('click', function(e){ 
        e.preventDefault(); 
        showSignupForm(); 
    });
    
    document.getElementById('go-to-login').addEventListener('click', function(e){ 
        e.preventDefault(); 
        showLoginForm(); 
    });
    
    function showLoginForm(){ 
        document.getElementById('login-form').style.display='block'; 
        document.getElementById('signup-form').style.display='none'; 
    }
    
    function showSignupForm(){ 
        document.getElementById('login-form').style.display='none'; 
        document.getElementById('signup-form').style.display='block'; 
    }

    // Toggle password visibility
    document.querySelectorAll('.password-toggle').forEach(function(toggle){
        toggle.addEventListener('click', function(){
            const passwordInput = this.parentElement.querySelector('input');
            if(passwordInput.type==='password'){ 
                passwordInput.type='text'; 
                this.textContent='🙈'; 
            } else{ 
                passwordInput.type='password'; 
                this.textContent='👁️'; 
            }
        });
    });
</script>
</body>
</html>