<?php

require_once __DIR__ . '/../includes/web_boot.php';

$conn = db_conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($full_name === '' || $email === '' || $password === '') {
        die('All fields are required.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "
        INSERT INTO users (full_name, email, password_hash)
        VALUES ($1, $2, $3)
        RETURNING id
    ";
    $res = pg_query_params($conn, $sql, [$full_name, $email, $hash]);

    if (!$res) {
        die('Signup failed: ' . pg_last_error($conn));
    }

    $row = pg_fetch_assoc($res);
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['user_name'] = $full_name;

    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Finance Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- DO NOT LOAD style.css - It's causing the issue! -->
    <style>
        /* Complete reset and isolated styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-content {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
            text-align: center;
        }

        .panel-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
        }

        /* THE MOST IMPORTANT PART - FORCE WHITE BACKGROUND AND BLACK TEXT */
        input {
            width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            font-size: 1rem;
            transition: all 0.2s;
            font-family: inherit;

            /* FORCE THESE STYLES */
            background-color: #ffffff !important;
            color: #000000 !important;
            -webkit-text-fill-color: #000000 !important;
        }

        input::placeholder {
            color: #9ca3af !important;
            opacity: 1 !important;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        /* Chrome autofill fix */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px white inset !important;
            -webkit-text-fill-color: #000000 !important;
            color: #000000 !important;
        }

        button {
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            color: #ffffff;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            width: 100%;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .login-link {
            display: inline-block;
            width: 100%;
            background: transparent;
            border: 2px solid #e5e7eb;
            color: #1f2937;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            text-align: center;
            transition: all 0.2s;
        }

        .login-link:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
    </style>
</head>
<body>
<main class="main-content">
    <h2 class="panel-title">Join Us Today</h2>
    <form method="POST" action="signup.php">
        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                    id="full_name"
                    name="full_name"
                    type="text"
                    placeholder="Enter your full name"
                    required
                    autofocus
            >
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="Enter your email"
                    required
            >
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Create a password"
                    required
            >
        </div>
        <button type="submit">Create Account</button>
        <a href="login.php" class="login-link">Already have an account? Login</a>
    </form>
</main>
</body>
</html>