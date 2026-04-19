<!DOCTYPE html>
<html lang="pt-br" data-theme="bmlab">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMLAB</title>
    <link href="public/css/app.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="img/icon_braga_mendes.png">
    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'bmlab';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        body {
            background-color: transparent;
        }
    </style>
</head>
<body class="bg-base-200 min-h-screen p-4">
    <?php 
    if (isset($contentView) && file_exists($contentView)) {
        include $contentView;
    } else {
        echo '<div class="alert alert-error">Conteúdo não encontrado.</div>';
    }
    ?>
</body>
</html>
