<?php

require_once __DIR__ . '/../includes/web_boot.php';

$conn = db_conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT id, full_name, password_hash FROM users WHERE email = $1 LIMIT 1";
    $res = pg_query_params($conn, $sql, [$email]);

    if (!$res || pg_num_rows($res) === 0) {
        die('Invalid email or password.');
    }

    $user = pg_fetch_assoc($res);

    if (!password_verify($password, $user['password_hash'])) {
        die('Invalid email or password.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];

    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Finance Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            padding: 3rem;
            max-width: 450px;
            width: 100%;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .field {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #1f2937;
        }

        input {
            width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            font-size: 1rem;
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
        }

        .link {
            display: block;
            width: 100%;
            background: transparent;
            border: 2px solid #e5e7eb;
            color: #1f2937;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome Back</h1>
    <form method="POST" action="login.php">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" placeholder="Enter your email" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="Enter your password" required>
        </div>
        <button type="submit">Login</button>
        <a href="signup.php" class="link">Don't have an account? Sign up</a>
    </form>
</div>

<!-- JAVASCRIPT FORCE FIX - THIS WILL WORK -->
<script>
    // Remove any dark mode
    document.body.classList.remove('dark-mode');
    document.documentElement.classList.remove('dark-mode');

    // Force styles on ALL inputs immediately
    function forceInputStyles() {
        var inputs = document.querySelectorAll('input');
        inputs.forEach(function(input) {
            // Set inline styles (highest priority)
            input.style.backgroundColor = '#ffffff';
            input.style.color = '#000000';
            input.style.caretColor = '#000000';
            input.style.setProperty('background-color', '#ffffff', 'important');
            input.style.setProperty('color', '#000000', 'important');
            input.style.setProperty('-webkit-text-fill-color', '#000000', 'important');
        });
    }

    // Run immediately
    forceInputStyles();

    // Run when DOM is ready
    document.addEventListener('DOMContentLoaded', forceInputStyles);

    // Run on every input event to catch any CSS trying to override
    document.addEventListener('input', forceInputStyles);
    document.addEventListener('focus', forceInputStyles, true);
    document.addEventListener('blur', forceInputStyles, true);

    // Force on a loop for the first 3 seconds (overkill but will work)
    var counter = 0;
    var interval = setInterval(function() {
        forceInputStyles();
        counter++;
        if (counter > 30) clearInterval(interval); // Stop after 3 seconds
    }, 100);

    // Add CSS to override autofill
    var style = document.createElement('style');
    style.textContent = `
    input, input:focus, input:active, input:hover {
        background-color: #ffffff !important;
        color: #000000 !important;
        -webkit-text-fill-color: #000000 !important;
        caret-color: #000000 !important;
    }
    input::placeholder {
        color: #9ca3af !important;
        opacity: 1 !important;
    }
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px white inset !important;
        -webkit-text-fill-color: #000000 !important;
    }
`;
    document.head.appendChild(style);
</script>

</body>
</html>