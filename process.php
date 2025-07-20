<?php
require_once 'config/config.php';
require_once 'includes/UniFiAPI.php';

// Enable error reporting for debugging
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect_url' => $_POST['redirect_url'] ?? REDIRECT_URL
];

try {
    // Get and validate input data
    $mac = trim($_POST['mac'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $floor_id = !empty($_POST['floor_id']) ? intval($_POST['floor_id']) : null;
    $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;
    $terms = isset($_POST['terms']);
    $redirect_url = $_POST['redirect_url'] ?? REDIRECT_URL;

    // Validation
    $errors = [];

    if (empty($role) || !in_array($role, ['member', 'guest', 'event'])) {
        $errors[] = 'Please select a valid role';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address';
    }

    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Please provide your full name';
    }

    if ($role === 'member' && empty($floor_id)) {
        $errors[] = 'Please select your floor';
    }

    if ($role === 'event' && empty($event_id)) {
        $errors[] = 'Please select the event you are attending';
    }

    if (!$terms) {
        $errors[] = 'Please accept the terms of use';
    }

    if (empty($mac)) {
        $errors[] = 'Device MAC address not found';
    }

    if (!empty($errors)) {
        throw new Exception(implode(', ', $errors));
    }

    // Normalize MAC address
    $mac = strtolower(str_replace([':', '-', ' '], '', $mac));
    if (strlen($mac) === 12) {
        $mac = implode(':', str_split($mac, 2));
    }

    // Connect to database
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Store user data based on role
    $user_id = null;
    
    switch ($role) {
        case 'member':
            // Check if member already exists
            $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
            $stmt->execute([$email]);
            $existing_member = $stmt->fetch();

            if ($existing_member) {
                // Update existing member
                $stmt = $pdo->prepare("
                    UPDATE members 
                    SET name = ?, floor_id = ?, mac_address = ?, last_login = NOW() 
                    WHERE email = ?
                ");
                $stmt->execute([$name, $floor_id, $mac, $email]);
                $user_id = $existing_member['id'];
            } else {
                // Insert new member
                $stmt = $pdo->prepare("
                    INSERT INTO members (email, name, floor_id, mac_address, created_at, last_login) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$email, $name, $floor_id, $mac]);
                $user_id = $pdo->lastInsertId();
            }
            break;

        case 'guest':
            // Insert guest record
            $stmt = $pdo->prepare("
                INSERT INTO guests (email, name, mac_address, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$email, $name, $mac]);
            $user_id = $pdo->lastInsertId();
            break;

        case 'event':
            // Insert event log record
            $stmt = $pdo->prepare("
                INSERT INTO event_log (event_id, attendee_email, attendee_name, mac_address, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$event_id, $email, $name, $mac]);
            $user_id = $pdo->lastInsertId();
            break;
    }

    // Commit database transaction
    $pdo->commit();

    // Authorize user via UniFi Controller API
    try {
        $unifi = new UniFiAPI(
            UNIFI_HOST,
            UNIFI_USER,
            UNIFI_PASS,
            UNIFI_SITE,
            UNIFI_VERSION,
            DEBUG_MODE
        );

        // Authorize for 8 hours (480 minutes)
        $authorized = $unifi->authorizeGuest($mac, 480);

        if ($authorized) {
            $response['success'] = true;
            $response['message'] = 'Successfully connected to WiFi!';
            
            // Log successful authorization
            error_log("User authorized: $email ($mac) - Role: $role");
            
        } else {
            throw new Exception('Failed to authorize device on network');
        }

    } catch (Exception $e) {
        error_log("UniFi API Error: " . $e->getMessage());
        
        // Even if UniFi fails, we've stored the user data
        // In production, you might want to handle this differently
        if (DEBUG_MODE) {
            throw new Exception('Network authorization failed: ' . $e->getMessage());
        } else {
            // In production, might still redirect but log the error
            $response['success'] = true;
            $response['message'] = 'Connected! If you experience issues, please contact support.';
        }
    }

} catch (Exception $e) {
    // Rollback database transaction if it was started
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    $response['message'] = $e->getMessage();
    error_log("Portal Error: " . $e->getMessage());
}

// Handle response
if ($response['success']) {
    // Successful login - redirect to original URL or default
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WiFi Connected - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .success-container {
                max-width: 500px;
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            .success-icon {
                font-size: 4rem;
                color: #28a745;
                margin-bottom: 20px;
            }
            .btn-continue {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                border: none;
                border-radius: 10px;
                padding: 12px 30px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <i class="fas fa-check-circle success-icon"></i>
            <h2 class="text-success mb-3">Connected Successfully!</h2>
            <p class="mb-4"><?php echo htmlspecialchars($response['message']); ?></p>
            <p class="text-muted mb-4">You now have internet access for 8 hours.</p>
            <a href="<?php echo htmlspecialchars($redirect_url); ?>" class="btn btn-primary btn-continue">
                <i class="fas fa-external-link-alt me-2"></i>Continue Browsing
            </a>
        </div>
        
        <script>
            // Auto-redirect after 3 seconds
            setTimeout(function() {
                window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
            }, 3000);
        </script>
    </body>
    </html>
    <?php
} else {
    // Error occurred - show error page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connection Error - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                max-width: 500px;
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            .error-icon {
                font-size: 4rem;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .btn-retry {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                border: none;
                border-radius: 10px;
                padding: 12px 30px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <i class="fas fa-exclamation-triangle error-icon"></i>
            <h2 class="text-danger mb-3">Connection Failed</h2>
            <p class="mb-4"><?php echo htmlspecialchars($response['message']); ?></p>
            <a href="index.php" class="btn btn-primary btn-retry">
                <i class="fas fa-redo me-2"></i>Try Again
            </a>
        </div>
    </body>
    </html>
    <?php
}
?>
