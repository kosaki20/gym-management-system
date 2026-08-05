<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin_dashboard.php");
            exit();
        case 'trainer':
            header("Location: trainer_dashboard.php");
            exit();
        case 'client':
            header("Location: client_dashboard.php");
            exit();
    }
}

$error = '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT u.*, m.member_type, u.client_type FROM users u LEFT JOIN members m ON u.id = m.user_id WHERE u.username = ? AND u.deleted_at IS NULL");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            $password_valid = false;
            $needs_rehash = false;
            
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            } elseif (strlen($user['password']) === 32 && ctype_xdigit($user['password']) && md5($password) === $user['password']) {
                $password_valid = true;
                $needs_rehash = true;
            }
            
            if ($password_valid) {
                if ($needs_rehash) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $rehash_stmt->bind_param("si", $new_hash, $user['id']);
                    $rehash_stmt->execute();
                }
                
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['client_type'] = $user['client_type'] ?? 'walk-in';
                
                switch($user['role']) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'trainer':
                        header("Location: trainer_dashboard.php");
                        break;
                    case 'client':
                        if (($user['client_type'] === 'walk-in') || ($user['member_type'] === 'walk-in')) {
                            header("Location: walkin_dashboard.php");
                        } else {
                            header("Location: client_dashboard.php");
                        }
                        break;
                    default:
                        header("Location: index.php");
                }
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to Boiyets Fitness Gym Management System.">
    <title>Boiyets Fitness Gym — Sign In</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏋️</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #0b0f19;
            color: #e8ecf4;
            -webkit-font-smoothing: antialiased;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
            background: #0b0f19;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 40px 36px;
            background: #131a2b;
            border: 1px solid #1e2740;
            border-radius: 16px;
            position: relative;
            z-index: 1;
            animation: cardIn 0.45s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-area {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: rgba(232, 160, 18, 0.12);
            color: #e8a012;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 14px;
            border: 1px solid rgba(232, 160, 18, 0.25);
        }

        .brand-area h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.03em;
            color: #e8ecf4;
        }

        .brand-area h1 span { color: #e8a012; }

        .brand-area p {
            color: #5b6478;
            font-size: 0.82rem;
            margin: 6px 0 0 0;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 600;
        }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #8a94a6;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            height: 44px;
            background: #0d1220;
            border: 1px solid #1e2740;
            border-radius: 8px;
            color: #e8ecf4;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            padding: 0 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-wrap input:focus {
            outline: none;
            border-color: #e8a012;
            box-shadow: 0 0 0 3px rgba(232, 160, 18, 0.15);
        }

        .input-wrap input::placeholder { color: #3e4a5e; }

        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #5b6478;
            cursor: pointer;
            padding: 4px;
            font-size: 0.95rem;
        }

        .toggle-pw:hover { color: #e8a012; }

        .btn-signin {
            width: 100%;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: #e8a012;
            color: #0b0f19;
            transition: all 0.2s ease;
        }

        .btn-signin:hover {
            background: #f0b840;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(232, 160, 18, 0.35);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #ef4444;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .divider {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #1e2740;
        }

        .divider-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #8a94a6;
            margin-bottom: 10px;
        }

        .demo-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #0d1220;
            border: 1px solid #1e2740;
            border-radius: 8px;
            color: #8a94a6;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .chip:hover {
            border-color: #e8a012;
            color: #e8a012;
            background: rgba(232, 160, 18, 0.08);
        }

        .signup-cta {
            margin-top: 22px;
            text-align: center;
            font-size: 0.85rem;
            color: #5b6478;
        }

        .signup-cta a {
            color: #e8a012;
            text-decoration: none;
            font-weight: 700;
        }

        .signup-cta a:hover { color: #f0b840; }

        @media (max-width: 480px) {
            .login-card { padding: 28px 22px; }
            .brand-area h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="brand-area">
                <div class="brand-icon"><i class="fa-solid fa-dumbbell"></i></div>
                <h1>BOIYETS <span>GYM</span></h1>
                <p>Fitness Management Portal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw()" aria-label="Toggle password"><i class="fa-solid fa-eye" id="pwIcon"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn-signin">
                    Sign In <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="divider">
                <div class="divider-label">Quick Demo Access</div>
                <div class="demo-chips">
                    <span class="chip" onclick="fill('admin','password')"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                    <span class="chip" onclick="fill('trainer','password')"><i class="fa-solid fa-user-ninja"></i> Trainer</span>
                    <span class="chip" onclick="fill('client','password')"><i class="fa-solid fa-user"></i> Client</span>
                </div>
            </div>

            <div class="signup-cta">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </div>
        </div>
    </div>

    <script>
        function fill(u, p) {
            const un = document.getElementById('username');
            const pw = document.getElementById('password');
            un.value = u;
            pw.value = p;
            un.style.borderColor = '#e8a012';
            pw.style.borderColor = '#e8a012';
            setTimeout(() => { un.style.borderColor = ''; pw.style.borderColor = ''; }, 600);
        }
        function togglePw() {
            const pw = document.getElementById('password');
            const ic = document.getElementById('pwIcon');
            if (pw.type === 'password') { pw.type = 'text'; ic.classList.replace('fa-eye', 'fa-eye-slash'); }
            else { pw.type = 'password'; ic.classList.replace('fa-eye-slash', 'fa-eye'); }
        }
    </script>
</body>
</html>
