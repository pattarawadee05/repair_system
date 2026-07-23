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
    <meta name="color-scheme" content="light">
    <title>Executive Control Center - MBS REPAIR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { color-scheme: light; }
        body { font-family: 'Kanit', 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; color: #1e293b; }
        .saas-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.02), 0 10px 15px -3px rgba(0, 0, 0, 0.03);
            transition: all 0.25s ease;
        }
        .saas-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            transform: translateY(-2px);
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        @media print {
            header, nav, .no-print { display: none !important; }
            main { padding: 0 !important; background: white; }
            .saas-card { box-shadow: none; border: 1px solid #cbd5e1; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- Top Navigation Header (โฉมใหม่: แถบควบคุมด้านบน) -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            
            <!-- Logo & Title -->
            <div class="flex items-center space-x-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
                    <i class="fas fa-chart-line text-lg"></i>
                </div>
                <div>
                    <span class="text-xs font-bold text-blue-600 uppercase tracking-widest">Executive Portal</span>
                    <h1 class="text-lg font-extrabold text-slate-800 leading-tight">MBS Smart Analytics</h1>
                </div>
            </div>

            <!-- Top Actions -->
            <div class="flex items-center space-x-4">
                <button onclick="window.print()" class="hidden sm:flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition-all">
                    <i class="fas fa-print mr-2 text-slate-500"></i> พิมพ์รายงาน
                </button>
                <a href="index.php" class="flex items-center px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold text-xs rounded-xl border border-rose-200 transition-all">
                    <i class="fas fa-sign-out-alt mr-2"></i> ออกจากระบบ
                </a>
            </div>

        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <!-- Welcome Hero Banner -->
        <div class="saas-card p-6 md:p-8 bg-gradient-to-r from-blue-900 via-slate-900 to-indigo-950 text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 bottom-0 w-1/3 bg-gradient-to-l from-blue-500/10 to-transparent pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <div class="inline-flex items-center space-x-2 bg-blue-500/20 px-3 py-1 rounded-full border border-blue-400/30 text-blue-300 text-xs font-semibold mb-3">
                        <i class="fas fa-sparkles"></i> <span>Executive Insight Hub</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">ภาพรวมสถิติและวิเคราะห์ระบบซ่อมบำรุง</h2>
                    <p class="text-slate-300 text-xs md:text-sm mt-1">ข้อมูลเพื่อประกอบการตัดสินใจเชิงบริหารและจัดสรรงบประมาณ</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/10 text-right shrink-0">
                    <span class="block text-[11px] text-slate-300 uppercase tracking-wider">สถานะระบบ</span>
                    <span class="text-sm font-bold text-emerald-400 flex items-center justify-end gap-1.5 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Online & Sync
                    </span>
                </div>
            </div>
        </div>

        <!-- 1. Executive KPIs Section -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <!-- KPI 1 -->
            <div class="saas-card p-5 border-l-4 border-l-blue-600">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">อัตราซ่อมสำเร็จ (KPI)</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $success_rate; ?><span class="text-lg text-slate-400 font-normal">%</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                        <i class="fas fa-chart-line text-lg"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">เสร็จสิ้นแล้ว</span>
                    <span class="font-bold text-emerald-600"><?php echo $completed_repairs; ?> / <?php echo $total_repairs; ?> งาน</span>
                </div>
            </div>

            <!-- KPI 2 -->
            <div class="saas-card p-5 border-l-4 border-l-indigo-600">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">จำนวนงานแจ้งซ่อมรวม</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $total_repairs; ?> <span class="text-xs font-normal text-slate-400">งาน</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i class="fas fa-boxes-stacked text-lg"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">กำลังดำเนินการ</span>
                    <span class="font-bold text-indigo-600"><?php echo $in_progress_repairs; ?> งาน</span>
                </div>
            </div>

            <!-- KPI 3 -->
            <div class="saas-card p-5 border-l-4 border-l-amber-500">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">งานรอดำเนินการ</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $pending_repairs; ?> <span class="text-xs font-normal text-slate-400">งาน</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">สถานะ</span>
                    <span class="font-bold text-amber-600">รอรับเรื่อง / จัดสรรช่าง</span>
                </div>
            </div>

            <!-- KPI 4 -->
            <div class="saas-card p-5 border-l-4 border-l-emerald-500">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายรวม</span>
                        <h3 class="text-2xl font-black text-slate-800 mt-1">฿<?php echo number_format($total_cost, 0); ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                        <i class="fas fa-wallet text-lg"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">รวมงบซ่อมบำรุง</span>
                    <span class="font-bold text-emerald-600">ตามบันทึกในระบบ</span>
                </div>
            </div>

        </div>

        <!-- AI Recommendation Widget -->
        <div class="saas-card p-5 bg-gradient-to-r from-blue-50 to-indigo-50 border-blue-200">
            <div class="flex items-start sm:items-center space-x-4">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-blue-500/20">
                    <i class="fas fa-robot text-lg"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        AI Executive Recommendation
                        <span class="text-[10px] bg-blue-200 text-blue-800 font-extrabold px-2 py-0.5 rounded-full">SMART ANALYTICS</span>
                    </h4>
                    <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                        พบสถิติการแจ้งเสียของ <strong class="text-blue-700 underline underline-offset-2">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> บ่อยที่สุดในองค์กร (รวม <?php echo $top_equipment_count; ?> ครั้ง) 
                        <span class="text-blue-800 font-medium block sm:inline">💡 แนะนำ: พิจารณาตั้งงบจัดซื้อเปลี่ยนทดแทนครุภัณฑ์ชุดใหม่เพื่อลดต้นทุนซ่อมบำรุงระยะยาว</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- 2. Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Line Chart: Predictive Trend (2 Cols) -->
            <div class="lg:col-span-2 saas-card p-6 flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base flex items-center">
                            <i class="fas fa-chart-area text-blue-600 mr-2"></i> สถิติงานซ่อมและวิเคราะห์แนวโน้มรายเดือน
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">สถิติจริงย้อนหลังเทียบกับระบบคาดการณ์ปริมาณงานในเดือนถัดไป</p>
                    </div>
                </div>
                <div class="flex-1 relative w-full h-[280px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Doughnut Chart: Device Breakdown (1 Col) -->
            <div class="saas-card p-6 flex flex-col">
                <div class="mb-6">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-chart-pie text-indigo-600 mr-2"></i> สัดส่วนประเภทอุปกรณ์
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">จำแนกตามกลุ่มอุปกรณ์ที่แจ้งซ่อม</p>
                </div>
                <div class="flex-1 relative w-full h-[280px] flex items-center justify-center">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>

        </div>

        <!-- 3. Bottom Grid: Top Locations & Recent Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Horizontal Bar: Location Breakdown (1 Col) -->
            <div class="saas-card p-6 flex flex-col">
                <div class="mb-6">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-building text-amber-500 mr-2"></i> 5 อันดับหน่วยงานที่แจ้งซ่อมสูงสุด
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">ข้อมูลวิเคราะห์เพื่อกระจายทีมช่าง</p>
                </div>
                <div class="flex-1 relative w-full h-[260px]">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>

            <!-- Recent Repairs Table (2 Cols) -->
            <div class="lg:col-span-2 saas-card overflow-hidden flex flex-col">
                <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-list-alt text-slate-400 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด
                    </h3>
                    <span class="text-xs text-slate-500 font-semibold bg-white border border-slate-200 px-3 py-1 rounded-lg">5 รายการล่าสุด</span>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
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

                                        echo "<tr class='hover:bg-slate-50/80 transition-colors'>
                                            <td class='px-6 py-4 text-slate-500'>{$date}</td>
                                            <td class='px-6 py-4 font-bold text-slate-800'>{$ticket}</td>
                                            <td class='px-6 py-4 font-semibold text-slate-700'>{$eq}</td>
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
            gradientBlue.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
            gradientBlue.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

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
                            tension: 0.35
                        },
                        {
                            label: 'คาดการณ์ (Forecast)',
                            data: <?php echo $forecast_data_json; ?>,
                            borderColor: '#f59e0b',
                            borderWidth: 3,
                            borderDash: [5, 5],
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#f59e0b',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { position: 'top', labels: { usePointStyle: true, font: { size: 12 } } } 
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4], color: '#e2e8f0' } },
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
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    }
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
                        borderRadius: 6,
                        barPercentage: 0.55
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    indexAxis: 'y', 
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4], color: '#e2e8f0' } }, 
                        y: { grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>