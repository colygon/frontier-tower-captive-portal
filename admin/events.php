<?php
require_once '../config/config.php';
require_once 'auth_check.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_event':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    
                    if (empty($name)) {
                        throw new Exception('Event name is required');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO events (name, description, active) VALUES (?, ?, 1)");
                    $stmt->execute([$name, $description]);
                    
                    $message = 'Event added successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'toggle_event':
                    $event_id = intval($_POST['event_id']);
                    $active = intval($_POST['active']);
                    
                    $stmt = $pdo->prepare("UPDATE events SET active = ? WHERE id = ?");
                    $stmt->execute([$active, $event_id]);
                    
                    $message = 'Event status updated successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'delete_event':
                    $event_id = intval($_POST['event_id']);
                    
                    // Check if event has attendees
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_log WHERE event_id = ?");
                    $stmt->execute([$event_id]);
                    $attendee_count = $stmt->fetchColumn();
                    
                    if ($attendee_count > 0) {
                        throw new Exception("Cannot delete event with {$attendee_count} attendees. Deactivate it instead.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$event_id]);
                    
                    $message = 'Event deleted successfully!';
                    $message_type = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Get events with attendee counts
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT e.*, 
               COUNT(el.id) as attendee_count,
               COUNT(CASE WHEN el.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_attendees
        FROM events e 
        LEFT JOIN event_log el ON e.id = el.event_id 
        GROUP BY e.id 
        ORDER BY e.created_at DESC
    ");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    $events = [];
    $message = 'Error loading events: ' . $e->getMessage();
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        .main-content { padding: 20px; }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-4">
                    <h5 class="mb-4">
                        <i class="fas fa-wifi me-2"></i>
                        Admin Panel
                    </h5>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                        <a class="nav-link active" href="events.php">
                            <i class="fas fa-calendar me-2"></i>Events
                        </a>
                        <a class="nav-link" href="floors.php">
                            <i class="fas fa-building me-2"></i>Floors
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Events Management</h2>
                            <p class="text-muted mb-0">Manage events and view attendee statistics</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                            <i class="fas fa-plus me-2"></i>Add Event
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Events List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>All Events
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($events)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Total Attendees</th>
                                            <th>Recent (24h)</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($event['name']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($event['description'] ?: 'No description'); ?>
                                                </td>
                                                <td>
                                                    <?php if ($event['active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $event['attendee_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $event['recent_attendees']; ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($event['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Toggle Active/Inactive -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_event">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="active" value="<?php echo $event['active'] ? 0 : 1; ?>">
                                                            <button type="submit" class="btn btn-outline-<?php echo $event['active'] ? 'warning' : 'success'; ?>" 
                                                                    title="<?php echo $event['active'] ? 'Deactivate' : 'Activate'; ?>">
                                                                <i class="fas fa-<?php echo $event['active'] ? 'pause' : 'play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Event -->
                                                        <?php if ($event['attendee_count'] == 0): ?>
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this event?')">
                                                                <input type="hidden" name="action" value="delete_event">
                                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No events found. Create your first event!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_event">
                        
                        <div class="mb-3">
                            <label for="eventName" class="form-label">Event Name *</label>
                            <input type="text" class="form-control" id="eventName" name="name" required 
                                   placeholder="e.g., Tech Meetup, Business Conference">
                        </div>
                        
                        <div class="mb-3">
                            <label for="eventDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="eventDescription" name="description" rows="3" 
                                      placeholder="Brief description of the event (optional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
