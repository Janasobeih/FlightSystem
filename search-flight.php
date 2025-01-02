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

$flights = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user inputs
    $departure = $_POST['from_location'] ?? '';
    $arrival = $_POST['to_location'] ?? '';

    // Validate inputs
    if (empty($departure) || empty($arrival)) {
        $error = "Both origin and destination are required.";
    } else {
        // Search for flights based on departure and arrival
        $sql = "SELECT flight_id, flight_name, departure, arrival, flight_time, fees FROM flights WHERE departure = ? AND arrival = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $departure, $arrival);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if any flights are found
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $flights[] = $row;
            }
        } else {
            $error = "No flights found for the specified origin and destination.";
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
    <title>Search Flights</title>
    <link rel="stylesheet" href="../CSS-File/search-flights.css">
</head>
<body>
    <div class="search-flight-container-wrapper">
        <!-- Search Flight Box -->
        <div class="search-flight-box">
            <h1>Search a Flight</h1>
            <div class="form-group">
                <label for="itinerary">Itinerary</label>
                <textarea id="itinerary" name="itinerary" rows="4" placeholder="Enter flight itinerary" required></textarea>
            </div>
            <!-- Search Form -->
            <form id="search-flight-form" method="POST">
                <div class="form-group">
                    <label for="from-location">From</label>
                    <input type="text" id="from-location" name="from_location" placeholder="Enter your origin city" required>
                </div>
                <div class="form-group">
                    <label for="to-location">To</label>
                    <input type="text" id="to-location" name="to_location" placeholder="Enter your destination city" required>
                </div>
                <div class="form-group">
                    <button type="submit" id="search-btn">Search</button>
                </div>
            </form>

            <!-- Display Errors -->
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Flights List -->
        <div class="flight-list-container">
            <h2>Available Flights</h2>
            <table class="flight-list">
                <thead>
                    <tr>
                        <th>Flight Name</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Time</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody id="flight-rows">
                 <?php if (!empty($flights)): ?>
                 <?php foreach ($flights as $flight): ?>
                 <tr onclick="window.location.href='flight-info.php?id=<?php echo htmlspecialchars($flight['flight_id']); ?>'">
                 <td><?php echo htmlspecialchars($flight['flight_name']); ?></td>
                 <td><?php echo htmlspecialchars($flight['departure']); ?></td>
                 <td><?php echo htmlspecialchars($flight['arrival']); ?></td>
                 <td><?php echo htmlspecialchars($flight['flight_time']); ?></td>
                 <td><?php echo htmlspecialchars($flight['fees']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="5">No flights available.</td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
