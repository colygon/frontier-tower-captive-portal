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
                case 'add_floor':
                    $floor_number = trim($_POST['floor_number']);
                    $floor_name = trim($_POST['floor_name']);
                    
                    if (empty($floor_number) || empty($floor_name)) {
                        throw new Exception('Floor number and name are required');
                    }
                    
                    // Check if floor number already exists
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM floors WHERE floor_number = ?");
                    $stmt->execute([$floor_number]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('Floor number already exists');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO floors (floor_number, floor_name, active) VALUES (?, ?, 1)");
                    $stmt->execute([$floor_number, $floor_name]);
                    
                    $message = 'Floor added successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'toggle_floor':
                    $floor_id = intval($_POST['floor_id']);
                    $active = intval($_POST['active']);
                    
                    $stmt = $pdo->prepare("UPDATE floors SET active = ? WHERE id = ?");
                    $stmt->execute([$active, $floor_id]);
                    
                    $message = 'Floor status updated successfully!';
                    $message_type = 'success';
                    break;
                    
                case 'delete_floor':
                    $floor_id = intval($_POST['floor_id']);
                    
                    // Check if floor has members
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE floor_id = ?");
                    $stmt->execute([$floor_id]);
                    $member_count = $stmt->fetchColumn();
                    
                    if ($member_count > 0) {
                        throw new Exception("Cannot delete floor with {$member_count} members. Deactivate it instead.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM floors WHERE id = ?");
                    $stmt->execute([$floor_id]);
                    
                    $message = 'Floor deleted successfully!';
                    $message_type = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Get floors with member counts
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT f.*, 
               COUNT(m.id) as member_count,
               COUNT(CASE WHEN m.last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_logins
        FROM floors f 
        LEFT JOIN members m ON f.id = m.floor_id 
        GROUP BY f.id 
        ORDER BY CAST(f.floor_number AS UNSIGNED), f.floor_number
    ");
    $floors = $stmt->fetchAll();
} catch (Exception $e) {
    $floors = [];
    $message = 'Error loading floors: ' . $e->getMessage();
    $message_type = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floors Management - <?php echo SITE_NAME; ?></title>
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
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-calendar me-2"></i>Events
                        </a>
                        <a class="nav-link active" href="floors.php">
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
                            <h2 class="mb-1">Floors Management</h2>
                            <p class="text-muted mb-0">Manage building floors and view member statistics</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFloorModal">
                            <i class="fas fa-plus me-2"></i>Add Floor
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Floors List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>All Floors
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($floors)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Floor Number</th>
                                            <th>Floor Name</th>
                                            <th>Status</th>
                                            <th>Total Members</th>
                                            <th>Recent Logins (24h)</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($floors as $floor): ?>
                                            <tr>
                                                <td>
                                                    <strong>Floor <?php echo htmlspecialchars($floor['floor_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($floor['floor_name']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($floor['active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $floor['member_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $floor['recent_logins']; ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($floor['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Toggle Active/Inactive -->
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_floor">
                                                            <input type="hidden" name="floor_id" value="<?php echo $floor['id']; ?>">
                                                            <input type="hidden" name="active" value="<?php echo $floor['active'] ? 0 : 1; ?>">
                                                            <button type="submit" class="btn btn-outline-<?php echo $floor['active'] ? 'warning' : 'success'; ?>" 
                                                                    title="<?php echo $floor['active'] ? 'Deactivate' : 'Activate'; ?>">
                                                                <i class="fas fa-<?php echo $floor['active'] ? 'pause' : 'play'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <!-- Delete Floor -->
                                                        <?php if ($floor['member_count'] == 0): ?>
                                                            <form method="POST" style="display: inline;" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this floor?')">
                                                                <input type="hidden" name="action" value="delete_floor">
                                                                <input type="hidden" name="floor_id" value="<?php echo $floor['id']; ?>">
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
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No floors found. Add your first floor!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Floor Modal -->
    <div class="modal fade" id="addFloorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Floor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_floor">
                        
                        <div class="mb-3">
                            <label for="floorNumber" class="form-label">Floor Number *</label>
                            <input type="text" class="form-control" id="floorNumber" name="floor_number" required 
                                   placeholder="e.g., 1, 2, B1, M">
                        </div>
                        
                        <div class="mb-3">
                            <label for="floorName" class="form-label">Floor Name *</label>
                            <input type="text" class="form-control" id="floorName" name="floor_name" required 
                                   placeholder="e.g., Ground Floor, Executive Level, Basement">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Floor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
