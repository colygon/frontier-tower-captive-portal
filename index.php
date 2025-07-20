<?php
require_once 'config/config.php';

// Get client MAC address and IP
$client_mac = $_GET['id'] ?? $_POST['id'] ?? '';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$redirect_url = $_GET['url'] ?? REDIRECT_URL;

// Get available floors and events from database
try {
    $pdo = getDBConnection();
    
    $floors_stmt = $pdo->query("SELECT * FROM floors WHERE active = 1 ORDER BY floor_number");
    $floors = $floors_stmt->fetchAll();
    
    $events_stmt = $pdo->query("SELECT * FROM events WHERE active = 1 ORDER BY name");
    $events = $events_stmt->fetchAll();
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        die("Database error: " . $e->getMessage());
    }
    $floors = [];
    $events = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - WiFi Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .portal-container {
            max-width: 500px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .portal-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .portal-body {
            padding: 40px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
        }
        .btn-connect {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-connect:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.3);
        }
        .role-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .role-card:hover {
            border-color: #4facfe;
            background: rgba(79, 172, 254, 0.05);
        }
        .role-card.active {
            border-color: #4facfe;
            background: rgba(79, 172, 254, 0.1);
        }
        .role-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #4facfe;
        }
        .conditional-field {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .wifi-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .terms-text {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="portal-container">
            <div class="portal-header">
                <i class="fas fa-wifi wifi-icon"></i>
                <h2 class="mb-0"><?php echo SITE_NAME; ?></h2>
                <p class="mb-0">Welcome! Please provide your details to connect.</p>
            </div>
            
            <div class="portal-body">
                <form id="loginForm" action="process.php" method="POST">
                    <input type="hidden" name="mac" value="<?php echo htmlspecialchars($client_mac); ?>">
                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($client_ip); ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">
                    
                    <!-- Role Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">I am a:</label>
                        <div class="row">
                            <div class="col-4">
                                <div class="role-card text-center" data-role="member">
                                    <i class="fas fa-user-tie role-icon"></i>
                                    <div class="fw-bold">Member</div>
                                    <small class="text-muted">Building member</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="role-card text-center" data-role="guest">
                                    <i class="fas fa-user role-icon"></i>
                                    <div class="fw-bold">Guest</div>
                                    <small class="text-muted">Visiting someone</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="role-card text-center" data-role="event">
                                    <i class="fas fa-calendar-alt role-icon"></i>
                                    <div class="fw-bold">Event</div>
                                    <small class="text-muted">Attending event</small>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="role" id="selectedRole" required>
                    </div>

                    <!-- Email (Always Required) -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold">
                            <i class="fas fa-envelope me-2"></i>Email Address *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="your.email@example.com">
                    </div>

                    <!-- Name (Always Required) -->
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">
                            <i class="fas fa-user me-2"></i>Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="Enter your full name">
                    </div>

                    <!-- Member Floor (Conditional) -->
                    <div class="mb-3 conditional-field" id="memberFields">
                        <label for="floor" class="form-label fw-bold">
                            <i class="fas fa-building me-2"></i>Floor
                        </label>
                        <select class="form-select" id="floor" name="floor_id">
                            <option value="">Select your floor</option>
                            <?php foreach ($floors as $floor): ?>
                                <option value="<?php echo $floor['id']; ?>">
                                    Floor <?php echo htmlspecialchars($floor['floor_number']); ?> - 
                                    <?php echo htmlspecialchars($floor['floor_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Event Selection (Conditional) -->
                    <div class="mb-3 conditional-field" id="eventFields">
                        <label for="event" class="form-label fw-bold">
                            <i class="fas fa-calendar me-2"></i>Event
                        </label>
                        <select class="form-select" id="event" name="event_id">
                            <option value="">Select the event you're attending</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>">
                                    <?php echo htmlspecialchars($event['name']); ?>
                                    <?php if ($event['description']): ?>
                                        - <?php echo htmlspecialchars($event['description']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label terms-text" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Use</a> 
                                and understand that my internet activity may be monitored for security purposes.
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-connect">
                            <i class="fas fa-wifi me-2"></i>Connect to WiFi
                        </button>
                    </div>
                </form>

                <!-- Connection Info -->
                <div class="mt-4 text-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Free WiFi access for 8 hours. Need help? Contact support.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms of Use</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>WiFi Access Terms</h6>
                    <ul>
                        <li>This WiFi service is provided free of charge for legitimate business purposes</li>
                        <li>Users must not engage in illegal activities or violate intellectual property rights</li>
                        <li>Bandwidth-intensive activities may be limited during peak hours</li>
                        <li>Network activity is monitored for security and compliance purposes</li>
                        <li>Personal data is collected in accordance with our Privacy Policy</li>
                        <li>Access may be terminated at any time without notice</li>
                    </ul>
                    <h6>Privacy Notice</h6>
                    <p>We collect your email and basic information to provide network access and improve our services. 
                    Your data is not shared with third parties except as required by law.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleCards = document.querySelectorAll('.role-card');
            const selectedRoleInput = document.getElementById('selectedRole');
            const memberFields = document.getElementById('memberFields');
            const eventFields = document.getElementById('eventFields');
            const floorSelect = document.getElementById('floor');
            const eventSelect = document.getElementById('event');

            roleCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove active class from all cards
                    roleCards.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked card
                    this.classList.add('active');
                    
                    // Set selected role
                    const role = this.dataset.role;
                    selectedRoleInput.value = role;
                    
                    // Hide all conditional fields
                    memberFields.style.display = 'none';
                    eventFields.style.display = 'none';
                    
                    // Clear previous selections
                    floorSelect.value = '';
                    eventSelect.value = '';
                    
                    // Show relevant fields
                    if (role === 'member') {
                        memberFields.style.display = 'block';
                        floorSelect.required = true;
                        eventSelect.required = false;
                    } else if (role === 'event') {
                        eventFields.style.display = 'block';
                        eventSelect.required = true;
                        floorSelect.required = false;
                    } else {
                        floorSelect.required = false;
                        eventSelect.required = false;
                    }
                });
            });

            // Form validation
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                if (!selectedRoleInput.value) {
                    e.preventDefault();
                    alert('Please select your role (Member, Guest, or Event)');
                    return false;
                }

                const role = selectedRoleInput.value;
                if (role === 'member' && !floorSelect.value) {
                    e.preventDefault();
                    alert('Please select your floor');
                    return false;
                }

                if (role === 'event' && !eventSelect.value) {
                    e.preventDefault();
                    alert('Please select the event you\'re attending');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
