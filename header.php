<?php session_start(); 
include 'koneksi.php'; // pastikan file koneksi dipanggil

// Default foto profil
$foto_profil = "assets/img/person.png";

// Ambil gambar user dari database jika login
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['profile_picture']) && file_exists($row['profile_picture'])) {
            $foto_profil = $row['profile_picture'];
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">


<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />

  <title><?= isset($pageTitle) ? $pageTitle : "Dashboard" ?></title>
  <meta content="" name="description" />
  <meta content="" name="keywords" />

  <!-- Favicons -->
  <link href="assets/img/logo kai.png" rel="icon" />
  <link href="assets/img/logo kai.png" rel="apple-touch-icon" />

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect" />
  <link
    href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i"
    rel="stylesheet" />

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet" />
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet" />
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet" />
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet" />
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet" />
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet" />

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet" />
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
      <a href="index.html" class="logo d-flex align-items-center">
        <img src="assets/img/logo kai.png" alt="Logo KAI" style="height: 40px; margin-right: 8px;" />
        <span class="d-none d-lg-block">Summary</span>
      </a>
      <i class="bi bi-list toggle-sidebar-btn"></i>
    </div><!-- End Logo -->

    <nav class="header-nav ms-auto">
      <ul class="d-flex align-items-center">
        <li class="nav-item dropdown pe-3">
  <a class="nav-link nav-profile d-flex align-items-center pe-0 dropdown-toggle"
     href="#" id="profileDropdown" role="button"
     data-bs-toggle="dropdown" aria-expanded="false">
    <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Profile"
         class="rounded-circle" style="width: 35px; height: 35px; object-fit: cover;">
    <span class="d-none d-md-block ps-2"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
  </a>

  <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow"
      aria-labelledby="profileDropdown">
    <li class="dropdown-header d-flex align-items-center">
      <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Profile"
           class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
      <div>
        <h6 class="mb-0"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></h6>
        <span class="text-muted"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
      </div>
    </li>
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item d-flex align-items-center" href="gantipassword.php">
        <i class="bi bi-person"></i>
        <span>Edit Profile & Ganti Password</span>
      </a>
    </li>
    <li>
      <a class="dropdown-item d-flex align-items-center" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Keluar</span>
      </a>
    </li>
  </ul>
</li><!-- End Profile Nav -->
      </ul>
    </nav><!-- End Icons Navigation -->

  </header><!-- End Header -->

  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'dashboard.php' ? '' : 'collapsed' ?>" href="dashboard.php">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li><!-- End Dashboard Nav -->

      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'laporan.php' ? '' : 'collapsed' ?>" href="laporan.php?kategori=pendapatan">
          <i class="bi bi-bar-chart"></i>
          <span>Laporan</span>
        </a>
      </li><!-- End Laporan Nav -->

      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'investasi.php' ? '' : 'collapsed' ?>" href="investasi.php">
          <i class="bi bi-cash-stack"></i>
          <span>Investasi</span>
        </a>
      </li><!-- End Investasi Nav -->

      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) == 'gantipassword.php' ? '' : 'collapsed' ?>" href="gantipassword.php">
          <i class="bi bi-person"></i>
          <span>Ubah Profil</span>
        </a>
      </li><!-- End Profile Page Nav -->

      <li class="nav-item">
        <a class="nav-link collapsed" href="logout.php">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Keluar</span>
        </a>
      </li><!-- End Login Page Nav -->
    </ul>
  </aside><!-- End Sidebar -->

  <script src="assets/js/sidebar-accordion.js"></script>
</body>
</html>