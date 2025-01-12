<?php
session_start(); // Start the session

// Check if the user is logged in as a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
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
    echo json_encode(["success" => false, "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit();
}

$query = "SELECT m.*, p.full_name, c.company_name 
          FROM messages m
          LEFT JOIN passengers p ON m.sender = p.id
          LEFT JOIN companies c ON m.company_id = c.id
          WHERE m.company_id = ?";

$stmt = $connect->prepare($query);

if ($stmt === false) {
    // Handle the error: prepared statement failed
    exit();
}

$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submission for replying
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'], $_POST['reply'])) {
    $message_id = $_POST['message_id']; // Get the message_id from the form
    $reply = trim($_POST['reply']); // Get the reply from the form

    if (!empty($reply)) {
        // Prepare the update query to reply by message_id
        $updateQuery = "UPDATE messages SET reply = ? WHERE message_id = ? AND company_id = ?";

        $stmt = $connect->prepare($updateQuery);

        if ($stmt === false) {
            // Handle the error: prepared statement failed
            exit();
        }
        

        // Bind parameters
        $stmt->bind_param("sii", $reply, $message_id, $company_id);

        // Execute and check if successful
        if ($stmt->execute()) {
        } else {
            echo json_encode(["success" => false, "message" => "Failed to send reply: " . mysqli_error($connect)]);
        }
        
        $stmt->close();
    }
}

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
                    <th>Reply</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr>
                        <td colspan="4">No messages found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= htmlspecialchars($message['full_name'] ?? 'Unknown Sender'); ?></td>
                            <td><?= htmlspecialchars($message['message']); ?></td>
                            <td><?= htmlspecialchars($message['reply'] ?? 'No reply yet'); ?></td>
                            <td>
                                <form action="" method="POST">
                                    <textarea name="reply" placeholder="Type your reply here..."></textarea>
                                    <!-- Hidden input to send the message_id -->
                                    <input type="hidden" name="message_id" value="<?= htmlspecialchars($message['message_id']); ?>">
                                    <button type="submit">Reply</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
