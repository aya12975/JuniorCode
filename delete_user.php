<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit();
}

$id = $_GET["id"] ?? null;
if (!$id) {
    header("Location: manage_users.php?error=1");
    exit();
}

$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: manage_users.php?success=1");
    exit();
} else {
    header("Location: manage_users.php?error=1");
    exit();
}