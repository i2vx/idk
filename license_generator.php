<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration - replace with your actual database details
$db_host = 'localhost';
$db_name = 'auth_system';
$db_user = 'your_username';
$db_pass = 'your_password';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

function generateLicenseKey($length = 32) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $license = '';
    for ($i = 0; $i < $length; $i++) {
        $license .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $license;
}

function generateHWID() {
    // This is a server-side HWID generator for testing purposes
    // In production, HWIDs should be generated client-side
    return strtoupper(hash('sha256', uniqid(rand(), true)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'generate_license':
            $user_email = $input['email'] ?? '';
            $user_name = $input['name'] ?? '';
            $expiry_days = intval($input['expiry_days'] ?? 30);
            
            if (empty($user_email) || empty($user_name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email and name are required']);
                exit;
            }
            
            try {
                $license_key = generateLicenseKey();
                $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
                $created_date = date('Y-m-d H:i:s');
                
                // Insert into database
                $stmt = $pdo->prepare("INSERT INTO licenses (license_key, user_email, user_name, created_at, expires_at, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$license_key, $user_email, $user_name, $created_date, $expiry_date]);
                
                echo json_encode([
                    'success' => true,
                    'license_key' => $license_key,
                    'user_name' => $user_name,
                    'user_email' => $user_email,
                    'expires_at' => $expiry_date,
                    'created_at' => $created_date
                ]);
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to create license']);
            }
            break;
            
        case 'authenticate':
            $license_key = $input['license_key'] ?? '';
            $hwid = $input['hwid'] ?? '';
            $version = $input['version'] ?? '';
            $client_type = $input['client_type'] ?? '';
            
            if (empty($license_key) || empty($hwid)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'License key and HWID are required']);
                exit;
            }
            
            try {
                // Check if license exists and is active
                $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND is_active = 1");
                $stmt->execute([$license_key]);
                $license = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$license) {
                    echo json_encode(['success' => false, 'error' => 'Invalid license key']);
                    exit;
                }
                
                // Check if license has expired
                if (strtotime($license['expires_at']) < time()) {
                    echo json_encode(['success' => false, 'error' => 'License has expired']);
                    exit;
                }
                
                // Check if HWID is already registered for this license
                $stmt = $pdo->prepare("SELECT * FROM hwid_registrations WHERE license_key = ? AND hwid = ?");
                $stmt->execute([$license_key, $hwid]);
                $existing_hwid = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing_hwid) {
                    // Check if this license already has an HWID registered
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM hwid_registrations WHERE license_key = ?");
                    $stmt->execute([$license_key]);
                    $hwid_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($hwid_count > 0) {
                        echo json_encode(['success' => false, 'error' => 'License is already bound to another device']);
                        exit;
                    }
                    
                    // Register new HWID
                    $stmt = $pdo->prepare("INSERT INTO hwid_registrations (license_key, hwid, first_used, last_used) VALUES (?, ?, NOW(), NOW())");
                    $stmt->execute([$license_key, $hwid]);
                } else {
                    // Update last used time
                    $stmt = $pdo->prepare("UPDATE hwid_registrations SET last_used = NOW() WHERE license_key = ? AND hwid = ?");
                    $stmt->execute([$license_key, $hwid]);
                }
                
                // Log authentication attempt
                $stmt = $pdo->prepare("INSERT INTO auth_logs (license_key, hwid, version, client_type, ip_address, success, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$license_key, $hwid, $version, $client_type, $_SERVER['REMOTE_ADDR']]);
                
                echo json_encode([
                    'success' => true,
                    'license_key' => $license_key,
                    'user_name' => $license['user_name'],
                    'expires_at' => strtotime($license['expires_at']) * 1000, // Convert to milliseconds
                    'message' => 'Authentication successful'
                ]);
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Authentication failed']);
            }
            break;
            
        case 'revoke_license':
            $license_key = $input['license_key'] ?? '';
            $admin_key = $input['admin_key'] ?? ''; // Add admin authentication
            
            if (empty($license_key) || $admin_key !== 'your_admin_key_here') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid request']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE licenses SET is_active = 0 WHERE license_key = ?");
                $stmt->execute([$license_key]);
                
                echo json_encode(['success' => true, 'message' => 'License revoked successfully']);
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to revoke license']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} else {
    // GET request - show license information
    $license_key = $_GET['license_key'] ?? '';
    
    if (empty($license_key)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'License key is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT l.*, h.hwid, h.first_used, h.last_used FROM licenses l LEFT JOIN hwid_registrations h ON l.license_key = h.license_key WHERE l.license_key = ?");
        $stmt->execute([$license_key]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$license) {
            echo json_encode(['success' => false, 'error' => 'License not found']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'license' => $license
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve license information']);
    }
}
?>
