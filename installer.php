<?php
/**
 * Postal Email Dashboard Installer
 * Automated installation script for cPanel hosting
 */

session_start();
$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Installation steps configuration
$steps = [
    1 => 'Welcome & Requirements Check',
    2 => 'Database Configuration',
    3 => 'Database Installation', 
    4 => 'Admin Account Setup',
    5 => 'Postal API Configuration',
    6 => 'Installation Complete'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Database configuration
            $_SESSION['db_host'] = $_POST['db_host'] ?? '';
            $_SESSION['db_name'] = $_POST['db_name'] ?? '';
            $_SESSION['db_user'] = $_POST['db_user'] ?? '';
            $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';
            
            // Test database connection
            try {
                $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $success[] = "Database connection successful!";
                $step = 3;
            } catch (PDOException $e) {
                $errors[] = "Database connection failed: " . $e->getMessage();
            }
            break;
            
        case 3:
            // Install database schema
            try {
                $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Read and execute SQL installation file
                $sql = file_get_contents('install.sql');
                if ($sql === false) {
                    throw new Exception("Could not read install.sql file");
                }
                
                // Split SQL into individual statements
                $statements = explode(';', $sql);
                $executed = 0;
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $pdo->exec($statement);
                        $executed++;
                    }
                }
                
                $success[] = "Database schema installed successfully! ($executed statements executed)";
                $step = 4;
                
            } catch (Exception $e) {
                $errors[] = "Database installation failed: " . $e->getMessage();
            }
            break;
            
        case 4:
            // Admin account setup
            $admin_username = $_POST['admin_username'] ?? 'admin';
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            
            if (empty($admin_password) || strlen($admin_password) < 6) {
                $errors[] = "Password must be at least 6 characters long";
            } else {
                try {
                    $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Update admin user
                    $hashedPassword = password_hash($admin_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, email = ? WHERE id = 1");
                    $stmt->execute([$admin_username, $hashedPassword, $admin_email]);
                    
                    $_SESSION['admin_username'] = $admin_username;
                    $_SESSION['admin_password'] = $admin_password;
                    $_SESSION['admin_email'] = $admin_email;
                    
                    $success[] = "Admin account configured successfully!";
                    $step = 5;
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to setup admin account: " . $e->getMessage();
                }
            }
            break;
            
        case 5:
            // Postal API configuration
            $_SESSION['postal_hostname'] = $_POST['postal_hostname'] ?? 'postal3.clfaceverifiy.com';
            $_SESSION['postal_api_key'] = $_POST['postal_api_key'] ?? 'KFBcjBpjIZQbUq3AMyfhDw0c';
            $_SESSION['postal_domain'] = $_POST['postal_domain'] ?? 'bmh3.clfaceverifiy.com';
            $_SESSION['default_from_email'] = $_POST['default_from_email'] ?? 'hello@bmh3.clfaceverifiy.com';
            
            // Test Postal API connection
            $testResult = testPostalConnection($_SESSION['postal_hostname'], $_SESSION['postal_api_key']);
            
            if ($testResult['success']) {
                // Update settings in database
                try {
                    $dsn = "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $_SESSION['db_user'], $_SESSION['db_pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $settings = [
                        'postal_hostname' => $_SESSION['postal_hostname'],
                        'postal_api_key' => $_SESSION['postal_api_key'],
                        'postal_domain' => $_SESSION['postal_domain'],
                        'default_from_email' => $_SESSION['default_from_email']
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                        $stmt->execute([$value, $key]);
                    }
                    
                    // Generate config.php file
                    generateConfigFile();
                    
                    $success[] = "Postal API configuration saved successfully!";
                    $step = 6;
                    
                } catch (Exception $e) {
                    $errors[] = "Failed to save Postal configuration: " . $e->getMessage();
                }
            } else {
                $errors[] = "Postal API connection failed: " . $testResult['error'];
            }
            break;
    }
}

function testPostalConnection($hostname, $apiKey) {
    $url = "https://$hostname/api/v1/servers";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Server-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => "HTTP $httpCode - Invalid API key or hostname"];
    }
}

function generateConfigFile() {
    $configContent = '<?php
/**
 * Postal Email Dashboard Configuration
 * Generated by installer on ' . date('Y-m-d H:i:s') . '
 */

// Database Configuration
define(\'DB_HOST\', \'' . addslashes($_SESSION['db_host']) . '\');
define(\'DB_NAME\', \'' . addslashes($_SESSION['db_name']) . '\');
define(\'DB_USER\', \'' . addslashes($_SESSION['db_user']) . '\');
define(\'DB_PASS\', \'' . addslashes($_SESSION['db_pass']) . '\');
define(\'DB_CHARSET\', \'utf8mb4\');

// Postal API Configuration
define(\'POSTAL_HOSTNAME\', \'' . addslashes($_SESSION['postal_hostname']) . '\');
define(\'POSTAL_API_KEY\', \'' . addslashes($_SESSION['postal_api_key']) . '\');
define(\'POSTAL_DOMAIN\', \'' . addslashes($_SESSION['postal_domain']) . '\');
define(\'DEFAULT_FROM_EMAIL\', \'' . addslashes($_SESSION['default_from_email']) . '\');

// Application Configuration
define(\'APP_NAME\', \'Postal Email Dashboard\');
define(\'APP_VERSION\', \'1.0.0\');
define(\'SESSION_NAME\', \'postal_dashboard\');

// Security Configuration
define(\'SESSION_TIMEOUT\', 3600); // 1 hour
define(\'CSRF_TOKEN_NAME\', \'csrf_token\');

// Timezone
date_default_timezone_set(\'UTC\');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);

// Start session
session_name(SESSION_NAME);
session_start();

/**
 * Database Connection Class
 */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

/**
 * Authentication Helper Functions
 */
function isLoggedIn() {
    return isset($_SESSION[\'user_id\']) && isset($_SESSION[\'username\']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header(\'Location: login.php\');
        exit;
    }
}

function login($username, $password) {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user[\'password\'])) {
        $_SESSION[\'user_id\'] = $user[\'id\'];
        $_SESSION[\'username\'] = $user[\'username\'];
        $_SESSION[\'login_time\'] = time();
        return true;
    }
    
    return false;
}

function logout() {
    session_destroy();
    header(\'Location: login.php\');
    exit;
}

/**
 * CSRF Protection
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Utility Functions
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, \'UTF-8\');
}

function formatDate($date) {
    return date(\'M d, Y H:i\', strtotime($date));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case \'delivered\':
            return \'badge-success\';
        case \'bounced\':
            return \'badge-warning\';
        case \'spam\':
        case \'failed\':
            return \'badge-danger\';
        case \'sent\':
            return \'badge-info\';
        default:
            return \'badge-secondary\';
    }
}

/**
 * API Response Helper
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header(\'Content-Type: application/json\');
    echo json_encode($data);
    exit;
}
?>';

    file_put_contents('config.php', $configContent);
}

// Check requirements
function checkRequirements() {
    $requirements = [];
    
    $requirements['PHP Version'] = [
        'required' => '8.0.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '8.0.0', '>=')
    ];
    
    $requirements['PDO Extension'] = [
        'required' => 'Enabled',
        'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo')
    ];
    
    $requirements['PDO MySQL'] = [
        'required' => 'Enabled', 
        'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo_mysql')
    ];
    
    $requirements['cURL Extension'] = [
        'required' => 'Enabled',
        'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled', 
        'status' => extension_loaded('curl')
    ];
    
    $requirements['JSON Extension'] = [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json')
    ];
    
    $requirements['install.sql File'] = [
        'required' => 'Present',
        'current' => file_exists('install.sql') ? 'Present' : 'Missing',
        'status' => file_exists('install.sql')
    ];
    
    return $requirements;
}

$requirements = checkRequirements();
$canProceed = array_reduce($requirements, function($carry, $req) {
    return $carry && $req['status'];
}, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postal Email Dashboard - Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .installer-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 2rem auto;
            max-width: 800px;
        }
        .installer-header {
            background: #2563eb;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 1rem 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            background: #e5e7eb;
            color: #6b7280;
            font-weight: bold;
        }
        .step.active {
            background: #2563eb;
            color: white;
        }
        .step.completed {
            background: #10b981;
            color: white;
        }
        .requirement-check {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .status-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        .status-pass {
            background: #10b981;
        }
        .status-fail {
            background: #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="installer-container">
            <div class="installer-header">
                <i class="fas fa-envelope fa-3x mb-3"></i>
                <h1>Postal Email Dashboard</h1>
                <p class="mb-0">Installation Wizard</p>
                
                <div class="step-indicator">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="step <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                            <?php echo $i < $step ? '<i class="fas fa-check"></i>' : $i; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <small>Step <?php echo $step; ?> of 6: <?php echo $steps[$step]; ?></small>
            </div>
            
            <div class="p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <ul class="mb-0">
                            <?php foreach ($success as $message): ?>
                                <li><?php echo htmlspecialchars($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php switch ($step): 
                    case 1: ?>
                        <h3><i class="fas fa-clipboard-check me-2"></i>Welcome to Installation</h3>
                        <p>This installer will guide you through setting up your Postal Email Dashboard. Let's start by checking system requirements.</p>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-server me-2"></i>System Requirements</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($requirements as $name => $req): ?>
                                    <div class="requirement-check">
                                        <div>
                                            <strong><?php echo $name; ?></strong><br>
                                            <small class="text-muted">Required: <?php echo $req['required']; ?></small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?php echo $req['current']; ?></span>
                                            <div class="status-icon <?php echo $req['status'] ? 'status-pass' : 'status-fail'; ?>">
                                                <i class="fas <?php echo $req['status'] ? 'fa-check' : 'fa-times'; ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <?php if ($canProceed): ?>
                                <a href="?step=2" class="btn btn-primary btn-lg">
                                    <i class="fas fa-arrow-right me-2"></i>Continue Installation
                                </a>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Please resolve the requirements above before continuing.
                                </div>
                                <button class="btn btn-secondary" onclick="location.reload()">
                                    <i class="fas fa-refresh me-2"></i>Recheck Requirements
                                </button>
                            <?php endif; ?>
                        </div>
                        
                    <?php break; case 2: ?>
                        <h3><i class="fas fa-database me-2"></i>Database Configuration</h3>
                        <p>Enter your MySQL database connection details. You can create these in your cPanel.</p>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Database Host</label>
                                    <input type="text" class="form-control" name="db_host" value="<?php echo htmlspecialchars($_SESSION['db_host'] ?? 'localhost'); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Database Name</label>
                                    <input type="text" class="form-control" name="db_name" value="<?php echo htmlspecialchars($_SESSION['db_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Database Username</label>
                                    <input type="text" class="form-control" name="db_user" value="<?php echo htmlspecialchars($_SESSION['db_user'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Database Password</label>
                                    <input type="password" class="form-control" name="db_pass" value="<?php echo htmlspecialchars($_SESSION['db_pass'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database me-2"></i>Test Database Connection
                                </button>
                            </div>
                        </form>
                        
                    <?php break; case 3: ?>
                        <h3><i class="fas fa-cogs me-2"></i>Database Installation</h3>
                        <p>Ready to install the database schema. This will create all necessary tables and default data.</p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will create the following tables:
                            <ul class="mt-2 mb-0">
                                <li>users (for authentication)</li>
                                <li>recipients (contact management)</li>
                                <li>email_templates (reusable templates)</li>
                                <li>emails (email tracking)</li>
                                <li>email_stats (analytics data)</li>
                                <li>settings (configuration)</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-download me-2"></i>Install Database Schema
                                </button>
                            </div>
                        </form>
                        
                    <?php break; case 4: ?>
                        <h3><i class="fas fa-user-shield me-2"></i>Admin Account Setup</h3>
                        <p>Create your administrator account for accessing the dashboard.</p>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Admin Username</label>
                                    <input type="text" class="form-control" name="admin_username" value="admin" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" class="form-control" name="admin_email" value="" placeholder="admin@yourdomain.com">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Password</label>
                                <input type="password" class="form-control" name="admin_password" placeholder="Enter secure password (min 6 characters)" required>
                                <div class="form-text">This will replace the default password: Mbg$MeM7709123</div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Create Admin Account
                                </button>
                            </div>
                        </form>
                        
                    <?php break; case 5: ?>
                        <h3><i class="fas fa-envelope-open me-2"></i>Postal API Configuration</h3>
                        <p>Configure your Postal server connection for sending emails.</p>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postal Hostname</label>
                                    <input type="text" class="form-control" name="postal_hostname" value="postal3.clfaceverifiy.com" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postal Domain</label>
                                    <input type="text" class="form-control" name="postal_domain" value="bmh3.clfaceverifiy.com" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Postal API Key</label>
                                <input type="text" class="form-control" name="postal_api_key" value="KFBcjBpjIZQbUq3AMyfhDw0c" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Default From Email</label>
                                <input type="email" class="form-control" name="default_from_email" value="hello@bmh3.clfaceverifiy.com" required>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plug me-2"></i>Test & Save Configuration
                                </button>
                            </div>
                        </form>
                        
                    <?php break; case 6: ?>
                        <h3><i class="fas fa-check-circle me-2 text-success"></i>Installation Complete!</h3>
                        <p>Your Postal Email Dashboard has been successfully installed and configured.</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-success text-white">
                                        <h6><i class="fas fa-user me-2"></i>Login Details</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                                        <p><strong>Password:</strong> <?php echo htmlspecialchars($_SESSION['admin_password']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['admin_email']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h6><i class="fas fa-cog me-2"></i>Next Steps</h6>
                                    </div>
                                    <div class="card-body">
                                        <ol class="mb-0">
                                            <li>Configure webhook in Postal admin</li>
                                            <li>Delete installer.php file</li>
                                            <li>Test email sending</li>
                                            <li>Add recipients and templates</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> For security, please delete the installer.php file after installation.
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-success btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Access Dashboard
                            </a>
                            <a href="?delete=installer" class="btn btn-danger btn-lg ms-2" onclick="return confirm('Delete installer.php file?')">
                                <i class="fas fa-trash me-2"></i>Delete Installer
                            </a>
                        </div>
                        
                    <?php break; endswitch; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Handle installer deletion
if (isset($_GET['delete']) && $_GET['delete'] === 'installer') {
    if (unlink(__FILE__)) {
        echo '<script>alert("Installer deleted successfully!"); window.location.href = "login.php";</script>';
    } else {
        echo '<script>alert("Could not delete installer. Please remove installer.php manually.");</script>';
    }
}

// Clear session on completion
if ($step == 6 && isset($_GET['clear'])) {
    session_destroy();
}
?>