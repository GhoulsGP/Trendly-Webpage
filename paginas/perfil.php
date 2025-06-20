<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/perfil.php
// Iniciar sesión
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

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

// Obtener datos del usuario con nombres de columnas correctos
$usuario = $_SESSION['usuario'];
$sql = "SELECT id, nombre, email, biografia, foto_perfil FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: login.php");
    exit();
}

$datos_usuario = $result->fetch_assoc();
$stmt->close();

// Obtener el número de seguidores
$sql_seguidores = "SELECT COUNT(*) AS total FROM seguidores WHERE seguido_id = ?";
$stmt = $conn->prepare($sql_seguidores);
$stmt->bind_param("i", $datos_usuario['id']);
$stmt->execute();
$result_seguidores = $stmt->get_result();
$total_seguidores = $result_seguidores->fetch_assoc()['total'];
$stmt->close();

// Obtener el número de usuarios seguidos
$sql_seguidos = "SELECT COUNT(*) AS total FROM seguidores WHERE (seguidor_id = ? OR (seguidor_id = 0 AND usuario_id = ?))";
$stmt = $conn->prepare($sql_seguidos);
$stmt->bind_param("ii", $datos_usuario['id'], $datos_usuario['id']);
$stmt->execute();
$result_seguidos = $stmt->get_result();
$total_seguidos = $result_seguidos->fetch_assoc()['total'];
$stmt->close();

// Obtener las publicaciones del usuario
$sql_publicaciones = "SELECT p.id, p.contenido, p.imagen, p.fecha, 
                      (SELECT COUNT(*) FROM likes WHERE publicacion_id = p.id) AS likes,
                      (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id) AS comentarios
                      FROM publicaciones p
                      WHERE p.usuario_id = ?
                      ORDER BY p.fecha DESC";
$stmt = $conn->prepare($sql_publicaciones);
$stmt->bind_param("i", $datos_usuario['id']);
$stmt->execute();
$result_publicaciones = $stmt->get_result();
$mis_publicaciones = [];
while ($row = $result_publicaciones->fetch_assoc()) {
    $mis_publicaciones[] = $row;
}
$stmt->close();

// Calcular el total de publicaciones
$total_publicaciones = count($mis_publicaciones);

// Procesar actualización de perfil
$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_perfil'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $biografia = trim($_POST['biografia']);
    
    // Validar nombre
    if (empty($nombre) || strlen($nombre) < 2) {
        $mensaje_error = "El nombre debe tener al menos 2 caracteres.";
    } elseif (strlen($nombre) > 50) {
        $mensaje_error = "El nombre no puede tener más de 50 caracteres.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "El formato del correo electrónico no es válido.";
    } else {
        // Verificar si el nombre ya existe (solo si es diferente al actual)
        if (strtolower($nombre) !== strtolower($datos_usuario['nombre'])) {
            $sql_verificar = "SELECT id FROM usuarios WHERE LOWER(nombre) = LOWER(?) AND id != ?";
            $stmt_verificar = $conn->prepare($sql_verificar);
            $stmt_verificar->bind_param("si", $nombre, $datos_usuario['id']);
            $stmt_verificar->execute();
            $result_verificar = $stmt_verificar->get_result();
            
            if ($result_verificar->num_rows > 0) {
                $mensaje_error = "Este nombre ya está en uso. Por favor, elige otro.";
            }
            $stmt_verificar->close();
        }
        
        if (empty($mensaje_error)) {
            $ruta_foto = $datos_usuario['foto_perfil'];
            
            // Procesar nueva foto de perfil si se ha subido
            if (isset($_FILES['nueva_foto']) && $_FILES['nueva_foto']['error'] == 0) {
                $nombre_archivo = $_FILES['nueva_foto']['name'];
                $tipo_archivo = $_FILES['nueva_foto']['type'];
                $tamano_archivo = $_FILES['nueva_foto']['size'];
                $temp_archivo = $_FILES['nueva_foto']['tmp_name'];
                
                // Verificar el tipo de archivo
                $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($ext), $extensiones_permitidas)) {
                    if ($tamano_archivo <= 5000000) { // 5MB máximo
                        // Crear directorio si no existe
                        $directorio_destino = '../uploads/';
                        if (!file_exists($directorio_destino)) {
                            mkdir($directorio_destino, 0777, true);
                        }
                        
                        // Crear un nombre único para la imagen
                        $nombre_unico = 'uploads/user_' . $datos_usuario['id'] . '_' . time() . '.' . $ext;
                        $ruta_destino = '../' . $nombre_unico;
                        
                        if (move_uploaded_file($temp_archivo, $ruta_destino)) {
                            $ruta_foto = $nombre_unico;
                        } else {
                            $mensaje_error = "Error al subir la imagen. Inténtalo de nuevo.";
                        }
                    } else {
                        $mensaje_error = "La imagen es demasiado grande. Máximo 5MB.";
                    }
                } else {
                    $mensaje_error = "Formato de imagen no válido. Usa JPG, JPEG, PNG o GIF.";
                }
            }
            
            if (empty($mensaje_error)) {
                // Actualizar datos del usuario incluyendo el nombre
                $sql_actualizar = "UPDATE usuarios SET nombre = ?, email = ?, biografia = ?, foto_perfil = ? WHERE id = ?";
                $stmt = $conn->prepare($sql_actualizar);
                $stmt->bind_param("ssssi", $nombre, $email, $biografia, $ruta_foto, $datos_usuario['id']);
                
                if ($stmt->execute()) {
                    $mensaje_exito = "Perfil actualizado correctamente.";
                    
                    // Actualizar los datos del usuario en la sesión actual
                    $datos_usuario['nombre'] = $nombre;
                    $datos_usuario['email'] = $email;
                    $datos_usuario['biografia'] = $biografia;
                    $datos_usuario['foto_perfil'] = $ruta_foto;
                    
                    // ✅ IMPORTANTE: Actualizar también la sesión
                    $_SESSION['usuario'] = $nombre;
                    
                } else {
                    $mensaje_error = "Error al actualizar el perfil. Inténtalo de nuevo.";
                }
                $stmt->close();
            }
        }
    }
}

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// Incluir el encabezado
include '../includes/header.php';
?>

<!-- Estilos específicos para el perfil optimizado para PC -->
<style>
    /* Variables del tema */
    :root {
        --profile-gradient: linear-gradient(135deg, var(--gray-dark) 0%, var(--dark-secondary) 100%);
        --glass-bg: rgba(255, 255, 255, 0.05);
        --glass-border: rgba(255, 255, 255, 0.1);
        --hover-lift: translateY(-12px);
        --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        --card-shadow-hover: 0 20px 60px rgba(0, 0, 0, 0.4);
    }

    /* Contenedor principal optimizado para PC */
    .profile-container {
        min-height: calc(100vh - 140px);
        background: var(--profile-gradient);
        position: relative;
        overflow: hidden;
    }

    .profile-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 300px;
        background: linear-gradient(45deg, var(--gray) 0%, var(--accent) 50%, var(--gray-light) 100%);
        opacity: 0.08;
        z-index: 1;
    }

    .profile-content {
        position: relative;
        z-index: 2;
        padding: 3rem 0;
    }

    /* Layout optimizado para pantallas grandes */
    .profile-layout {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 3rem;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Sidebar del perfil */
    .profile-sidebar {
        position: sticky;
        top: 120px;
        height: fit-content;
    }

    /* Header del perfil rediseñado */
    .profile-header {
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        padding: 2.5rem;
        margin-bottom: 2rem;
        transition: all 0.4s ease;
        box-shadow: var(--card-shadow);
    }

    .profile-header:hover {
        transform: translateY(-8px);
        box-shadow: var(--card-shadow-hover);
        border-color: var(--accent);
    }

    /* Avatar mejorado */
    .profile-avatar-container {
        position: relative;
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .profile-avatar {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid var(--glass-border);
        transition: all 0.4s ease;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    }

    .profile-avatar:hover {
        transform: scale(1.08);
        border-color: var(--accent);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
    }

    .avatar-status {
        position: absolute;
        bottom: 12px;
        right: 12px;
        width: 28px;
        height: 28px;
        background: linear-gradient(45deg, #4ade80, #22c55e);
        border: 4px solid var(--dark-secondary);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.1); }
    }

    /* Información del usuario centrada */
    .profile-info {
        text-align: center;
    }

    .profile-info h1 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        background: linear-gradient(45deg, var(--text-primary), var(--text-secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: -0.02em;
    }

    .profile-username {
        color: var(--text-muted);
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
    }

    .profile-bio {
        color: var(--text-secondary);
        font-size: 1.05rem;
        line-height: 1.7;
        margin-bottom: 2rem;
    }

    /* Estadísticas mejoradas */
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-item {
        text-align: center;
        padding: 1.5rem 1rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .stat-item:hover {
        transform: translateY(-6px);
        background: var(--gray-light);
        border-color: var(--accent);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-primary);
        display: block;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Botones de acción rediseñados */
    .profile-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .action-btn {
        background: var(--gray);
        color: var(--text-primary);
        border: none;
        padding: 1rem 1.5rem;
        border-radius: 16px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        width: 100%;
        text-decoration: none;
    }

    .action-btn:hover {
        background: var(--accent);
        transform: translateY(-3px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
        color: var(--text-primary);
        text-decoration: none;
    }

    .action-btn.primary {
        background: linear-gradient(45deg, var(--accent), var(--accent-hover));
        box-shadow: 0 8px 20px rgba(86, 86, 86, 0.2);
    }

    .action-btn.primary:hover {
        box-shadow: 0 15px 35px rgba(86, 86, 86, 0.3);
        transform: translateY(-4px);
    }

    /* Área de contenido principal */
    .profile-main {
        display: flex;
        flex-direction: column;
    }

    /* Navegación de contenido mejorada */
    .content-nav {
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 0.75rem;
        margin-bottom: 2rem;
        display: flex;
        gap: 0.75rem;
        box-shadow: var(--card-shadow);
    }

    .nav-tab {
        flex: 1;
        background: transparent;
        color: var(--text-secondary);
        border: none;
        padding: 1rem 1.5rem;
        border-radius: 16px;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .nav-tab.active,
    .nav-tab:hover {
        background: var(--gray);
        color: var(--text-primary);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    /* Grid de publicaciones optimizado para PC */
    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
    }

    /* Tarjetas de publicación mejoradas */
    .post-card {
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        overflow: hidden;
        transition: all 0.4s ease;
        position: relative;
        box-shadow: var(--card-shadow);
    }

    .post-card:hover {
        transform: var(--hover-lift);
        box-shadow: var(--card-shadow-hover);
        border-color: var(--accent);
    }

    .post-card-header {
        padding: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .post-author-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .post-author-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .post-author-name {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .post-date {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
    }

    .post-menu {
        background: transparent;
        color: var(--text-muted);
        border: none;
        padding: 0.75rem;
        border-radius: 12px;
        transition: all 0.2s ease;
        font-size: 1.1rem;
    }

    .post-menu:hover {
        background: var(--gray);
        color: var(--text-primary);
        transform: scale(1.1);
    }

    .post-content {
        padding: 0 1.5rem 1.5rem;
        color: var(--text-primary);
        line-height: 1.6;
        font-size: 1rem;
    }

    /* Imágenes de publicación más altas */
    .post-image {
        width: 100%;
        height: 320px; /* Aumentado de 200px a 320px */
        object-fit: cover;
        margin: 1.5rem 0;
        border-radius: 16px;
        transition: all 0.3s ease;
    }

    .post-image:hover {
        transform: scale(1.02);
        border-radius: 20px;
    }

    .post-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--glass-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.02);
    }

    .post-stats {
        display: flex;
        gap: 1.5rem;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .post-interactions {
        display: flex;
        gap: 0.75rem;
    }

    .interaction-btn {
        background: transparent;
        color: var(--text-muted);
        border: none;
        padding: 0.75rem;
        border-radius: 12px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .interaction-btn:hover {
        background: var(--gray);
        color: var(--text-primary);
        transform: scale(1.1);
    }

    .interaction-btn.liked {
        color: #ff6b6b !important;
    }

    .interaction-btn.liked:hover {
        color: #ff5252 !important;
    }

    /* Formulario de nueva publicación mejorado */
    .new-post-form {
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 2rem;
        margin-bottom: 2rem;
        transition: all 0.3s ease;
        box-shadow: var(--card-shadow);
    }

    .new-post-form:hover {
        border-color: var(--accent);
        box-shadow: var(--card-shadow-hover);
    }

    .post-input-area {
        background: var(--gray-light);
        border: 1px solid var(--gray);
        border-radius: 20px;
        padding: 1.5rem;
        color: var(--text-primary);
        resize: none;
        min-height: 120px;
        transition: all 0.3s ease;
        font-size: 1rem;
        line-height: 1.5;
    }

    .post-input-area:focus {
        outline: none;
        border-color: var(--accent);
        background: var(--gray);
        box-shadow: 0 0 0 4px rgba(86, 86, 86, 0.1);
    }

    .post-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
    }

    .post-tools {
        display: flex;
        gap: 1rem;
    }

    .post-tool {
        width: 48px;
        height: 48px;
        background: var(--gray);
        color: var(--text-secondary);
        border: none;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 1.1rem;
    }

    .post-tool:hover {
        background: var(--accent);
        color: var(--text-primary);
        transform: scale(1.15);
    }

    /* Estado vacío mejorado */
    .empty-state {
        text-align: center;
        padding: 5rem 3rem;
        background: var(--glass-bg);
        backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        border-radius: 28px;
        margin: 2rem 0;
        box-shadow: var(--card-shadow);
    }

    .empty-icon {
        font-size: 5rem;
        color: var(--accent);
        margin-bottom: 2rem;
        opacity: 0.8;
    }

    .empty-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .empty-description {
        color: var(--text-muted);
        font-size: 1.1rem;
        margin-bottom: 2.5rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.6;
    }

    /* Estilos adicionales para modales */
    .follower-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        gap: 1rem;
        border-radius: 16px;
        transition: all 0.3s ease;
        margin-bottom: 0.5rem;
    }

    .follower-item:hover {
        background: var(--glass-bg);
    }

    .follower-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .follower-info {
        flex: 1;
    }

    .follower-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .follower-username {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .comment-item {
        display: flex;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--glass-border);
    }

    .comment-item:last-child {
        border-bottom: none;
    }

    .comment-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .comment-content {
        flex: 1;
    }

    .comment-author {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .comment-text {
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
        line-height: 1.5;
    }

    .comment-date {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    /* Estilos para validación de formularios */
    .form-control.is-valid {
        border-color: #22c55e !important;
        box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.25) !important;
    }

    .form-control.is-invalid {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.25) !important;
    }

    .error-message {
        color: #dc2626;
        font-size: 0.85rem;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .error-message::before {
        content: "⚠️";
        font-size: 0.8rem;
    }

    /* Responsive para tablet */
    @media (max-width: 1200px) {
        .profile-layout {
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }
        
        .posts-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Responsive para móvil */
    @media (max-width: 768px) {
        .profile-layout {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .profile-sidebar {
            position: static;
        }
        
        .profile-stats {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }
        
        .profile-actions {
            flex-direction: column;
        }
        
        .content-nav {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .post-image {
            height: 250px;
        }
        
        .profile-info h1 {
            font-size: 2rem;
        }
    }

    /* Animaciones mejoradas */
    .fade-in {
        animation: fadeIn 0.8s ease-out forwards;
    }

    .slide-up {
        animation: slideUp 0.8s ease-out forwards;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="profile-container">
    <div class="profile-content">
        <div class="container-fluid px-4">
            <div class="profile-layout">
                <!-- Sidebar del perfil -->
                <div class="profile-sidebar">
                    <div class="profile-header fade-in">
                        <div class="profile-avatar-container">
                            <img src="../<?php echo $datos_usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                 alt="Foto de perfil" 
                                 class="profile-avatar">
                            <div class="avatar-status"></div>
                        </div>
                        
                        <div class="profile-info">
                            <h1><?php echo htmlspecialchars($datos_usuario['nombre']); ?></h1>
                            <div class="profile-username">@<?php echo strtolower(str_replace(' ', '', $datos_usuario['nombre'])); ?></div>
                            <div class="profile-bio">
                                <?php echo !empty($datos_usuario['biografia']) ? nl2br(htmlspecialchars($datos_usuario['biografia'])) : 'Creador de contenido digital. Explorando nuevas experiencias y conectando con el mundo.'; ?>
                            </div>
                            
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $total_publicaciones; ?></span>
                                    <div class="stat-label">Posts</div>
                                </div>
                                <div class="stat-item" onclick="mostrarSeguidores()" style="cursor: pointer;">
                                    <span class="stat-number"><?php echo $total_seguidores; ?></span>
                                    <div class="stat-label">Seguidores</div>
                                </div>
                                <div class="stat-item" onclick="mostrarSeguidos()" style="cursor: pointer;">
                                    <span class="stat-number"><?php echo $total_seguidos; ?></span>
                                    <div class="stat-label">Siguiendo</div>
                                </div>
                            </div>
                            
                            <div class="profile-actions">
                                <button class="action-btn primary" data-bs-toggle="modal" data-bs-target="#editarPerfilModal">
                                    <i class="bi bi-pencil-square"></i>
                                    Editar perfil
                                </button>
                                <button class="action-btn" onclick="shareProfile()">
                                    <i class="bi bi-share"></i>
                                    Compartir perfil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido principal -->
                <div class="profile-main">
                    <!-- Navegación de contenido -->
                    <div class="content-nav slide-up">
                        <button class="nav-tab active" data-tab="posts">
                            <i class="bi bi-grid-3x3"></i>
                            Publicaciones
                        </button>
                        <button class="nav-tab" data-tab="create">
                            <i class="bi bi-plus-circle"></i>
                            Crear contenido
                        </button>
                        <button class="nav-tab" data-tab="analytics">
                            <i class="bi bi-bar-chart-line"></i>
                            Analíticas
                        </button>
                    </div>

                    <!-- Contenido de publicaciones -->
                    <div id="posts-content" class="tab-content">
                        <?php if (empty($mis_publicaciones)): ?>
                            <div class="empty-state slide-up">
                                <i class="bi bi-camera-reels empty-icon"></i>
                                <h3 class="empty-title">Tu historia comienza aquí</h3>
                                <p class="empty-description">
                                    Comparte tus momentos únicos y conecta con tu audiencia. 
                                    Cada publicación es una oportunidad de inspirar.
                                </p>
                                <button class="action-btn primary" data-tab="create" onclick="switchTab(this)">
                                    <i class="bi bi-plus-lg"></i>
                                    Crear primera publicación
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="posts-grid">
                                <?php foreach ($mis_publicaciones as $index => $publicacion): ?>
                                    <div class="post-card slide-up" style="animation-delay: <?php echo $index * 0.1; ?>s" data-post-id="<?php echo $publicacion['id']; ?>">
                                        <div class="post-card-header">
                                            <div class="post-author-info">
                                                <img src="../<?php echo $datos_usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                                     alt="Perfil" 
                                                     class="post-author-avatar">
                                                <div>
                                                    <div class="post-author-name"><?php echo htmlspecialchars($datos_usuario['nombre']); ?></div>
                                                    <div class="post-date"><?php echo date('d M Y · H:i', strtotime($publicacion['fecha'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="post-menu" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#"><i class="bi bi-pencil me-2"></i>Editar publicación</a></li>
                                                    <li><a class="dropdown-item" href="#"><i class="bi bi-eye me-2"></i>Ver estadísticas</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="eliminar_publicacion.php?id=<?php echo $publicacion['id']; ?>" onclick="return confirm('¿Estás seguro de que quieres eliminar esta publicación?')"><i class="bi bi-trash me-2"></i>Eliminar</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="post-content">
                                            <?php echo nl2br(htmlspecialchars($publicacion['contenido'])); ?>
                                            
                                            <?php if (!empty($publicacion['imagen'])): ?>
                                                <img src="../<?php echo $publicacion['imagen']; ?>" 
                                                     alt="Imagen de la publicación" 
                                                     class="post-image">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="post-footer">
                                            <div class="post-stats">
                                                <span><i class="bi bi-heart-fill me-1"></i><span class="likes-count"><?php echo $publicacion['likes']; ?></span> me gusta</span>
                                                <span><i class="bi bi-chat-fill me-1"></i><span class="comments-count"><?php echo $publicacion['comentarios']; ?></span> comentarios</span>
                                            </div>
                                            <div class="post-interactions">
                                                <button class="interaction-btn like-btn" 
                                                        data-post-id="<?php echo $publicacion['id']; ?>"
                                                        data-user-id="<?php echo $datos_usuario['id']; ?>">
                                                    <i class="bi bi-heart"></i>
                                                </button>
                                                <button class="interaction-btn comment-btn" 
                                                        onclick="mostrarComentarios(<?php echo $publicacion['id']; ?>)">
                                                    <i class="bi bi-chat"></i>
                                                </button>
                                                <button class="interaction-btn">
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Contenido de crear publicación -->
                    <div id="create-content" class="tab-content" style="display: none;">
                        <div class="new-post-form slide-up">
                            <h3 style="color: var(--text-primary); margin-bottom: 2rem; font-size: 1.75rem; font-weight: 700;">Crear nueva publicación</h3>
                            <form action="crear_publicacion.php" method="POST" enctype="multipart/form-data">
                                <div class="d-flex gap-3 mb-4">
                                    <img src="../<?php echo $datos_usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                         alt="Tu perfil" 
                                         class="post-author-avatar">
                                    <div class="flex-grow-1">
                                        <textarea name="contenido" 
                                                  class="form-control post-input-area" 
                                                  placeholder="¿Qué quieres compartir hoy? Cuenta tu historia..." 
                                                  required></textarea>
                                    </div>
                                </div>
                                
                                <div class="post-actions">
                                    <div class="post-tools">
                                        <label for="imagen" class="post-tool" title="Añadir imagen">
                                            <i class="bi bi-image"></i>
                                        </label>
                                        <input type="file" id="imagen" name="imagen" class="d-none" accept="image/*">
                                        <button type="button" class="post-tool" title="Añadir emoji">
                                            <i class="bi bi-emoji-smile"></i>
                                        </button>
                                        <button type="button" class="post-tool" title="Programar publicación">
                                            <i class="bi bi-clock"></i>
                                        </button>
                                        <button type="button" class="post-tool" title="Configuración">
                                            <i class="bi bi-gear"></i>
                                        </button>
                                    </div>
                                    <button type="submit" class="action-btn primary">
                                        <i class="bi bi-send-fill"></i>
                                        Publicar ahora
                                    </button>
                                </div>
                                <div id="image-preview" class="mt-3"></div>
                            </form>
                        </div>
                    </div>

                    <!-- Contenido de estadísticas -->
                    <div id="analytics-content" class="tab-content" style="display: none;">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="post-card slide-up">
                                    <div class="post-card-header">
                                        <h5 style="color: var(--text-primary); margin: 0; font-weight: 700;">Alcance total</h5>
                                        <i class="bi bi-graph-up text-success" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <div class="post-content">
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="stat-item">
                                                <span class="stat-number"><?php echo number_format($total_publicaciones * 127); ?></span>
                                                <div class="stat-label">Vistas totales</div>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number"><?php echo number_format($total_publicaciones * 23); ?></span>
                                                <div class="stat-label">Interacciones</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="post-card slide-up">
                                    <div class="post-card-header">
                                        <h5 style="color: var(--text-primary); margin: 0; font-weight: 700;">Crecimiento</h5>
                                        <i class="bi bi-trending-up text-success" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <div class="post-content">
                                        <div class="d-flex align-items-center gap-4">
                                            <div class="stat-item">
                                                <span class="stat-number text-success">+<?php echo rand(12, 28); ?>%</span>
                                                <div class="stat-label">Esta semana</div>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number text-success">+<?php echo rand(35, 67); ?>%</span>
                                                <div class="stat-label">Este mes</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="post-card slide-up">
                                    <div class="post-card-header">
                                        <h5 style="color: var(--text-primary); margin: 0; font-weight: 700;">Rendimiento por publicación</h5>
                                        <i class="bi bi-bar-chart-line text-info" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <div class="post-content">
                                        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Promedio de interacciones por tipo de contenido</p>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <div class="stat-item">
                                                    <span class="stat-number">87%</span>
                                                    <div class="stat-label">Con imagen</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-item">
                                                    <span class="stat-number">64%</span>
                                                    <div class="stat-label">Solo texto</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-item">
                                                    <span class="stat-number">42</span>
                                                    <div class="stat-label">Promedio likes</div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="stat-item">
                                                    <span class="stat-number">13</span>
                                                    <div class="stat-label">Promedio comentarios</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para mostrar seguidos -->
<div class="modal fade" id="seguidosModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--dark-secondary); border: 1px solid var(--glass-border); border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 2rem;">
                <h5 class="modal-title" style="color: var(--text-primary); font-size: 1.5rem; font-weight: 700;">Siguiendo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem 2rem;" id="seguidos-list">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para mostrar seguidores -->
<div class="modal fade" id="seguidoresModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--dark-secondary); border: 1px solid var(--glass-border); border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 2rem;">
                <h5 class="modal-title" style="color: var(--text-primary); font-size: 1.5rem; font-weight: 700;">Seguidores</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem 2rem;" id="seguidores-list">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<!-- Modal para mostrar comentarios -->
<div class="modal fade" id="comentariosModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--dark-secondary); border: 1px solid var(--glass-border); border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 2rem;">
                <h5 class="modal-title" style="color: var(--text-primary); font-size: 1.5rem; font-weight: 700;">Comentarios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem; max-height: 60vh; overflow-y: auto;" id="comentarios-content">
                <!-- Contenido dinámico -->
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 1.5rem 2rem;">
                <form id="comment-form" style="width: 100%; display: flex; gap: 1rem;">
                    <input type="hidden" id="comment-publicacion-id">
                    <textarea class="form-control post-input-area" 
                              id="comment-text" 
                              placeholder="Escribe un comentario..." 
                              rows="1" 
                              style="resize: none; min-height: 45px;"
                              required></textarea>
                    <button type="submit" class="action-btn primary" style="white-space: nowrap;">
                        <i class="bi bi-send-fill"></i>
                        Enviar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar perfil -->
<div class="modal fade" id="editarPerfilModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--dark-secondary); border: 1px solid var(--glass-border); border-radius: 24px;">
            <div class="modal-header" style="border-bottom: 1px solid var(--glass-border); padding: 2rem;">
                <h5 class="modal-title" style="color: var(--text-primary); font-size: 1.5rem; font-weight: 700;">Editar perfil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body" style="padding: 2rem;">
                    <div class="text-center mb-4">
                        <div class="profile-avatar-container">
                            <img src="../<?php echo $datos_usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                 alt="Foto de perfil" 
                                 class="profile-avatar"
                                 style="width: 100px; height: 100px;">
                        </div>
                        <label for="nuevaFoto" class="action-btn mt-3" style="cursor: pointer; display: inline-flex;">
                            <i class="bi bi-camera"></i>
                            Cambiar foto de perfil
                        </label>
                        <input type="file" id="nuevaFoto" name="nueva_foto" class="d-none" accept="image/*">
                    </div>
                    
                    <!-- ✅ NUEVO CAMPO PARA EL NOMBRE -->
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600;">Nombre completo</label>
                        <input type="text" class="form-control post-input-area" name="nombre" 
                               value="<?php echo htmlspecialchars($datos_usuario['nombre']); ?>" 
                               required
                               minlength="2"
                               maxlength="50"
                               placeholder="Tu nombre completo">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600;">Correo electrónico</label>
                        <input type="email" class="form-control post-input-area" name="email" 
                               value="<?php echo htmlspecialchars($datos_usuario['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--text-secondary); font-weight: 600;">Biografía</label>
                        <textarea class="form-control post-input-area" name="biografia" rows="4" 
                                  placeholder="Cuéntanos sobre ti..."><?php echo htmlspecialchars($datos_usuario['biografia'] ?: ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--glass-border); padding: 2rem;">
                    <button type="button" class="action-btn" data-bs-dismiss="modal" style="margin-right: 1rem;">Cancelar</button>
                    <button type="submit" name="actualizar_perfil" class="action-btn primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Variables globales
const userId = <?php echo $datos_usuario['id']; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar botones de like
    initializeLikeButtons();
    
    // Verificar estado inicial de likes
    checkInitialLikeStatus();
});

// Navegación entre pestañas
function switchTab(button) {
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    button.classList.add('active');
    
    const tabName = button.getAttribute('data-tab');
    document.getElementById(tabName + '-content').style.display = 'block';
}

document.querySelectorAll('.nav-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        switchTab(this);
    });
});

// Funcionalidad para mostrar seguidores
function mostrarSeguidores() {
    fetch('get_followers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            usuario_id: userId,
            tipo: 'seguidores'
        })
    })
    .then(response => response.json())
    .then(data => {
        const list = document.getElementById('seguidores-list');
        if (data.success && data.usuarios.length > 0) {
            list.innerHTML = data.usuarios.map(usuario => `
                <div class="follower-item">
                    <img src="../${usuario.foto_perfil || 'uploads/default.png'}" 
                         alt="Avatar" 
                         class="follower-avatar">
                    <div class="follower-info">
                        <div class="follower-name">${usuario.nombre}</div>
                        <div class="follower-username">@${usuario.nombre.toLowerCase().replace(/\s+/g, '')}</div>
                    </div>
                    <a href="perfil_usuario.php?id=${usuario.id}" class="action-btn">
                        <i class="bi bi-person"></i>
                        Ver perfil
                    </a>
                </div>
            `).join('');
        } else {
            list.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-people" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">Aún no tienes seguidores</p>
                </div>
            `;
        }
        new bootstrap.Modal(document.getElementById('seguidoresModal')).show();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al cargar seguidores', 'error');
    });
}

function mostrarSeguidos() {
    fetch('get_followers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            usuario_id: userId,
            tipo: 'seguidos'
        })
    })
    .then(response => response.json())
    .then(data => {
        const list = document.getElementById('seguidos-list');
        if (data.success && data.usuarios.length > 0) {
            list.innerHTML = data.usuarios.map(usuario => `
                <div class="follower-item">
                    <img src="../${usuario.foto_perfil || 'uploads/default.png'}" 
                         alt="Avatar" 
                         class="follower-avatar">
                    <div class="follower-info">
                        <div class="follower-name">${usuario.nombre}</div>
                        <div class="follower-username">@${usuario.nombre.toLowerCase().replace(/\s+/g, '')}</div>
                    </div>
                    <a href="perfil_usuario.php?id=${usuario.id}" class="action-btn">
                        <i class="bi bi-person"></i>
                        Ver perfil
                    </a>
                </div>
            `).join('');
        } else {
            list.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-person-plus" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">No sigues a nadie aún</p>
                </div>
            `;
        }
        new bootstrap.Modal(document.getElementById('seguidosModal')).show();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al cargar seguidos', 'error');
    });
}

// Funcionalidad de likes
function initializeLikeButtons() {
    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const userId = this.getAttribute('data-user-id');
            toggleLike(postId, userId, this);
        });
    });
}

function toggleLike(postId, userId, button) {
    button.disabled = true;
    
    fetch('toggle_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            publicacion_id: postId,
            usuario_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar el botón
            const heartIcon = button.querySelector('i');
            if (data.liked) {
                button.classList.add('liked');
                heartIcon.className = 'bi bi-heart-fill';
                button.style.color = '#ff6b6b';
            } else {
                button.classList.remove('liked');
                heartIcon.className = 'bi bi-heart';
                button.style.color = '';
            }
            
            // Actualizar el contador
            const postCard = button.closest('.post-card');
            const likesCount = postCard.querySelector('.likes-count');
            likesCount.textContent = data.total_likes;
            
            showToast(data.liked ? 'Te gusta esta publicación' : 'Ya no te gusta', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al procesar like', 'error');
    })
    .finally(() => {
        button.disabled = false;
    });
}

function checkInitialLikeStatus() {
    document.querySelectorAll('.like-btn').forEach(button => {
        const postId = button.getAttribute('data-post-id');
        const userId = button.getAttribute('data-user-id');
        
        fetch('check_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                publicacion_id: postId,
                usuario_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.liked) {
                button.classList.add('liked');
                button.querySelector('i').className = 'bi bi-heart-fill';
                button.style.color = '#ff6b6b';
            }
        })
        .catch(error => console.error('Error checking like status:', error));
    });
}

// Funcionalidad de comentarios
function mostrarComentarios(publicacionId) {
    document.getElementById('comment-publicacion-id').value = publicacionId;
    
    fetch('get_comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            publicacion_id: publicacionId
        })
    })
    .then(response => response.json())
    .then(data => {
        const content = document.getElementById('comentarios-content');
        if (data.success && data.comentarios.length > 0) {
            content.innerHTML = data.comentarios.map(comentario => `
                <div class="comment-item">
                    <img src="../${comentario.foto_perfil || 'uploads/default.png'}" 
                         alt="Avatar" 
                         class="comment-avatar">
                    <div class="comment-content">
                        <div class="comment-author">${comentario.nombre_usuario}</div>
                        <div class="comment-text">${comentario.contenido}</div>
                        <div class="comment-date">${formatDate(comentario.fecha)}</div>
                    </div>
                </div>
            `).join('');
        } else {
            content.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-chat" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">Sé el primero en comentar</p>
                </div>
            `;
        }
        new bootstrap.Modal(document.getElementById('comentariosModal')).show();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al cargar comentarios', 'error');
    });
}

// Enviar comentario
document.getElementById('comment-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const publicacionId = document.getElementById('comment-publicacion-id').value;
    const comentario = document.getElementById('comment-text').value.trim();
    
    if (!comentario) return;
    
    fetch('add_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            publicacion_id: publicacionId,
            usuario_id: userId,
            contenido: comentario
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('comment-text').value = '';
            mostrarComentarios(publicacionId); // Recargar comentarios
            
            // Actualizar contador en la publicación
            const postCard = document.querySelector(`[data-post-id="${publicacionId}"]`);
            if (postCard) {
                const commentsCount = postCard.querySelector('.comments-count');
                const currentCount = parseInt(commentsCount.textContent);
                commentsCount.textContent = currentCount + 1;
            }
            
            showToast('Comentario agregado', 'success');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al enviar comentario', 'error');
    });
});

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'Ahora mismo';
    if (minutes < 60) return `${minutes}m`;
    if (hours < 24) return `${hours}h`;
    if (days < 7) return `${days}d`;
    
    return date.toLocaleDateString();
}

// Previsualización de imagen mejorada
document.getElementById('imagen').addEventListener('change', function() {
    const file = this.files[0];
    const preview = document.getElementById('image-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div style="position: relative; display: inline-block; margin-top: 1rem;">
                    <img src="${e.target.result}" style="max-width: 100%; max-height: 300px; border-radius: 16px; box-shadow: var(--card-shadow);">
                    <button type="button" onclick="removeImage()" style="position: absolute; top: 0.75rem; right: 0.75rem; background: var(--dark-secondary); color: var(--text-primary); border: none; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; box-shadow: var(--card-shadow);">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

function removeImage() {
    document.getElementById('imagen').value = '';
    document.getElementById('image-preview').innerHTML = '';
}

// Función para compartir perfil
function shareProfile() {
    if (navigator.share) {
        navigator.share({
            title: 'Perfil de <?php echo htmlspecialchars($datos_usuario['nombre']); ?> en Trendly',
            text: 'Descubre el contenido increíble de <?php echo htmlspecialchars($datos_usuario['nombre']); ?>',
            url: window.location.href
        });
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            showToast('Enlace copiado al portapapeles', 'success');
        });
    }
}

// Función para mostrar toasts
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
    toast.style.background = 'var(--dark-secondary)';
    toast.style.color = 'var(--text-primary)';
    toast.style.border = '1px solid var(--glass-border)';
    toast.style.borderRadius = '16px';
    toast.style.zIndex = '9999';
    
    const iconClass = type === 'success' ? 'bi-check-circle text-success' : 
                     type === 'error' ? 'bi-exclamation-circle text-danger' : 
                     'bi-info-circle text-info';
    
    toast.innerHTML = `
        <div class="toast-body" style="padding: 1rem 1.5rem;">
            <i class="bi ${iconClass} me-2"></i>
            ${message}
        </div>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 4000);
}

// Animaciones de entrada
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    });
    
    document.querySelectorAll('.slide-up').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});

// Mostrar mensajes de estado
<?php if (!empty($mensaje_exito)): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => showToast('<?php echo $mensaje_exito; ?>', 'success'), 500);
});
<?php endif; ?>

<?php if (!empty($mensaje_error)): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => showToast('<?php echo $mensaje_error; ?>', 'error'), 500);
});
<?php endif; ?>

// Validación en tiempo real para el nombre
document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.querySelector('input[name="nombre"]');
    
    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            const valor = this.value.trim();
            const submitBtn = document.querySelector('button[name="actualizar_perfil"]');
            
            // Remover clases de validación anteriores
            this.classList.remove('is-valid', 'is-invalid');
            
            if (valor.length < 2) {
                this.classList.add('is-invalid');
                submitBtn.disabled = true;
                showInputError(this, 'El nombre debe tener al menos 2 caracteres');
            } else if (valor.length > 50) {
                this.classList.add('is-invalid');
                submitBtn.disabled = true;
                showInputError(this, 'El nombre no puede tener más de 50 caracteres');
            } else {
                this.classList.add('is-valid');
                submitBtn.disabled = false;
                hideInputError(this);
            }
        });
    }
});

function showInputError(input, message) {
    // Remover mensaje de error anterior
    const existingError = input.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Crear nuevo mensaje de error
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#dc2626';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '0.5rem';
    errorDiv.textContent = message;
    
    input.parentNode.appendChild(errorDiv);
}

function hideInputError(input) {
    const existingError = input.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
}
</script>

<?php include '../includes/footer.php'; ?>