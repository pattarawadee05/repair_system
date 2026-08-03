<?php
session_start();
@include 'db_connect.php';

$error_msg = "";
$status_result = null;
$search_keyword = "";

$db_connected = isset($conn) && $conn instanceof mysqli && !$conn->connect_error;

$stats = [
    'pending' => 0,
    'progress' => 0,
    'completed' => 0,
    'total' => 0
];

if ($db_connected) {
    $res_p = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'รอรับเรื่อง'");
    if ($res_p) $stats['pending'] = $res_p->fetch_assoc()['cnt'];

    $res_prog = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'กำลังดำเนินการ'");
    if ($res_prog) $stats['progress'] = $res_prog->fetch_assoc()['cnt'];

    $res_c = $conn->query("SELECT COUNT(*) as cnt FROM repairs WHERE status = 'ซ่อมเสร็จแล้ว'");
    if ($res_c) $stats['completed'] = $res_c->fetch_assoc()['cnt'];

    $res_t = $conn->query("SELECT COUNT(*) as cnt FROM repairs");
    if ($res_t) $stats['total'] = $res_t->fetch_assoc()['cnt'];
}

$all_repairs_json = "[]";
if ($db_connected) {
    $rep_res = $conn->query("SELECT ticket_no, equipment_type, status, DATE_FORMAT(created_at, '%Y-%m-%d') as created_at_fmt, reporter_name, technician_name FROM repairs ORDER BY created_at DESC");
    $reps = [];
    if($rep_res) {
        while($r = $rep_res->fetch_assoc()){ $reps[] = $r; }
        $all_repairs_json = json_encode($reps);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    if ($db_connected) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password']; 

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            $role = strtolower($user['role']);
            if ($role === 'executive') {
                header("Location: executive_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error_msg = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง!";
        }
    } else {
        $error_msg = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_status'])) {
    if ($db_connected) {
        $search_keyword = trim($_POST['search_query']);
        $search_param = "%" . $search_keyword . "%";

        $stmt = $conn->prepare("SELECT ticket_no, equipment_type, status, created_at, technician_name, repair_note, reporter_name 
                                FROM repairs 
                                WHERE ticket_no = ? OR reporter_name LIKE ? OR equipment_type LIKE ?
                                ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("sss", $search_keyword, $search_param, $search_param);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $status_result = [];
            while($row = $res->fetch_assoc()) {
                $status_result[] = $row;
            }
        } else {
            $status_result = 'not_found';
        }
    } else {
        $error_msg = "ไม่สามารถเชื่อมต่อฐานข้อมูลได้";
    }
}
?>
<!DOCTYPE html>
<html lang="th" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBS EXECUTIVE REPAIR — ระบบบริหารจัดการงานซ่อมระดับพรีเมียม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', 'Kanit', sans-serif; 
            background-color: #f8fafc;
            color: #0f172a;
        }

        .premium-hero {
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 50%, #f8fafc 100%);
        }

        .executive-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }
        .executive-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.1);
            border-color: #3b82f6;
        }

        .modal { transition: opacity 0.25s ease, visibility 0.25s ease; }
        body.modal-active { overflow: hidden; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    <!-- 1. EXECUTIVE NAVBAR -->
    <header class="w-full bg-white/90 backdrop-blur-md border-b border-slate-200 sticky top-0 z-45">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl shadow-md shadow-blue-500/20">
                    <i class="fas fa-cube"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black tracking-tight text-slate-900 leading-none">
                        MBS <span class="text-blue-600">EXECUTIVE REPAIR</span>
                    </h1>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">คณะการบัญชีและการจัดการ มมส.</p>
                </div>
            </div>

            <nav class="hidden md:flex items-center gap-8 text-xs font-bold text-slate-600 uppercase tracking-widest">
                <a href="#" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home text-blue-500"></i> หน้าแรก</a>
                <a href="#technicians_section" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-user-gear text-blue-500"></i> ทีมช่างผู้ดูแล</a>
                <a href="#process" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-list-check text-blue-500"></i> ขั้นตอนการทำงาน</a>
                <a href="#developers" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-code text-blue-500"></i> ผู้พัฒนา</a>
            </nav>

            <button onclick="toggleModal('loginModal')" class="bg-slate-900 hover:bg-blue-600 text-white font-extrabold px-5 py-2.5 rounded-xl text-xs transition-all flex items-center gap-2 shadow-sm active:scale-95">
                <i class="fas fa-lock text-blue-400"></i> เจ้าหน้าที่เข้าสู่ระบบ
            </button>
        </div>
    </header>

    <!-- 2. PREMIUM HERO SECTION -->
    <section class="premium-hero pt-16 pb-24 px-4 sm:px-6 lg:px-8 relative overflow-hidden border-b border-slate-200">
        <div class="max-w-4xl mx-auto text-center space-y-6 relative z-10">
            <span class="inline-block px-4 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-extrabold tracking-widest uppercase">
                <i class="fas fa-star text-amber-500 mr-1"></i> ระบบบริหารงานซ่อมระดับองค์กร
            </span>

            <h1 class="text-3xl sm:text-5xl font-black text-slate-900 tracking-tight leading-tight">
                ศูนย์รวมการแจ้งซ่อมและติดตามสถานะ <br>
                <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">สะดวก รวดเร็ว และแม่นยำ</span>
            </h1>

            <p class="text-slate-600 text-xs sm:text-sm max-w-2xl mx-auto font-normal leading-relaxed">
                บริการรับแจ้งซ่อมคอมพิวเตอร์ ระบบเครือข่าย ไฟฟ้า อาคารสถานที่ และอุปกรณ์ในห้องเรียน สำหรับบุคลากรและนิสิต MBS
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                <a href="form_repair.php" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold px-7 py-3.5 rounded-2xl text-xs sm:text-sm shadow-lg shadow-blue-500/25 transition-all transform hover:-translate-y-0.5 flex items-center gap-2">
                    <i class="fas fa-file-pen"></i> กรอกแบบฟอร์มแจ้งซ่อมใหม่
                </a>
                <a href="https://line.me" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold px-6 py-3.5 rounded-2xl text-xs sm:text-sm shadow-lg shadow-emerald-500/20 transition-all transform hover:-translate-y-0.5 flex items-center gap-2">
                    <i class="fab fa-line text-lg"></i> ติดต่อผ่าน LINE Official
                </a>
            </div>

            <!-- Modern Search Box -->
            <div class="max-w-2xl mx-auto bg-white p-4 sm:p-5 rounded-3xl shadow-xl border border-slate-200 text-left mt-8">
                <div class="flex items-center justify-between mb-2.5 px-1">
                    <label class="text-xs font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-magnifying-glass text-blue-600"></i> ค้นหาประวัติ / ตรวจสอบสถานะ (ห้อง, อุปกรณ์, ผู้แจ้ง)
                    </label>
                    <span class="text-[10px] bg-slate-100 text-slate-600 font-bold px-2.5 py-0.5 rounded-full">Instant Search</span>
                </div>
                
                <form action="" method="POST" class="flex flex-col sm:flex-row gap-2.5">
                    <input type="hidden" name="check_status" value="1">
                    <div class="relative flex-1">
                        <i class="fas fa-ticket absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search_query" required placeholder="กรอกเลขใบงาน, ชื่อผู้แจ้ง, หรืออุปกรณ์..." class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 placeholder-slate-400 focus:outline-none focus:border-blue-600 focus:bg-white transition-all">
                    </div>
                    <button type="submit" class="bg-slate-900 hover:bg-blue-600 text-white font-extrabold px-6 py-3 rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-sm">
                        <i class="fas fa-search"></i> ค้นหาข้อมูล
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- 3. METRICS STATS BAR -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-10 relative z-20 w-full">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <div class="executive-card p-5 flex items-center gap-4 bg-white">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold shrink-0">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">รอรับเรื่อง</p>
                    <h3 class="text-xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['pending']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="executive-card p-5 flex items-center gap-4 bg-white">
                <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-bold shrink-0">
                    <i class="fa-regular fa-compass"></i>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">กำลังดำเนินการ</p>
                    <h3 class="text-xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['progress']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="executive-card p-5 flex items-center gap-4 bg-white">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">ซ่อมเสร็จแล้ว</p>
                    <h3 class="text-xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['completed']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>

            <div class="executive-card p-5 flex items-center gap-4 bg-white">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg font-bold shrink-0">
                    <i class="fa-regular fa-clipboard"></i>
                </div>
                <div>
                    <p class="text-[11px] font-bold text-slate-400 uppercase">งานซ่อมทั้งหมด</p>
                    <h3 class="text-xl font-black text-slate-900 mt-0.5"><?php echo number_format($stats['total']); ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. TECHNICIANS DIRECTORY SECTION (เริ่มต้นซ่อนข้อมูลช่างทั้งหมด ต้องกดปุ่มเลือกฝ่ายถึงจะแสดง) -->
    <section id="technicians_section" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full space-y-8">
        <div class="text-center space-y-2">
            <span class="text-blue-600 text-xs font-extrabold uppercase tracking-widest bg-blue-50 px-3.5 py-1.5 rounded-full border border-blue-200">
                <i class="fas fa-users-gear mr-1"></i> Professional Team Directory
            </span>
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">ทีมช่างผู้ดูแลระบบ และข้อมูลการปฏิบัติงาน</h2>
            <p class="text-xs sm:text-sm text-slate-500 font-medium">กรุณาคลิกเลือกฝ่ายงานด้านล่างเพื่อเรียกดูข้อมูลเจ้าหน้าที่และประวัติการทำงาน</p>
        </div>

        <?php 
        $departments_data = [
            'digital' => [
                'name' => 'ฝ่ายงานบริการเทคโนโลยีดิจิทัล',
                'icon' => 'fas fa-laptop-code',
                'techs' => [
                    ['th' => 'นาย สมพร วงษ์จำปา', 'eng' => 'Mr. Somporn Wongchampa', 'phone' => '', 'img' => 'img/Somporn.jpg'],
                    ['th' => 'นาย ปริญญา จันทรภา', 'eng' => 'Mr. Parinya Chanthapha', 'phone' => '', 'img' => 'img/Parinya.jpg'],
                    ['th' => 'นาย ทองสน พลมีศักดิ์', 'eng' => 'Mr. Thongson Phonmeesak', 'phone' => '0-4375-4333 to 3446', 'img' => 'img/Thongson.jpg'],
                    ['th' => 'นาย ธีรศักดิ์ พาโคกทม', 'eng' => 'Mr. Teerasak Pakhokthom', 'phone' => '0-4375-4333 to 3446', 'img' => 'img/Teerasak.jpg']
                ]
            ],
            'audio' => [
                'name' => 'ฝ่ายงานโสตทัศนูปกรณ์',
                'icon' => 'fas fa-tv',
                'techs' => [
                    ['th' => 'นาย จิตรณรงค์ นาใจคง', 'eng' => 'Mr. Chitnarong Najaikong', 'phone' => '086-6343363', 'img' => 'img/chitnarong.jpg'],
                    ['th' => 'นาย ลำไพร ทองบ่อ', 'eng' => 'Mr. Lamprai Thongbo', 'phone' => '080-403-3589 Office. 043-754333-3421', 'img' => 'img/Lamprai.jpg'],
                    ['th' => 'นาย รักชาติ แดงเทโพธิ์', 'eng' => 'Mr. Rakchart Daeng Thepho', 'phone' => '3421', 'img' => 'img/rakchart.jpg'],
                    ['th' => 'นาย ปิยะสันต์ บุญพระ', 'eng' => 'Mr. Piyasan Boonpra', 'phone' => '043-754333 to 3421', 'img' => 'img/piyasan.jpg'],
                    ['th' => 'นาย จตุพล ฤทธิสิงห์', 'eng' => 'Mrs. Jatuphon Rittising', 'phone' => '', 'img' => 'img/Jatuphon.jpg'],
                    ['th' => 'นาย อาทิตย์ บรรเทา', 'eng' => 'Mrs. artin buntua', 'phone' => '', 'img' => 'img/atid.jpg']
                ]
            ],
            'vehicle' => [
                'name' => 'ฝ่ายงานยานยนต์',
                'icon' => 'fas fa-car',
                'techs' => [
                    ['th' => 'นาย ธวัชชัย รัสสมบัติ', 'eng' => 'Mr. Thawatchai Ratchasombat', 'phone' => '0-4371-9800, 064-865-7116', 'img' => 'img/tawatchai.jpg'],
                    ['th' => 'นาย ทรงภพ จันทร์ลอย', 'eng' => 'Mr. Songpop Chanloy', 'phone' => '043-754422 to 3421, Phone 085-0143374', 'img' => 'img/Songpop.jpg'],
                    ['th' => 'นาย รนภักดี ลิงลม', 'eng' => 'Mrs. Ronpakdee Linglom', 'phone' => '', 'img' => 'img/Ronpakdee.jpg'],
                    ['th' => 'นาย กิตติภณ รัดถา', 'eng' => 'Mrs. Kittiphon Rattha', 'phone' => '', 'img' => 'img/Kittiphon.jpg'],
                    ['th' => 'นาย ทิวา เนื่องทะบาล', 'eng' => 'Mr. Tiwa Nuangtabal', 'phone' => '', 'img' => 'img/tiwa.jpg'],
                    ['th' => 'นาย นิรุตติ์ กองเงิน', 'eng' => 'Mr. Nirut Gong-ngern', 'phone' => '', 'img' => 'img/Nirut.jpg'],
                    ['th' => 'นาย อุทัย หาหอม', 'eng' => 'Mr. Uthai Hahom', 'phone' => '', 'img' => 'img/Uthai.jpg']
                ]
            ]
        ];
        ?>

        <!-- ปุ่มกดเลือกฝ่ายงาน (เริ่มต้นไม่มีฝ่ายไหนถูกเปิดแสดงเนื้อหาไว้) -->
        <div class="flex flex-wrap justify-center gap-4">
            <?php foreach($departments_data as $key => $dept): ?>
                <button onclick="switchDept('<?php echo $key; ?>')" id="btn-dept-<?php echo $key; ?>" class="dept-tab-btn px-7 py-3.5 rounded-2xl text-xs sm:text-sm font-bold transition-all shadow-sm flex items-center gap-2.5 bg-white text-slate-700 border border-slate-200 hover:border-blue-400 hover:bg-slate-50">
                    <i class="<?php echo $dept['icon']; ?> text-blue-600"></i> <?php echo $dept['name']; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- กล่องแสดงรายชื่อช่าง (เริ่มต้นซ่อนทั้งหมด hidden) -->
        <div class="mt-8">
            <?php foreach($departments_data as $key => $dept): ?>
                <div id="dept-content-<?php echo $key; ?>" class="dept-content-pane space-y-6 hidden">
                    <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-200/80 space-y-8 animate-fade-in">
                        <h3 class="font-extrabold text-blue-900 text-base sm:text-lg flex items-center border-b pb-4 border-slate-100">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mr-4 text-base shadow-xs">
                                <i class="<?php echo $dept['icon']; ?>"></i>
                            </div>
                            <?php echo $dept['name']; ?>
                        </h3>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 items-start">
                            <?php foreach ($dept['techs'] as $tech): ?>
                            <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs hover:border-blue-300 hover:shadow-md transition-all flex flex-col">
                                <div class="bg-slate-100 aspect-[4/5] overflow-hidden relative">
                                    <img src="<?php echo htmlspecialchars($tech['img']); ?>" alt="<?php echo htmlspecialchars($tech['th']); ?>" class="w-full h-full object-cover" onerror="this.src='https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo urlencode($tech['th']); ?>';">
                                </div>
                                <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                                    <div>
                                        <h5 class="font-bold text-slate-800 text-sm leading-snug">
                                            <?php echo htmlspecialchars($tech['th']); ?>
                                        </h5>
                                        <p class="text-[11px] font-medium text-slate-400 italic mt-0.5">
                                            <?php echo htmlspecialchars($tech['eng']); ?>
                                        </p>
                                        <?php if (!empty($tech['phone'])): ?>
                                        <p class="text-xs text-blue-600 font-semibold mt-2.5 flex items-center">
                                            <i class="fas fa-phone text-[10px] mr-2 opacity-70"></i> 
                                            <?php echo htmlspecialchars($tech['phone']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <button onclick="viewTechnicianHistory('<?php echo htmlspecialchars($tech['th']); ?>')" class="w-full text-xs font-bold text-slate-700 hover:text-white bg-slate-50 hover:bg-blue-600 border border-slate-200 hover:border-blue-600 py-2.5 rounded-xl transition-all shadow-2xs flex items-center justify-center gap-1.5">
                                        <i class="fas fa-history text-[11px]"></i> ดูประวัติการปฏิบัติงาน
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 5. WORKFLOW PROCESS -->
    <section id="process" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
        <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-3xl p-8 sm:p-12 text-white shadow-2xl relative overflow-hidden">
            <div class="text-center space-y-2 mb-12 relative z-10">
                <span class="text-blue-400 text-xs font-extrabold uppercase tracking-widest"><i class="fas fa-route mr-1"></i> Workflow Process</span>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight">ขั้นตอนการแจ้งซ่อมง่ายๆ ใน 4 ขั้นตอน</h2>
                <p class="text-xs sm:text-sm text-slate-300 font-light">ติดตามเรื่องซ่อมสะดวกรวดเร็ว แม่นยำทุกขั้นตอน</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative z-10">
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-blue-400/50 transition-all space-y-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-600 text-white font-extrabold flex items-center justify-center text-sm shadow-md">01</span>
                    <h3 class="text-sm font-bold text-white">1. กรอกแบบฟอร์ม</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ระบุรายละเอียดอุปกรณ์ อาคารสถานที่ และปัญหาที่พบผ่านเว็บ</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-amber-400/50 transition-all space-y-3">
                    <span class="w-10 h-10 rounded-xl bg-amber-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">02</span>
                    <h3 class="text-sm font-bold text-white">2. เจ้าหน้าที่รับเรื่อง</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ทีมช่างตรวจสอบข้อมูลและมอบหมายผู้รับผิดชอบงานซ่อม</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-sky-400/50 transition-all space-y-3">
                    <span class="w-10 h-10 rounded-xl bg-sky-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">03</span>
                    <h3 class="text-sm font-bold text-white">3. ดำเนินการซ่อม</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">ช่างผู้เชี่ยวชาญเข้าแก้ไขตามจุดที่ได้รับแจ้งอย่างรวดเร็ว</p>
                </div>
                <div class="bg-white/10 backdrop-blur-md p-6 rounded-2xl border border-white/10 hover:border-emerald-400/50 transition-all space-y-3">
                    <span class="w-10 h-10 rounded-xl bg-emerald-500 text-white font-extrabold flex items-center justify-center text-sm shadow-md">04</span>
                    <h3 class="text-sm font-bold text-white">4. เสร็จสิ้น & แจ้งเตือน</h3>
                    <p class="text-xs text-slate-300 font-light leading-relaxed">รับอุปกรณ์คืน พร้อมอัปเดตสถานะเป็นซ่อมเสร็จเรียบร้อย</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. FOOTER -->
    <footer id="developers" class="w-full bg-white border-t border-slate-200/80 pt-12 pb-8 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start pb-8 border-b border-slate-100">
                <div class="lg:col-span-5 space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-lg font-bold shadow-md shadow-blue-500/20">
                            <i class="fas fa-cube"></i>
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">MBS <span class="text-blue-600">EXECUTIVE REPAIR</span></h3>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-md font-normal">
                        ระบบรับแจ้งซ่อมออนไลน์ คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม พัฒนาเพื่อยกระดับการให้บริการบุคลากรและนิสิตอย่างมีประสิทธิภาพ
                    </p>
                </div>

                <div class="lg:col-span-7 space-y-3">
                    <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                        <i class="fas fa-code text-blue-600"></i> ผู้พัฒนาโครงการ (Project Developers)
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-2xl flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-xs font-bold text-slate-800 truncate">นางสาวภัทรวดี ขามประโคน</h5>
                                <p class="text-[11px] text-slate-500">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>

                        <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-2xl flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-sm font-bold shrink-0">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h5 class="text-xs font-bold text-slate-800 truncate">นางสาวมัทนา รัตนแสง</h5>
                                <p class="text-[11px] text-slate-500">นิสิตชั้นปีที่ 4 คอมพิวเตอร์ธุรกิจ (BC)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-medium text-slate-400">
                <p>© 2026 MBS REPAIR — คณะการบัญชีและการจัดการ มหาวิทยาลัยมหาสารคาม</p>
                <p class="text-slate-500 font-semibold flex items-center gap-1.5">
                    <i class="fas fa-graduation-cap text-blue-600"></i> Business Computer (BC) MBS
                </p>
            </div>
        </div>
    </footer>

    <!-- MODAL 1: RESULT MODAL -->
    <div id="resultModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="toggleModal('resultModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-2xl mx-auto z-50 overflow-hidden transform transition-all flex flex-col max-h-[85vh] p-6 rounded-3xl shadow-2xl border border-slate-100">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-list-check text-blue-600"></i> ผลการค้นหาประวัติการแจ้งซ่อม
                </h2>
                <button onclick="toggleModal('resultModal')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">
                    <i class="fas fa-xmark text-xs"></i>
                </button>
            </div>
            <div class="py-4 overflow-y-auto flex-1 space-y-3">
                <?php if (is_array($status_result)): ?>
                    <?php foreach($status_result as $res): 
                        $statusClass = "bg-slate-100 text-slate-700 border-slate-200"; 
                        if($res['status'] == 'รอรับเรื่อง') $statusClass = "bg-amber-50 text-amber-800 border-amber-200";
                        elseif($res['status'] == 'กำลังดำเนินการ') $statusClass = "bg-sky-50 text-sky-800 border-sky-200";
                        elseif($res['status'] == 'ซ่อมเสร็จแล้ว') $statusClass = "bg-emerald-50 text-emerald-800 border-emerald-200";
                    ?>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">เลขที่ใบงาน</span>
                                    <h4 class="text-base font-bold text-blue-600"><?php echo $res['ticket_no']; ?></h4>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold border <?php echo $statusClass; ?>">
                                    <?php echo $res['status']; ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs border-t border-slate-200/60 pt-2 text-slate-600">
                                <p><b class="text-slate-800">ห้อง / อุปกรณ์:</b> <?php echo $res['equipment_type']; ?></p>
                                <p><b class="text-slate-800">ผู้แจ้ง:</b> <?php echo $res['reporter_name']; ?></p>
                                <p><b class="text-slate-800">ช่างผู้ดูแล:</b> <?php echo !empty($res['technician_name']) ? $res['technician_name'] : '-'; ?></p>
                                <p><b class="text-slate-800">วันที่แจ้ง:</b> <?php echo date("d/m/Y H:i", strtotime($res['created_at'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button onclick="toggleModal('resultModal')" class="w-full bg-slate-900 hover:bg-blue-600 py-3 rounded-xl font-bold text-xs text-white transition-colors">ปิดหน้าต่าง</button>
        </div>
    </div>

    <!-- MODAL 2: TECHNICIAN HISTORY MODAL -->
    <div id="techHistoryModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50 px-4">
        <div class="modal-overlay absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="toggleModal('techHistoryModal')"></div>
        <div class="modal-container bg-white w-full max-w-3xl mx-auto rounded-3xl shadow-2xl z-50 overflow-hidden transform transition-all flex flex-col h-[80vh] max-h-[800px]">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-3xl shrink-0">
                <p class="text-lg font-extrabold text-slate-900 truncate pr-4 flex items-center gap-2">
                    <i class="fas fa-clipboard-user text-blue-600"></i> ประวัติการปฏิบัติงาน: <span id="techModalName" class="text-blue-600"></span>
                </p>
                <button onclick="toggleModal('techHistoryModal')" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center shrink-0">
                    <i class="fas fa-xmark text-xs"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 bg-white">
                <div class="w-full overflow-x-auto rounded-2xl border border-slate-100 shadow-sm">
                    <table class="w-full text-left whitespace-nowrap min-w-[500px]">
                        <thead class="bg-slate-50 text-slate-400 text-xs uppercase tracking-widest font-bold border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-4">Ticket No.</th>
                                <th class="px-5 py-4">Equipment / ห้อง</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50" id="techHistoryTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 shrink-0">
                <button onclick="toggleModal('techHistoryModal')" class="w-full bg-slate-900 hover:bg-blue-600 py-3 rounded-xl font-bold text-xs text-white transition-colors">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: LOGIN MODAL -->
    <div id="loginModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-slate-950/60 backdrop-blur-xs" onclick="toggleModal('loginModal')"></div>
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto z-50 overflow-hidden transform transition-all p-8 rounded-3xl shadow-2xl border border-slate-100">
            <div class="text-center pb-4 border-b border-slate-100 relative">
                <button onclick="toggleModal('loginModal')" class="absolute top-0 right-0 w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center">
                    <i class="fas fa-xmark text-xs"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl mx-auto mb-2 shadow-md shadow-blue-500/30">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h2 class="text-lg font-bold text-slate-900">เจ้าหน้าที่เข้าสู่ระบบ</h2>
            </div>
            <form action="" method="POST" class="mt-6 space-y-4">
                <input type="hidden" name="login" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">ชื่อผู้ใช้งาน (Username)</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-600 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:border-blue-600 focus:bg-white">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl font-bold text-xs text-white shadow-lg shadow-blue-600/30 transition-all mt-4">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        const allRepairs = <?php echo $all_repairs_json; ?>;

        function toggleModal(m) { 
            document.getElementById(m).classList.toggle('opacity-0'); 
            document.getElementById(m).classList.toggle('pointer-events-none'); 
            document.body.classList.toggle('modal-active'); 
        }

        // ฟังก์ชันกดสลับเปิดดูข้อมูลช่างทีละฝ่าย (เริ่มต้นถูกซ่อนไว้ทั้งหมด จะแสดงเฉพาะเมื่อกดปุ่ม)
        function switchDept(deptKey) {
            const targetPane = document.getElementById('dept-content-' + deptKey);
            const targetBtn = document.getElementById('btn-dept-' + deptKey);
            const isOpen = !targetPane.classList.contains('hidden');

            document.querySelectorAll('.dept-content-pane').forEach(pane => pane.classList.add('hidden'));
            document.querySelectorAll('.dept-tab-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
                btn.classList.add('bg-white', 'text-slate-700', 'border', 'border-slate-200', 'hover:bg-slate-50');
            });

            if (!isOpen) {
                targetPane.classList.remove('hidden');
                targetBtn.classList.remove('bg-white', 'text-slate-700', 'border', 'border-slate-200', 'hover:bg-slate-50');
                targetBtn.classList.add('bg-blue-600', 'text-white', 'shadow-md');
                
                targetPane.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function viewTechnicianHistory(techName) {
            const tbody = document.getElementById('techHistoryTableBody'); 
            tbody.innerHTML = '';
            
            const techJobs = allRepairs.filter(r => r.technician_name === techName);
            
            if(techJobs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="px-5 py-8 text-center text-slate-400 font-medium">ยังไม่มีประวัติการปฏิบัติงานที่ได้รับมอบหมาย</td></tr>`;
            } else {
                techJobs.forEach(r => {
                    let statusClass = 'bg-amber-50 text-amber-800 border-amber-200';
                    if(r.status === 'กำลังดำเนินการ') statusClass = 'bg-sky-50 text-sky-800 border-sky-200';
                    else if(r.status === 'ซ่อมเสร็จแล้ว') statusClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';

                    tbody.innerHTML += `<tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-5 py-4 font-mono font-semibold text-blue-600">${r.ticket_no}</td>
                        <td class="px-5 py-4 text-slate-800 font-medium whitespace-normal min-w-[150px]">${r.equipment_type}</td>
                        <td class="px-5 py-4 text-center"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold border ${statusClass}">${r.status}</span></td>
                        <td class="px-5 py-4 text-slate-500 font-medium">${r.created_at_fmt}</td>
                    </tr>`;
                });
            }
            document.getElementById('techModalName').innerText = techName;
            toggleModal('techHistoryModal');
        }
    </script>

    <?php if(!empty($error_msg)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'error', title: 'แจ้งเตือนระบบ', text: '<?php echo $error_msg; ?>', confirmButtonColor: '#2563eb' });
        });
    </script>
    <?php endif; ?>

    <?php if($status_result === 'not_found'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({ icon: 'warning', title: 'ไม่พบข้อมูล', text: 'ไม่พบประวัติการแจ้งซ่อมจาก "<?php echo htmlspecialchars($search_keyword, ENT_QUOTES); ?>"', confirmButtonColor: '#2563eb' });
        });
    </script>
    <?php elseif(is_array($status_result)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() { toggleModal('resultModal'); });
    </script>
    <?php endif; ?>

</body>
</html>