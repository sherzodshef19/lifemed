<?php
http_response_code(404);
$page_title = '404 — Страница не найдена';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — LifeMed</title>
    <link rel="stylesheet" href="/assets/vendor/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/css/fontawesome.min.css">
    <style>
        body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; }
        .error-container { text-align: center; padding: 2rem; }
        .error-code { font-size: 8rem; font-weight: 900; color: #e2e8f0; line-height: 1; letter-spacing: -4px; }
        .error-code span { color: #3b82f6; }
        .error-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 1rem 0 0.5rem; }
        .error-text { color: #64748b; margin-bottom: 2rem; }
        .btn-home { background: #3b82f6; color: #fff; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; text-decoration: none; transition: background 0.2s; }
        .btn-home:hover { background: #2563eb; color: #fff; }
        .btn-back { background: transparent; color: #64748b; border: 1px solid #cbd5e1; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; text-decoration: none; margin-left: 0.75rem; transition: all 0.2s; }
        .btn-back:hover { background: #f8fafc; color: #1e293b; border-color: #94a3b8; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">4<span>0</span>4</div>
        <h1 class="error-title">Страница не найдена</h1>
        <p class="error-text">Запрашиваемая страница не существует или была перемещена.</p>
        <a href="/admin/index.php" class="btn-home"><i class="fas fa-home me-2"></i>На главную</a>
        <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left me-2"></i>Назад</a>
    </div>
</body>
</html>
