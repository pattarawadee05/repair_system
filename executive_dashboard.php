<?php 
include 'db_connect.php'; 

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
    // 1. KPIs
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

    // 2. Top Equipment
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

    // 4. Device Chart
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

    // 5. Monthly Trend
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
    <title>MBS Repair Executive Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', 'Plus Jakarta Sans', sans-serif; background: #f0f4f9; color: #1e293b; }
        
        .ultra-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .ultra-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 35px -10px rgba(37, 99, 235, 0.12);
            border-color: #cbd5e1;
        }

        .gradient-logo {
            background: linear-gradient(135deg, #0099ff 0%, #0052d4 100%);
            box-shadow: 0 8px 20px rgba(0, 153, 255, 0.35);
        }

        @media print {
            .no-print { display: none !important; }
            main { padding: 0 !important; }
            .ultra-card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- Top Floating Header -->
    <header class="p-4 md:px-8 no-print">
        <div class="max-w-7xl mx-auto bg-white/80 backdrop-blur-md border border-slate-200/80 rounded-2xl px-6 py-4 flex items-center justify-between shadow-sm">
            
            <!-- Logo + Title (โลโก้รูปประแจตามภาพแนบเลยครับ!) -->
            <div class="flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl gradient-logo flex items-center justify-center text-white shrink-0">
                    <i class="fas fa-wrench text-xl transform -rotate-45"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 tracking-tight leading-none">MBS REPAIR</h1>
                    <span class="text-[11px] font-extrabold text-blue-600 uppercase tracking-widest">Executive Analytics Hub</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center space-x-3">
                <button onclick="window.print()" class="hidden sm:flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-xl transition-all">
                    <i class="fas fa-print mr-2 text-slate-500"></i> พิมพ์รายงาน
                </button>
                <a href="index.php" class="flex items-center px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold text-xs rounded-xl border border-rose-200/60 transition-all">
                    <i class="fas fa-power-off mr-2"></i> ออกจากระบบ
                </a>
            </div>

        </div>
    </header>

    <main class="max-w-7xl w-full mx-auto px-4 md:px-8 pb-12 space-y-6 flex-1">
        
        <!-- Big Hero Banner -->
        <div class="ultra-card p-6 md:p-8 bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-500 text-white relative overflow-hidden border-none">
            <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <span class="inline-block bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-[11px] font-bold text-white mb-2">
                        <i class="fas fa-chart-line mr-1"></i> Executive Dashboard 2026
                    </span>
                    <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">ศูนย์ควบคุมและวิเคราะห์ระบบซ่อมบำรุง</h2>
                    <p class="text-blue-100 text-xs md:text-sm mt-1">สรุปข้อมูลภาพรวมเชิงลึกสำหรับผู้บริหารเพื่อการวางแผนแม่นยำ</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 text-center shrink-0">
                    <span class="text-xs text-blue-100 block font-medium">อัตราซ่อมสำเร็จรวม</span>
                    <span class="text-3xl font-black text-white leading-tight"><?php echo $success_rate; ?>%</span>
                </div>
            </div>
        </div>

        <!-- KPI 4 Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            
            <div class="ultra-card p-5 relative overflow-hidden group">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">งานซ่อมทั้งหมด</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $total_repairs; ?> <span class="text-xs font-normal text-slate-400">รายการ</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
                        <i class="fas fa-folder-open"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    เสร็จแล้ว <strong class="text-emerald-600 font-bold"><?php echo $completed_repairs; ?></strong> รายการ
                </div>
            </div>

            <div class="ultra-card p-5 relative overflow-hidden group">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $in_progress_repairs; ?> <span class="text-xs font-normal text-slate-400">รายการ</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                        <i class="fas fa-screwdriver-wrench"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    ช่างกำลังเข้าซ่อมแซม
                </div>
            </div>

            <div class="ultra-card p-5 relative overflow-hidden group">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">งานค้าง / รอรับเรื่อง</span>
                        <h3 class="text-3xl font-black text-slate-800 mt-1"><?php echo $pending_repairs; ?> <span class="text-xs font-normal text-slate-400">รายการ</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl font-bold">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-rose-500 font-medium">
                    ต้องจัดสรรช่างรับเรื่อง
                </div>
            </div>

            <div class="ultra-card p-5 relative overflow-hidden group">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">งบประมาณรวม</span>
                        <h3 class="text-2xl font-black text-slate-800 mt-1">฿<?php echo number_format($total_cost, 0); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                    สรุปตามลงบันทึกในระบบ
                </div>
            </div>

        </div>

        <!-- AI Executive Recommendation Banner -->
        <div class="ultra-card p-5 bg-gradient-to-r from-sky-50 via-indigo-50 to-purple-50 border-blue-200">
            <div class="flex items-center space-x-4">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-bold text-slate-800 text-sm">Executive AI Insight</h4>
                    <p class="text-xs text-slate-600 mt-0.5">
                        อุปกรณ์ประเภท <strong class="text-blue-700">"<?php echo htmlspecialchars($top_equipment); ?>"</strong> มีสถิติแจ้งเสียสูงที่สุด (รวม <?php echo $top_equipment_count; ?> ครั้ง) 💡 <em>แนะนำ: พิจารณาตั้งงบจัดซื้อเปลี่ยนทดแทนอุปกรณ์ล็อตเก่าเพื่อลดค่าใช้จ่ายซ่อมบำรุงระยะยาว</em>
                    </p>
                </div>
            </div>
        </div>

        <!-- Main Analytics Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Line Chart (2 Cols) -->
            <div class="lg:col-span-2 ultra-card p-6 flex flex-col">
                <div class="mb-4">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-chart-area text-blue-600 mr-2"></i> สถิติแจ้งซ่อมรายเดือน & คาดการณ์แนวโน้ม
                    </h3>
                    <p class="text-xs text-slate-400">เปรียบเทียบสถิติจริงและระบบคาดการณ์ล่วงหน้า</p>
                </div>
                <div class="flex-1 relative w-full h-[280px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Doughnut Chart (1 Col) -->
            <div class="ultra-card p-6 flex flex-col">
                <div class="mb-4">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-chart-pie text-indigo-600 mr-2"></i> สัดส่วนตามประเภทอุปกรณ์
                    </h3>
                    <p class="text-xs text-slate-400">จำแนกตามประเภทของครุภัณฑ์</p>
                </div>
                <div class="flex-1 relative w-full h-[280px] flex items-center justify-center">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Bottom Grid: Bar Chart & Recent Table -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="ultra-card p-6 flex flex-col">
                <div class="mb-4">
                    <h3 class="font-bold text-slate-800 text-base flex items-center">
                        <i class="fas fa-location-dot text-rose-500 mr-2"></i> 5 หน่วยงานแจ้งซ่อมมากที่สุด
                    </h3>
                    <p class="text-xs text-slate-400">เพื่อจัดสรรกำลังช่างเข้าดูแลประจำจุด</p>
                </div>
                <div class="flex-1 relative w-full h-[260px]">
                    <canvas id="locationChart"></canvas>
                </div>
            </div>

            <div class="lg:col-span-2 ultra-card p-6 flex flex-col overflow-hidden">
                <div class="mb-4 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base flex items-center">
                            <i class="fas fa-list-check text-emerald-600 mr-2"></i> บันทึกงานแจ้งซ่อมล่าสุด
                        </h3>
                        <p class="text-xs text-slate-400">รายการแจ้งซ่อม 5 รายการล่าสุดในระบบ</p>
                    </div>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-400 text-xs font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="pb-3 px-3">วัน/เวลา</th>
                                <th class="pb-3 px-3">เลขใบงาน</th>
                                <th class="pb-3 px-3">ประเภทอุปกรณ์</th>
                                <th class="pb-3 px-3">สถานที่</th>
                                <th class="pb-3 px-3 text-center">สถานะ</th>
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
                                        $badgeStyle = "bg-slate-100 text-slate-600";
                                        if($st == 'รอรับเรื่อง' || $st == 'รอดำเนินการ') $badgeStyle = "bg-amber-100 text-amber-700 font-bold";
                                        elseif($st == 'กำลังดำเนินการ') $badgeStyle = "bg-blue-100 text-blue-700 font-bold";
                                        elseif($st == 'ซ่อมเสร็จแล้ว' || $st == 'เสร็จสิ้น') $badgeStyle = "bg-emerald-100 text-emerald-700 font-bold";

                                        $ticket = $row['ticket_no'] ?? ('#REP-'.$row['id']);
                                        $eq = $row['equipment_type'] ?? ($row['device_name'] ?? 'ไม่ระบุ');
                                        $loc = $row['location'] ?? 'ไม่ระบุ';

                                        echo "<tr class='hover:bg-slate-50 transition-colors'>
                                            <td class='py-3.5 px-3 text-slate-400'>{$date}</td>
                                            <td class='py-3.5 px-3 font-bold text-blue-600'>{$ticket}</td>
                                            <td class='py-3.5 px-3 font-semibold text-slate-700'>{$eq}</td>
                                            <td class='py-3.5 px-3 text-slate-500'>{$loc}</td>
                                            <td class='py-3.5 px-3 text-center'><span class='inline-block px-3 py-1 rounded-full text-[10px] {$badgeStyle}'>{$st}</span></td>
                                        </tr>";
                                    }
                                } else { echo "<tr><td colspan='5' class='py-6 text-center text-slate-400'>ไม่มีข้อมูล</td></tr>"; }
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
            const grad = trendCtx.createLinearGradient(0, 0, 0, 250);
            grad.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
            grad.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo $monthly_labels_json; ?>,
                    datasets: [
                        {
                            label: 'งานซ่อมจริง',
                            data: <?php echo $monthly_data_json; ?>,
                            borderColor: '#2563eb',
                            backgroundColor: grad,
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#2563eb',
                            pointBorderWidth: 3,
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
                            pointBorderWidth: 3,
                            pointRadius: 6,
                            pointStyle: 'rectRot',
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4] } },
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
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10 } } }
                }
            });

            // 3. Bar Chart
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
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    plugins: { legend: { display: false } }, 
                    scales: { 
                        x: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [4, 4] } }, 
                        y: { grid: { display: false } } 
                    } 
                }
            });

        });
    </script>
</body>
</html>