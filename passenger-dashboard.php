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

// Check if the user is logged in (this assumes login.php sets a session variable)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Fetch logged-in user's details
$user_id = $_SESSION['user_id'];
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

// Fetch flights associated with the logged-in user
$flights_sql = "SELECT f.flight_id, f.departure, f.arrival, f.flight_time, fp.status 
                FROM flight_passengers fp
                JOIN flights f ON fp.flight_id = f.flight_id
                WHERE fp.passenger_id = ?";

$flights_stmt = $conn->prepare($flights_sql);
$flights_stmt->bind_param("i", $user_id);
$flights_stmt->execute();
$flights_result = $flights_stmt->get_result();

// Close statements
$stmt->close();
$flights_stmt->close();

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Dashboard</title>
    <link rel="stylesheet" href="../CSS-File/passenger-dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Profile Section -->
        <div class="profile-section">
            <img src="<?php echo $user['photo_path']; ?>" alt="Passenger Image" class="profile-img">
            <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
            <p class="profile-tel">Tel: <?php echo htmlspecialchars($user['phone']); ?></p>
        </div>

        <!-- Flight Sections -->
        <div class="flights-section">
            <div class="completed-flights">
                <h3>Completed Flights</h3>
                <ul>
                    <?php
                    // Fetch and display completed flights
                    while ($flight = $flights_result->fetch_assoc()) {
                        // Check if the flight date has passed
                        if (strtotime($flight['flight_time']) < time()) {
                            // Flight has passed, mark it as completed
                            if ($flight['status'] != 'completed') {
                                // Update the flight status to 'completed' in the database
                                $conn = new mysqli($servername, $username, $password, $dbname);
                                $update_status_sql = "UPDATE flight_passengers SET status = 'completed' WHERE flight_id = ? AND passenger_id = ?";
                                $update_status_stmt = $conn->prepare($update_status_sql);
                                $update_status_stmt->bind_param("ii", $flight['flight_id'], $user_id);
                                $update_status_stmt->execute();
                                $update_status_stmt->close();
                                $conn->close();
                            }

                            // Display the completed flight
                            echo "<li>Flight: " . htmlspecialchars($flight['departure']) . " to " . htmlspecialchars($flight['arrival']) . " - Status: Completed</li>";
                        }
                    }
                    ?>
                </ul>
            </div>

            <div class="current-flights">
                <h3>Current Flights</h3>
                <ul>
                    <?php
                    // Reset the result pointer and fetch again for current flights
                    $flights_result->data_seek(0);
                    while ($flight = $flights_result->fetch_assoc()) {
                        // Check if the flight is pending or registered
                        if (strtotime($flight['flight_time']) >= time() && ($flight['status'] == 'pending' || $flight['status'] == 'registered')) {
                            echo "<li>Flight: " . htmlspecialchars($flight['departure']) . " to " . htmlspecialchars($flight['arrival']) . " - Status: " . htmlspecialchars($flight['status']) . "</li>";
                        }
                    }
                    ?>
                </ul>
            </div>
        </div>

        <!-- Profile Edit Section -->
        <div class="profile-edit">
            <a href="edit-passengerProfile.php" class="edit-profile-btn">Edit Profile</a>
        </div>

        <!-- Flight Search Section -->
        <div class="search-section">
            <a href="search-flight.php" class="search-btn">Search Flights</a>
        </div>
    </div>
</body>
</html>
