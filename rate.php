<?php
// rate.php - ให้คะแนนดาวช่างซ่อม
require_once 'db_connect.php';
$ticket = $_GET['ticket'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);
    $ticket_no = $_POST['ticket_no'];

    $stmt = $conn->prepare("UPDATE repairs SET rating = ?, review_comment = ? WHERE ticket_no = ?");
    $stmt->bind_param("iss", $rating, $comment, $ticket_no);
    $stmt->execute();
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประเมินการบริการ - MBS Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Kanit', sans-serif; background: #f8fafc; }</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white w-full max-w-md rounded-2xl p-6 shadow-xl border border-slate-100 text-center">
        <?php if (!empty($success)): ?>
            <h2 class="text-xl font-bold text-emerald-600">ขอบคุณสำหรับคะแนนประเมินครับ! ⭐</h2>
            <p class="text-xs text-slate-500 mt-2">คำติชมของคุณจะนำไปพัฒนาการทำงานของทีมช่างให้ดียิ่งขึ้น</p>
        <?php else: ?>
            <h2 class="text-lg font-bold text-slate-800">ประเมินความพึงพอใจการซ่อม</h2>
            <p class="text-xs text-slate-500 mb-4">ใบงานเลขที่: <?= htmlspecialchars($ticket) ?></p>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="ticket_no" value="<?= htmlspecialchars($ticket) ?>">
                
                <div class="flex justify-center gap-2 text-2xl text-amber-400">
                    <select name="rating" class="bg-slate-50 border border-slate-200 rounded-xl p-2 text-sm font-bold">
                        <option value="5">⭐⭐⭐⭐⭐ (5 ดาว - ประทับใจมาก)</option>
                        <option value="4">⭐⭐⭐⭐ (4 ดาว - ดี)</option>
                        <option value="3">⭐⭐⭐ (3 ดาว - ปานกลาง)</option>
                        <option value="2">⭐⭐ (2 ดาว - พอใช้)</option>
                        <option value="1">⭐ (1 ดาว - ควรปรับปรุง)</option>
                    </select>
                </div>

                <textarea name="comment" rows="3" placeholder="ข้อเสนอแนะเพิ่มเติม..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs"></textarea>

                <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-xl text-xs shadow-md">ส่งผลประเมิน</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>