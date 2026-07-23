<?php
// เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// 1. ดึงข้อมูลตัวเลขสรุป (Summary Cards)
$total_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM repairs");
$total_jobs = mysqli_fetch_assoc($total_query)['count'] ?? 0;

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM repairs WHERE status = 'รอดำเนินการ'");
$pending_jobs = mysqli_fetch_assoc($pending_query)['count'] ?? 0;

$in_progress_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM repairs WHERE status = 'กำลังดำเนินการ'");
$in_progress_jobs = mysqli_fetch_assoc($in_progress_query)['count'] ?? 0;

$completed_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM repairs WHERE status = 'เสร็จสิ้น'");
$completed_jobs = mysqli_fetch_assoc($completed_query)['count'] ?? 0;

// 2. ดึงสถิติตามประเภทอุปกรณ์ (สำหรับ Pie Chart)
$device_query = mysqli_query($conn, "SELECT device_type, COUNT(*) as count FROM repairs GROUP BY device_type");
$device_labels = [];
$device_counts = [];
while ($row = mysqli_fetch_assoc($device_query)) {
    $device_labels[] = $row['device_type'] ? $row['device_type'] : 'ไม่ระบุ';
    $device_counts[] = $row['count'];
}

// 3. ดึงสถิติตามเดือน (สำหรับ Bar Chart)
$monthly_query = mysqli_query($conn, "SELECT MONTHNAME(created_at) as month_name, COUNT(*) as count FROM repairs GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)");
$month_labels = [];
$month_counts = [];
while ($row = mysqli_fetch_assoc($monthly_query)) {
    $month_labels[] = $row['month_name'];
    $month_counts[] = $row['count'];
}

// 4. ดึง Top 5 อุปกรณ์เสียบ่อย
$top_devices = mysqli_query($conn, "SELECT device_name, COUNT(*) as total FROM repairs GROUP BY device_name ORDER BY total DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Dashboard - ระบบบริหารงานแจ้งซ่อม</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: 'Prompt', sans-serif; }
        .card-summary { border: none; border-radius: 12px; transition: transform 0.2s; }
        .card-summary:hover { transform: translateY(-3px); }
        .icon-box { font-size: 2.5rem; opacity: 0.8; }
    </style>
</head>
<body>

<div class="container my-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fa-solid fa-chart-line text-primary me-2"></i>Executive Dashboard</h2>
            <p class="text-muted mb-0">ระบบวิเคราะห์ข้อมูลและสรุปภาพรวมสำหรับผู้บริหาร</p>
        </div>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> พิมพ์รายงาน</button>
    </div>

    <!-- 1. KPI Cards ภาพรวม -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-summary bg-primary text-white shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">งานแจ้งซ่อมทั้งหมด</h6>
                        <h2 class="fw-bold mb-0"><?php echo $total_jobs; ?></h2>
                    </div>
                    <i class="fa-solid fa-list-check icon-box"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-warning text-dark shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-dark-50">งานค้าง / รอดำเนินการ</h6>
                        <h2 class="fw-bold mb-0"><?php echo $pending_jobs; ?></h2>
                    </div>
                    <i class="fa-solid fa-clock icon-box"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-info text-white shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">กำลังดำเนินการ</h6>
                        <h2 class="fw-bold mb-0"><?php echo $in_progress_jobs; ?></h2>
                    </div>
                    <i class="fa-solid fa-screwdriver-wrench icon-box"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-success text-white shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50">งานเสร็จสมบูรณ์</h6>
                        <h2 class="fw-bold mb-0"><?php echo $completed_jobs; ?></h2>
                    </div>
                    <i class="fa-solid fa-circle-check icon-box"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Monthly Bar Chart -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-bar text-primary me-2"></i>สถิติการแจ้งซ่อมรายเดือน</h5>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        <!-- Device Type Doughnut Chart -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-chart-pie text-success me-2"></i>สัดส่วนสถิติตามประเภทอุปกรณ์</h5>
                <canvas id="deviceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- 3. Top Devices Table -->
    <div class="row g-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3 p-3">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>5 อันดับอุปกรณ์ที่เสียบ่อยที่สุด</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ชื่ออุปกรณ์ / รายการ</th>
                                <th class="text-center">จำนวนครั้งที่แจ้งซ่อม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            while ($top = mysqli_fetch_assoc($top_devices)): 
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary rounded-circle"><?php echo $i++; ?></span></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($top['device_name'] ?? 'ไม่ระบุ'); ?></td>
                                <td class="text-center"><span class="badge bg-danger fs-6"><?php echo $top['total']; ?> ครั้ง</span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
// 1. Bar Chart สถิติรายเดือน
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctxMonthly, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($month_labels); ?>,
        datasets: [{
            label: 'จำนวนงานซ่อม (งาน)',
            data: <?php echo json_encode($month_counts); ?>,
            backgroundColor: '#0d6efd',
            borderRadius: 6
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
});

// 2. Doughnut Chart ตามประเภทอุปกรณ์
const ctxDevice = document.getElementById('deviceChart').getContext('2d');
new Chart(ctxDevice, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($device_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($device_counts); ?>,
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6c757d']
        }]
    },
    options: { responsive: true }
});
</script>

</body>
</html>