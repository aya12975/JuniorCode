<?php
/* notifications.php — shared notification helpers */

function ensureNotificationsTable($conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT          NOT NULL,
        type       VARCHAR(50)  NOT NULL DEFAULT 'info',
        title      VARCHAR(255) NOT NULL DEFAULT '',
        message    TEXT         NOT NULL,
        is_read    TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function addNotification($conn, int $userId, string $type, string $title, string $message): void {
    ensureNotificationsTable($conn);
    $s = $conn->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?,?,?,?)");
    if ($s) { $s->bind_param("isss", $userId, $type, $title, $message); $s->execute(); $s->close(); }
}

function getUnreadCount($conn, int $userId): int {
    ensureNotificationsTable($conn);
    $s = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    if (!$s) return 0;
    $s->bind_param("i", $userId); $s->execute();
    $s->bind_result($c); $s->fetch(); $s->close();
    return (int)$c;
}

function getNotifications($conn, int $userId, int $limit = 15): array {
    ensureNotificationsTable($conn);
    $s = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
    if (!$s) return [];
    $s->bind_param("ii", $userId, $limit); $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function markAllRead($conn, int $userId): void {
    ensureNotificationsTable($conn);
    $s = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
    if ($s) { $s->bind_param("i", $userId); $s->execute(); $s->close(); }
}
