<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/login.php
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
    <title>Trendly - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --dark: #121212;
            --dark-secondary: #1e1e1e;
            --gray-light: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #e0e0e0;
            --text-muted: #b0b0b0;
            --glass-border: rgba(255, 255, 255, 0.1);
            --accent: #667eea;
            --accent-hover: #5a67d8;
        }

        body {
            background: var(--dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: var(--dark-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }

        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand h1 {
            color: var(--text-primary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .brand p {
            color: var(--text-secondary);
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            background: var(--gray-light);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: var(--gray-light);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .register-link a:hover {
            color: var(--accent-hover);
        }

        .register-link p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }

        /* Animación de entrada */
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Marca -->
        <div class="brand">
            <h1><i class="bi bi-lightning-charge-fill me-2"></i>Trendly</h1>
            <p>Bienvenido de vuelta</p>
        </div>

        <!-- Alerta de error -->
        <?php if ($error): ?>
            <div class="alert" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="email" class="form-label">
                    <i class="bi bi-envelope me-2"></i>Correo electrónico
                </label>
                <input type="email" 
                       class="form-control" 
                       id="email" 
                       name="email" 
                       placeholder="tu@email.com" 
                       required>
            </div>

            <div class="form-group">
                <label for="contrasena" class="form-label">
                    <i class="bi bi-lock me-2"></i>Contraseña
                </label>
                <input type="password" 
                       class="form-control" 
                       id="contrasena" 
                       name="contrasena" 
                       placeholder="Tu contraseña" 
                       required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Iniciar Sesión
            </button>
        </form>

        <!-- Enlaces -->
        <div class="register-link">
            <p>¿No tienes una cuenta?</p>
            <a href="user_registration.php">
                <i class="bi bi-person-plus me-1"></i>
                Crear cuenta nueva
            </a>
        </div>
    </div>

    <script src="../js/page-specific-loading.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>