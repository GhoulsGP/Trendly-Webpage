<?php
// filepath: c:\xampp server\htdocs\Trendly\paginas\login.php

// Iniciar sesión para manejar la sesión del usuario
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Procesar el formulario de inicio de sesión
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $contrasena = $_POST['contrasena']; // Contraseña sin hash

    // Usar prepared statements para seguridad
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND contrasena = ?");
    $stmt->bind_param("ss", $email, $contrasena);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Credenciales válidas
        $row = $result->fetch_assoc();
        $_SESSION['usuario'] = $row['nombre']; // Guardar el nombre del usuario en la sesión
        $_SESSION['usuario_id'] = $row['id']; // Agregar ID para futuras referencias
        
        header("Location: inicio.php"); // Redirigir a la página de inicio
        exit();
    } else {
        // Credenciales inválidas
        $error = "Correo electrónico o contraseña incorrectos.";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trendly - Conecta con el mundo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --shadow-soft: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Formas geométricas animadas de fondo */
        .background-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            top: 10%;
            left: 10%;
            width: 80px;
            height: 80px;
            background: var(--secondary-gradient);
            border-radius: 50%;
            animation-delay: 0s;
        }

        .shape-2 {
            top: 70%;
            right: 15%;
            width: 120px;
            height: 120px;
            background: var(--accent-gradient);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            animation-delay: 2s;
        }

        .shape-3 {
            bottom: 20%;
            left: 20%;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(45deg);
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }

        /* Contenedor principal */
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-medium);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            border-radius: 24px 24px 0 0;
        }

        /* Logo y título */
        .brand {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .brand h1 {
            font-weight: 700;
            font-size: 2.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .brand p {
            color: var(--text-secondary);
            font-weight: 400;
            font-size: 1rem;
            margin: 0;
        }

        /* Formulario */
        .form-floating {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-floating > label {
            color: var(--text-secondary);
            font-weight: 500;
            padding: 1rem 1.25rem;
        }

        /* Botón principal */
        .btn-login {
            width: 100%;
            background: var(--primary-gradient);
            border: none;
            border-radius: 16px;
            padding: 1rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            margin: 1.5rem 0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Enlaces */
        .link-register {
            text-align: center;
            margin-top: 1.5rem;
        }

        .link-register a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 600;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
        }

        .link-register a:hover {
            transform: translateY(-1px);
            display: inline-block;
        }

        /* Alertas */
        .alert {
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #dc2626;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        /* Características destacadas */
        .features {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 2rem;
            opacity: 0.7;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .feature i {
            font-size: 1.1rem;
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .features {
                position: static;
                transform: none;
                justify-content: center;
                margin-top: 2rem;
                flex-wrap: wrap;
            }

            .brand h1 {
                font-size: 2rem;
            }
        }

        /* Efectos de entrada */
        .login-container {
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Loading state */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Formas geométricas de fondo -->
    <div class="background-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Contenedor principal -->
    <div class="login-container">
        <!-- Marca -->
        <div class="brand">
            <h1>Trendly</h1>
            <p>Conecta con el mundo de forma auténtica</p>
        </div>

        <!-- Alerta de error -->
        <?php if ($error): ?>
            <div class="alert" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="" id="loginForm">
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" required>
                <label for="email">
                    <i class="bi bi-envelope me-2"></i>Correo electrónico
                </label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="Contraseña" required>
                <label for="contrasena">
                    <i class="bi bi-lock me-2"></i>Contraseña
                </label>
            </div>

            <button type="submit" class="btn btn-login" id="loginBtn">
                <i class="bi bi-arrow-right me-2"></i>
                Iniciar Sesión
            </button>
        </form>

        <!-- Enlaces -->
        <div class="link-register">
            <p class="mb-2" style="color: var(--text-secondary);">¿Primera vez en Trendly?</p>
            <a href="user_registration.php">
                <i class="bi bi-person-plus me-1"></i>
                Crear cuenta nueva
            </a>
        </div>
    </div>

    <!-- Características destacadas -->
    <div class="features">
        <div class="feature">
            <i class="bi bi-shield-check"></i>
            <span>Seguro</span>
        </div>
        <div class="feature">
            <i class="bi bi-lightning"></i>
            <span>Rápido</span>
        </div>
        <div class="feature">
            <i class="bi bi-heart"></i>
            <span>Auténtico</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efectos interactivos
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<span>Iniciando sesión...</span>';
        });

        // Efectos de focus en inputs
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.form-floating').style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.closest('.form-floating').style.transform = 'translateY(0)';
            });
        });

        // Parallax suave en las formas
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.5;
                const translateX = (x - 0.5) * speed * 20;
                const translateY = (y - 0.5) * speed * 20;
                shape.style.transform = `translate(${translateX}px, ${translateY}px)`;
            });
        });
    </script>
</body>
</html>