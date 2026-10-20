<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$validPages = ['applicants', 'preapply', 'examresults', 'announce', 'reports'];
$activeMenu = $_GET['page'] ?? 'applicants';
if (!in_array($activeMenu, $validPages, true)) {
    $activeMenu = 'applicants';
}

if ($activeMenu === 'reports' && (!empty($_GET['download']) || !empty($_GET['download_job']))) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../lib/mpdf/autoload.php';

    function sanitizeZipPathSegment($value)
    {
        $value = str_replace(['/', '\\', '..'], '_', (string) $value);
        return trim($value, '. ');
    }

    function createReportMpdf(array $overrides = [])
    {
        // ต้องเปิด useOTL (OpenType Layout shaping) ไม่งั้นสระ/วรรณยุกต์ไทยที่ซ้อนกัน 2 ตัว
        // (เช่น นั่ง, ที่มา) จะวางตำแหน่งผิดหรือหายไป — ฟอนต์ไทยในตัวของ mPDF ทุกตัวเปิดค่านี้ไว้
        // แต่ต้องกำหนดเองเมื่อประกาศฟอนต์ custom แบบนี้
        // ต้องปิด useDictionaryLBR ด้วย ไม่งั้น mPDF จะแทรกอักขระ U+200B (ตัดคำไทยด้วยดิกชันนารี)
        // ระหว่างคำ ซึ่งฟอนต์ Sarabun ที่ตัดมาไม่มีกลิฟสำหรับอักขระนี้ เลยโชว์เป็นกล่องสี่เหลี่ยม
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        return new \Mpdf\Mpdf($overrides + [
            'tempDir' => __DIR__ . '/../lib/mpdf/tmp',
            'fontDir' => array_merge($fontDirs, [__DIR__ . '/../lib/mpdf-fonts']),
            'fontdata' => $fontData + [
                'sarabun' => [
                    'R' => 'Sarabun-Regular.ttf',
                    'B' => 'Sarabun-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'sarabun',
            'useDictionaryLBR' => false,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);
    }

    function buildApplicantSummaryHtml($appId, array $summaryRows)
    {
        $html = '<div style="text-align:center; font-weight:bold; font-size:18pt;">ข้อมูลผู้สมัคร</div>'
            . '<div style="text-align:center; font-size:12pt; margin-bottom:12px;">เลขที่ใบสมัคร ' . htmlspecialchars((string) $appId) . '</div>'
            . '<table style="width:100%; border-collapse:collapse; font-size:12pt;">';
        foreach ($summaryRows as $summaryRow) {
            [$label, $value] = $summaryRow;
            $value = ($value === null || $value === '') ? '-' : (string) $value;

            $html .= '<tr style="border-bottom:1px solid #dcdcdc;">'
                . '<td style="width:35%; font-weight:bold; padding:5px 0; vertical-align:top;">' . htmlspecialchars($label) . '</td>'
                . '<td style="padding:5px 0; vertical-align:top;">' . nl2br(htmlspecialchars($value)) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    function buildApplicantSummaryPdf($appId, array $summaryRows)
    {
        $mpdf = createReportMpdf();
        $mpdf->WriteHTML(buildApplicantSummaryHtml($appId, $summaryRows));

        return $mpdf->Output('', 'S');
    }

    function formatThaiDate($dateValue, $withTime = false)
    {
        $timestamp = $dateValue ? strtotime((string) $dateValue) : false;
        if ($timestamp === false) {
            return '-';
        }
        $thaiMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $text = (int) date('j', $timestamp) . ' ' . $thaiMonths[(int) date('n', $timestamp)] . ' ' . ((int) date('Y', $timestamp) + 543);

        return $withTime ? $text . ' เวลา ' . date('H:i', $timestamp) . ' น.' : $text;
    }

    function toThaiDigits($text)
    {
        return strtr((string) $text, ['0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
            '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙']);
    }

    function buildJobApplicantsReportPdf(array $job, array $apps, $issuerName, $issuerTitle)
    {
        $mpdf = createReportMpdf();

        $logoPath = __DIR__ . '/../assets/images/logo.png';
        $html = '<div style="text-align:center;">'
            . (is_file($logoPath) ? '<img src="' . htmlspecialchars($logoPath) . '" style="height:28mm;">' : '')
            . '<div style="font-weight:bold; font-size:18pt; margin-top:6px;">โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</div>'
            . '<div style="font-weight:bold; font-size:14pt;">รายงานสรุปรายชื่อผู้สมัคร</div>'
            . '</div>'
            . '<table style="font-size:13pt; margin-top:10px;">'
            . '<tr><td style="font-weight:bold; width:32mm;">ชื่อตำแหน่ง</td><td>' . htmlspecialchars($job['title']) . '</td></tr>'
            . '<tr><td style="font-weight:bold;">รหัสตำแหน่ง</td><td>' . htmlspecialchars($job['job_id']) . '</td></tr>'
            . '<tr><td style="font-weight:bold;">วันเปิด-ปิดรับสมัคร</td><td>' . formatThaiDate($job['opening_date']) . ' ถึง ' . formatThaiDate($job['closing_date']) . '</td></tr>'
            . '</table>'
            . '<div style="font-size:13pt; margin:8px 0;">มีผู้สมัครจำนวน ' . count($apps) . ' คน ตามรายชื่อต่อไปนี้</div>'
            . '<table style="width:100%; border-collapse:collapse; font-size:12pt;" autosize="1">'
            . '<thead><tr style="background-color:#e6e6e6;">'
            . '<th style="border:1px solid #666; padding:5px; width:12mm;">ที่</th>'
            . '<th style="border:1px solid #666; padding:5px; width:28mm;">เลขที่ใบสมัคร</th>'
            . '<th style="border:1px solid #666; padding:5px;">ชื่อ - สกุล</th>'
            . '<th style="border:1px solid #666; padding:5px; width:38mm;">วันที่สมัคร</th>'
            . '<th style="border:1px solid #666; padding:5px; width:30mm;">หมายเหตุ</th>'
            . '</tr></thead><tbody>';
        foreach ($apps as $index => $app) {
            $html .= '<tr>'
                . '<td style="border:1px solid #666; padding:5px; text-align:center;">' . ($index + 1) . '</td>'
                . '<td style="border:1px solid #666; padding:5px; text-align:center;">' . htmlspecialchars($app['app_id']) . '</td>'
                . '<td style="border:1px solid #666; padding:5px;">' . htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) . '</td>'
                . '<td style="border:1px solid #666; padding:5px; text-align:center;">' . formatThaiDate($app['created_at']) . '</td>'
                . '<td style="border:1px solid #666; padding:5px;"></td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';

        // ส่วนท้ายรายงานแบบท้ายประกาศ: วันที่ / ช่องลายเซ็น / (ชื่อ) / ตำแหน่ง จัดกึ่งกลางครึ่งขวาของหน้า
        $html .= '<table style="width:100%; margin-top:14mm; font-size:14pt; page-break-inside:avoid;"><tr>'
            . '<td style="width:45%;"></td>'
            . '<td style="text-align:center;">'
            . '<div>ประกาศ ณ วันที่ ' . toThaiDigits(preg_replace('/(\d{4})$/', 'พ.ศ. $1', formatThaiDate(date('Y-m-d')))) . '</div>'
            . '<div>&nbsp;</div><div>&nbsp;</div><div>&nbsp;</div>'
            . '<div>(' . htmlspecialchars($issuerName) . ')</div>'
            . '<div>' . htmlspecialchars($issuerTitle) . '</div>'
            . '</td></tr></table>';

        $mpdf->WriteHTML($html);

        foreach ($apps as $app) {
            $mpdf->AddPage();
            $mpdf->WriteHTML(buildApplicantSummaryHtml($app['app_id'], buildApplicantSummaryRows($app)));
        }

        return $mpdf->Output('', 'S');
    }

    function buildApplicantSummaryRows($app)
    {
        return [
            ['ชื่อ', $app['first_name']],
            ['สกุล', $app['last_name']],
            ['เลขบัตรประชาชน', $app['national_id']],
            ['วันเดือนปีเกิด', $app['birth_date']],
            ['อายุ', $app['age']],
            ['เพศ', $app['gender']],
            ['เชื้อชาติ', $app['ethnicity']],
            ['สัญชาติ', $app['nationality']],
            ['ศาสนา', $app['religion']],
            ['เบอร์โทร', $app['phone']],
            ['อีเมล', $app['email']],
            ['ตำแหน่งที่สมัคร', $app['title']],
            ['สถานะภาพ', $app['marital_status']],
            ['สถานะทางทหาร', $app['military_status']],
            ['ผลการสมัครงาน', $app['application_status'] ?: 'รอการยืนยัน'],
            ['ที่มาของใบสมัคร', $app['source'] === 'preapply' ? 'แมตช์จากสมัครล่วงหน้า' : 'สมัครตรง'],
            ['วันที่สมัคร', $app['created_at']],
        ];
    }

    function addApplicantToReportZip($zip, $folderPrefix, $app)
    {
        $folderName = $folderPrefix . $app['app_id'] . '_' . sanitizeZipPathSegment($app['first_name']) . '_' . sanitizeZipPathSegment($app['last_name']);

        $pdfContent = buildApplicantSummaryPdf($app['app_id'], buildApplicantSummaryRows($app));

        $zip->addFromString($folderName . '/ข้อมูลผู้สมัคร.pdf', $pdfContent);

        $uploadDir = __DIR__ . '/../assets/uploads/applications/';
        $docFields = [
            'id_card_copy_file' => 'สำเนาบัตรประชาชน',
            'resume_file' => 'เรซูเม่',
            'edu_degree_file' => 'ข้อมูลการศึกษา_ปริญญา',
            'medical_cert_file' => 'ใบรับรองแพทย์',
            'profile_photo_file' => 'รูปถ่ายหน้าผู้สมัคร',
            'payment_proof_file' => 'สลิปค่าสมัคร',
        ];
        foreach ($docFields as $field => $label) {
            if (!empty($app[$field]) && file_exists($uploadDir . $app[$field])) {
                $ext = pathinfo($app[$field], PATHINFO_EXTENSION);
                $zip->addFile($uploadDir . $app[$field], $folderName . '/' . $label . ($ext !== '' ? '.' . $ext : ''));
            }
        }
    }

    if (!empty($_GET['download'])) {
        $downloadAppId = $_GET['download'];
        $downloadStmt = $pdo->prepare('SELECT ja.*, a.first_name, a.last_name, a.birth_date, a.national_id, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone, j.title
            FROM job_applications ja
            JOIN applicants a ON a.national_id = ja.national_id
            JOIN jobs j ON j.job_id = ja.job_id
            WHERE ja.app_id = ?');
        $downloadStmt->execute([$downloadAppId]);
        $downloadApp = $downloadStmt->fetch();

        if ($downloadApp) {
            $tmpZipPath = tempnam(sys_get_temp_dir(), 'report_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            addApplicantToReportZip($zip, '', $downloadApp);
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="report_' . $downloadAppId . '.zip"');
            header('Content-Length: ' . filesize($tmpZipPath));
            readfile($tmpZipPath);
            unlink($tmpZipPath);
            exit;
        }
    } else {
        $downloadJobId = $_GET['download_job'];
        $downloadJobStmt = $pdo->prepare('SELECT job_id, title, opening_date, closing_date FROM jobs WHERE job_id = ?');
        $downloadJobStmt->execute([$downloadJobId]);
        $downloadJob = $downloadJobStmt->fetch();

        if ($downloadJob) {
            $downloadAppsStmt = $pdo->prepare('SELECT ja.*, a.first_name, a.last_name, a.birth_date, a.national_id, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone, j.title
                FROM job_applications ja
                JOIN applicants a ON a.national_id = ja.national_id
                JOIN jobs j ON j.job_id = ja.job_id
                WHERE ja.job_id = ?
                ORDER BY ja.created_at, ja.app_id');
            $downloadAppsStmt->execute([$downloadJobId]);
            $downloadApps = $downloadAppsStmt->fetchAll();

            if (!$downloadApps) {
                header('Location: ?page=reports');
                exit;
            }

            $tmpZipPath = tempnam(sys_get_temp_dir(), 'report_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($downloadApps as $app) {
                addApplicantToReportZip($zip, '', $app);
            }
            $issuerStmt = $pdo->prepare('SELECT Name, username, role FROM admins WHERE admin_id = ?');
            $issuerStmt->execute([$_SESSION['admin_id']]);
            $issuer = $issuerStmt->fetch();
            $issuerName = $issuer ? (trim((string) $issuer['Name']) !== '' ? $issuer['Name'] : $issuer['username']) : '-';
            $issuerTitle = ['Admin' => 'ผู้ดูแลระบบ', 'Staff' => 'เจ้าหน้าที่', 'Recruiter' => 'เจ้าหน้าที่สรรหาบุคลากร', 'Manager' => 'ผู้จัดการ'][$issuer['role'] ?? ''] ?? 'ผู้ออกรายงาน';

            $zip->addFromString(
                'ไฟล์ข้อมูลผู้สมัคร/ข้อมูลผู้สมัครทั้งหมด_' . sanitizeZipPathSegment($downloadJob['title']) . '.pdf',
                buildJobApplicantsReportPdf($downloadJob, $downloadApps, $issuerName, $issuerTitle)
            );
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="report_' . $downloadJobId . '.zip"');
            header('Content-Length: ' . filesize($tmpZipPath));
            readfile($tmpZipPath);
            unlink($tmpZipPath);
            exit;
        }
    }

    header('Location: ?page=reports');
    exit;
}

$announceError = '';
$announceSuccess = isset($_GET['saved']);
$nextJobId = '00000';

if ($activeMenu === 'announce') {
    require_once __DIR__ . '/../config/db.php';

    $editJobId = trim($_GET['edit'] ?? '');
    $editingJob = null;
    if ($editJobId !== '') {
        $editStmt = $pdo->prepare('SELECT * FROM jobs WHERE job_id = ?');
        $editStmt->execute([$editJobId]);
        $editingJob = $editStmt->fetch();
        if (!$editingJob) {
            header('Location: ?page=applicants');
            exit;
        }

        $editCountStmt = $pdo->prepare('SELECT COUNT(*) FROM job_applications WHERE job_id = ?');
        $editCountStmt->execute([$editingJob['job_id']]);
        $editApplicantCount = (int) $editCountStmt->fetchColumn();
        $jobIsEditable = !empty($editingJob['opening_date']) && $editingJob['opening_date'] > date('Y-m-d') && $editApplicantCount === 0;
        if (!$jobIsEditable) {
            header('Location: ?page=applicants');
            exit;
        }
    }

    $nextJobId = $editingJob ? $editingJob['job_id'] : nextSequentialId($pdo, 'jobs', 'job_id');
    $minOpeningDate = date('Y-m-d');
    $maxOpeningDate = date('Y-m-d', strtotime('+7 days'));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrfCheck();
        $positionLevel = trim($_POST['position_level'] ?? '');
        $title = $positionLevel;
        $openings = trim($_POST['openings'] ?? '');
        $jobField = trim($_POST['job_field'] ?? '');
        $staffType = trim($_POST['staff_type'] ?? '');
        $salary = trim($_POST['salary'] ?? '');
        $educationLevel = trim($_POST['education_level'] ?? '');
        $educationLevelOther = trim($_POST['education_level_other'] ?? '');
        if ($educationLevel === 'อื่นๆ' && $educationLevelOther !== '') {
            $educationLevel = $educationLevelOther;
        }
        $about = trim($_POST['about'] ?? '');
        $openingDate = trim($_POST['opening_date'] ?? '');
        $closingDate = trim($_POST['closing_date'] ?? '');

        if ($title === '') {
            $announceError = 'กรุณาเลือกตำแหน่ง';
        } elseif ($openingDate !== '' && ($openingDate < $minOpeningDate || $openingDate > $maxOpeningDate)) {
            $announceError = 'วันที่เปิดรับต้องเป็นวันปัจจุบัน หรือไม่เกิน 7 วันล่วงหน้า';
        } elseif ($closingDate !== '' && $openingDate !== '' && ($closingDate < $openingDate || $closingDate > date('Y-m-d', strtotime($openingDate . ' +30 days')))) {
            $announceError = 'วันที่ปิดรับต้องอยู่ภายใน 30 วันนับจากวันที่เปิดรับ';
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/job_postings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $postingFile = $editingJob['posting_file'] ?? null;
            if (!empty($_FILES['job_posting']['name']) && $_FILES['job_posting']['error'] === UPLOAD_ERR_OK) {
                if (($e = uploadValidationError($_FILES['job_posting'])) !== null) {
                    http_response_code(400);
                    die($e);
                }
                $oldPostingFile = $postingFile;
                $ext = pathinfo($_FILES['job_posting']['name'], PATHINFO_EXTENSION);
                $postingFile = $nextJobId . '_posting_' . time() . ($ext !== '' ? '.' . $ext : '');
                storeUploadedFile($_FILES['job_posting'], $uploadDir . $postingFile);
                if (!empty($oldPostingFile) && $oldPostingFile !== $postingFile && file_exists($uploadDir . $oldPostingFile)) {
                    unlink($uploadDir . $oldPostingFile);
                }
            }

            $paymentSlipFile = $editingJob['payment_slip_file'] ?? null;
            if (!empty($_FILES['payment_slip']['name']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                if (($e = uploadValidationError($_FILES['payment_slip'])) !== null) {
                    http_response_code(400);
                    die($e);
                }
                $oldPaymentSlipFile = $paymentSlipFile;
                $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                $paymentSlipFile = $nextJobId . '_slip_' . time() . ($ext !== '' ? '.' . $ext : '');
                storeUploadedFile($_FILES['payment_slip'], $uploadDir . $paymentSlipFile);
                if (!empty($oldPaymentSlipFile) && $oldPaymentSlipFile !== $paymentSlipFile && file_exists($uploadDir . $oldPaymentSlipFile)) {
                    unlink($uploadDir . $oldPaymentSlipFile);
                }
            }

            $values = [
                $title,
                $postingFile,
                $openings !== '' ? (int) $openings : null,
                $jobField,
                $staffType,
                $salary,
                $positionLevel,
                $educationLevel,
                $about,
                $openingDate !== '' ? $openingDate : null,
                $closingDate !== '' ? $closingDate : null,
                $paymentSlipFile,
            ];

            if ($editingJob) {
                $update = $pdo->prepare('UPDATE jobs SET title = ?, posting_file = ?, openings = ?, job_field = ?, staff_type = ?, salary = ?, position_level = ?, education_level = ?, about = ?, opening_date = ?, closing_date = ?, payment_slip_file = ? WHERE job_id = ?');
                $update->execute(array_merge($values, [$nextJobId]));
            } else {
                $insert = $pdo->prepare('INSERT INTO jobs (job_id, title, posting_file, openings, job_field, staff_type, salary, position_level, education_level, about, opening_date, closing_date, payment_slip_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute(array_merge([$nextJobId], $values));
            }

            syncPreapplicationMatches($pdo);

            header('Location: ?page=announce&saved=1');
            exit;
        }
    }
}

$cssVersion = filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบผู้ดูแลระบบ - โรงเรียนสาธิตมหาวิทยาลัยทักษิณ ฝ่ายมัธยม</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?php echo $cssVersion; ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <img src="../assets/images/logo.png" alt="โลโก้โรงเรียนสาธิตมหาวิทยาลัยทักษิณ" class="site-logo">
        <h1 class="site-title">
            โรงเรียนสาธิตมหาวิทยาลัยทักษิณ<br>
            ฝ่ายมัธยม
        </h1>
    </div>
    <div class="admin-account">
        <i class="fa-solid fa-circle-user admin-avatar"></i>
        <span class="admin-account-name"><?php echo htmlspecialchars($_SESSION['admin_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="logout.php" class="admin-logout-btn">ออกจากระบบ</a>
    </div>
</header>

<nav class="admin-nav">
    <a href="?page=applicants" class="admin-nav-item <?php echo $activeMenu === 'applicants' ? 'active' : ''; ?>">ตรวจสอบข้อมูลผู้สมัคร<br>ตรวจสอบข้อมูลการชำระเงิน</a>
    <a href="?page=preapply" class="admin-nav-item <?php echo $activeMenu === 'preapply' ? 'active' : ''; ?>">ตรวจสอบข้อมูลผู้สมัคร<br>ที่ลงสมัครล่วงหน้า</a>
    <a href="?page=examresults" class="admin-nav-item <?php echo $activeMenu === 'examresults' ? 'active' : ''; ?>">ประกาศตารางสอบ<br>และผลสอบ</a>
    <a href="?page=announce" class="admin-nav-item <?php echo $activeMenu === 'announce' ? 'active' : ''; ?>">ประกาศเปิดรับสมัคร</a>
    <a href="?page=reports" class="admin-nav-item <?php echo $activeMenu === 'reports' ? 'active' : ''; ?>">ออกรายงาน</a>
</nav>

<main class="page-content">
    <?php if ($activeMenu === 'applicants'): ?>
    <?php
    require_once __DIR__ . '/../config/db.php';
    syncPreapplicationMatches($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['toggle_job_id'])) {
        csrfCheck();
        $toggleJobId = $_POST['toggle_job_id'];
        $toggleStmt = $pdo->prepare('SELECT opening_date, closing_date FROM jobs WHERE job_id = ?');
        $toggleStmt->execute([$toggleJobId]);
        $toggleJob = $toggleStmt->fetch();
        if ($toggleJob) {
            $todayDate = date('Y-m-d');
            $isClosed = !empty($toggleJob['closing_date']) && $toggleJob['closing_date'] < $todayDate;
            if ($isClosed) {
                // เปิดรับสมัครใหม่: เปิดตั้งแต่วันนี้ ปิดอีก 30 วัน
                $newOpeningDate = (!empty($toggleJob['opening_date']) && $toggleJob['opening_date'] < $todayDate) ? $toggleJob['opening_date'] : $todayDate;
                $newClosingDate = date('Y-m-d', strtotime($todayDate . ' +30 days'));
                $pdo->prepare('UPDATE jobs SET opening_date = ?, closing_date = ? WHERE job_id = ?')->execute([$newOpeningDate, $newClosingDate, $toggleJobId]);
            } else {
                // ปิดรับสมัครทันที: ตั้งวันปิด (และวันเปิด ถ้ายังไม่ถึงกำหนด) เป็นเมื่อวาน
                // ให้ปิดผลทันทีทั้งฝั่งแอดมินและหน้าเว็บ ไม่ค้างอยู่ในหมวด "รอเปิดรับ"
                $yesterday = date('Y-m-d', strtotime($todayDate . ' -1 day'));
                $newOpeningDate = (!empty($toggleJob['opening_date']) && $toggleJob['opening_date'] < $yesterday) ? $toggleJob['opening_date'] : $yesterday;
                $pdo->prepare('UPDATE jobs SET opening_date = ?, closing_date = ? WHERE job_id = ?')->execute([$newOpeningDate, $yesterday, $toggleJobId]);
            }
        }
        header('Location: ?page=applicants&q=' . urlencode($_GET['q'] ?? ''));
        exit;
    }
    ?>
    <?php if (!empty($_GET['app_id'])):
        $detailAppId = $_GET['app_id'];
        $detailStmt = $pdo->prepare('SELECT ja.*, a.first_name, a.last_name, a.birth_date, a.national_id, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone, j.title
            FROM job_applications ja
            JOIN applicants a ON a.national_id = ja.national_id
            JOIN jobs j ON j.job_id = ja.job_id
            WHERE ja.app_id = ?');
        $detailStmt->execute([$detailAppId]);
        $detail = $detailStmt->fetch();
        if (!$detail) {
            header('Location: ?page=applicants');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_status'])) {
            csrfCheck();
            $allowedStatuses = ['ผ่าน', 'ไม่ผ่าน', 'เอกสารไม่ครบถ้วน'];
            $newStatus = in_array($_POST['application_status'], $allowedStatuses, true) ? $_POST['application_status'] : 'ไม่ผ่าน';
            $rejectReason = $newStatus === 'เอกสารไม่ครบถ้วน' ? trim($_POST['reject_reason'] ?? '') : null;
            $updateStmt = $pdo->prepare('UPDATE job_applications SET application_status = ?, reject_reason = ? WHERE app_id = ?');
            $updateStmt->execute([$newStatus, $rejectReason, $detailAppId]);
            header('Location: ?page=applicants&job_id=' . urlencode($detail['job_id']) . '&q=' . urlencode($_GET['q'] ?? ''));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['upload_payment_qr']) && $detail['source'] === 'preapply') {
            csrfCheck();
            if (!empty($_FILES['payment_qr']['name']) && $_FILES['payment_qr']['error'] === UPLOAD_ERR_OK) {
                $qrUploadError = uploadValidationError($_FILES['payment_qr']);
                if ($qrUploadError !== null) {
                    http_response_code(400);
                    die($qrUploadError);
                }
                $uploadDir = __DIR__ . '/../assets/uploads/applications/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($_FILES['payment_qr']['name'], PATHINFO_EXTENSION);
                $qrFilename = $detailAppId . '_payment_qr_' . time() . ($ext !== '' ? '.' . $ext : '');
                storeUploadedFile($_FILES['payment_qr'], $uploadDir . $qrFilename);
                $pdo->prepare('UPDATE job_applications SET payment_qr_file = ? WHERE app_id = ?')->execute([$qrFilename, $detailAppId]);
            }
            header('Location: ?page=applicants&app_id=' . urlencode($detailAppId) . '&job_id=' . urlencode($detail['job_id']) . '&q=' . urlencode($_GET['q'] ?? '') . '&panel=reject');
            exit;
        }

        function reviewFileField($label, $filename)
        {
            $url = !empty($filename) ? '../file.php?f=' . rawurlencode($filename) : '';
            ob_start();
            ?>
            <div class="field-group">
                <label class="field-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <?php if ($url !== ''): ?>
                <a href="<?php echo $url; ?>" target="_blank" class="announce-input applicant-review-file"><span>ดูไฟล์</span><i class="fa-solid fa-eye"></i></a>
                <?php else: ?>
                <div class="announce-input applicant-review-value">-</div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        function reviewTextField($label, $value)
        {
            ob_start();
            ?>
            <div class="field-group">
                <label class="field-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <div class="announce-input applicant-review-value"><?php echo htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php
            return ob_get_clean();
        }

        $showRejectPanel = ($_GET['panel'] ?? '') === 'reject';
        ?>
    <div class="applicant-review-panel" <?php echo $showRejectPanel ? 'hidden' : ''; ?>>
        <h2 class="applicant-review-title">ตรวจสอบข้อมูลผู้สมัครและตรวจสอบข้อมูล<br>การชำระเงิน</h2>

        <div class="applicant-review-body">
            <div class="applicant-review-photo-col">
                <?php if (!empty($detail['profile_photo_file'])): ?>
                <img src="../file.php?f=<?php echo rawurlencode($detail['profile_photo_file']); ?>" alt="รูปผู้สมัคร" class="applicant-review-photo">
                <?php else: ?>
                <div class="applicant-review-photo applicant-review-photo-placeholder"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <span class="applicant-review-name"><?php echo htmlspecialchars($detail['first_name'] . ' ' . $detail['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <div class="applicant-review-grid">
                <?php echo reviewTextField('ชื่อ สกุล', $detail['first_name'] . ' ' . $detail['last_name']); ?>
                <?php echo reviewTextField('วัน เดือน ปีเกิด', $detail['birth_date']); ?>
                <?php echo reviewTextField('เลขบัตรประชาชน', $detail['national_id']); ?>

                <?php echo reviewTextField('อายุ', $detail['age']); ?>
                <?php echo reviewTextField('เพศ', $detail['gender']); ?>
                <?php echo reviewTextField('เชื้อชาติ', $detail['ethnicity']); ?>

                <?php echo reviewTextField('สัญชาติ', $detail['nationality']); ?>
                <?php echo reviewTextField('ศาสนา', $detail['religion']); ?>
                <?php echo reviewTextField('ที่อยู่', '-'); ?>

                <?php echo reviewTextField('เบอร์โทร', $detail['phone']); ?>
                <?php echo reviewFileField('สำเนาบัตรประชาชน', $detail['id_card_copy_file']); ?>
                <?php echo reviewFileField('ใบรับรองแพทย์', $detail['medical_cert_file']); ?>

                <?php echo reviewTextField('สถานะภาพ', $detail['marital_status']); ?>
                <?php echo reviewTextField('สถานะทางทหาร(ถ้ามี)', $detail['military_status']); ?>

                <?php echo reviewFileField('เรซูเม่', $detail['resume_file']); ?>
                <?php echo reviewFileField('ข้อมูลการศึกษา(ปริญญา)', $detail['edu_degree_file']); ?>

                <?php echo reviewFileField('สลิปค่าสมัคร', $detail['payment_proof_file']); ?>
            </div>
        </div>

        <div class="applicant-review-actions">
            <a href="?page=applicants&job_id=<?php echo urlencode($detail['job_id']); ?>&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="applicant-review-back-btn" aria-label="กลับ">
                <i class="fa-solid fa-reply"></i>
            </a>
            <button type="button" id="applicantFailBtn" class="applicant-review-fail-btn">ไม่ผ่าน</button>
            <button type="button" id="applicantIncompleteOpenBtn" class="applicant-review-incomplete-btn">เอกสารไม่ครบถ้วน</button>
            <button type="button" id="applicantPassBtn" class="applicant-review-pass-btn">ผ่าน</button>
        </div>
        <form method="post" id="applicantFailForm">
            <?php csrfField(); ?>
            <input type="hidden" name="application_status" value="ไม่ผ่าน">
        </form>
        <form method="post" id="applicantPassForm">
            <?php csrfField(); ?>
            <input type="hidden" name="application_status" value="ผ่าน">
        </form>
    </div>

    <div class="applicant-reject-panel" id="applicantRejectPanel" <?php echo $showRejectPanel ? '' : 'hidden'; ?>>
        <div class="applicant-reject-photo-col">
            <?php if (!empty($detail['profile_photo_file'])): ?>
            <img src="../file.php?f=<?php echo rawurlencode($detail['profile_photo_file']); ?>" alt="รูปผู้สมัคร" class="applicant-reject-photo">
            <?php else: ?>
            <div class="applicant-reject-photo applicant-review-photo-placeholder"><i class="fa-solid fa-user"></i></div>
            <?php endif; ?>
            <span class="applicant-reject-name"><?php echo htmlspecialchars($detail['first_name'] . ' ' . $detail['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <p class="applicant-reject-text">เอกสารไม่ครบถ้วนในการสมัครงานในตำแหน่ง<?php echo htmlspecialchars($detail['title'], ENT_QUOTES, 'UTF-8'); ?>เพราะ</p>

        <div class="applicant-reject-input-row">
            <form method="post" class="applicant-reject-form" id="applicantRejectForm">
                <?php csrfField(); ?>
                <input type="hidden" name="application_status" value="เอกสารไม่ครบถ้วน">
                <div class="field-group applicant-reject-reason-col">
                    <label class="field-label applicant-reject-label-spacer" aria-hidden="true">&nbsp;</label>
                    <input type="text" name="reject_reason" class="applicant-reject-input" value="<?php echo htmlspecialchars($detail['reject_reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </form>

            <?php if ($detail['source'] === 'preapply'): ?>
            <div class="field-group applicant-reject-qr-inline">
                <label class="field-label">QR Code ชำระเงิน (สำหรับผู้สมัครล่วงหน้า)</label>
                <?php if (!empty($detail['payment_qr_file'])): ?>
                <a href="../file.php?f=<?php echo rawurlencode($detail['payment_qr_file']); ?>" target="_blank" class="announce-input applicant-review-file"><span>ดูไฟล์</span><i class="fa-solid fa-eye"></i></a>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="applicant-qr-upload-form">
                    <?php csrfField(); ?>
                    <input type="hidden" name="upload_payment_qr" value="1">
                    <label class="announce-input announce-upload-field">
                        <span class="announce-upload-label"><?php echo !empty($detail['payment_qr_file']) ? 'เปลี่ยน QR Code' : 'แนบไฟล์ QR Code'; ?></span>
                        <input type="file" name="payment_qr" class="announce-upload-native" hidden>
                        <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                    </label>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <div class="applicant-reject-actions">
            <button type="button" id="applicantRejectCloseBtn" class="applicant-review-back-btn" aria-label="กลับ">
                <i class="fa-solid fa-reply"></i>
            </button>
            <button type="button" id="applicantRejectSaveBtn" class="applicant-reject-save-btn">บันทึก</button>
        </div>
    </div>

    <div class="incomplete-modal-overlay" id="applicantConfirmModal">
        <div class="incomplete-modal-box">
            <p class="incomplete-modal-message">ตรวจสอบข้อมูลให้ครบก่อนที่ยืนยัน</p>
            <div class="confirm-modal-actions">
                <button type="button" class="incomplete-modal-close-btn" id="applicantConfirmModalCancel">ย้อนกลับ</button>
                <button type="button" class="confirm-modal-confirm-btn" id="applicantConfirmModalConfirm">ยืนยัน</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var openBtn = document.getElementById('applicantIncompleteOpenBtn');
        var closeBtn = document.getElementById('applicantRejectCloseBtn');
        var rejectPanel = document.getElementById('applicantRejectPanel');
        var reviewPanel = document.querySelector('.applicant-review-panel');
        if (openBtn && rejectPanel && reviewPanel) {
            openBtn.addEventListener('click', function () {
                reviewPanel.hidden = true;
                rejectPanel.hidden = false;
            });
        }
        if (closeBtn && rejectPanel && reviewPanel) {
            closeBtn.addEventListener('click', function () {
                rejectPanel.hidden = true;
                reviewPanel.hidden = false;
            });
        }

        document.querySelectorAll('.applicant-qr-upload-form .announce-upload-native').forEach(function (input) {
            var label = input.closest('.announce-upload-field').querySelector('.announce-upload-label');
            var defaultText = label.textContent;
            input.addEventListener('change', function () {
                if (input.files.length) {
                    label.textContent = input.files[0].name;
                    input.closest('form').submit();
                } else {
                    label.textContent = defaultText;
                }
            });
        });

        var applicantPassForm = document.getElementById('applicantPassForm');
        var applicantPassBtn = document.getElementById('applicantPassBtn');
        var applicantFailForm = document.getElementById('applicantFailForm');
        var applicantFailBtn = document.getElementById('applicantFailBtn');
        var applicantRejectForm = document.getElementById('applicantRejectForm');
        var applicantRejectSaveBtn = document.getElementById('applicantRejectSaveBtn');
        var applicantConfirmModal = document.getElementById('applicantConfirmModal');
        var applicantConfirmModalCancel = document.getElementById('applicantConfirmModalCancel');
        var applicantConfirmModalConfirm = document.getElementById('applicantConfirmModalConfirm');
        var pendingForm = null;

        if (applicantPassBtn && applicantPassForm && applicantConfirmModal) {
            applicantPassBtn.addEventListener('click', function () {
                pendingForm = applicantPassForm;
                applicantConfirmModal.classList.add('open');
            });
        }

        if (applicantFailBtn && applicantFailForm && applicantConfirmModal) {
            applicantFailBtn.addEventListener('click', function () {
                pendingForm = applicantFailForm;
                applicantConfirmModal.classList.add('open');
            });
        }

        if (applicantRejectSaveBtn && applicantRejectForm && applicantConfirmModal) {
            applicantRejectSaveBtn.addEventListener('click', function () {
                pendingForm = applicantRejectForm;
                applicantConfirmModal.classList.add('open');
            });
        }

        if (applicantConfirmModalCancel) {
            applicantConfirmModalCancel.addEventListener('click', function () {
                applicantConfirmModal.classList.remove('open');
                pendingForm = null;
            });
        }

        if (applicantConfirmModalConfirm) {
            applicantConfirmModalConfirm.addEventListener('click', function () {
                applicantConfirmModal.classList.remove('open');
                if (pendingForm) {
                    pendingForm.submit();
                }
                pendingForm = null;
            });
        }
    });
    </script>
    <?php elseif (!empty($_GET['job_id'])): ?>
    <?php
    $listJobId = $_GET['job_id'];
    $listJobStmt = $pdo->prepare('SELECT title FROM jobs WHERE job_id = ?');
    $listJobStmt->execute([$listJobId]);
    $listJob = $listJobStmt->fetch();
    if (!$listJob) {
        header('Location: ?page=applicants');
        exit;
    }
    $listStmt = $pdo->prepare('SELECT a.first_name, a.last_name, ja.app_id, ja.source, ja.application_status
        FROM job_applications ja
        JOIN applicants a ON a.national_id = ja.national_id
        WHERE ja.job_id = ?
        ORDER BY ja.created_at');
    $listStmt->execute([$listJobId]);
    $jobApplicantList = $listStmt->fetchAll();
    ?>
    <div class="announce-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($listJob['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <?php foreach ($jobApplicantList as $applicant): ?>
        <div class="admin-applicant-row">
            <span class="admin-applicant-name<?php echo $applicant['source'] === 'preapply' ? ' admin-applicant-name-preapply' : ''; ?>">
                <?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($applicant['application_status'] === 'ผ่าน'): ?>
                <span class="admin-applicant-checked admin-applicant-checked-pass">(ตรวจสอบแล้ว - ผ่าน)</span>
                <?php elseif ($applicant['application_status'] === 'ไม่ผ่าน'): ?>
                <span class="admin-applicant-checked admin-applicant-checked-fail">(ตรวจสอบแล้ว - ไม่ผ่าน)</span>
                <?php elseif ($applicant['application_status'] === 'เอกสารไม่ครบถ้วน'): ?>
                <span class="admin-applicant-checked admin-applicant-checked-incomplete">(ตรวจสอบแล้ว - เอกสารไม่ครบถ้วน)</span>
                <?php else: ?>
                <span class="admin-applicant-checked admin-applicant-checked-pending">(ยังไม่ตรวจสอบ)</span>
                <?php endif; ?>
            </span>
            <a href="?page=applicants&job_id=<?php echo urlencode($listJobId); ?>&q=<?php echo urlencode($_GET['q'] ?? ''); ?>&app_id=<?php echo urlencode($applicant['app_id']); ?>" class="admin-applicant-check-btn">ตรวจสอบ</a>
        </div>
        <?php endforeach; ?>

        <p class="admin-applicant-legend">
            <span class="admin-applicant-legend-item">ช่องสี <span class="admin-applicant-legend-swatch admin-applicant-legend-swatch-preapply"></span> คือ ผู้สมัครงานล่วงหน้า</span>
            <span class="admin-applicant-legend-item">ช่องสี <span class="admin-applicant-legend-swatch"></span> คือ ผู้สมัครงานปกติ</span>
        </p>

        <a href="?page=applicants&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="announce-back" aria-label="กลับหน้าค้นหา">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>
    <?php else: ?>
    <form class="admin-search-bar" action="?page=applicants" method="get">
        <input type="hidden" name="page" value="applicants">
        <div class="field-group">
            <label class="field-label" for="adminApplicantsQ">เลขบัตรประจำตัวผู้สมัครหรือชื่อตำแหน่ง</label>
            <input type="text" id="adminApplicantsQ" name="q" class="admin-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit" class="admin-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <?php
    $adminApplicantSearch = trim($_GET['q'] ?? '');
    if ($adminApplicantSearch !== '') {
        $stmt = $pdo->prepare('SELECT DISTINCT j.job_id, j.title, j.opening_date, j.closing_date,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id AND source = \'preapply\') AS matched_count
            FROM jobs j
            LEFT JOIN job_applications ja ON ja.job_id = j.job_id
            WHERE j.title LIKE ? OR ja.national_id = ?
            ORDER BY j.created_at DESC');
        $stmt->execute(['%' . $adminApplicantSearch . '%', $adminApplicantSearch]);
    } else {
        $stmt = $pdo->query('SELECT j.job_id, j.title, j.opening_date, j.closing_date,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id AND source = \'preapply\') AS matched_count
            FROM jobs j
            ORDER BY j.created_at DESC');
    }
    $adminJobResults = $stmt->fetchAll();

    $today = date('Y-m-d');
    $adminJobCategories = [
        'pending' => ['label' => 'งานที่รอเปิดรับ', 'jobs' => []],
        'open' => ['label' => 'งานที่กำลังเปิดรับอยู่', 'jobs' => []],
        'closed' => ['label' => 'งานที่ปิดรับการสมัคร', 'jobs' => []],
    ];
    foreach ($adminJobResults as $jobResult) {
        if (!empty($jobResult['opening_date']) && $jobResult['opening_date'] > $today) {
            $adminJobCategories['pending']['jobs'][] = $jobResult;
        } elseif (!empty($jobResult['closing_date']) && $jobResult['closing_date'] < $today) {
            $adminJobCategories['closed']['jobs'][] = $jobResult;
        } else {
            $adminJobCategories['open']['jobs'][] = $jobResult;
        }
    }

    function adminJobResultCard($jobResult, $adminApplicantSearch)
    {
        $jobResultEditable = !empty($jobResult['opening_date']) && $jobResult['opening_date'] > date('Y-m-d') && (int) $jobResult['applicant_count'] === 0;
        $jobIsClosed = !empty($jobResult['closing_date']) && $jobResult['closing_date'] < date('Y-m-d');
        ob_start();
        ?>
        <div class="admin-job-result-card">
            <span class="admin-job-result-title">
                ตำแหน่ง : <?php echo htmlspecialchars($jobResult['title'], ENT_QUOTES, 'UTF-8'); ?>
                <span class="admin-job-result-dates">(<?php echo !empty($jobResult['opening_date']) ? date('d-m-Y', strtotime($jobResult['opening_date'])) : '-'; ?> ถึง <?php echo !empty($jobResult['closing_date']) ? date('d-m-Y', strtotime($jobResult['closing_date'])) : '-'; ?>)</span>
                <?php if ($jobResultEditable): ?>
                <a href="?page=announce&edit=<?php echo urlencode($jobResult['job_id']); ?>" class="admin-job-result-edit-btn" aria-label="แก้ไขตำแหน่งงาน"><i class="fa-solid fa-pencil"></i></a>
                <?php endif; ?>
            </span>
            <span class="admin-job-result-count"><i class="fa-solid fa-user"></i> <?php echo (int) $jobResult['applicant_count']; ?></span>
            <span class="admin-job-result-count admin-job-result-matched" title="จำนวนผู้สมัครที่แมตช์จากการสมัครล่วงหน้า"><i class="fa-solid fa-user-check"></i> แมตช์ <?php echo (int) $jobResult['matched_count']; ?></span>
            <form method="post" class="admin-job-toggle-form" onsubmit="return confirm('<?php echo $jobIsClosed ? 'ต้องการเปิดรับสมัครตำแหน่งนี้หรือไม่?' : 'ต้องการปิดรับสมัครตำแหน่งนี้หรือไม่?'; ?>');">
                <?php csrfField(); ?>
                <input type="hidden" name="toggle_job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="admin-job-toggle-btn <?php echo $jobIsClosed ? 'admin-job-toggle-open' : 'admin-job-toggle-close'; ?>"><?php echo $jobIsClosed ? 'เปิดรับสมัคร' : 'ปิดรับสมัคร'; ?></button>
            </form>
            <a href="?page=applicants&q=<?php echo urlencode($adminApplicantSearch); ?>&job_id=<?php echo urlencode($jobResult['job_id']); ?>" class="admin-job-result-btn">ตรวจสอบรายชื่อ</a>
        </div>
        <?php
        return ob_get_clean();
    }
    ?>
    <?php if ($adminApplicantSearch !== '' && count($adminJobResults) === 0): ?>
    <p class="announce-message announce-message-error">ไม่พบข้อมูลตามคำค้นหานี้</p>
    <?php endif; ?>
    <?php foreach ($adminJobCategories as $categoryKey => $category): ?>
    <div class="admin-job-category-header admin-job-category-<?php echo $categoryKey; ?>"><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php foreach ($category['jobs'] as $jobResult): ?>
    <?php echo adminJobResultCard($jobResult, $adminApplicantSearch); ?>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php elseif ($activeMenu === 'announce'): ?>
    <?php
    function jv($editingJob, $key)
    {
        return $editingJob ? htmlspecialchars((string) ($editingJob[$key] ?? ''), ENT_QUOTES, 'UTF-8') : '';
    }
    function juploadLabel($editingJob, $key, $default)
    {
        return $editingJob && !empty($editingJob[$key]) ? htmlspecialchars($editingJob[$key], ENT_QUOTES, 'UTF-8') : $default;
    }
    ?>
    <div class="announce-panel">
        <div class="announce-header-row">
            <div class="announce-code-badge">
                <span>รหัสตำแหน่ง</span>
                <span><?php echo htmlspecialchars($nextJobId, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <h2 class="announce-title"><?php echo $editingJob ? 'แก้ไขประกาศตำแหน่งงาน' : 'ประกาศเปิดรับสมัคร'; ?></h2>
        </div>

        <?php if ($announceSuccess): ?>
        <p class="announce-message announce-message-success">บันทึกประกาศตำแหน่งงานเรียบร้อยแล้ว</p>
        <?php elseif ($announceError !== ''): ?>
        <p class="announce-message announce-message-error"><?php echo htmlspecialchars($announceError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form class="announce-form" action="?page=announce<?php echo $editingJob ? '&edit=' . urlencode($editingJob['job_id']) : ''; ?>" method="post" enctype="multipart/form-data" id="announceForm">
            <?php csrfField(); ?>
            <div class="announce-grid">
                <div class="field-group">
                    <label class="field-label" for="jobField">ประเภทสายงาน</label>
                    <select id="jobField" name="job_field" class="announce-input">
                        <option value="">เลือกประเภทสายงาน</option>
                        <option value="งานการสอน">งานการสอน</option>
                        <option value="บุคลากรสนับสนุนการศึกษา">บุคลากรสนับสนุนการศึกษา</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="jobPositionLevel">ตำแหน่ง</label>
                    <select id="jobPositionLevel" name="position_level" class="announce-input"></select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="jobStaffType">ประเภทบุคลากร</label>
                    <select id="jobStaffType" name="staff_type" class="announce-input"></select>
                </div>
                <div class="field-group">
                    <label class="field-label">ประกาศสมัครงาน</label>
                    <label class="announce-input announce-upload-field">
                        <span class="announce-upload-label"><?php echo juploadLabel($editingJob, 'posting_file', 'แนบไฟล์'); ?></span>
                        <input type="file" name="job_posting" class="announce-upload-native" hidden>
                        <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                    </label>
                </div>

                <div class="field-group">
                    <label class="field-label" for="jobOpenings">จำนวนที่เปิดรับ(อัตรา)</label>
                    <input type="text" id="jobOpenings" name="openings" class="announce-input" value="<?php echo jv($editingJob, 'openings'); ?>">
                </div>
                <div class="field-group">
                    <label class="field-label" for="jobSalary">เงินเดือน</label>
                    <input type="text" id="jobSalary" name="salary" class="announce-input" value="<?php echo jv($editingJob, 'salary'); ?>">
                </div>

                <div class="field-group">
                    <label class="field-label" for="jobEducationLevel">ระดับวุฒิการศึกษา</label>
                    <?php
                    $currentEducationLevel = jv($editingJob, 'education_level');
                    $otherEducationLevels = ['ระดับปริญญาโท', 'ระดับปริญญาเอก'];
                    $isOtherEducationLevel = in_array($currentEducationLevel, $otherEducationLevels, true);
                    ?>
                    <select id="jobEducationLevel" name="education_level" class="announce-input">
                        <option value="">เลือกระดับวุฒิการศึกษา</option>
                        <option value="ระดับปริญญาตรี" <?php echo $currentEducationLevel === 'ระดับปริญญาตรี' ? 'selected' : ''; ?>>ระดับปริญญาตรี</option>
                        <option value="อื่นๆ" <?php echo ($currentEducationLevel === 'อื่นๆ' || $isOtherEducationLevel) ? 'selected' : ''; ?>>อื่นๆ</option>
                    </select>
                </div>

                <div class="field-group" id="jobEducationLevelOtherGroup" <?php echo $isOtherEducationLevel ? '' : 'hidden'; ?>>
                    <label class="field-label" for="jobEducationLevelOther">ระบุระดับวุฒิการศึกษา</label>
                    <select id="jobEducationLevelOther" name="education_level_other" class="announce-input">
                        <option value="">เลือกระดับวุฒิการศึกษา</option>
                        <option value="ระดับปริญญาโท" <?php echo $currentEducationLevel === 'ระดับปริญญาโท' ? 'selected' : ''; ?>>ระดับปริญญาโท</option>
                        <option value="ระดับปริญญาเอก" <?php echo $currentEducationLevel === 'ระดับปริญญาเอก' ? 'selected' : ''; ?>>ระดับปริญญาเอก</option>
                    </select>
                </div>

                <div class="field-group">
                    <label class="field-label" for="jobAbout">รายละเอียดเพิ่มเติม</label>
                    <input type="text" id="jobAbout" name="about" class="announce-input" value="<?php echo jv($editingJob, 'about'); ?>">
                </div>

                <div class="field-group">
                    <label class="field-label" for="jobOpeningDate">วันที่เปิดรับ</label>
                    <input type="date" id="jobOpeningDate" name="opening_date" class="announce-input" min="<?php echo $minOpeningDate; ?>" max="<?php echo $maxOpeningDate; ?>" value="<?php echo jv($editingJob, 'opening_date'); ?>">
                </div>
                <div class="field-group">
                    <label class="field-label" for="jobClosingDate">วันที่ปิดรับ</label>
                    <?php $hasOpeningDate = !empty($editingJob['opening_date']); ?>
                    <input type="<?php echo $hasOpeningDate ? 'date' : 'text'; ?>" id="jobClosingDate" name="closing_date" class="announce-input" value="<?php echo jv($editingJob, 'closing_date'); ?>" placeholder="กรุณากรอกวันที่เปิดรับก่อน" <?php echo $hasOpeningDate ? '' : 'readonly'; ?>>
                </div>

                <div class="field-group">
                    <label class="field-label">คิวอาร์โค้ดค่าสมัคร</label>
                    <label class="announce-input announce-upload-field">
                        <span class="announce-upload-label"><?php echo juploadLabel($editingJob, 'payment_slip_file', 'แนบไฟล์'); ?></span>
                        <input type="file" name="payment_slip" class="announce-upload-native" hidden>
                        <span class="announce-upload-icon"><i class="fa-solid fa-arrow-up"></i></span>
                    </label>
                </div>
            </div>

            <div class="announce-save-wrap">
                <button type="button" class="announce-save-btn" id="announceSaveBtn"><?php echo $editingJob ? 'บันทึกการแก้ไข' : 'บันทึก'; ?></button>
            </div>
        </form>

        <a href="index.php" class="announce-back" aria-label="กลับหน้าหลัก">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>

    <div class="incomplete-modal-overlay" id="announceIncompleteModal">
        <div class="incomplete-modal-box">
            <p class="incomplete-modal-message">ท่านยังกรอกข้อมูลไม่ครบ</p>
            <button type="button" class="incomplete-modal-close-btn" id="announceIncompleteModalClose">ย้อนกลับ</button>
        </div>
    </div>

    <div class="incomplete-modal-overlay" id="announceConfirmModal">
        <div class="incomplete-modal-box">
            <p class="incomplete-modal-message">กรุณาตรวจสอบข้อมูลของท่าน<br>ก่อนที่จะกดยืนยัน</p>
            <div class="confirm-modal-actions">
                <button type="button" class="incomplete-modal-close-btn" id="announceConfirmModalCancel">ย้อนกลับ</button>
                <button type="button" class="confirm-modal-confirm-btn" id="announceConfirmModalConfirm">ยืนยันการบันทึก</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var announceForm = document.getElementById('announceForm');
        var announceSaveBtn = document.getElementById('announceSaveBtn');
        var announceIncompleteModal = document.getElementById('announceIncompleteModal');
        var announceIncompleteModalClose = document.getElementById('announceIncompleteModalClose');
        var announceConfirmModal = document.getElementById('announceConfirmModal');
        var announceConfirmModalCancel = document.getElementById('announceConfirmModalCancel');
        var announceConfirmModalConfirm = document.getElementById('announceConfirmModalConfirm');
        var jobOpeningDate = document.getElementById('jobOpeningDate');
        var jobClosingDate = document.getElementById('jobClosingDate');
        var jobFieldSelect = document.getElementById('jobField');
        var jobPositionLevelSelect = document.getElementById('jobPositionLevel');
        var jobStaffTypeSelect = document.getElementById('jobStaffType');

        var positionLevelOptions = {
            'งานการสอน': ['ครูคณิตศาสตร์', 'ครูวิทยาศาสตร์', 'ครูภาษาไทย', 'ครูภาษาอังกฤษ', 'ครูภาษาต่างประเทศที่สอง', 'ครูสังคมศึกษา', 'ครูพละศึกษา', 'ครูสุขศึกษา', 'ครูศิลปะ', 'ครูดนตรี', 'ครูนาฏศิลป์', 'ครูคอมพิวเตอร์และเทคโนโลยี'],
            'บุคลากรสนับสนุนการศึกษา': ['เจ้าพนักงานธุรการโรงเรียน', 'เจ้าหน้าที่ระบบสารสนเทศ', 'เจ้าหน้าที่ประชาสัมพันธ์', 'เจ้าพนักงานการเงินและบัญชี', 'เจ้าพนักงานพัสดุ', 'เจ้าหน้าที่ทะเบียนและวัดผล', 'เจ้าหน้าที่ห้องปฏิบัติการ', 'นักการภารโรง', 'พนักงานขับรถ', 'พนักงานรักษาความปลอดภัย']
        };

        var staffTypeOptions = {
            'งานการสอน': ['ข้าราชการ', 'ลูกจ้างประจำ', 'ลูกจ้างชั่วคราว'],
            'บุคลากรสนับสนุนการศึกษา': ['พนักงานราชการ', 'ลูกจ้างประจำ', 'ลูกจ้างชั่วคราว']
        };

        function renderOptions(selectEl, optionsMap, jobField, selectedValue, placeholderWhenEmpty, placeholderWhenFilled) {
            var options = optionsMap[jobField] || [];
            selectEl.innerHTML = '';

            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = options.length ? placeholderWhenFilled : placeholderWhenEmpty;
            selectEl.appendChild(placeholder);

            options.forEach(function (opt) {
                var el = document.createElement('option');
                el.value = opt;
                el.textContent = opt;
                if (opt === selectedValue) {
                    el.selected = true;
                }
                selectEl.appendChild(el);
            });
        }

        if (jobFieldSelect && jobPositionLevelSelect && jobStaffTypeSelect) {
            var initialJobField = <?php echo json_encode($editingJob['job_field'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
            var initialPositionLevel = <?php echo json_encode($editingJob['position_level'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
            var initialStaffType = <?php echo json_encode($editingJob['staff_type'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
            jobFieldSelect.value = initialJobField;
            renderOptions(jobPositionLevelSelect, positionLevelOptions, initialJobField, initialPositionLevel, 'กรุณาเลือกประเภทสายงานก่อน', 'เลือกตำแหน่ง');
            renderOptions(jobStaffTypeSelect, staffTypeOptions, initialJobField, initialStaffType, 'กรุณาเลือกประเภทสายงานก่อน', 'เลือกประเภทบุคลากร');

            jobFieldSelect.addEventListener('change', function () {
                renderOptions(jobPositionLevelSelect, positionLevelOptions, jobFieldSelect.value, '', 'กรุณาเลือกประเภทสายงานก่อน', 'เลือกตำแหน่ง');
                renderOptions(jobStaffTypeSelect, staffTypeOptions, jobFieldSelect.value, '', 'กรุณาเลือกประเภทสายงานก่อน', 'เลือกประเภทบุคลากร');
            });
        }

        var jobEducationLevelSelect = document.getElementById('jobEducationLevel');
        var jobEducationLevelOtherGroup = document.getElementById('jobEducationLevelOtherGroup');
        if (jobEducationLevelSelect && jobEducationLevelOtherGroup) {
            jobEducationLevelSelect.addEventListener('change', function () {
                jobEducationLevelOtherGroup.hidden = jobEducationLevelSelect.value !== 'อื่นๆ';
            });
        }

        if (jobOpeningDate && jobClosingDate) {
            jobOpeningDate.addEventListener('change', function () {
                if (!jobOpeningDate.value) {
                    jobClosingDate.value = '';
                    jobClosingDate.type = 'text';
                    jobClosingDate.placeholder = 'กรุณากรอกวันที่เปิดรับก่อน';
                    jobClosingDate.setAttribute('readonly', 'readonly');
                    jobClosingDate.removeAttribute('min');
                    jobClosingDate.removeAttribute('max');
                    return;
                }

                if (jobClosingDate.type !== 'date') {
                    jobClosingDate.type = 'date';
                    jobClosingDate.removeAttribute('readonly');
                }

                var maxDate = new Date(jobOpeningDate.value);
                maxDate.setDate(maxDate.getDate() + 30);
                jobClosingDate.min = jobOpeningDate.value;
                jobClosingDate.max = maxDate.toISOString().slice(0, 10);
                if (jobClosingDate.value && (jobClosingDate.value < jobClosingDate.min || jobClosingDate.value > jobClosingDate.max)) {
                    jobClosingDate.value = '';
                }
            });
        }

        if (announceSaveBtn) {
            announceSaveBtn.addEventListener('click', function () {
                var incomplete = false;
                announceForm.querySelectorAll('.announce-input').forEach(function (field) {
                    if (field.tagName === 'LABEL') {
                        return;
                    }
                    if (field.offsetParent === null) {
                        return;
                    }
                    if (field.value.trim() === '') {
                        incomplete = true;
                    }
                });
                if (incomplete) {
                    announceIncompleteModal.classList.add('open');
                } else {
                    announceConfirmModal.classList.add('open');
                }
            });
        }

        if (announceIncompleteModalClose) {
            announceIncompleteModalClose.addEventListener('click', function () {
                announceIncompleteModal.classList.remove('open');
            });
        }

        if (announceConfirmModalCancel) {
            announceConfirmModalCancel.addEventListener('click', function () {
                announceConfirmModal.classList.remove('open');
            });
        }

        if (announceConfirmModalConfirm) {
            announceConfirmModalConfirm.addEventListener('click', function () {
                announceConfirmModal.classList.remove('open');
                announceForm.submit();
            });
        }
    });
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.announce-upload-field').forEach(function (field) {
            var fileInput = field.querySelector('.announce-upload-native');
            var label = field.querySelector('.announce-upload-label');
            var defaultText = label.textContent;
            fileInput.addEventListener('change', function () {
                label.textContent = fileInput.files.length ? fileInput.files[0].name : defaultText;
            });
        });
    });
    </script>
    <?php elseif ($activeMenu === 'examresults'): ?>
    <?php require_once __DIR__ . '/../config/db.php'; ?>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['toggle_publish']) && !empty($_POST['job_id']) && !empty($_POST['doc_type'])) {
        csrfCheck();
        $publishColumn = $_POST['doc_type'] === 'score' ? 'score_published' : 'schedule_published';
        $pdo->prepare("INSERT INTO exam_documents (job_id, $publishColumn) VALUES (?, 1)
            ON DUPLICATE KEY UPDATE $publishColumn = NOT $publishColumn")->execute([$_POST['job_id']]);

        header('Location: ?page=examresults&q=' . urlencode($_GET['q'] ?? ''));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['job_id']) && !empty($_POST['doc_type'])) {
        csrfCheck();
        $docJobId = $_POST['job_id'];
        $docType = $_POST['doc_type'] === 'score' ? 'score' : 'schedule';
        $docColumn = $docType === 'score' ? 'score_file' : 'schedule_file';
        $uploadDir = __DIR__ . '/../assets/uploads/exam_documents/';

        $existingStmt = $pdo->prepare("SELECT $docColumn AS existing_file FROM exam_documents WHERE job_id = ?");
        $existingStmt->execute([$docJobId]);
        $existingFile = $existingStmt->fetchColumn();

        if (!empty($_POST['delete_doc'])) {
            if (!empty($existingFile) && file_exists($uploadDir . $existingFile)) {
                unlink($uploadDir . $existingFile);
            }
            $pdo->prepare("UPDATE exam_documents SET $docColumn = NULL WHERE job_id = ?")->execute([$docJobId]);
        } elseif (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            if (($docUploadError = uploadValidationError($_FILES['doc_file'])) !== null) {
                http_response_code(400);
                die($docUploadError);
            }
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION);
            $docFilename = $docJobId . '_' . $docType . '_' . time() . ($ext !== '' ? '.' . $ext : '');
            storeUploadedFile($_FILES['doc_file'], $uploadDir . $docFilename);

            $pdo->prepare("INSERT INTO exam_documents (job_id, $docColumn) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE $docColumn = VALUES($docColumn)")->execute([$docJobId, $docFilename]);

            if (!empty($existingFile) && $existingFile !== $docFilename && file_exists($uploadDir . $existingFile)) {
                unlink($uploadDir . $existingFile);
            }

            $bothDocsStmt = $pdo->prepare('SELECT schedule_file, score_file FROM exam_documents WHERE job_id = ?');
            $bothDocsStmt->execute([$docJobId]);
            $bothDocs = $bothDocsStmt->fetch();
            if (!empty($bothDocs['schedule_file']) && !empty($bothDocs['score_file'])) {
                $pdo->prepare('UPDATE jobs SET closing_date = CURDATE() WHERE job_id = ?')->execute([$docJobId]);
            }
        }

        header('Location: ?page=examresults&q=' . urlencode($_GET['q'] ?? ''));
        exit;
    }
    ?>
    <?php if (!empty($_GET['job_id'])):
        $examListJobId = $_GET['job_id'];
        $examListJobStmt = $pdo->prepare('SELECT title FROM jobs WHERE job_id = ?');
        $examListJobStmt->execute([$examListJobId]);
        $examListJob = $examListJobStmt->fetch();
        if (!$examListJob) {
            header('Location: ?page=examresults&q=' . urlencode($_GET['q'] ?? ''));
            exit;
        }
        $examApplicantStmt = $pdo->prepare('SELECT a.first_name, a.last_name, ja.source
            FROM job_applications ja
            JOIN applicants a ON a.national_id = ja.national_id
            WHERE ja.job_id = ?
            ORDER BY ja.created_at');
        $examApplicantStmt->execute([$examListJobId]);
        $examApplicantList = $examApplicantStmt->fetchAll();
        ?>
    <div class="announce-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($examListJob['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <?php if (count($examApplicantList) === 0): ?>
        <p class="announce-message announce-message-error">ยังไม่มีผู้สมัครสำหรับตำแหน่งนี้</p>
        <?php endif; ?>
        <?php foreach ($examApplicantList as $applicant): ?>
        <div class="admin-applicant-row">
            <span class="admin-applicant-name<?php echo $applicant['source'] === 'preapply' ? ' admin-applicant-name-preapply' : ''; ?>"><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endforeach; ?>

        <a href="?page=examresults&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="announce-back" aria-label="กลับ">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>
    <?php else: ?>
    <form class="admin-search-bar" action="?page=examresults" method="get">
        <input type="hidden" name="page" value="examresults">
        <div class="field-group">
            <label class="field-label" for="adminExamQ">เลขบัตรประจำตัวผู้สมัครหรือชื่อตำแหน่ง</label>
            <input type="text" id="adminExamQ" name="q" class="admin-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit" class="admin-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <?php
    $adminExamSearch = trim($_GET['q'] ?? '');
    if ($adminExamSearch !== '') {
        $stmt = $pdo->prepare('SELECT DISTINCT j.job_id, j.title, j.opening_date, j.closing_date,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count,
                ed.schedule_file, ed.score_file, ed.schedule_published, ed.score_published
            FROM jobs j
            LEFT JOIN job_applications ja ON ja.job_id = j.job_id
            LEFT JOIN exam_documents ed ON ed.job_id = j.job_id
            WHERE j.title LIKE ? OR ja.national_id = ?
            ORDER BY j.created_at DESC');
        $stmt->execute(['%' . $adminExamSearch . '%', $adminExamSearch]);
    } else {
        $stmt = $pdo->query('SELECT j.job_id, j.title, j.opening_date, j.closing_date,
                (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count,
                ed.schedule_file, ed.score_file, ed.schedule_published, ed.score_published
            FROM jobs j
            LEFT JOIN exam_documents ed ON ed.job_id = j.job_id
            ORDER BY j.created_at DESC');
    }
    $adminExamJobResults = $stmt->fetchAll();

    $examToday = date('Y-m-d');
    $adminExamCategories = [
        'pending' => ['label' => 'งานที่รอเปิดรับ', 'jobs' => []],
        'open' => ['label' => 'งานที่กำลังเปิดรับอยู่', 'jobs' => []],
        'closed' => ['label' => 'งานที่ปิดรับการสมัคร', 'jobs' => []],
        'completed' => ['label' => 'งานที่ดำเนินการเสร็จสิ้น', 'jobs' => []],
    ];
    foreach ($adminExamJobResults as $jobResult) {
        if (!empty($jobResult['schedule_file']) && !empty($jobResult['score_file'])) {
            $adminExamCategories['completed']['jobs'][] = $jobResult;
        } elseif (!empty($jobResult['opening_date']) && $jobResult['opening_date'] > $examToday) {
            $adminExamCategories['pending']['jobs'][] = $jobResult;
        } elseif (!empty($jobResult['closing_date']) && $jobResult['closing_date'] < $examToday) {
            $adminExamCategories['closed']['jobs'][] = $jobResult;
        } else {
            $adminExamCategories['open']['jobs'][] = $jobResult;
        }
    }

    function adminExamJobCard($jobResult, $categoryKey = 'open')
    {
        ob_start();
        ?>
        <div class="admin-job-result-card">
            <span class="admin-job-result-title">
                ตำแหน่ง : <?php echo htmlspecialchars($jobResult['title'], ENT_QUOTES, 'UTF-8'); ?>
                <span class="admin-job-result-dates">(<?php echo !empty($jobResult['opening_date']) ? date('d-m-Y', strtotime($jobResult['opening_date'])) : '-'; ?> ถึง <?php echo !empty($jobResult['closing_date']) ? date('d-m-Y', strtotime($jobResult['closing_date'])) : '-'; ?>)</span>
            </span>
            <span class="admin-job-result-count"><i class="fa-solid fa-user"></i> <?php echo (int) $jobResult['applicant_count']; ?></span>
            <div class="admin-job-result-actions">
                <div class="exam-doc-group">
                    <?php if ($categoryKey === 'closed' || $categoryKey === 'completed'): ?>
                    <form method="post" enctype="multipart/form-data" class="exam-doc-form">
                        <?php csrfField(); ?>
                        <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="doc_type" value="schedule">
                        <label class="admin-job-result-btn exam-doc-upload-label<?php echo !empty($jobResult['schedule_file']) ? ' exam-doc-upload-label-done' : ''; ?>">
                            อัปโหลดตารางสอบ <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            <input type="file" name="doc_file" class="exam-doc-file-input" hidden>
                        </label>
                    </form>
                    <?php elseif ($categoryKey === 'open'): ?>
                    <a href="../user/job_detail.php?id=<?php echo urlencode($jobResult['job_id']); ?>&from=examresults&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="admin-job-result-btn">ข้อมูลงาน</a>
                    <?php endif; ?>
                    <?php if (!empty($jobResult['schedule_file'])): ?>
                    <span class="exam-doc-icon-group">
                        <form method="post" class="exam-doc-form" onsubmit="return confirm('<?php echo !empty($jobResult['schedule_published']) ? 'ต้องการยกเลิกการเผยแพร่ตารางสอบหรือไม่?' : 'ต้องการเผยแพร่ตารางสอบให้ผู้สมัครเห็นหรือไม่?'; ?>');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="doc_type" value="schedule">
                            <input type="hidden" name="toggle_publish" value="1">
                            <button type="submit" class="exam-doc-publish-btn<?php echo !empty($jobResult['schedule_published']) ? ' exam-doc-publish-btn-active' : ''; ?>" aria-label="<?php echo !empty($jobResult['schedule_published']) ? 'ยกเลิกการเผยแพร่ตารางสอบ' : 'เผยแพร่ตารางสอบให้ผู้สมัครเห็น'; ?>"><i class="fa-solid fa-bullhorn"></i></button>
                        </form>
                        <form method="post" class="exam-doc-form" onsubmit="return confirm('ต้องการลบไฟล์ตารางสอบเดิมหรือไม่?');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="doc_type" value="schedule">
                            <input type="hidden" name="delete_doc" value="1">
                            <button type="submit" class="exam-doc-edit-btn" aria-label="แก้ไขไฟล์ตารางสอบ"><i class="fa-solid fa-pencil"></i></button>
                        </form>
                        <a href="../assets/uploads/exam_documents/<?php echo rawurlencode($jobResult['schedule_file']); ?>" target="_blank" class="exam-doc-view-link" aria-label="ดูไฟล์ตารางสอบ"><i class="fa-solid fa-eye"></i></a>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="exam-doc-group">
                    <?php if ($categoryKey === 'closed' || $categoryKey === 'completed'): ?>
                    <form method="post" enctype="multipart/form-data" class="exam-doc-form">
                        <?php csrfField(); ?>
                        <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="doc_type" value="score">
                        <label class="admin-job-result-btn exam-doc-upload-label<?php echo !empty($jobResult['score_file']) ? ' exam-doc-upload-label-done' : ''; ?>">
                            อัปโหลดผลสอบ <i class="fa-solid fa-arrow-up-from-bracket"></i>
                            <input type="file" name="doc_file" class="exam-doc-file-input" hidden>
                        </label>
                    </form>
                    <?php elseif ($categoryKey === 'open'): ?>
                    <a href="?page=examresults&job_id=<?php echo urlencode($jobResult['job_id']); ?>&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="admin-job-result-btn">ดูรายชื่อ</a>
                    <?php endif; ?>
                    <?php if (!empty($jobResult['score_file'])): ?>
                    <span class="exam-doc-icon-group">
                        <form method="post" class="exam-doc-form" onsubmit="return confirm('<?php echo !empty($jobResult['score_published']) ? 'ต้องการยกเลิกการเผยแพร่ผลสอบหรือไม่?' : 'ต้องการเผยแพร่ผลสอบให้ผู้สมัครเห็นหรือไม่?'; ?>');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="doc_type" value="score">
                            <input type="hidden" name="toggle_publish" value="1">
                            <button type="submit" class="exam-doc-publish-btn<?php echo !empty($jobResult['score_published']) ? ' exam-doc-publish-btn-active' : ''; ?>" aria-label="<?php echo !empty($jobResult['score_published']) ? 'ยกเลิกการเผยแพร่ผลสอบ' : 'เผยแพร่ผลสอบให้ผู้สมัครเห็น'; ?>"><i class="fa-solid fa-bullhorn"></i></button>
                        </form>
                        <form method="post" class="exam-doc-form" onsubmit="return confirm('ต้องการลบไฟล์ผลสอบเดิมหรือไม่?');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($jobResult['job_id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="doc_type" value="score">
                            <input type="hidden" name="delete_doc" value="1">
                            <button type="submit" class="exam-doc-edit-btn" aria-label="แก้ไขไฟล์ผลสอบ"><i class="fa-solid fa-pencil"></i></button>
                        </form>
                        <a href="../assets/uploads/exam_documents/<?php echo rawurlencode($jobResult['score_file']); ?>" target="_blank" class="exam-doc-view-link" aria-label="ดูไฟล์ผลสอบ"><i class="fa-solid fa-eye"></i></a>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    ?>
    <?php if ($adminExamSearch !== '' && count($adminExamJobResults) === 0): ?>
    <p class="announce-message announce-message-error">ไม่พบข้อมูลตามคำค้นหานี้</p>
    <?php endif; ?>
    <?php foreach ($adminExamCategories as $categoryKey => $category): ?>
    <div class="admin-job-category-header admin-job-category-<?php echo $categoryKey; ?>"><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php foreach ($category['jobs'] as $jobResult): ?>
    <?php echo adminExamJobCard($jobResult, $categoryKey); ?>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.exam-doc-file-input').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.files.length) {
                    input.closest('form').submit();
                }
            });
        });
    });
    </script>
    <?php endif; ?>
    <?php elseif ($activeMenu === 'preapply'): ?>
    <?php
    require_once __DIR__ . '/../config/db.php';
    syncPreapplicationMatches($pdo);
    ?>
    <?php
    $preapplyCategoryLabels = [
        'teaching' => 'งานการสอน',
        'support' => 'บุคลากรสนับสนุนการศึกษา',
    ];

    $preapplyCategory = $_GET['category'] ?? '';
    $preapplyPreappId = $_GET['preapp_id'] ?? '';
    ?>
    <?php if ($preapplyCategory !== '' && isset($preapplyCategoryLabels[$preapplyCategory])): ?>
        <?php if ($preapplyPreappId !== ''):
        $preapplyDetailStmt = $pdo->prepare('SELECT pa.*, a.first_name, a.last_name, a.birth_date, a.national_id, a.age, a.gender, a.ethnicity, a.nationality, a.religion, a.phone
            FROM preapplications pa
            JOIN applicants a ON a.national_id = pa.national_id
            WHERE pa.preapp_id = ?');
        $preapplyDetailStmt->execute([$preapplyPreappId]);
        $preapplyDetail = $preapplyDetailStmt->fetch();
        if (!$preapplyDetail) {
            header('Location: ?page=preapply&category=' . urlencode($preapplyCategory));
            exit;
        }

        function preapplyReviewFileField($label, $filename)
        {
            $url = !empty($filename) ? '../file.php?f=' . rawurlencode($filename) : '';
            ob_start();
            ?>
            <div class="field-group">
                <label class="field-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <?php if ($url !== ''): ?>
                <a href="<?php echo $url; ?>" target="_blank" class="announce-input applicant-review-file"><span>ดูไฟล์</span><i class="fa-solid fa-eye"></i></a>
                <?php else: ?>
                <div class="announce-input applicant-review-value">-</div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        function preapplyReviewTextField($label, $value)
        {
            ob_start();
            ?>
            <div class="field-group">
                <label class="field-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                <div class="announce-input applicant-review-value"><?php echo htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php
            return ob_get_clean();
        }
        ?>
        <div class="applicant-review-panel">
            <h2 class="applicant-review-title">ตรวจสอบข้อมูลผู้สมัครล่วงหน้า</h2>

            <div class="applicant-review-body">
                <div class="applicant-review-photo-col">
                    <?php if (!empty($preapplyDetail['profile_photo_file'])): ?>
                    <img src="../file.php?f=<?php echo rawurlencode($preapplyDetail['profile_photo_file']); ?>" alt="รูปผู้สมัคร" class="applicant-review-photo">
                    <?php else: ?>
                    <div class="applicant-review-photo applicant-review-photo-placeholder"><i class="fa-solid fa-user"></i></div>
                    <?php endif; ?>
                    <span class="applicant-review-name"><?php echo htmlspecialchars($preapplyDetail['first_name'] . ' ' . $preapplyDetail['last_name'], ENT_QUOTES, 'UTF-8'); ?><br>ตำแหน่ง : <?php echo htmlspecialchars($preapplyDetail['position_level'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="applicant-review-grid">
                    <?php echo preapplyReviewTextField('ชื่อ สกุล', $preapplyDetail['first_name'] . ' ' . $preapplyDetail['last_name']); ?>
                    <?php echo preapplyReviewTextField('วัน เดือน ปีเกิด', $preapplyDetail['birth_date']); ?>
                    <?php echo preapplyReviewTextField('เลขบัตรประชาชน', $preapplyDetail['national_id']); ?>

                    <?php echo preapplyReviewTextField('อายุ', $preapplyDetail['age']); ?>
                    <?php echo preapplyReviewTextField('เพศ', $preapplyDetail['gender']); ?>
                    <?php echo preapplyReviewTextField('เชื้อชาติ', $preapplyDetail['ethnicity']); ?>

                    <?php echo preapplyReviewTextField('สัญชาติ', $preapplyDetail['nationality']); ?>
                    <?php echo preapplyReviewTextField('ศาสนา', $preapplyDetail['religion']); ?>
                    <?php echo preapplyReviewTextField('ตำแหน่งที่สนใจ', $preapplyDetail['position_level']); ?>

                    <?php echo preapplyReviewTextField('เบอร์โทร', $preapplyDetail['phone']); ?>
                    <?php echo preapplyReviewFileField('สำเนาบัตรประชาชน', $preapplyDetail['id_card_copy_file']); ?>
                    <?php echo preapplyReviewFileField('ใบรับรองแพทย์', $preapplyDetail['medical_cert_file']); ?>

                    <?php echo preapplyReviewTextField('สถานะภาพ', $preapplyDetail['marital_status']); ?>
                    <?php echo preapplyReviewTextField('สถานะทางทหาร(ถ้ามี)', $preapplyDetail['military_status']); ?>

                    <?php echo preapplyReviewFileField('เรซูเม่', $preapplyDetail['resume_file']); ?>
                    <?php echo preapplyReviewFileField('ข้อมูลการศึกษา(ปริญญา)', $preapplyDetail['edu_degree_file']); ?>
                </div>
            </div>

            <div class="applicant-review-actions">
                <a href="?page=preapply&category=<?php echo urlencode($preapplyCategory); ?>" class="applicant-review-back-btn" aria-label="กลับ">
                    <i class="fa-solid fa-reply"></i>
                </a>
            </div>
        </div>
        <?php else:
        $preapplyListStmt = $pdo->prepare('SELECT pa.preapp_id, pa.status, a.first_name, a.last_name
            FROM preapplications pa
            JOIN applicants a ON a.national_id = pa.national_id
            WHERE pa.job_field = ?
                AND NOT EXISTS (
                    SELECT 1 FROM job_applications ja
                    JOIN jobs j ON j.job_id = ja.job_id
                    WHERE ja.national_id = pa.national_id AND j.job_field = pa.job_field AND j.position_level = pa.position_level
                )
            ORDER BY pa.created_at DESC');
        $preapplyListStmt->execute([$preapplyCategoryLabels[$preapplyCategory]]);
        $preapplyList = $preapplyListStmt->fetchAll();
        ?>
        <div class="announce-panel">
            <div class="apply-category-bar"><?php echo htmlspecialchars($preapplyCategoryLabels[$preapplyCategory], ENT_QUOTES, 'UTF-8'); ?></div>

            <?php if (count($preapplyList) === 0): ?>
            <p class="announce-message announce-message-error">ยังไม่มีผู้ลงสมัครล่วงหน้าในสายงานนี้</p>
            <?php endif; ?>
            <?php foreach ($preapplyList as $preapplicant): ?>
            <div class="admin-applicant-row">
                <span class="admin-applicant-name"><?php echo htmlspecialchars($preapplicant['first_name'] . ' ' . $preapplicant['last_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($preapplicant['status']) ? ' (' . htmlspecialchars($preapplicant['status'], ENT_QUOTES, 'UTF-8') . ')' : ''; ?></span>
                <a href="?page=preapply&category=<?php echo urlencode($preapplyCategory); ?>&preapp_id=<?php echo urlencode($preapplicant['preapp_id']); ?>" class="admin-applicant-check-btn">ตรวจสอบ</a>
            </div>
            <?php endforeach; ?>

            <a href="?page=preapply" class="announce-back" aria-label="กลับหน้าเลือกสายงาน">
                <i class="fa-solid fa-reply"></i>
            </a>
        </div>
        <?php endif; ?>
    <?php else:
    $preapplyCounts = [];
    foreach ($preapplyCategoryLabels as $key => $label) {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM preapplications pa WHERE pa.job_field = ?
            AND NOT EXISTS (
                SELECT 1 FROM job_applications ja
                JOIN jobs j ON j.job_id = ja.job_id
                WHERE ja.national_id = pa.national_id AND j.job_field = pa.job_field AND j.position_level = pa.position_level
            )');
        $countStmt->execute([$label]);
        $preapplyCounts[$key] = (int) $countStmt->fetchColumn();
    }
    ?>
    <form class="admin-search-bar" action="?page=preapply" method="get">
        <input type="hidden" name="page" value="preapply">
        <div class="field-group">
            <label class="field-label" for="adminPreapplyQ">เลขบัตรประจำตัวผู้สมัครหรือชื่อตำแหน่ง</label>
            <input type="text" id="adminPreapplyQ" name="q" class="admin-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit" class="admin-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <div class="preapply-category-grid">
        <a href="?page=preapply&category=teaching" class="preapply-category-card">
            <span>งานการสอน</span>
            <span class="preapply-category-count"><i class="fa-solid fa-user"></i> <?php echo $preapplyCounts['teaching']; ?></span>
        </a>
        <a href="?page=preapply&category=support" class="preapply-category-card">
            <span>บุคลากรสนับสนุนการศึกษา</span>
            <span class="preapply-category-count"><i class="fa-solid fa-user"></i> <?php echo $preapplyCounts['support']; ?></span>
        </a>
    </div>
    <?php endif; ?>
    <?php elseif ($activeMenu === 'reports'): ?>
    <?php require_once __DIR__ . '/../config/db.php'; ?>
    <?php if (!empty($_GET['job_id'])):
        $reportJobId = $_GET['job_id'];
        $reportJobStmt = $pdo->prepare('SELECT title FROM jobs WHERE job_id = ?');
        $reportJobStmt->execute([$reportJobId]);
        $reportJob = $reportJobStmt->fetch();
        if (!$reportJob) {
            header('Location: ?page=reports');
            exit;
        }
        $reportListStmt = $pdo->prepare('SELECT a.first_name, a.last_name, ja.app_id, ja.source
            FROM job_applications ja
            JOIN applicants a ON a.national_id = ja.national_id
            WHERE ja.job_id = ?
            ORDER BY ja.created_at');
        $reportListStmt->execute([$reportJobId]);
        $reportApplicantList = $reportListStmt->fetchAll();
        ?>
    <div class="announce-panel">
        <div class="job-detail-title"><?php echo htmlspecialchars($reportJob['title'], ENT_QUOTES, 'UTF-8'); ?></div>

        <?php foreach ($reportApplicantList as $applicant): ?>
        <div class="admin-applicant-row">
            <span class="admin-applicant-name<?php echo $applicant['source'] === 'preapply' ? ' admin-applicant-name-preapply' : ''; ?>"><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="?page=reports&job_id=<?php echo urlencode($reportJobId); ?>&q=<?php echo urlencode($_GET['q'] ?? ''); ?>&download=<?php echo urlencode($applicant['app_id']); ?>" class="admin-applicant-download-btn">ดาวน์โหลด</a>
        </div>
        <?php endforeach; ?>

        <a href="?page=reports&q=<?php echo urlencode($_GET['q'] ?? ''); ?>" class="announce-back" aria-label="กลับหน้าค้นหา">
            <i class="fa-solid fa-reply"></i>
        </a>
    </div>
    <?php else:
        $reportSearch = trim($_GET['q'] ?? '');
        if ($reportSearch !== '') {
            $reportStmt = $pdo->prepare('SELECT DISTINCT j.job_id, j.title, j.opening_date, j.closing_date,
                    (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count
                FROM jobs j
                LEFT JOIN job_applications ja ON ja.job_id = j.job_id
                WHERE j.title LIKE ? OR ja.national_id = ?
                ORDER BY j.created_at DESC');
            $reportStmt->execute(['%' . $reportSearch . '%', $reportSearch]);
        } else {
            $reportStmt = $pdo->query('SELECT j.job_id, j.title, j.opening_date, j.closing_date,
                    (SELECT COUNT(*) FROM job_applications WHERE job_id = j.job_id) AS applicant_count
                FROM jobs j
                ORDER BY j.created_at DESC');
        }
        $reportJobResults = $reportStmt->fetchAll();

        $reportToday = date('Y-m-d');
        $adminReportCategories = [
            'pending' => ['label' => 'งานที่รอเปิดรับ', 'jobs' => []],
            'open' => ['label' => 'งานที่กำลังเปิดรับอยู่', 'jobs' => []],
            'closed' => ['label' => 'งานที่ปิดรับการสมัคร', 'jobs' => []],
        ];
        foreach ($reportJobResults as $jobResult) {
            if (!empty($jobResult['opening_date']) && $jobResult['opening_date'] > $reportToday) {
                $adminReportCategories['pending']['jobs'][] = $jobResult;
            } elseif (!empty($jobResult['closing_date']) && $jobResult['closing_date'] < $reportToday) {
                $adminReportCategories['closed']['jobs'][] = $jobResult;
            } else {
                $adminReportCategories['open']['jobs'][] = $jobResult;
            }
        }

        function adminReportJobCard($jobResult, $reportSearch)
        {
            ob_start();
            ?>
            <div class="admin-job-result-card">
                <span class="admin-job-result-title">
                    ตำแหน่ง : <?php echo htmlspecialchars($jobResult['title'], ENT_QUOTES, 'UTF-8'); ?>
                    <span class="admin-job-result-dates">(<?php echo !empty($jobResult['opening_date']) ? date('d-m-Y', strtotime($jobResult['opening_date'])) : '-'; ?> ถึง <?php echo !empty($jobResult['closing_date']) ? date('d-m-Y', strtotime($jobResult['closing_date'])) : '-'; ?>)</span>
                </span>
                <span class="admin-job-result-count"><i class="fa-solid fa-user"></i> <?php echo (int) $jobResult['applicant_count']; ?></span>
                <?php if ((int) $jobResult['applicant_count'] > 0): ?>
                <a href="?page=reports&download_job=<?php echo urlencode($jobResult['job_id']); ?>" class="admin-applicant-download-btn">ดาวน์โหลด</a>
                <?php else: ?>
                <span class="admin-applicant-download-btn" style="background:#d6d6d6; color:#8a8a8a; cursor:not-allowed;" title="ยังไม่มีผู้สมัคร">ดาวน์โหลด</span>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }
        ?>
    <form class="admin-search-bar" action="?page=reports" method="get">
        <input type="hidden" name="page" value="reports">
        <div class="field-group">
            <label class="field-label" for="adminReportsQ">เลขบัตรประจำตัวผู้สมัครหรือชื่อตำแหน่ง</label>
            <input type="text" id="adminReportsQ" name="q" class="admin-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <button type="submit" class="admin-search-btn">ค้นหา <i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <?php if ($reportSearch !== '' && count($reportJobResults) === 0): ?>
    <p class="announce-message announce-message-error">ไม่พบข้อมูลตามคำค้นหานี้</p>
    <?php endif; ?>
    <?php foreach ($adminReportCategories as $categoryKey => $category): ?>
    <div class="admin-job-category-header admin-job-category-<?php echo $categoryKey; ?>"><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php foreach ($category['jobs'] as $jobResult): ?>
    <?php echo adminReportJobCard($jobResult, $reportSearch); ?>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php else: ?>
    <!-- เนื้อหาของแต่ละหน้าจะถูกใส่ตรงนี้ -->
    <?php endif; ?>
</main>

</body>
</html>
