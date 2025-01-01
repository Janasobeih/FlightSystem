<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "travel";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables for errors and success messages
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $companyName = htmlspecialchars(trim($_POST['companyName']));
    $bio = htmlspecialchars(trim($_POST['bio']));
    $address = htmlspecialchars(trim($_POST['address']));
    $location = htmlspecialchars(trim($_POST['location'])); // Optional
    $username = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $password = trim($_POST['password']);

    // Validate required fields
    if (!$companyName || !$bio || !$address || !$username || !$email || !$phone || !$password) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $error = 'Invalid phone number.';
    } else {
        // Check if email already exists in the companies table
        $emailCheckQuery = $conn->prepare("SELECT COUNT(*) FROM companies WHERE email = ?");
        $emailCheckQuery->bind_param("s", $email);
        $emailCheckQuery->execute();
        $emailCheckQuery->bind_result($emailCount);
        $emailCheckQuery->fetch();
        $emailCheckQuery->close();

        if ($emailCount > 0) {
            $error = 'Email is already registered.';
        } else {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Handle file upload for the company logo (similar to passenger registration)
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoTmpName = $_FILES['logo']['tmp_name'];
                $logoName = basename($_FILES['logo']['name']);
                $logoType = $_FILES['logo']['type'];
                $logoSize = $_FILES['logo']['size'];

                // Validate file type (image only)
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($logoType, $allowedTypes)) {
                    $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                } elseif ($logoSize > 5000000) { // 5MB limit
                    $error = 'File size is too large. Maximum allowed size is 5MB.';
                } else {
                    // Define upload directory and file path
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    // Generate unique file name
                    $logoPath = $uploadDir . uniqid() . '-' . $logoName;

                    // Move the uploaded file
                    if (!move_uploaded_file($logoTmpName, $logoPath)) {
                        $error = 'Failed to upload logo.';
                    } else {
                        // Insert company data into the database
                        $stmt = $conn->prepare("INSERT INTO companies (company_name, bio, address, location, username, email, phone, password, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if (!$stmt) {
                            die("Error in query preparation: " . $conn->error);
                        }
                        $stmt->bind_param("sssssssss", $companyName, $bio, $address, $location, $username, $email, $phone, $hashedPassword, $logoPath);

                        if ($stmt->execute()) {
                            $success = "Registration successful!";
                            $_SESSION['userType'] = 'company';
                            header("Location: login.php?userType=company");
                            exit();
                        } else {
                            $error = "Error executing query: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            } else {
                $error = 'Logo is required and must be uploaded.';
            }
        }
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Registration</title>
    <link rel="stylesheet" href="../CSS-File/styles.css">
    <script>
        // Hide the success or error message after 5 seconds
        function hideMessage() {
            setTimeout(function() {
                var successMessage = document.querySelector('.success');
                var errorMessage = document.querySelector('.error');
                if (successMessage) successMessage.style.display = 'none';
                if (errorMessage) errorMessage.style.display = 'none';
            }, 5000); // 5 seconds delay
        }
    </script>
</head>
<body>
    <div class="registration-container">
        <div class="form-box">
            <h1>Company Registration</h1>
            <form action="" method="POST" enctype="multipart/form-data">
                <!-- Company Name -->
                <div class="form-group">
                    <label for="company-name">Company Name</label>
                    <input type="text" id="company-name" name="companyName" required>
                </div>

                <!-- Company Logo -->
                <div class="form-group">
                    <label for="company-logo">Company Logo</label>
                    <input type="file" id="company-logo" name="logo" accept="image/*" required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="company-email">Email</label>
                    <input type="email" id="company-email" name="email" required>
                </div>

                <!-- Address -->
                <div class="form-group">
                    <label for="company-address">Address</label>
                    <input type="text" id="company-address" name="address" required>
                </div>

                <!-- Bio -->
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Enter company bio" required></textarea>
                </div>

                <!-- Location -->
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="Enter company location">
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter phone number" required>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn">Register</button>

                <?php if ($error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php elseif ($success): ?>
                    <div class="success" onload="hideMessage()"><?php echo $success; ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Call hideMessage() to hide success/error message after 5 seconds
        <?php if ($success || $error): ?>
            hideMessage();
        <?php endif; ?>
    </script>
</body>
</html>
