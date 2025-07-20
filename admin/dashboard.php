<?php
require_once '../config/config.php';
require_once 'auth_check.php';

// Get statistics
try {
    $pdo = getDBConnection();
    
    // Get counts
    $stats = [];
    $stats['total_members'] = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $stats['total_guests'] = $pdo->query("SELECT COUNT(*) FROM guests")->fetchColumn();
    $stats['total_events'] = $pdo->query("SELECT COUNT(*) FROM event_log")->fetchColumn();
    $stats['active_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE active = 1")->fetchColumn();
    
    // Get recent activity (last 24 hours)
    $stats['recent_members'] = $pdo->query("SELECT COUNT(*) FROM members WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $stats['recent_guests'] = $pdo->query("SELECT COUNT(*) FROM guests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $stats['recent_events'] = $pdo->query("SELECT COUNT(*) FROM event_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    
    // Get recent logins
    $recent_logins_stmt = $pdo->query("
        (SELECT 'member' as type, name, email, last_login as login_time FROM members ORDER BY last_login DESC LIMIT 5)
        UNION ALL
        (SELECT 'guest' as type, name, email, created_at as login_time FROM guests ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'event' as type, attendee_name as name, attendee_email as email, created_at as login_time FROM event_log ORDER BY created_at DESC LIMIT 5)
        ORDER BY login_time DESC LIMIT 10
    ");
    $recent_logins = $recent_logins_stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $stats = [];
    $recent_logins = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .main-content {
            padding: 20px;
        }
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                        <a class="nav-link" href="events.php">
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
                            <h2 class="mb-1">Dashboard</h2>
                            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('M j, Y g:i A'); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?php echo $stats['total_members'] ?? 0; ?></h3>
                                        <p class="mb-0">Total Members</p>
                                        <small class="opacity-75">
                                            +<?php echo $stats['recent_members'] ?? 0; ?> today
                                        </small>
                                    </div>
                                    <i class="fas fa-user-tie stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?php echo $stats['total_guests'] ?? 0; ?></h3>
                                        <p class="mb-0">Total Guests</p>
                                        <small class="opacity-75">
                                            +<?php echo $stats['recent_guests'] ?? 0; ?> today
                                        </small>
                                    </div>
                                    <i class="fas fa-user stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?php echo $stats['total_events'] ?? 0; ?></h3>
                                        <p class="mb-0">Event Attendees</p>
                                        <small class="opacity-75">
                                            +<?php echo $stats['recent_events'] ?? 0; ?> today
                                        </small>
                                    </div>
                                    <i class="fas fa-calendar-alt stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h3 class="mb-1"><?php echo $stats['active_events'] ?? 0; ?></h3>
                                        <p class="mb-0">Active Events</p>
                                        <small class="opacity-75">
                                            Currently running
                                        </small>
                                    </div>
                                    <i class="fas fa-calendar-check stat-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </h5>
                                <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_logins)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_logins as $login): ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                            $badge_class = '';
                                                            $icon = '';
                                                            switch ($login['type']) {
                                                                case 'member':
                                                                    $badge_class = 'bg-primary';
                                                                    $icon = 'fas fa-user-tie';
                                                                    break;
                                                                case 'guest':
                                                                    $badge_class = 'bg-success';
                                                                    $icon = 'fas fa-user';
                                                                    break;
                                                                case 'event':
                                                                    $badge_class = 'bg-warning';
                                                                    $icon = 'fas fa-calendar';
                                                                    break;
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <i class="<?php echo $icon; ?> me-1"></i>
                                                                <?php echo ucfirst($login['type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($login['name']); ?></td>
                                                        <td><?php echo htmlspecialchars($login['email']); ?></td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php echo date('M j, g:i A', strtotime($login['login_time'])); ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
