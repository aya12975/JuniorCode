<?php
session_start();
require_once "db.php";
require_once "notifications.php";
header("Content-Type: application/json");
if (!isset($_SESSION["user_id"])) { echo json_encode(["ok" => false]); exit(); }
markAllRead($conn, (int)$_SESSION["user_id"]);
echo json_encode(["ok" => true]);
