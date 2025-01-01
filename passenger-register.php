<?php
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = htmlspecialchars(trim($_POST['fullName']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Secure password
    $phone = htmlspecialchars(trim($_POST['phone']));

    // Check if email already exists
    $emailCheckQuery = $conn->prepare("SELECT COUNT(*) FROM passengers WHERE email = ?");
    if ($emailCheckQuery === false) {
        die("Error preparing query: " . $conn->error);  // Handle query preparation error
    }
    
    $emailCheckQuery->bind_param("s", $email);
    $emailCheckQuery->execute();
    $emailCheckQuery->bind_result($emailCount);
    $emailCheckQuery->fetch();
    $emailCheckQuery->close();

    if ($emailCount > 0) {
        // Email already exists
        echo "<div id='emailError' class='error-message'>This email is already registered. Please use a different email.</div>";
    } else {
        // Handle file uploads and registration logic...
        // Prepare and bind SQL statement
        $stmt = $conn->prepare("INSERT INTO passengers (full_name, email, password, phone, photo_path, passport_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $fullName, $email, $password, $phone, $photoPath, $passportPath);

        if ($stmt->execute()) {
            // Redirect to login page after successful registration
            header("Location: login.php");
            exit(); // Make sure no further code is executed after the redirect
        } else {
            // Log error for debugging purposes
            error_log("Error: " . $stmt->error, 3, "errors.log");
            echo "<p class='error-message'>Error: There was an issue with the registration process.</p>";
        }

        $stmt->close();
    }
}

$conn->close(); // Close the connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Registration</title>
    <!-- Link to CSS -->
    <link rel="stylesheet" href="../CSS-File/styles.css">
</head>
<body>

    <div class="registration-container">
        <div class="form-box">
            <h1>Register as Passenger</h1>
            <form id="passengerRegisterForm" action="" method="POST" enctype="multipart/form-data">
                <!-- Full Name -->
                <div class="form-group">
                    <label for="fullName">Full Name:</label>
                    <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" class="form-control" required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" class="form-control" required>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" class="form-control" required>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" class="form-control" required>
                </div>

                <!-- Profile Photo -->
                <div class="form-group">
                    <label for="photo">Profile Photo:</label>
                    <input type="file" id="photo" name="photo" accept="image/*" class="form-control" required>
                </div>

                <!-- Passport Image -->
                <div class="form-group">
                    <label for="passport">Passport Image:</label>
                    <input type="file" id="passport" name="passport" accept="image/*" class="form-control" required>
                </div>

                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
            </form>
        </div>
    </div>
<style>
        .error-message {
            display: none; /* Initially hidden */
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 20px;
            background-color: #fff;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: #e74c3c;
            font-size: 18px;
            font-family: 'Arial', sans-serif;
            z-index: 1000;
            width: 80%;
            max-width: 400px;
            text-align: center;
            opacity: 0; /* Initially invisible */
            transition: opacity 0.5s ease;
        }

        .error-message.show {
            display: block; /* Show the message */
            opacity: 1; /* Fade in */
        }
    </style>
 <?php
    // This is where the error message is printed when the email already exists
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $emailCount = 1; // Simulating an existing email for demonstration
        if ($emailCount > 0) {
            echo "<div id='emailError' class='error-message'>This email is already registered. Please use a different email.</div>";
        }
    }
    ?>
<script>
        // Check if the email error message exists
        window.onload = function() {
            var errorMessage = document.getElementById('emailError');
            if (errorMessage) {
                // Show the error message with a smooth fade-in effect
                errorMessage.classList.add('show');

                // Hide the error message after 3 seconds (3000 milliseconds)
                setTimeout(function() {
                    errorMessage.classList.remove('show');
                }, 3000); // Adjust the duration as needed
            }
        };
    </script>

</body>
</html>
