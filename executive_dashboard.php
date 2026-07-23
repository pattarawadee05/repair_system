<?php 
include 'db_connect.php'; 

// ตรวจสอบการมีอยู่ของตาราง repairs
$check_repairs = $conn->query("SHOW TABLES LIKE 'repairs'");

$total_repairs = 0;
$completed_repairs = 0;
$pending_repairs = 0;
$in_progress_repairs = 0;
$success_rate = 0;
$total_cost = 0;

$monthly_labels_json = "[]";
$monthly_data_json = "[]";
$forecast_data_json = "[]";

$location_labels_json = "[]";
$location_data_json = "[]";

$device_labels_json = "[]";
$device_data_json = "[]";

$top_equipment = "ไม่มีข้อมูล";
$top_equipment_count = 0;

if($check_repairs && $check_repairs->num_rows > 0) {
    // 1. KPIs สถิติต่างๆ
    $res = $conn->query("SELECT count(*) as c FROM repairs");
    $total_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='ซ่อมเสร็จแล้ว' OR status='เสร็จสิ้น'");
    $completed_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='กำลังดำเนินการ'");
    $in_progress_repairs = $res ? $res->fetch_assoc()['c'] : 0;

    $res = $conn->query("SELECT count(*) as c FROM repairs WHERE status='รอดำเนินการ' OR status='รอรับเรื่อง'");
    $pending_repairs = $res ? $res->fetch_assoc()['c'] : 0;
    
    $success_rate = ($total_repairs > 0) ? round(($completed_repairs / $total_repairs) * 100) : 0;

    $cost_res = $conn->query("SELECT SUM(cost) as total FROM repairs");
    if($cost_res) {
        $cost_row = $cost_res->fetch_assoc();
        $total_cost = $cost_row['total'] ?? 0;
    }

    // 2. วิเคราะห์ Top Equipment
    $top_eq_query = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC LIMIT 1");
    if($top_eq_query && $top_eq_query->num_rows > 0) {
        $top_eq_data = $top_eq_query->fetch_assoc();
        $top_equipment = $top_eq_data['equipment_type'] ?: 'ไม่ระบุ';
        $top_equipment_count = $top_eq_data['cnt'];
    }

    // 3. Top Locations
    $loc_res = $conn->query("SELECT location, COUNT(*) as cnt FROM repairs GROUP BY location ORDER BY cnt DESC LIMIT 5");
    $loc_labels = []; $loc_counts = [];
    if ($loc_res) {
        while($loc = $loc_res->fetch_assoc()){ 
            $loc_labels[] = $loc['location'] ?: 'ไม่ระบุ'; 
            $loc_counts[] = $loc['cnt']; 
        }
    }
    $location_labels_json = json_encode($loc_labels);
    $location_data_json = json_encode($loc_counts);

    // 4. Device Type
    $dev_res = $conn->query("SELECT equipment_type, COUNT(*) as cnt FROM repairs GROUP BY equipment_type ORDER BY cnt DESC");
    $dev_labels = []; $dev_counts = [];
    if ($dev_res) {
        while($dev = $dev_res->fetch_assoc()){ 
            $dev_labels[] = $dev['equipment_type'] ?: 'อื่นๆ'; 
            $dev_counts[] = $dev['cnt']; 
        }
    }
    $device_labels_json = json_encode($dev_labels);
    $device_data_json = json_encode($dev_counts);

    // 5. Monthly Predictive Trend
    $thai_months = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $current_m = (int)date('m') - 1;
    
    $labels = [];
    $actual_data = [];
    $forecast_data = [];

    for($i = 4; $i >= 0; $i--) {
        $m_index = ($current_m - $i + 12) % 12;
        $m_num = $m_index + 1;
        $labels[] = $thai_months[$m_index];
        
        $m_query = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE MONTH(created_at) = {$m_num}");
        $cnt = $m_query ? $m_query->fetch_assoc()['cnt'] : 0;
        $actual_data[] = (int)$cnt;
        $forecast_data[] = null;
    }

    $next_m_index = ($current_m + 1) % 12;
    $labels[] = $thai_months[$next_m_index] . " (คาดการณ์)";
    $avg = count($actual_data) > 0 ? array_sum($actual_data) / count($actual_data) : 0;
    $predicted_value = round($avg * 1.15);
    
    $forecast_data[count($actual_data)-1] = $actual_data[count($actual_data)-1];
    $actual_data[] = null; 
    $forecast_data[] = $predicted_value;

    $monthly_labels_json = json_encode($labels);
    $monthly_data_json = json_encode($actual_data);
    $forecast_data_json = json_encode($forecast_data);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Control Dashboard - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #0f172a; }
        .bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bento-card:hover {
            box-shadow: 0 12px 30px -4px rgba(59, 130, 246, 0.08);
            border-color: #cbd5e1;
            transform: translateY(-3px);
        }
        .app-logo-box {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 1.25rem;
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.4);
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        @media print {
            header, .no-print { display: none !important; }
            main { padding: 0 !important; }
            .bento-card { box-shadow: none; border: 1px solid #cbd5e1; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased pb-12">

    <!-- Top Floating Header -->
    <header class="sticky top-4 z-40 px-4 sm:px-8 max-w-7xl mx-auto w-full no-print mb-6">
        <div class="bento-card bg-white/90 backdrop-blur-xl px-6 py-4 flex items-center justify-between border border-slate-200/80">
            
            <!-- Logo Zone (คืนชีพโลโก้เดิม) -->
            <div class="flex items-center space-x-4">
                <div class="app-logo-box w-12 h-12 flex items-center justify-center text-white text-2xl shrink-0">
                    <i class="fas fa-wrench"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight leading-none">MBS REPAIR</h1>
                    <span class="text-[11px] font-bold text-blue-600 uppercase tracking-widest">Executive Analytics</span>
                </div>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center space-x-3">
                <button onclick="window.print()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold flex items-center transition-all">
                    <i class="fas fa-print mr-2 text-slate-500"></i> พิมพ์รายงาน
                </button>
                <a href="index.php" class="px-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold flex items-center transition-all border border-rose-200/60">
                    <i class="fas fa-power-off mr-2"></i> ออกจากระบบ
                </a>
            </div>

        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-8 space-y-6">

        <!-- Banner Welcome Header -->
        <div class="bento-card p-8 bg-gradient-to-r from-slate-900 via-blue-950 to-indigo-900 text-white relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between md:items-center gap-6">
                <div>
                    <span class="inline-block px-3 py-1 bg-blue-500/20 text-blue-300 rounded-full text-xs font-bold tracking-wider uppercase mb-3 border border-blue-400/30">
                        ⚡ Executive Command Center
                    </span>
                    <h2 class="text-3xl font-black tracking-tight">ภาพรวมและดัชนีชี้วัดผลการดำเนินงาน</h2>
                    <p class="text-slate-300 text-sm mt-1">วิเคราะห์ข้อมูลการแจ้งซ่อม สถิติอุปกรณ์ และการพยากรณ์งานล่วงหน้า</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-5 py-3.5 rounded-2xl border border-white/10 flex items-center space-x-3 shrink-0">
                    <div class="w-3 h-3 rounded-full bg-emerald-400 animate-ping"></div>
                    <div>
                        <div class="text-[10px] text-slate-300 uppercase font-bold tracking-wider">System Status</div>
                        <div class="text-xs font-extrabold text-white">พร้อมใช้งาน (Live Data)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bento Grid 1: KPI Highlight Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <!-- Card 1: Success Rate -->
            <div class="bento-card p-6 bg-gradient-to-br from-white to-blue-50/50">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-extrabold text-blue-600 uppercase tracking-wider">อัตราซ่อมสำเร็จ (KPI)</span>
                    <div class="w-10 h-10 rounded-2xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-4xl font-black text-slate-900"><?php echo $success_rate; ?>%</span>
                </div>
                <div class="mt-3 text-xs font-semibold text-slate-500 flex items-center">
                    <span class="text-emerald-600 font-bold mr-1">✓ เสร็จสิ้น <?php echo $completed_repairs; ?></span> จาก <?php echo $total_repairs; ?> รายการ
                </div>
            </div>

            <!-- Card 2: Total Jobs -->
            <div class="bento-card p-6 bg-gradient-to-br from-white to-indigo-50/50">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-extrabold text-indigo-600 uppercase tracking-wider">แจ้งซ่อมทั้งหมด</span>
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 text-indigo-600 flex items-center justify-center font-bold">
                        <i class="fas fa-screwdriver-wrench"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-4xl font-black text-slate-900"><?php echo $total_repairs; ?></span>
                    <span class="text-xs font-semibold text-slate-400 ml-1">รายการ</span>
                </div>
                <div class="mt-3 text-xs font-semibold text-indigo-600">
                    ⏳ กำลังซ่อมอยู่ <?php echo $in_progress_repairs; ?> รายการ
                </div>
            </div>

            <!-- Card 3: Pending -->
            <div class="bento-card p-6 bg-gradient-to-br from-white to-amber-50/50">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-extrabold text-amber-600 uppercase tracking-wider">งานค้าง / รอรับเรื่อง</span>
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-4xl font-black text-slate-900"><?php echo $pending_repairs; ?></span>
                    <span class="text-xs font-semibold text-slate-400 ml-1">รายการ</span>
                </div>
                <div class="mt-3 text-xs font-semibold text-amber-600">
                    ⚠️ ต้องจัดสรรช่างเข้าดูแล
                </div>
            </div>

            <!-- Card 4: Total Cost -->
            <div class="bento-card p-6 bg-gradient-to-br from-white to-emerald-50/50">
                <div class="flex justify-between items-start">
                    <span class="text-xs font-extrabold text-emerald-600 uppercase tracking-wider">งบประมาณรวม</span>
                    <div class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="mt-4">
                    <span class="text-3xl font-black text-slate-900">฿<?php echo number_format($total_cost, 0); ?></span>
                </div>
                <div class="mt-3 text-xs font-semibold text-emerald-600">
                    📊 สรุปยอดรวมค่าใช้จ่ายจริง
                </div>
            </div>

        </div>

        <!-- AI Executive Insight Banner -->
        <div class="bento-card p-5 bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-transparent border-blue-200">
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl shrink-0 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center space-x-2">
                        <h4 class="font-extrabold text-slate-900 text-sm">AI Executive Recommendation</h4>
                        <span class="bg-blue-600 text-white text-[9px] font-black px-2 py-0.5 rounded-full">SMART ANALYTICS</span>
                    </div>
                    <p class="text-xs text-slate-600 mt-1">
                        อุปกรณ์ประเภท <strong class="text-blue-700 underline underline-offset-2">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> มีสถิติการเสียสูงสุดในองค์กร (รวม <?php echo $top_equipment_count; ?> ครั้ง) 💡 <strong>ข้อแนะนำ:</strong> พิจารณาจัดตั้งงบประมาณเปลี่ยนใหม่เพื่อลดค่าซ่อมบำรุงระยะยาว
                    </p>
                </div>
            </div>
        </div>

        <!-- Bento Grid 2: Main Data Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Line Chart (2 Cols) -->
            <div class="lg:col-span-2 bento-card p-6 flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="font-bold text-slate-900 text-base flex items-center">
                            <i class="fas fa-chart-area text-blue-600 mr-2"></i> สถิติการแจ้งซ่อมและคาดการณ์แนวโน้ม
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">เปรียบเทียบสถิติจริงย้อนหลังและระบบทำนายล่วงหน้า</p>
                    </div>
                </div>
                <div class="flex-1 relative w-full h-[280px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Doughnut Chart (1 Col) -->
            <div class="bento-card p-6 flex flex-col">
                <div class="mb-6">
                    <h3 class="font-bold text-slate-900 text-base flex items-center">
                        <i class="fas fa-chart-pie text-indigo-600 mr-2"></i> สัดส่วนตามประเภทอุปกรณ์
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">จำแนกตามกลุ่มของครุภัณฑ์</p>
                </div>
                <div class="flex-1 relative w-full h-[280px] flex items-center justify-center">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Bento Grid 3: Locations & Recent Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Horizontal Bar Chart (1 Col) -->
            <div class="bento-card p-6 flex flex-col">
                <div class="mb-6">
                    <h3 class="font-bold text-slate-900 text-base flex items-center">
                        <i class="fas fa-building-flag text-amber-500 mr-2"></i> 5 หน่วยงานที่แจ้งซ่อมมากสุด
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">วิเคราะห์พื้นที่เพื่อกระจายกำลังช่าง</p>
                </div>
                <div class="flex-1 relative w-full h-[260px]">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>

            <!-- Recent Repairs Table (2 Cols) -->
            <div class="lg:col-span-2 bento-card overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-900 text-base flex items-center">
                        <i class="fas fa-list-check text-slate-500 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด
                    </h3>
                    <span class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1 rounded-xl">5 รายการล่าสุด</span>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-400 text-[11px] font-extrabold uppercase tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-3.5">วัน/เวลา</th>
                                <th class="px-6 py-3.5">เลขใบงาน</th>
                                <th class="px-6 py-3.5">ประเภทอุปกรณ์</th>
                                <th class="px-6 py-3.5">สถานที่</th>
                                <th class="px-6 py-3.5 text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-slate-100">
                            <?php
                            if($check_repairs && $check_repairs->num_rows > 0) {
                                $recent_res = $conn->query("SELECT * FROM repairs ORDER BY created_at DESC LIMIT 5");
                                if($recent_res && $recent_res->num_rows > 0){
                                    while($row = $recent_res->fetch_assoc()) {
                                        $date = date("d/m/Y H:i", strtotime($row['created_at']));
                                        
                                        $st = $row['status'] ?? '';
                                        $badgeStyle = "bg-slate-100 text-slate-600 border-slate-200";
                                        if($st == 'รอรับเรื่อง' || $st == 'รอดำเนินการ') $badgeStyle = "bg-amber-50 text-amber-700 border-amber-200";
                                        elseif($st == 'กำลังดำเนินการ') $badgeStyle = "bg-blue-50 text-blue-700 border-blue-200";
                                        elseif($st == 'ซ่อมเสร็จแล้ว' || $st == 'เสร็จสิ้น') $badgeStyle = "bg-emerald-50 text-emerald-700 border-emerald-200";

                                        $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                        $eq = $row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ');
                                        $loc = $row['location'] ?? 'ไม่ระบุ';

                                        echo "<tr class='hover:bg-slate-50 transition-colors'>
                                            <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                            <td class='px-6 py-4 font-bold text-blue-600'>{$ticket}</td>
                                            <td class='px-6 py-4 font-semibold text-slate-800'>{$eq}</td>
                                            <td class='px-6 py-4 text-slate-600'>{$loc}</td>
                                            <td class='px-6 py-4 text-center'><span class='inline-block px-3 py-1 rounded-full text-[11px] font-bold border {$badgeStyle}'>{$st}</span></td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='5' class='px-6 py-8 text-center text-slate-400'>ไม่มีข้อมูลงานแจ้งซ่อม</td></tr>"; }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <script>
        Chart.defaults.font.family = "'Kanit', 'Plus Jakarta Sans', sans-serif";
        Chart.defaults.color = '#64748b';

        document.addEventListener('DOMContentLoaded', () => {
            
            // 1. Line Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const gradientBlue = trendCtx.createLinearGradient(0, 0, 0, 300);
            gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
            gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'งานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#2563eb',
                            backgroundColor: gradientBlue,
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#2563eb',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'คาดการณ์ (Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#f59e0b',
                            borderWidth: 3,
                            borderDash: [6, 6],
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#f59e0b',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true } } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4], color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            // 2. Doughnut Chart
            const devCtx = document.getElementById('deviceChart').getContext('2d');
            new Chart(devCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo $device_labels_json; ?>,
                    datasets: [{
                        data: <?php echo $device_data_json; ?>,
                        backgroundColor: ['#2563eb', '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'],
                        borderWidth: 3,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
                }
            });

            // 3. Horizontal Bar Chart
            const locCtx = document.getElementById('locationChart').getContext('2d');
            new Chart(locCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo $location_labels_json; ?>,
                    datasets: [{ 
                        label: 'จำนวนงาน', 
                        data: <?php echo $location_data_json; ?>, 
                        backgroundColor: '#3b82f6',
                        borderRadius: 8,
                        barPercentage: 0.5
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4], color: '#f1f5f9' } }, 
                        y: { grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>