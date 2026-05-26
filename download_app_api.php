<?php
/**
 * Download App API
 * Sends download links via email and stores request in database
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'api/utils.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get database connection
$conn = get_db_connection();

// Get email and refercode from form data
$email = trim($_POST['email'] ?? '');
$refercode = trim($_POST['refercode'] ?? 0);
if ($refercode === '') {
    $refercode = 0; // Set to 0 if empty
}
// Validate email
if (empty($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

// App download links (placeholders - replace with actual links)
$androidLink = 'https://play.google.com/store/apps/details?id=com.desibhasha.app';
$iosLink = 'https://apps.apple.com/app/desibhasha/id123456789';
$logoUrl = 'https://desibhasha.com/logo.jpeg';
// Email content
$subject = 'Download the Desibhasha App';
$message = '
<html>
<body style="margin:0; padding:0; background-color:#f4f6fb; font-family:Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6fb;">
  <tr>
    <td align="center">

```
  <!-- Container -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px; background:#ffffff; margin:0 auto;">
    
    <!-- Header -->
    <tr>
      <td align="center" style="padding:20px 15px 10px 15px;">
        <img src="https://desibhasha.com/logo.jpeg" alt="Desibhasha Logo" width="120" style="display:block; border:0;">
      </td>
    </tr>

    <tr>
      <td align="center" style="padding:0 20px;">
        <h2 style="margin:10px 0; color:#5b2cff; font-size:20px;">
          Welcome to Desibhasha!
        </h2>
        <p style="margin:0; font-size:14px; color:#555;">
          Discover content in your own language.
        </p>
      </td>
    </tr>

    <!-- Body Text -->
    <tr>
      <td align="center" style="padding:20px;">
        <p style="margin:0; font-size:14px; color:#333;">
          Get started by downloading the app on your preferred device.
        </p>
      </td>
    </tr>

    <!-- Android Button -->
    <tr>
      <td align="center" style="padding:10px;">
        <a href="https://apps.apple.com/in/app/desibhasha/id6759467232">
          <img src="https://play.google.com/intl/en_us/badges/images/generic/en_badge_web_generic.png"
               alt="Get it on Google Play"
               width="180"
               style="display:block; border:0;">
        </a>
      </td>
    </tr>

    <!-- iOS Button -->
    <tr>
      <td align="center" style="padding:10px;">
        <a href="https://apps.apple.com/in/app/desibhasha/id6759467232">
          <img src="https://desibhasha.com/apple-badge.png"
               alt="Download on the App Store"
               width="180"
               style="display:block; border:0;">
        </a>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td align="center" style="padding:20px; font-size:12px; color:#888;">
        © 2026 Desibhasha<br>
        All rights reserved
      </td>
    </tr>

  </table>

</td>

  </tr>
</table>
</body>
</html>
';

// Email headers
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: noreply@desibhasha.com" . "\r\n";

// Check if record already exists
$query = "SELECT download_request_id FROM download_requests WHERE referred_email = ? LIMIT 1";
$stmt = $conn->prepare($query);
$recordExists = false;

if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $recordExists = $result->num_rows > 0;
    $stmt->close();
}

// Send email
$emailSent = mail($email, $subject, $message, $headers);
//$emailSent = true; // Simulate email sent for testing
// Insert record only if it doesn't exist
if (!$recordExists && $emailSent) {
    $status = 'sent';
   
    // Insert new record
    $insertQuery = "INSERT INTO download_requests (referred_email, refer_code, status) VALUES (?, ?, ?)";
    $insertStmt = $conn->prepare($insertQuery);
    if ($insertStmt) {
        $insertStmt->bind_param("sss", $email, $refercode, $status);
        if (!$insertStmt->execute()) {
            error_log("Database insert error: " . $insertStmt->error);
        }
        $insertStmt->close();
    }
}

// Send response
if ($emailSent) {
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email']);
}

$conn->close();
?>