<?php
session_start(); // Start the session

// Check if the user is logged in as a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    header("Location: login.php?type=company");
    exit();
}

$company_id = $_SESSION['user_id']; // Get the company ID from the session

$host = 'localhost';
$dbname = 'travel';
$username = 'root';
$password = '';

// Create connection
$connect = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$connect) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch messages from the database with sender's name
$query = "SELECT u.full_name AS sender_name, m.message
          FROM messages m
          JOIN passengers u ON m.sender = u.id
          WHERE m.company_id = $company_id";

$result = mysqli_query($connect, $query);

// Check if the query was successful
if (!$result) {
    die("Query failed: " . mysqli_error($connect));
}

// Fetch all the messages as an associative array
$messages = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Close the connection
mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="../CSS-File/messages.css">
</head>
<body>

    <!-- Messages Container -->
    <div class="messages-container">
        <header class="messages-header">
            <h1>Messages</h1>
        </header>

        <!-- Table of Messages -->
        <table class="messages-table">
            <thead>
                <tr>
                    <th>Sender</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($messages as $message) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($message['sender_name']) . "</td>"; // Display sender's name
                    echo "<td>" . htmlspecialchars($message['message']) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Reply Input (Initially Hidden) -->
        <div class="reply-section" id="replySection" style="display: none;">
            <textarea id="responseText" placeholder="Type your response here..."></textarea>
            <button class="send-reply-btn">Send Reply</button>
        </div>

        <div class="button" style="display: flex; flex-direction: column; align-items: center; gap: 20px;">
            <button onclick="window.location.href='../HTML-Files/company-dashboard.php'" 
                style="background-color: #007bff; color: white; border: none; padding: 10px 20px; font-size: 16px; cursor: pointer; transition: transform 0.2s ease, background-color 0.2s ease;" 
                onmouseover="this.style.backgroundColor='#0056b3'; this.style.transform='scale(1.05)';"
                onmouseout="this.style.backgroundColor='#007bff'; this.style.transform='scale(1)';"
                onclick="this.classList.add('clicked'); setTimeout(() => this.classList.remove('clicked'), 200);">
                Back to Dashboard
            </button>
        </div>

        <style>
            /* Button clicked animation */
            .button button.clicked {
                transform: scale(0.95);
                opacity: 0.8;
                transition: transform 0.2s ease, opacity 0.2s ease;
            }
        </style>

    </div>

    <script src="../JS-File/script.js"></script>

</body>
</html>
