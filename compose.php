<?php
require_once 'config.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromEmail = sanitizeInput($_POST['from_email'] ?? '');
    $toEmail = sanitizeInput($_POST['to_email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $content = $_POST['content'] ?? '';
    $templateId = $_POST['template_id'] ?? null;
    
    if (empty($fromEmail) || empty($toEmail) || empty($subject) || empty($content)) {
        $error = 'All fields are required.';
    } else if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter valid email addresses.';
    } else {
        try {
            // Send email via Postal API
            $postData = [
                'to' => [$toEmail],
                'from' => $fromEmail,
                'subject' => $subject,
                'html_body' => $content,
                'headers' => [
                    'X-Track-Delivery' => 'true'
                ]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://' . POSTAL_HOSTNAME . '/api/v1/send/message');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Server-API-Key: ' . POSTAL_API_KEY
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                if ($responseData['status'] === 'success') {
                    // Save email record to database
                    $db = Database::getInstance()->getConnection();
                    
                    // Get or create recipient
                    $stmt = $db->prepare("SELECT id FROM recipients WHERE email = ?");
                    $stmt->execute([$toEmail]);
                    $recipient = $stmt->fetch();
                    
                    if (!$recipient) {
                        $stmt = $db->prepare("INSERT INTO recipients (email, name) VALUES (?, ?)");
                        $stmt->execute([$toEmail, explode('@', $toEmail)[0]]);
                        $recipientId = $db->lastInsertId();
                    } else {
                        $recipientId = $recipient['id'];
                    }
                    
                    // Insert email record
                    $stmt = $db->prepare("
                        INSERT INTO emails (postal_message_id, from_email, to_email, subject, content, status, recipient_id, template_id, metadata) 
                        VALUES (?, ?, ?, ?, ?, 'sent', ?, ?, ?)
                    ");
                    $metadata = json_encode(['token' => $responseData['data']['token'] ?? null]);
                    $stmt->execute([
                        $responseData['data']['id'],
                        $fromEmail,
                        $toEmail,
                        $subject,
                        $content,
                        $recipientId,
                        $templateId ?: null,
                        $metadata
                    ]);
                    
                    $success = 'Email sent successfully! Message ID: ' . $responseData['data']['id'];
                    
                    // Clear form
                    $_POST = [];
                } else {
                    $error = 'Failed to send email: ' . ($responseData['message'] ?? 'Unknown error');
                }
            } else {
                $error = 'Failed to connect to Postal API. HTTP Code: ' . $httpCode;
            }
            
        } catch (Exception $e) {
            $error = 'Error sending email: ' . $e->getMessage();
        }
    }
}

// Get email templates
$templates = [];
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name, subject, content FROM email_templates WHERE is_active = 1 ORDER BY name");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    // Templates not critical for functionality
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Email - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
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
        }
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
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="compose.php">
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
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit me-2"></i>
                        Compose Email
                    </h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-paper-plane me-2"></i>
                            Send Email via Postal
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="from_email" class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="from_email" name="from_email" 
                                           value="<?php echo htmlspecialchars($_POST['from_email'] ?? DEFAULT_FROM_EMAIL); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="to_email" class="form-label">To Email</label>
                                    <input type="email" class="form-control" id="to_email" name="to_email" 
                                           value="<?php echo htmlspecialchars($_POST['to_email'] ?? ''); ?>" 
                                           placeholder="recipient@example.com" required>
                                </div>
                            </div>

                            <?php if (!empty($templates)): ?>
                            <div class="mb-3">
                                <label for="template_id" class="form-label">Email Template (Optional)</label>
                                <select class="form-select" id="template_id" name="template_id" onchange="loadTemplate()">
                                    <option value="">Choose a template...</option>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                                data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                            <?php echo htmlspecialchars($template['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                                       placeholder="Enter email subject" required>
                            </div>

                            <div class="mb-3">
                                <label for="content" class="form-label">Message Content</label>
                                <textarea class="form-control" id="content" name="content" rows="12" 
                                          placeholder="Enter your email content here. HTML is supported." required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                <div class="form-text">You can use HTML formatting in your email content.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="track_delivery" checked>
                                        <label class="form-check-label" for="track_delivery">
                                            Track delivery status
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn btn-outline-secondary me-2" onclick="clearForm()">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i> Send Email
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Email Preview -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-eye me-2"></i>
                            Email Preview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="email-preview" class="border rounded p-3" style="background-color: #f8f9fa; min-height: 200px;">
                            <div class="text-muted text-center">
                                <i class="fas fa-eye-slash fa-2x mb-2"></i>
                                <p>Enter email content above to see preview</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadTemplate() {
            const select = document.getElementById('template_id');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                document.getElementById('subject').value = selectedOption.dataset.subject || '';
                document.getElementById('content').value = selectedOption.dataset.content || '';
                updatePreview();
            }
        }

        function clearForm() {
            document.getElementById('template_id').value = '';
            document.getElementById('subject').value = '';
            document.getElementById('content').value = '';
            document.getElementById('to_email').value = '';
            updatePreview();
        }

        function updatePreview() {
            const content = document.getElementById('content').value;
            const preview = document.getElementById('email-preview');
            
            if (content.trim()) {
                preview.innerHTML = content;
            } else {
                preview.innerHTML = `
                    <div class="text-muted text-center">
                        <i class="fas fa-eye-slash fa-2x mb-2"></i>
                        <p>Enter email content above to see preview</p>
                    </div>
                `;
            }
        }

        // Update preview when content changes
        document.getElementById('content').addEventListener('input', updatePreview);
        
        // Initial preview update
        updatePreview();
    </script>
</body>
</html>