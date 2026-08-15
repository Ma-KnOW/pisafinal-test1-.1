<?php
// ==========================================
// 1. เริ่ม Session และตรวจสิทธิ์การเข้าใช้งาน
// ==========================================
session_start();

// ถ้ายังไม่ได้ล็อกอิน ให้ดีดกลับไปหน้า login ทันที
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ==========================================
// 2. เชื่อมต่อฐานข้อมูล (ใช้ PDO เพื่อความปลอดภัย)
// ==========================================
$host     = "localhost";
$db_name  = "your_database_name"; // ใส่ชื่อฐานข้อมูลของคุณ
$username = "root";               // ใส่ username ฐานข้อมูล
$password = "";                   // ใส่ password ฐานข้อมูล

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    // ตั้งค่าให้แสดง Exception เมื่อเกิด Error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$message = ""; // ตัวแปรสำหรับเก็บข้อความแจ้งเตือน

// ==========================================
// 3. ตรวจสอบเมื่อมีการกด Submit (Method POST)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ตัดช่องว่างหน้า-หลังของข้อความ
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    // ตรวจสอบว่าไม่ได้กรอกค่าว่าง
    if (empty($fullname) || empty($email)) {
        $message = "<div class='alert error'>กรุณากรอกข้อมูลให้ครบทุกช่อง</div>";
    } 
    // ตรวจสอบรูปแบบอีเมลว่าถูกต้องหรือไม่
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='alert error'>รูปแบบอีเมลไม่ถูกต้อง</div>";
    } 
    else {
        try {
            // เช็คว่าอีเมลใหม่ซ้ำกับผู้อื่นหรือไม่ (ยกเว้นของตัวเอง)
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $check_email->execute([
                ':email' => $email,
                ':id'    => $user_id
            ]);

            if ($check_email->rowCount() > 0) {
                $message = "<div class='alert error'>อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว</div>";
            } else {
                // คำสั่ง SQL Update ข้อมูล
                $sql = "UPDATE users SET fullname = :fullname, email = :email WHERE id = :id";
                $stmt = $conn->prepare($sql);
                
                // Binding ค่าเพื่อป้องกัน SQL Injection
                $stmt->execute([
                    ':fullname' => $fullname,
                    ':email'    => $email,
                    ':id'       => $user_id
                ]);

                // อัปเดตชื่อใน Session ด้วย (ถ้ามีการเก็บไว้)
                $_SESSION['fullname'] = $fullname;

                $message = "<div class='alert success'>อัปเดตข้อมูลเรียบร้อยแล้ว!</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert error'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
        }
    }
}

// ==========================================
// 4. ดึงข้อมูลล่าสุดของผู้ใช้มาแสดงใน Form
// ==========================================
$stmt = $conn->prepare("SELECT fullname, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// กรณีไม่พบ User ในฐานข้อมูล
if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลส่วนตัว</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            background: #ffffff;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .card h2 {
            margin-bottom: 20px;
            color: #333333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #555555;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cccccc;
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            border-color: #3b82f6;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #3b82f6;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background-color: #2563eb;
        }
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .alert.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .links-group {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .links-group a {
            color: #6b7280;
            text-decoration: none;
        }
        .links-group a:hover {
            color: #111827;
            text-decoration: underline;
        }
        .logout-link {
            color: #ef4444 !important;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>แก้ไขข้อมูลส่วนตัว</h2>

    <!-- แสดงข้อความแจ้งเตือน (ถ้ามี) -->
    <?= $message; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="fullname">ชื่อ-นามสกุล:</label>
            <!-- นำค่าเดิมมาใส่ใน value และใช้ htmlspecialchars ป้องกัน XSS -->
            <input type="text" id="fullname" name="fullname" 
                   value="<?= htmlspecialchars($user['fullname'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">อีเมล:</label>
            <input type="email" id="email" name="email" 
                   value="<?= htmlspecialchars($user['email'] ?? ''); ?>" required>
        </div>

        <button type="submit" class="btn-submit">บันทึกการเปลี่ยนแปลง</button>
    </form>

    <div class="links-group">
        <a href="index.html">⬅ กลับหน้าหลัก</a>
        <a href="logout.php" class="logout-link">ออกจากระบบ</a>
    </div>
</div>

</body>
</html>