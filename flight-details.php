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

    // Begin a transaction to ensure both queries are handled together
    mysqli_begin_transaction($connect);

    try {
        // Fetch the flight fee
        $query_flight_fee = "SELECT fees FROM flights WHERE flight_id = $flight_id";
        $result_fee = mysqli_query($connect, $query_flight_fee);
        if (!$result_fee) {
            throw new Exception("Error fetching flight fee: " . mysqli_error($connect));
        }

        
        $flight_fee = mysqli_fetch_assoc($result_fee)['fees'];

        // Update the registered passengers' accounts
        // Fetch the registered passengers' ids
        $query_registered = "
        SELECT p.id
        FROM flight_passengers fp 
        JOIN passengers p ON fp.passenger_id = p.id 
        WHERE fp.flight_id = $flight_id AND fp.status = 'registered'"; 

        $result_registered_passengers = mysqli_query($connect, $query_registered);
        if (!$result_registered_passengers) {
            throw new Exception("Error fetching registered passengers: " . mysqli_error($connect));
        }

        // Update the registered passengers' accounts
        while ($row = mysqli_fetch_assoc($result_registered_passengers)) {
            $passenger_id = $row['id'];
            // Add the flight fee to the passenger's account
            $query_add_to_passenger = "UPDATE passengers SET account = account + $flight_fee WHERE id = $passenger_id";
            if (!mysqli_query($connect, $query_add_to_passenger)) {
                throw new Exception("Error updating passenger account: " . mysqli_error($connect));
            }
        }

         // Update the company account
         $query_company_deduction = "UPDATE companies SET account = account - $flight_fee WHERE id = $company_id";
         if (!mysqli_query($connect, $query_company_deduction)) {
             throw new Exception("Error updating company account: " . mysqli_error($connect));
         }

        // Query to delete from flight_passengers
        $query2 = "DELETE FROM flight_passengers WHERE flight_id = $flight_id";
        if (!mysqli_query($connect, $query2)) {
            throw new Exception("Error deleting passengers: " . mysqli_error($connect));
        }

        // Query to delete the flight
        $query = "DELETE FROM flights WHERE flight_id = $flight_id";
        if (!mysqli_query($connect, $query)) {
            throw new Exception("Error deleting flight: " . mysqli_error($connect));
        }

        // Commit the transaction if all queries succeed
        mysqli_commit($connect);
        echo json_encode(["success" => true]);

    } 
    
    catch (Exception $e) {
        // Rollback transaction if any query fails
        mysqli_rollback($connect);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
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
    WHERE fp.flight_id = $flight_id AND fp.status = 'pending'"; 

$result_pending_passengers = mysqli_query($connect, $query_pending);

if (!$result_pending_passengers) {
    die("Query failed: " . mysqli_error($connect));
}

$pending_passengers = [];
while ($row = mysqli_fetch_assoc($result_pending_passengers)) {
    $pending_passengers[] = $row['full_name'];
}

// Fetch registered passengers
$query_registered = "
    SELECT p.full_name 
    FROM flight_passengers fp 
    JOIN passengers p ON fp.passenger_id = p.id 
    WHERE fp.flight_id = $flight_id AND fp.status = 'registered'"; 

$result_registered_passengers = mysqli_query($connect, $query_registered);

if (!$result_registered_passengers) {
    die("Query failed: " . mysqli_error($connect));
}

$registered_passengers = [];
while ($row = mysqli_fetch_assoc($result_registered_passengers)) {
    $registered_passengers[] = $row['full_name'];
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
            const pendingPassengers = <?php echo json_encode($pending_passengers); ?>; 
            const registeredPassengers = <?php echo json_encode($registered_passengers); ?>; 

            const pendingPassengersList = document.getElementById("pending-passengers");
            const registeredPassengersList = document.getElementById("registered-passengers");

            pendingPassengers.forEach(function(name) {
                const listItem = document.createElement("li");
                listItem.textContent = name;
                pendingPassengersList.appendChild(listItem);
            });

            registeredPassengers.forEach(function(name) {
                const listItem = document.createElement("li");
                listItem.textContent = name;
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
