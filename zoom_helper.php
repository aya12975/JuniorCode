<?php
require_once __DIR__ . "/admin_prefs.php";

function getZoomToken($conn) {
    $accountId    = getAdminSetting($conn, 'zoom_account_id',    '');
    $clientId     = getAdminSetting($conn, 'zoom_client_id',     '');
    $clientSecret = getAdminSetting($conn, 'zoom_client_secret', '');

    if (!$accountId || !$clientId || !$clientSecret) return null;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://zoom.us/oauth/token?grant_type=account_credentials&account_id=" . urlencode($accountId),
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => "",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Basic " . base64_encode("$clientId:$clientSecret"),
            "Content-Type: application/x-www-form-urlencoded",
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function createZoomMeeting($conn, $topic, $startDate, $startTime, $durationMinutes = 60) {
    $token = getZoomToken($conn);
    if (!$token) return null;

    $timezone      = getAdminSetting($conn, 'zoom_timezone', 'UTC');
    $startDatetime = date("Y-m-d\TH:i:s", strtotime("$startDate $startTime"));

    $payload = json_encode([
        "topic"      => $topic,
        "type"       => 2,
        "start_time" => $startDatetime,
        "duration"   => (int)$durationMinutes,
        "timezone"   => $timezone,
        "settings"   => [
            "host_video"        => true,
            "participant_video" => true,
            "join_before_host"  => true,
            "mute_upon_entry"   => false,
            "waiting_room"      => false,
        ],
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => "https://api.zoom.us/v2/users/me/meetings",
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['join_url'] ?? null;
}

function zoomCredentialsSet($conn) {
    return getAdminSetting($conn, 'zoom_account_id', '') !== ''
        && getAdminSetting($conn, 'zoom_client_id',  '') !== ''
        && getAdminSetting($conn, 'zoom_client_secret', '') !== '';
}
?>
