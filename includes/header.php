<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Creamos una conexión única que se puede reutilizar
if (!isset($conn) || $conn->connect_error) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "trendly";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }
    
    // Flag para controlar si header.php creó la conexión
    $header_created_conn = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trendly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/modal-fix.css">
    <link rel="stylesheet" href="../css/loading.css">
    
    <!-- IMPORTANTE: Cargar jQuery antes que Bootstrap -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Estilos para todo el sitio - Tema Oscuro Minimalista -->
    <style>
    :root {
        --dark: #121212;
        --dark-secondary: #1e1e1e;
        --gray-light: #2d2d2d;
        --gray: #333333;
        --gray-dark: #1a1a1a;
        --text-primary: #ffffff;
        --text-secondary: #b0b0b0;
        --text-muted: #808080;
        --accent: #565656;
        --accent-hover: #6e6e6e;
        --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        color: var(--text-primary);
        background-color: var(--dark);
        position: relative;
        min-height: 100vh;
    }
    
    /* Navbar */
    .navbar {
        background-color: var(--dark-secondary);
        box-shadow: var(--shadow);
        transition: all 0.3s ease;
        z-index: 1030;
        padding: 12px 0;
    }
    
    .navbar-scrolled {
        padding-top: 8px;
        padding-bottom: 8px;
    }
    
    .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        color: var(--text-primary);
    }
    
    .nav-link {
        color: var(--text-secondary);
        font-weight: 500;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
    }
    
    .nav-link:hover {
        color: var(--text-primary) !important;
        background-color: var(--gray);
    }
    
    .nav-link.active {
        color: var(--text-primary) !important;
        background-color: var(--gray);
    }
    
    .navbar .dropdown-menu {
        background-color: var(--dark-secondary);
        border: 1px solid var(--gray);
        box-shadow: var(--shadow);
        border-radius: var(--radius-md);
    }
    
    .dropdown-item {
        color: var(--text-secondary);
    }
    
    .dropdown-item:hover {
        background-color: var(--gray);
        color: var(--text-primary);
    }
    
    /* Tarjetas */
    .card {
        background-color: var(--dark-secondary);
        border: none;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow);
        transition: transform 0.3s ease;
        margin-bottom: 1.5rem;
    }
    
    .card:hover {
        transform: translateY(-3px);
    }
    
    .card-header {
        background-color: transparent;
        border-bottom: 1px solid var(--gray);
        padding: 1rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    /* Botones */
    .btn {
        font-weight: 500;
        padding: 0.5rem 1.5rem;
        border-radius: var(--radius-sm);
        transition: all 0.2s ease;
    }
    
    .btn-primary {
        background-color: var(--gray);
        border: none;
        color: var(--text-primary);
    }
    
    .btn-primary:hover {
        background-color: var(--accent-hover);
        transform: translateY(-1px);
    }
    
    .form-control {
        background-color: var(--gray-light);
        border: 1px solid var(--gray);
        border-radius: var(--radius-sm);
        color: var(--text-primary);
    }
    
    .form-control:focus {
        background-color: var(--gray-light);
        border-color: var(--accent);
        box-shadow: none;
        color: var(--text-primary);
    }
    
    /* Imágenes */
    .img-publicacion, .img-thumbnail {
        border-radius: var(--radius-sm);
        object-fit: cover;
    }
    
    /* Modales */
    .modal {
        z-index: 9999 !important;
        padding-right: 0 !important;
    }
    
    .modal-backdrop {
        z-index: 9998 !important;
    }
    
    .modal-dialog {
        margin: 1.75rem auto;
        max-width: 600px;
    }
    
    .modal-content {
        background-color: var(--dark-secondary);
        border-radius: var(--radius-md);
        border: none;
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    
    .modal-header {
        border-bottom: 1px solid var(--gray);
        padding: 1.25rem 1.5rem;
        background-color: var(--dark-secondary);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        border-top: 1px solid var(--gray);
        padding: 1.25rem 1.5rem;
        background-color: var(--dark-secondary);
    }
    
    /* Evitar que el fondo se mueva al abrir un modal */
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }
    
    /* Scrollbar personalizado */
    ::-webkit-scrollbar {
        width: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: var(--dark);
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--gray);
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--accent);
    }
    
    /* Links */
    a {
        color: var(--text-secondary);
        text-decoration: none;
        transition: color 0.2s ease;
    }
    
    a:hover {
        color: var(--text-primary);
    }
    
    /* Toast */
    .toast {
        background-color: var(--dark-secondary);
        color: var(--text-primary);
        border: 1px solid var(--gray);
    }
    
    .notification-badge {
        font-size: 0.65rem !important;
        min-width: 18px;
        height: 18px;
        background: #ff6b6b !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    </style>
</head>
<body>
    <!-- Barra de navegación minimalista -->
    <nav class="navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="inicio.php">T</a>
            <div class="d-flex align-items-center">
                <ul class="navbar-nav d-flex flex-row me-3">
                    <li class="nav-item me-3">
                        <a class="nav-link" href="inicio.php">
                            <i class="bi bi-house-fill"></i>
                        </a>
                    </li>
                    <li class="nav-item me-3">
                        <a class="nav-link" href="explorar.php">
                            <i class="bi bi-compass"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat"></i>
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="notificaciones.php">
                            <i class="bi bi-bell"></i>
                        </a>
                    </li>
                </ul>
                
                <?php if (isset($_SESSION['usuario'])): ?>
                    <div class="dropdown">
                        <?php 
                        $usuario_actual = $_SESSION['usuario'];
                        $result = $conn->query("SELECT foto_perfil FROM usuarios WHERE nombre = '$usuario_actual'");
                        $foto_perfil = $result->fetch_assoc()['foto_perfil'] ?? 'uploads/default.png';
                        ?>
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle no-loading" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="../<?php echo $foto_perfil; ?>" 
                                 alt="Perfil" 
                                 class="rounded-circle"
                                 style="width: 34px; height: 34px; object-fit: cover;">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">

<?php
// ✅ AGREGAR CONTADOR DE NOTIFICACIONES
if (isset($_SESSION['usuario']) && $conn) {
    $usuario = $_SESSION['usuario'];
    $sql_usuario_header = "SELECT id FROM usuarios WHERE nombre = ?";
    $stmt_header = $conn->prepare($sql_usuario_header);
    $stmt_header->bind_param("s", $usuario);
    $stmt_header->execute();
    $result_header = $stmt_header->get_result();
    $usuario_header_data = $result_header->fetch_assoc();
    $usuario_header_id = $usuario_header_data['id'];
    $stmt_header->close();
    
    // Contar notificaciones no leídas
    $sql_notif_count = "SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = FALSE";
    $stmt_notif = $conn->prepare($sql_notif_count);
    $stmt_notif->bind_param("i", $usuario_header_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    $notif_data = $result_notif->fetch_assoc();
    $notificaciones_no_leidas = $notif_data['total'];
    $stmt_notif->close();
}
?>

<script>
// Función para agregar la clase 'navbar-scrolled' al hacer scroll
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', function() {
        if (window.scrollY > 10) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }
    });
    
    // Detectar la página actual y marcar el enlace correspondiente como activo
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
    
    // Arreglos para modal
    $(document).on('show.bs.modal', '.modal', function () {
        const zIndex = 9999;
        $(this).css('z-index', zIndex);
        setTimeout(function() {
            $('.modal-backdrop').not('.modal-stack').css('z-index', zIndex - 1).addClass('modal-stack');
        }, 0);
    });
});
</script>
