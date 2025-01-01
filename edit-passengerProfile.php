<?php
// Start session for user authentication
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "travel";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch logged-in user's ID
$user_id = $_SESSION['user_id'];

// Fetch the user's data
$sql = "SELECT * FROM passengers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "User not found.";
    exit();
}

$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $tel = $_POST['tel'];
    $password = $_POST['password'];
    $photo = $_FILES['photo'];

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($tel)) {
        $errors[] = "Telephone number is required.";
    }

    // File upload logic
    $photo_path = $user['photo_path']; // Default to the current photo path
    if (!empty($photo['name'])) {
        $upload_dir = 'uploads/';
        $photo_path = $upload_dir . basename($photo['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = pathinfo($photo_path, PATHINFO_EXTENSION);

        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }

        if (!move_uploaded_file($photo['tmp_name'], $photo_path)) {
            $errors[] = "Failed to upload profile photo.";
        }
    }

    // If no errors, update the database
    if (empty($errors)) {
        $sql = "UPDATE passengers SET full_name = ?, email = ?, phone = ?, photo_path = ? WHERE id = ?";
        if (!empty($password)) {
            $sql = "UPDATE passengers SET full_name = ?, email = ?, phone = ?, password = ?, photo_path = ? WHERE id = ?";
        }

        $stmt = $conn->prepare($sql);

        // Hash password if provided
        $hashed_password = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

        // Bind parameters
        if (!empty($password)) {
            $stmt->bind_param("sssssi", $name, $email, $tel, $hashed_password, $photo_path, $user_id);
        } else {
            $stmt->bind_param("sssii", $name, $email, $tel, $photo_path, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully.";
            header("Location: passenger-dashboard.php");
            exit();
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }

        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Passenger Profile</title>
    <link rel="stylesheet" href="../CSS-File/edit-passengerProfile.css">
</head>
<body>
    <div class="dashboard-container">
        <h2>Edit Profile</h2>
        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="edit-profile-form">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['full_name']); ?>">

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">

            <label for="tel">Telephone:</label>
            <input type="tel" id="tel" name="tel" value="<?php echo htmlspecialchars($user['phone']); ?>">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" placeholder="Enter new password">

            <label for="photo">Profile Photo:</label>
            <input type="file" id="photo" name="photo">

            <div class="button-group">
                <button type="submit" class="save-changes-btn">Save Changes</button>
                <a href="passenger-dashboard.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
