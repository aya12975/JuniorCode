<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit();
}

$teacherId = (int)($_SESSION["user_id"] ?? 0);
$teacherName = $_SESSION["username"] ?? "Teacher";

/* week start */
$startDate = isset($_GET["start"]) ? $_GET["start"] : date("Y-m-d");
$startTimestamp = strtotime($startDate);

$days = [];
for ($i = 0; $i < 7; $i++) {
    $days[] = date("Y-m-d", strtotime("+$i day", $startTimestamp));
}

$times = [
    "08:00:00", "09:00:00", "10:00:00", "11:00:00",
    "12:00:00", "13:00:00", "14:00:00", "15:00:00",
    "16:00:00", "17:00:00", "18:00:00", "19:00:00"
];

/* load saved availability */
$availability = [];

$stmt = $conn->prepare("
    SELECT available_date, available_time, status
    FROM teacher_availability
    WHERE teacher_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $key = $row["available_date"] . "_" . $row["available_time"];
    $availability[$key] = $row["status"];
}

$stmt->close();

$prevWeek = date("Y-m-d", strtotime("-7 days", $startTimestamp));
$nextWeek = date("Y-m-d", strtotime("+7 days", $startTimestamp));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Schedule</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f4f7fb; font-family:Arial,sans-serif; }
    .page-wrap { max-width:1400px; margin:30px auto; padding:0 15px; }
    .top-box {
      background: linear-gradient(135deg, #0f4fd6, #1d4ed8);
      color:white; border-radius:20px; padding:20px 24px; margin-bottom:20px;
    }
    .card-box {
      background:white; border-radius:20px; padding:20px;
      box-shadow:0 10px 24px rgba(15,23,42,0.08);
    }
    .schedule-table { width:100%; border-collapse:collapse; }
    .schedule-table th, .schedule-table td {
      border:1px solid #e5e7eb; text-align:center; padding:10px;
    }
    .time-col { width:110px; font-weight:bold; background:#f8fafc; }
    .slot {
      cursor:pointer;
      min-height:55px;
      transition:0.2s;
      background:#ffffff;
    }
    .slot:hover { background:#eef2ff; }
    .slot.available {
      background:#dcfce7;
      color:#166534;
      font-weight:bold;
    }
    .week-nav {
      display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;
      gap: 12px;
    }
  </style>
</head>
<body>

<div class="page-wrap">
  <div class="top-box">
    <h2 class="mb-1">Hello, <?php echo htmlspecialchars($teacherName); ?></h2>
    <p class="mb-0">Your Teaching Schedule</p>
  </div>

  <div class="card-box">
    <div class="week-nav">
      <a href="teacher_schedule.php?start=<?php echo $prevWeek; ?>" class="btn btn-primary">&lt;</a>
      <h4 class="mb-0">
        <?php echo date("M d", strtotime($days[0])); ?> - <?php echo date("M d", strtotime($days[6])); ?>
      </h4>
      <a href="teacher_schedule.php?start=<?php echo $nextWeek; ?>" class="btn btn-primary">&gt;</a>
    </div>

    <table class="schedule-table">
      <thead>
        <tr>
          <th class="time-col">Time</th>
          <?php foreach ($days as $day): ?>
            <th>
              <?php echo date("D", strtotime($day)); ?><br>
              <?php echo date("d-M", strtotime($day)); ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($times as $time): ?>
          <tr>
            <td class="time-col"><?php echo date("g:i A", strtotime($time)); ?></td>
            <?php foreach ($days as $day): ?>
              <?php
                $key = $day . "_" . $time;
                $status = $availability[$key] ?? "";
              ?>
              <td
                class="slot <?php echo $status === "available" ? "available" : ""; ?>"
                data-date="<?php echo $day; ?>"
                data-time="<?php echo $time; ?>"
              >
                <?php echo $status === "available" ? "Available" : ""; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="mt-3">
      <a href="teacher_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
  </div>
</div>

<script>
document.querySelectorAll(".slot").forEach(slot => {
  slot.addEventListener("click", function () {
    const date = this.dataset.date;
    const time = this.dataset.time;
    const currentStatus = this.classList.contains("available") ? "available" : "none";
    const newStatus = currentStatus === "available" ? "remove" : "available";
    const cell = this;

    fetch("save_availability.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "date=" + encodeURIComponent(date) +
            "&time=" + encodeURIComponent(time) +
            "&status=" + encodeURIComponent(newStatus)
    })
    .then(response => response.text())
    .then(data => {
      if (data.trim() === "success") {
        if (newStatus === "available") {
          cell.classList.add("available");
          cell.textContent = "Available";
        } else {
          cell.classList.remove("available");
          cell.textContent = "";
        }
      } else {
        alert("Error saving availability");
      }
    })
    .catch(() => {
      alert("Error saving availability");
    });
  });
});
</script>

</body>
</html>