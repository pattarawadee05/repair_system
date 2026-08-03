<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // ดึงข้อมูลผู้ใช้โดยไม่สนตัวพิมพ์เล็ก-ใหญ่
    $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // เช็ครหัสผ่าน (รองรับทั้งแบบ Hash และ Plain Text)
        $password_matched = false;
        if (password_verify($password, $user['password'])) {
            $password_matched = true;
        } else if ($password === $user['password']) { // กรณีเก็บรหัสผ่านตรงๆ
            $password_matched = true;
        }

        if ($password_matched) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // รีไดเรกต์ตามบทบาท (Role)
            $user_role = strtolower($user['role']);
            if ($user_role === 'executive') {
                header("Location: executive_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            echo "รหัสผ่านไม่ตรง! (ใส่มา: '$password' | ใน DB: '{$user['password']}')<br>";
        }
    } else {
        echo "ไม่พบ Username นี้ในระบบ! (Username ที่รับมา: '$username')<br>";
    }

    echo "<a href='login.php'>กลับไปหน้า Login</a>";
}
?>