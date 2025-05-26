<?php
require_once 'config.php';
requireLogin();

// Get dashboard statistics
$db = Database::getInstance()->getConnection();

try {
    // Get email counts
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as total_delivered,
            SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as total_bounced,
            SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as total_spam
        FROM emails
    ");
    $stats = $stmt->fetch();
    
    // Calculate rates
    $deliveryRate = $stats['total_sent'] > 0 ? round(($stats['total_delivered'] / $stats['total_sent']) * 100, 2) : 0;
    $bounceRate = $stats['total_sent'] > 0 ? round(($stats['total_bounced'] / $stats['total_sent']) * 100, 2) : 0;
    $spamRate = $stats['total_sent'] > 0 ? round(($stats['total_spam'] / $stats['total_sent']) * 100, 2) : 0;
    
    // Get recent emails
    $stmt = $db->query("
        SELECT id, from_email, to_email, subject, status, created_at 
        FROM emails 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $recentEmails = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1d4ed8 100%);
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            border-left: 4px solid var(--primary-color);
        }
        .stat-card.success { border-left-color: var(--success-color); }
        .stat-card.warning { border-left-color: var(--warning-color); }
        .stat-card.danger { border-left-color: var(--danger-color); }
        .badge-success { background-color: var(--success-color); }
        .badge-warning { background-color: var(--warning-color); }
        .badge-danger { background-color: var(--danger-color); }
        .badge-info { background-color: var(--primary-color); }
        .badge-secondary { background-color: #6b7280; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-envelope text-white" style="font-size: 2rem;"></i>
                        <h5 class="text-white mt-2"><?php echo APP_NAME; ?></h5>
                        <small class="text-white-50"><?php echo POSTAL_HOSTNAME; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="compose.php">
                                <i class="fas fa-edit me-2"></i> Compose Email
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="emails.php">
                                <i class="fas fa-history me-2"></i> Email History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-line me-2"></i> Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="recipients.php">
                                <i class="fas fa-users me-2"></i> Recipients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="templates.php">
                                <i class="fas fa-file-alt me-2"></i> Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i> Settings
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                    
                    <div class="mt-4 p-3 text-center">
                        <div class="d-flex align-items-center justify-content-center text-white-50">
                            <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                            <small>API Connected</small>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Email Deliverability Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-calendar me-1"></i> Last 7 days
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Last 24 hours</a></li>
                                <li><a class="dropdown-item" href="#">Last 7 days</a></li>
                                <li><a class="dropdown-item" href="#">Last 30 days</a></li>
                            </ul>
                        </div>
                        <a href="compose.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Compose Email
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total Sent</div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_sent']); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-paper-plane fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Delivery Rate</div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $deliveryRate; ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x" style="color: var(--success-color);"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Bounce Rate</div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $bounceRate; ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x" style="color: var(--warning-color);"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card danger">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-uppercase mb-1">Spam Rate</div>
                                        <div class="h5 mb-0 font-weight-bold"><?php echo $spamRate; ?>%</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shield-alt fa-2x" style="color: var(--danger-color);"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Delivery Trends</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="deliveryChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Status Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Email Activity</h6>
                        <a href="emails.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentEmails)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No emails sent yet</h5>
                                <p class="text-muted">Start by <a href="compose.php">composing your first email</a></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Status</th>
                                            <th>Sent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentEmails as $email): ?>
                                            <tr>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 200px;">
                                                        <?php echo htmlspecialchars($email['subject']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($email['from_email']); ?></small>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($email['to_email']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($email['status']); ?>">
                                                        <?php echo ucfirst($email['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo formatDate($email['created_at']); ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sample chart data - in production, this would come from PHP/AJAX
        const deliveryCtx = document.getElementById('deliveryChart').getContext('2d');
        new Chart(deliveryCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Delivered',
                    data: [12, 19, 3, 5, 2, 3, 7],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Bounced',
                    data: [2, 1, 0, 1, 0, 0, 1],
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Delivered', 'Sent', 'Bounced', 'Failed'],
                datasets: [{
                    data: [<?php echo $stats['total_delivered']; ?>, <?php echo $stats['total_sent'] - $stats['total_delivered'] - $stats['total_bounced'] - $stats['total_spam']; ?>, <?php echo $stats['total_bounced']; ?>, <?php echo $stats['total_spam']; ?>],
                    backgroundColor: ['#10b981', '#2563eb', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>