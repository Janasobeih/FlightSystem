<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if the user is logged in and redirect them based on their role
if (isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if ($role == 'passenger') {
        header("Location: passenger-dashboard.php");
        exit();
    } elseif ($role == 'company') {
        header("Location: company-dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <!-- Link to the CSS file -->
    <link rel="stylesheet" href="../CSS-File/styles.css">
</head>
<body>
    <div class="landing-container">
        <!-- Content Overlay -->
        <div class="overlay">
            <h1>Welcome to BlueSkies Travel</h1>
            <p>Choose your role to continue:</p>
            <div class="buttons">
                <a href="login.php?type=passenger" class="btn">Passenger</a>
                <a href="login.php?type=company" class="btn">Company</a>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>
