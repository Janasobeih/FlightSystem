<?php
session_start();

// Check if the user is logged in as a passenger
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'passenger') {
    header("Location: login.php?type=passenger");  
    exit();
}

$host = 'localhost';
$dbname = 'travel';
$username = 'root';
$password = '';

$connect = mysqli_connect($host, $username, $password, $dbname);

if (!$connect) {
    die("Database connection failed: " . mysqli_connect_error());
}

$flight_id = $_GET['id'] ?? null; 
$flight = null;
$error = null;
$successMessage = null;
$errorMessage = null;
$isAlreadyRegistered = false;
$passengerLimitReached = false;

if ($flight_id) {
    // Fetch flight details from the database, including num_of_passengers (passenger limit)
    $query = "SELECT flight_name, departure, arrival, flight_time, fees, company_id, num_of_passengers FROM flights WHERE flight_id = ?";
    $stmt = $connect->prepare($query);

    if ($stmt === false) {
        die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
    }

    $stmt->bind_param("i", $flight_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $flight = $result->fetch_assoc();

        // Fetch the number of registered passengers for the flight
        $query = "SELECT COUNT(*) AS registered_count FROM flight_passengers WHERE flight_id = ? AND status = 'registered'";
        $stmt = $connect->prepare($query);
        $stmt->bind_param("i", $flight_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $registeredCount = $result->fetch_assoc()['registered_count'];

        // Check if the number of registered passengers has reached the limit
        if ($registeredCount >= $flight['num_of_passengers']) {
            $passengerLimitReached = true;
        }

        // Check if the user is already registered for this flight
        $user_id = $_SESSION['user_id'];
        $checkRegistrationQuery = "SELECT * FROM flight_passengers WHERE flight_id = ? AND passenger_id = ? AND (status = 'registered' OR status = 'pending')";
        $stmt = $connect->prepare($checkRegistrationQuery);
        if ($stmt === false) {
            die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
        }
        $stmt->bind_param("ii", $flight_id, $user_id);
        $stmt->execute();
        $checkResult = $stmt->get_result();

        if ($checkResult->num_rows > 0) {
            $isAlreadyRegistered = true;
        }
    } else {
        $error = "Flight not found.";
    }
    $stmt->close();
} else {
    $error = "No flight selected.";
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $sender = $_SESSION['user_id'];
    $company_id = $flight['company_id'] ?? null;

    if (!empty($message) && $company_id) {
        if ($isAlreadyRegistered) {
            $errorMessage = "You're already on that flight.";
        } else {
            $insertQuery = "INSERT INTO messages (sender, message, company_id) VALUES (?, ?, ?)";
            $stmt = $connect->prepare($insertQuery);
            if ($stmt === false) {
                die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
            }
            $stmt->bind_param("isi", $sender, $message, $company_id);
            if ($stmt->execute()) {
                $successMessage = "Message sent successfully!";
            } else {
                $errorMessage = "Failed to send the message.";
            }
            $stmt->close();
        }
    } else {
        $errorMessage = "Message cannot be empty.";
    }
}

// Handle payment (account or cash)
if (isset($_POST['payment_method'])) {
    if ($isAlreadyRegistered) {
        $errorMessage = "You're already on that flight.";
    } elseif ($passengerLimitReached) {
        $errorMessage = "The flight has reached its passenger limit.";
    } else {
        $payment_method = $_POST['payment_method'];
        $flight_fees = $flight['fees'] ?? 0;
        $company_id = $flight['company_id'] ?? null;

        if ($payment_method === 'account') {
            // Fetch user's account balance
            $query = "SELECT account FROM passengers WHERE id = ?";
            $stmt = $connect->prepare($query);
            if ($stmt === false) {
                die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user_account = $result->fetch_assoc()['account'];
            
                // Check if the user has enough funds
                if ($user_account >= $flight_fees) {
                    // Begin transaction to update both accounts
                    mysqli_begin_transaction($connect);

                    try {
                        // Deduct the fee from the user's account
                        $new_user_account = $user_account - $flight_fees;
                        $query = "UPDATE passengers SET account = ? WHERE id = ?";
                        $stmt = $connect->prepare($query);
                        if ($stmt === false) {
                            die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
                        }
                        $stmt->bind_param("di", $new_user_account, $user_id);
                        $stmt->execute();

                        // Add the fee to the company's account
                        $query = "SELECT account FROM companies WHERE id = ?";
                        $stmt = $connect->prepare($query);
                        if ($stmt === false) {
                            die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
                        }
                        $stmt->bind_param("i", $company_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $company_account = $result->fetch_assoc()['account'];
                        $new_company_account = $company_account + $flight_fees;

                        $query = "UPDATE companies SET account = ? WHERE id = ?";
                        $stmt = $connect->prepare($query);
                        if ($stmt === false) {
                            die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
                        }
                        $stmt->bind_param("di", $new_company_account, $company_id);
                        $stmt->execute();

                        // Add the passenger to the flight_passengers table
                        $status = 'registered';
                        $insertQuery = "INSERT INTO flight_passengers (flight_id, company_id, passenger_id, status) VALUES (?, ?, ?, ?)";
                        $stmt = $connect->prepare($insertQuery);
                        if ($stmt === false) {
                            die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
                        }
                        $stmt->bind_param("iiis", $flight_id, $company_id, $user_id, $status);
                        $stmt->execute();

                        // Commit transaction
                        mysqli_commit($connect);

                        $successMessage = "Payment successful! You have been registered for the flight.";
                    } catch (Exception $e) {
                        // Rollback transaction in case of error
                        mysqli_roll_back($connect);
                        $errorMessage = "Transaction failed: " . $e->getMessage();
                    }
                } else {
                    $errorMessage = "Insufficient funds in your account.";
                }
            } else {
                $errorMessage = "Error retrieving user account.";
            }
            $stmt->close();
        } elseif ($payment_method === 'cash') {
            // Add the passenger to the flight_passengers table with pending status
            $status = 'pending';
            $insertQuery = "INSERT INTO flight_passengers (flight_id, company_id, passenger_id, status) VALUES (?, ?, ?, ?)";
            $stmt = $connect->prepare($insertQuery);
            if ($stmt === false) {
                die('MySQL prepare failed: ' . mysqli_error($connect));  // Handle query preparation error
            }
            $stmt->bind_param("iiis", $flight_id, $company_id, $user_id, $status);
            if ($stmt->execute()) {
                $successMessage = "Payment via cash selected. You are now marked as a pending passenger.";
            } else {
                $errorMessage = "Failed to register passenger for cash payment.";
            }
            $stmt->close();
        }
    }
}

mysqli_close($connect);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Info</title>
    <link rel="stylesheet" href="../CSS-File/flight-info.css">
</head>
<body>
    <div class="container">
        <h1>Flight Information</h1>

        <?php if (!empty($flight)): ?>
            <!-- Displaying Flight Details -->
            <table class="flight-details">
                <tr>
                    <th>Flight Name</th>
                    <td><?php echo htmlspecialchars($flight['flight_name']); ?></td>
                </tr>
                <tr>
                    <th>Origin</th>
                    <td><?php echo htmlspecialchars($flight['departure']); ?></td>
                </tr>
                <tr>
                    <th>Destination</th>
                    <td><?php echo htmlspecialchars($flight['arrival']); ?></td>
                </tr>
                <tr>
                    <th>Time</th>
                    <td><?php echo htmlspecialchars($flight['flight_time']); ?></td>
                </tr>
                <tr>
                    <th>Price</th>
                    <td>$<?php echo htmlspecialchars($flight['fees']); ?></td>
                </tr>
            </table>
        <?php elseif (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <!-- Take It Option -->
        <div class="payment-options">
            <h2>Take This Flight?</h2>
            <form method="POST">
                <button type="submit" name="payment_method" value="account">Pay from Account</button>
                <button type="submit" name="payment_method" value="cash">Pay with Cash</button>
            </form>
            <p id="payment-message">
                <?php if (isset($successMessage)) {
                    echo $successMessage;
                } elseif (isset($errorMessage)) {
                    echo $errorMessage;
                } ?>
            </p>
        </div>

        <!-- Message Company -->
        <div class="message-section">
            <h2>Message the Company</h2>
            <form method="post">
                <textarea name="message" placeholder="Write your message here..." required></textarea>
                <button type="submit">Send Message</button>
            </form>
        </div>
    </div>

    <script>
        function selectPayment(type) {
            const message = document.getElementById('payment-message');
            if (type === 'account') {
                message.textContent = 'Payment from your account is selected.';
            } else if (type === 'cash') {
                message.textContent = 'Payment via cash is selected.';
            }
        }
    </script>
</body>
</html>
