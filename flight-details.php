<?php
session_start(); // Start the session
// Check if the user is logged in as a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'company') {
    // Redirect to login page if not logged in as company
    header("Location: login.php?type=company");
    exit();
}
$company_id = $_SESSION['user_id']; 

$host = 'localhost';
$dbname = 'travel';
$username = 'root';
$password = '';

$connect = mysqli_connect($host, $username, $password, $dbname);

if (!$connect) {
    die("Database connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flight_id'])) {
    $flight_id = (int)$_POST['flight_id'];

    $query = "DELETE FROM flights WHERE flight_id = $flight_id";
    
    if (mysqli_query($connect, $query)) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting flight"]);
    }

    mysqli_close($connect);
    exit;
}

$flight_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

$query = "SELECT * FROM flights WHERE flight_id = $flight_id";
$result = mysqli_query($connect, $query);

$query = "SELECT * FROM companies WHERE id = $company_id";
$result1 = mysqli_query($connect, $query);

if (!$result1) {
    die("Query failed: " . mysqli_error($connect));
}

$companyInfo = mysqli_fetch_assoc($result1);

if (!$result) {
    die("Query failed: " . mysqli_error($connect));
}

$flight = mysqli_fetch_assoc($result);

// Fetch pending passengers
$query_pending = "
    SELECT p.full_name 
    FROM flight_passengers fp 
    JOIN passengers p ON fp.passenger_id = p.id 
    WHERE fp.flight_id = $flight_id AND fp.status = 'pending'"; // Assuming 'pending' is the status of pending passengers

$result_pending_passengers = mysqli_query($connect, $query_pending);

if (!$result_pending_passengers) {
    die("Query failed: " . mysqli_error($connect));
}

$pending_passengers = [];
while ($row = mysqli_fetch_assoc($result_pending_passengers)) {
    $pending_passengers[] = $row['full_name']; // Add passenger names to the array
}

// Fetch registered passengers
$query_registered = "
    SELECT p.full_name 
    FROM flight_passengers fp 
    JOIN passengers p ON fp.passenger_id = p.id 
    WHERE fp.flight_id = $flight_id AND fp.status = 'registered'"; // Assuming 'registered' is the status of registered passengers

$result_registered_passengers = mysqli_query($connect, $query_registered);

if (!$result_registered_passengers) {
    die("Query failed: " . mysqli_error($connect));
}

$registered_passengers = [];
while ($row = mysqli_fetch_assoc($result_registered_passengers)) {
    $registered_passengers[] = $row['full_name']; // Add passenger names to the array
}

mysqli_free_result($result_pending_passengers);
mysqli_free_result($result_registered_passengers);

mysqli_close($connect);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Details</title>
    <link rel="stylesheet" href="../CSS-File/styles.css">
</head>
<body>
  
    <div class="flight-details-container">
      
        <header class="flight-header">
            <img src="" alt="Company Logo" class="company-logo">
            <h1 class="company-name"><?php echo $companyInfo["company_name"]; ?></h1> 
        </header>

        <section class="flight-info">
            <h2>Flight Information</h2>
            <p><strong>ID:</strong> <?php echo htmlspecialchars($flight['flight_id']); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($flight['flight_name']); ?></p>
            <p><strong>Itinerary:</strong> <?php echo htmlspecialchars($flight['departure'] . " - " . $flight['arrival']); ?></p>
        </section>

        <section class="passenger-list">
            <h3>Pending Passengers</h3>
            <ul id="pending-passengers">
                <!-- Pending passengers will be added here dynamically -->
            </ul>
        </section>

        <section class="passenger-list">
            <h3>Registered Passengers</h3>
            <ul id="registered-passengers">
                <!-- Registered passengers will be added here dynamically -->
            </ul>
        </section>

        <section class="cancel-flight">
            <button id="cancel-flight-btn" data-flight-id="<?php echo $flight['flight_id']; ?>">Cancel Flight and Refund Fees</button>
        </section>

        <div class="navigation-buttons">
            <button onclick="window.location.href='company-dashboard.php'">Back to Dashboard</button>
        </div>
    </div>

    <script src="../JS-File/script.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const pendingPassengers = <?php echo json_encode($pending_passengers); ?>; // Pass the array of names from PHP to JS
            const registeredPassengers = <?php echo json_encode($registered_passengers); ?>; // Pass the array of names from PHP to JS

            const pendingPassengersList = document.getElementById("pending-passengers");
            const registeredPassengersList = document.getElementById("registered-passengers");
            
            // Add each pending passenger to the list
            pendingPassengers.forEach(function(name) {
                const listItem = document.createElement("li");
                listItem.textContent = name; // Set the passenger name
                pendingPassengersList.appendChild(listItem);
            });

            // Add each registered passenger to the list
            registeredPassengers.forEach(function(name) {
                const listItem = document.createElement("li");
                listItem.textContent = name; // Set the passenger name
                registeredPassengersList.appendChild(listItem);
            });
        });

        document.getElementById("cancel-flight-btn").addEventListener("click", function() {
            const flightId = this.getAttribute("data-flight-id");

            if (confirm("Are you sure you want to cancel this flight? This action cannot be undone.")) {
             
                fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({ flight_id: flightId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Flight cancelled successfully.");
                        window.location.href = "company-dashboard.php"; 
                    } else {
                        alert("Failed to cancel the flight. Please try again.");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("An error occurred. Please try again.");
                });
            }
        });
    </script> 
</body>
</html>
