<?php
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

$mensaje = "";
$tipo_mensaje = "";

// Procesar el formulario de registro
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $contrasena = $_POST['contrasena'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $ubicacion = trim($_POST['ubicacion']) ?? null;
    $sexo = $_POST['sexo'] ?? null;

    // Validaciones
    if (strlen($nombre) < 2) {
        $mensaje = "El nombre debe tener al menos 2 caracteres";
        $tipo_mensaje = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Email no válido";
        $tipo_mensaje = "error";
    } elseif (strlen($contrasena) < 6) {
        $mensaje = "La contraseña debe tener al menos 6 caracteres";
        $tipo_mensaje = "error";
    } else {
        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $mensaje = "Este email ya está registrado";
            $tipo_mensaje = "error";
        } else {
            // Insertar nuevo usuario con prepared statements
            $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, contrasena, fecha_nacimiento, ubicacion, sexo, foto_perfil) VALUES (?, ?, ?, ?, ?, ?, 'uploads/default.png')");
            $stmt->bind_param("ssssss", $nombre, $email, $contrasena, $fecha_nacimiento, $ubicacion, $sexo);
            
            if ($stmt->execute()) {
                $mensaje = "¡Cuenta creada exitosamente! Ya puedes iniciar sesión";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al crear la cuenta. Inténtalo de nuevo";
                $tipo_mensaje = "error";
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trendly - Crear Cuenta</title>
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
            --success: #22c55e;
            --error: #ef4444;
        }

        body {
            background: var(--dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* Formas geométricas de fondo */
        .background-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--accent), var(--accent-hover));
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 120px;
            height: 120px;
            top: 10%;
            left: 15%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 80px;
            height: 80px;
            top: 70%;
            right: 20%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 100px;
            height: 100px;
            bottom: 20%;
            left: 10%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .register-container {
            background: var(--dark-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            animation: slideIn 0.6s ease-out;
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

        .form-floating {
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control,
        .form-floating > .form-select {
            background: var(--gray-light);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--text-primary);
            padding: 1rem 1rem 1rem 1rem;
            height: 3.5rem;
            transition: all 0.3s ease;
        }

        .form-floating > .form-control:focus,
        .form-floating > .form-select:focus {
            background: var(--gray-light);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-floating > .form-control::placeholder {
            color: transparent;
        }

        .form-floating > label {
            color: var(--text-muted);
            padding: 1rem 1rem;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: var(--accent);
        }

        .form-select option {
            background: var(--gray-light);
            color: var(--text-primary);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--accent), var(--accent-hover));
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-weight: 600;
            width: 100%;
            color: white;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-register.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-register.loading::after {
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

        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            border: none;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
        }

        .link-login {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .link-login a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .link-login a:hover {
            color: var(--accent-hover);
        }

        .link-login p {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        /* Validación visual */
        .form-control.is-valid {
            border-color: var(--success);
        }

        .form-control.is-invalid {
            border-color: var(--error);
        }

        /* Animación de entrada */
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

        /* Responsive */
        @media (max-width: 768px) {
            .register-container {
                margin: 1rem;
                padding: 2rem;
            }

            .brand h1 {
                font-size: 1.8rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
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
    <div class="register-container">
        <!-- Marca -->
        <div class="brand">
            <h1><i class="bi bi-lightning-charge-fill me-2"></i>Trendly</h1>
            <p>Únete a la comunidad</p>
        </div>

        <!-- Alerta de mensaje -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <i class="bi bi-<?php echo $tipo_mensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="" id="registerForm">
            <div class="form-floating">
                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Tu nombre" required minlength="2">
                <label for="nombre">
                    <i class="bi bi-person me-2"></i>Nombre completo
                </label>
            </div>

            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" required>
                <label for="email">
                    <i class="bi bi-envelope me-2"></i>Correo electrónico
                </label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="Contraseña" required minlength="6">
                <label for="contrasena">
                    <i class="bi bi-lock me-2"></i>Contraseña (mín. 6 caracteres)
                </label>
            </div>

            <div class="form-row">
                <div class="form-floating">
                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" placeholder=" ">
                    <label for="fecha_nacimiento">
                        <i class="bi bi-calendar me-2"></i>Fecha de nacimiento
                    </label>
                </div>

                <div class="form-floating">
                    <select class="form-select" id="sexo" name="sexo">
                        <option value="">Seleccionar</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                        <option value="Otro">Otro</option>
                        <option value="Prefiero no decir">Prefiero no decir</option>
                    </select>
                    <label for="sexo">
                        <i class="bi bi-gender-ambiguous me-2"></i>Sexo
                    </label>
                </div>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="ubicacion" name="ubicacion" placeholder="Tu ciudad">
                <label for="ubicacion">
                    <i class="bi bi-geo-alt me-2"></i>Ubicación (opcional)
                </label>
            </div>

            <button type="submit" class="btn btn-register" id="registerBtn">
                <i class="bi bi-person-plus me-2"></i>
                Crear mi cuenta
            </button>
        </form>

        <!-- Enlaces -->
        <div class="link-login">
            <p>¿Ya tienes cuenta?</p>
            <a href="login.php">
                <i class="bi bi-arrow-left me-1"></i>
                Iniciar sesión
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efectos interactivos
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('registerBtn');
            btn.classList.add('loading');
            btn.innerHTML = '<span>Creando cuenta...</span>';
        });

        // Efectos de focus en inputs
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.form-floating').style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.closest('.form-floating').style.transform = 'translateY(0)';
            });
        });

        // Validación en tiempo real
        document.getElementById('email').addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(this.value)) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });

        document.getElementById('contrasena').addEventListener('input', function() {
            if (this.value.length >= 6) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });

        document.getElementById('nombre').addEventListener('input', function() {
            if (this.value.length >= 2) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });

        // Parallax suave en las formas
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;

            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.3;
                const translateX = (x - 0.5) * speed * 15;
                const translateY = (y - 0.5) * speed * 15;
                shape.style.transform = `translate(${translateX}px, ${translateY}px)`;
            });
        });

        // Auto-redirect después de registro exitoso
        <?php if ($tipo_mensaje === 'success'): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>