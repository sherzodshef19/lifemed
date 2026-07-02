<?php
// Fetch basic settings for global use
$global_settings = [];
if (isset($pdo)) {
    $gs_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('clinic_logo', 'clinic_name')");
    while ($row = $gs_stmt->fetch()) {
        $global_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$app_logo = $global_settings['clinic_logo'] ?? '';
$app_name = $global_settings['clinic_name'] ?? 'LiFe Med';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeMed CRM - <?= $page_title ?? 'Панель управления' ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/assets/vendor/css/fontawesome.min.css">

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">

    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --accent-color: #0ea5e9;
            --bg-color: #f8fafc;
            --sidebar-bg: #1e293b;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --navbar-bg: #ffffff;
            --input-bg: #ffffff;
            --card-radius: 0.75rem;
        }

        [data-theme="dark"] {
            --bg-color: #0f172a;
            --sidebar-bg: #020617;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3), 0 2px 4px -2px rgb(0 0 0 / 0.3);
            --text-color: #e2e8f0;
            --card-bg: #1e293b;
            --border-color: #334155;
            --navbar-bg: #1e293b;
            --input-bg: #334155;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar {
            height: 100vh;
            width: 260px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--sidebar-bg);
            color: white;
            padding-top: 1.5rem;
            z-index: 1000;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar .sidebar-logo {
            flex-shrink: 0;
            padding: 0 1rem 1rem;
        }

        .sidebar nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.2) transparent;
            display: flex;
            flex-direction: column;
        }

        .sidebar nav .nav-scroll {
            flex: 1;
            overflow-y: auto;
        }

        .sidebar nav .sidebar-footer {
            flex-shrink: 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding: 0.5rem 0;
        }

        .sidebar nav::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
        }

        .sidebar .nav-link {
            color: #cbd5e1;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            border-radius: 0.5rem;
            margin: 0.25rem 0.75rem;
            transition: all 0.2s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            width: 24px;
            margin-right: 12px;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        .navbar-custom {
            height: 70px;
            background: var(--navbar-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            transition: background-color 0.3s;
        }

        .card {
            border: none;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            transition: background-color 0.3s;
            background-color: var(--card-bg);
        }

        .x-small { font-size: 0.7rem; }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.25rem;
            font-weight: 500;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        [data-theme="dark"] .glass-card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        [v-cloak] {
            display: none;
        }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast-item {
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s;
            min-width: 250px;
        }
        .toast-success { background: #10b981; }
        .toast-error { background: #ef4444; }
        .toast-info { background: #3b82f6; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

        /* Mobile hamburger */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-color);
            cursor: pointer;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .sidebar-toggle { display: block; }
            .main-content { margin-left: 0; padding: 1rem; }
            .navbar-custom { padding: 0 1rem; }
        }

        /* Dark mode toggle */
        .theme-toggle {
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .theme-toggle:hover {
            background: rgba(255,255,255,0.1);
        }

        /* Confirm modal */
        .confirm-modal .modal-content {
            border-radius: 1rem;
            border: none;
        }
    </style>

    <!-- Core JS dependencies (Local for Offline) -->
    <script src="/assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/vendor/js/vue.global.prod.js"></script>
    <script src="/assets/vendor/js/axios.min.js"></script>

    <!-- PWA Registration -->
    <script>
        window.addEventListener('load', () => {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker registered'))
                    .catch(err => console.log('SW registration failed', err));
            }
        });
    </script>
</head>

<body>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Confirm Modal -->
    <div class="modal fade confirm-modal" id="confirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div id="confirmIcon" class="mb-3"></div>
                    <h6 class="fw-bold mb-2" id="confirmTitle">Подтверждение</h6>
                    <p class="text-secondary small mb-4" id="confirmMessage">Вы уверены?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button class="btn btn-light px-3" data-bs-dismiss="modal">Отмена</button>
                        <button class="btn btn-danger px-3" id="confirmBtn">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-logo text-center">
                <?php if ($app_logo): ?>
                    <img src="/assets/img/<?= h($app_logo) ?>" alt="Logo" class="img-fluid mb-2"
                        style="max-height: 50px;">
                <?php else: ?>
                    <h4 class="fw-bold mb-0 text-white">LiFe <span class="text-info">Med</span></h4>
                <?php endif; ?>
                <div class="small text-secondary"><?= h($app_name) ?></div>
            </div>
            <nav class="nav flex-column">
                <div class="nav-scroll">
                <?php if ($_SESSION['role'] === 'doctor'): ?>
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'doctor_panel.php') !== false ? 'active' : '' ?>"
                        href="doctor_panel.php">
                        <i class="fas fa-desktop"></i> Рабочий стол
                    </a>

                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'appointments.php') !== false ? 'active' : '' ?>"
                        href="appointments.php">
                        <i class="fas fa-calendar-alt"></i> Мои приёмы
                    </a>
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'lab_forms.php') !== false ? 'active' : '' ?>"
                        href="lab_forms.php">
                        <i class="fas fa-file-medical"></i> Бланки анализов
                    </a>
                <?php else: ?>
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'index.php') !== false ? 'active' : '' ?>"
                        href="index.php">
                        <i class="fas fa-home"></i> Дашборд
                    </a>
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'registration.php') !== false ? 'active' : '' ?>"
                        href="registration.php">
                        <i class="fas fa-plus-circle"></i> Оформление
                    </a>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'cashier'): ?>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'lab_results.php') !== false ? 'active' : '' ?>"
                            href="lab_results.php">
                            <i class="fas fa-microscope"></i> Анализы
                        </a>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'cashier'): ?>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'doctors.php') !== false ? 'active' : '' ?>"
                            href="doctors.php">
                            <i class="fas fa-user-md"></i> Врачи
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'patients.php') !== false ? 'active' : '' ?>"
                            href="patients.php">
                            <i class="fas fa-user-injured"></i> Пациенты
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'lab_results.php') !== false ? 'active' : '' ?>"
                            href="lab_results.php">
                            <i class="fas fa-microscope"></i> Анализы
                        </a>
                    <?php endif; ?>

                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'services.php') !== false ? 'active' : '' ?>"
                        href="services.php">
                        <i class="fas fa-briefcase-medical"></i> Услуги
                    </a>
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'appointments.php') !== false ? 'active' : '' ?>"
                        href="appointments.php">
                        <i class="fas fa-calendar-alt"></i> Приёмы
                    </a>
                    <div class="sidebar-divider mx-3 my-2 border-top border-secondary"></div>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'active' : '' ?>"
                            href="reports.php">
                            <i class="fas fa-chart-line"></i> Отчёты
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'lab_forms.php') !== false ? 'active' : '' ?>"
                            href="lab_forms.php">
                            <i class="fas fa-file-medical"></i> Бланки
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users.php') !== false ? 'active' : '' ?>"
                            href="users.php">
                            <i class="fas fa-users-cog"></i> Пользователи
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'telegram_settings.php') !== false ? 'active' : '' ?>"
                            href="telegram_settings.php">
                            <i class="fab fa-telegram"></i> Telegram Bot
                        </a>
                        <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : '' ?>"
                            href="settings.php">
                            <i class="fas fa-cog"></i> Настройки
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
                <div class="sidebar-footer">
                    <a class="nav-link text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Выход
                    </a>
                </div>
            </nav>
        </div>
        <div class="main-content">
            <header class="navbar-custom mb-4 justify-content-between">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle me-3" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="fw-bold mb-0"><?= h($page_title ?? 'Панель управления') ?></h5>
                </div>
                <div class="user-info d-flex align-items-center">
                    <div class="theme-toggle me-3" onclick="toggleTheme()" title="Сменить тему">
                        <i class="fas fa-moon" id="themeIcon"></i>
                    </div>
                    <span class="me-3 text-secondary d-none d-md-inline"><?= h($_SESSION['full_name']) ?>
                        (<?= ucfirst(h($_SESSION['role'])) ?>)</span>
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </header>
        <?php endif; ?>