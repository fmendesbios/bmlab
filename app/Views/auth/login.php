<?php
// app/Views/auth/login.php
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>BMLAB - Login</title>
    <link rel="icon" type="image/png" href="img/icon_braga_mendes.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            background: #f8f9fa;
        }

        /* 🔥 Fundo com 3 imagens fundidas */
        .background-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            z-index: -1;
            overflow: hidden;
        }

        .bg-img {
            flex: 1;
            height: 100%;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        /* Imagem 1 → Fade à direita */
        .bg-img:nth-child(1)::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 180px;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
        }

        /* Imagem 2 → Fade nos dois lados */
        .bg-img:nth-child(2)::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 180px;
            height: 100%;
            background: linear-gradient(to left, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
        }

        .bg-img:nth-child(2)::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 180px;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
        }

        /* Imagem 3 → Fade à esquerda */
        .bg-img:nth-child(3)::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 180px;
            height: 100%;
            background: linear-gradient(to left, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
        }

        /* Overlay escuro para melhorar leitura */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            /* Escurece levemente */
            z-index: 0;
        }

        /* 🔹 CARD DE LOGIN */
        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            padding: 20px;
        }

        .card-login {
            background: rgba(255, 255, 255, 0.95);
            /* Quase opaco */
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.8s ease-in-out;
        }

        .card-login img {
            max-width: 180px;
            margin-bottom: 1.5rem;
        }

        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 1rem;
        }

        .btn-login {
            background: linear-gradient(135deg, #00B298, #009688);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #009688, #00796B);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 178, 152, 0.4);
        }

        .footer {
            text-align: center;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            z-index: 1;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <!-- 🔥 Background com 3 imagens -->
    <div class="background-wrapper">
        <div class="bg-img" style="background-image: url('img/laboratorio_fundo_1.jpg');"></div>
        <div class="bg-img" style="background-image: url('img/laboratorio_fundo_2.jpg');"></div>
        <div class="bg-img" style="background-image: url('img/laboratorio_fundo_3.jpg');"></div>
    </div>

    <!-- Overlay escuro -->
    <div class="overlay"></div>

    <!-- Container Login -->
    <div class="login-container">
        <div class="card-login">
            <img src="img/logo_oficial_braga_mendes_hd.png" alt="Logo Braga Mendes">

            <?php if (!empty($erro)): ?>
                <div class="alert alert-danger p-2 mb-3 text-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form action="index.php?r=auth/authenticate" method="POST">
                <div class="mb-3 text-start">
                    <label for="usuario" class="form-label fw-bold">Usuário</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
                        <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Digite seu usuário" required autofocus>
                    </div>
                </div>

                <div class="mb-4 text-start">
                    <label for="senha" class="form-label fw-bold">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="senha" id="senha" class="form-control" placeholder="Digite sua senha" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">
                    ENTRAR <i class="bi bi-arrow-right-short"></i>
                </button>
            </form>

            <div class="mt-4 pt-3 border-top">
                <small class="text-muted">© <?= date('Y') ?> Braga Mendes Laboratório</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>