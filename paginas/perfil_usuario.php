<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/perfil_usuario.php
// DEBUG TEMPORAL - Habilitar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "trendly";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Iniciar sesión
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar si se especificó el ID del usuario
if (!isset($_GET['id'])) {
    header("Location: inicio.php");
    exit();
}

$usuario_id = (int)$_GET['id'];

if ($usuario_id <= 0) {
    header("Location: inicio.php");
    exit();
}

// Obtener datos del usuario del perfil
$sql_usuario = "SELECT id, nombre, fecha_registro, biografia, foto_perfil, email FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result_usuario = $stmt->get_result();

if ($result_usuario->num_rows === 0) {
    echo "Usuario no encontrado";
    exit();
}

$usuario = $result_usuario->fetch_assoc();
$stmt->close();

// Obtener el ID del usuario actual
$usuario_actual = $_SESSION['usuario'];
$sql_usuario_actual = "SELECT id, foto_perfil FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario_actual);
$stmt->bind_param("s", $usuario_actual);
$stmt->execute();
$result = $stmt->get_result();
$datos_usuario_actual = $result->fetch_assoc();
$usuario_actual_id = $datos_usuario_actual['id'];
$stmt->close();

// Verificar seguimiento
$sql_verificar_seguimiento = "SELECT id FROM seguidores WHERE seguidor_id = ? AND seguido_id = ?";
$stmt = $conn->prepare($sql_verificar_seguimiento);
$stmt->bind_param("ii", $usuario_actual_id, $usuario_id);
$stmt->execute();
$result_verificar_seguimiento = $stmt->get_result();
$ya_sigue = $result_verificar_seguimiento->num_rows > 0;
$stmt->close();

// Obtener estadísticas
$sql_stats = "
    SELECT 
        (SELECT COUNT(*) FROM seguidores WHERE seguido_id = ?) AS seguidores,
        (SELECT COUNT(*) FROM seguidores WHERE seguidor_id = ?) AS siguiendo,
        (SELECT COUNT(*) FROM publicaciones WHERE usuario_id = ?) AS publicaciones
";
$stmt = $conn->prepare($sql_stats);
$stmt->bind_param("iii", $usuario_id, $usuario_id, $usuario_id);
$stmt->execute();
$result_stats = $stmt->get_result();
$stats = $result_stats->fetch_assoc();
$stmt->close();

// Obtener publicaciones
$sql_publicaciones = "
    SELECT p.id, p.contenido, p.imagen, p.fecha,
           (SELECT COUNT(*) FROM likes WHERE publicacion_id = p.id) AS total_likes,
           (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id) AS total_comentarios
    FROM publicaciones p
    WHERE p.usuario_id = ?
    ORDER BY p.fecha DESC
    LIMIT 20
";
$stmt = $conn->prepare($sql_publicaciones);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result_publicaciones = $stmt->get_result();

$publicaciones_usuario = [];
while ($row = $result_publicaciones->fetch_assoc()) {
    $publicaciones_usuario[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($usuario['nombre']); ?> - Trendly</title>
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
        }

        body {
            background: var(--dark);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: var(--dark-secondary) !important;
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: var(--text-primary) !important;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .nav-link {
            color: var(--text-secondary) !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--text-primary) !important;
        }

        .profile-container {
            min-height: 100vh;
            background: var(--dark);
            padding: 2rem 1rem;
        }
        
        .profile-card {
            background: var(--dark-secondary);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2rem;
            margin: 0 auto;
            max-width: 800px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--glass-border);
            margin-bottom: 1rem;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .profile-username {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        
        .profile-bio {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-top: 1px solid var(--glass-border);
            border-bottom: 1px solid var(--glass-border);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .action-btn {
            background: var(--gray-light);
            border: 1px solid var(--glass-border);
            color: var(--text-secondary);
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem;
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-primary);
            border-color: transparent;
        }
        
        .action-btn.danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: var(--text-primary);
            border-color: transparent;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            color: var(--text-primary);
        }
        
        .posts-section {
            margin-top: 2rem;
        }
        
        .posts-header {
            background: var(--gray-light);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .posts-header h3 {
            color: var(--text-primary);
            margin: 0;
        }
        
        .post-card {
            background: var(--gray-light);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .post-content {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .post-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 12px;
            margin: 1rem 0;
        }
        
        .post-stats {
            display: flex;
            justify-content: space-between;
            color: var(--text-muted);
            font-size: 0.9rem;
            padding-top: 1rem;
            border-top: 1px solid var(--glass-border);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .profile-stats {
                gap: 1rem;
            }
            
            .profile-card {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar simple -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="inicio.php">Trendly</a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="inicio.php"><i class="bi bi-house-fill"></i></a>
            <a class="nav-link" href="explorar.php"><i class="bi bi-compass"></i></a>
            <a class="nav-link" href="mensajes.php"><i class="bi bi-chat"></i></a>
            <a class="nav-link" href="notificaciones.php"><i class="bi bi-bell"></i></a>
            <a class="nav-link" href="perfil.php"><i class="bi bi-person-circle"></i></a>
        </div>
    </div>
</nav>

<div class="profile-container">
    <div class="profile-card">
        
        <!-- Header del perfil -->
        <div class="profile-header">
            <img src="../<?php echo $usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                 alt="Foto de perfil" 
                 class="profile-avatar">
            
            <div class="profile-name"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
            <div class="profile-username">@<?php echo strtolower(str_replace([' ', '.', '-'], '', $usuario['nombre'])); ?></div>
            
            <div class="profile-bio">
                <?php echo !empty($usuario['biografia']) ? nl2br(htmlspecialchars($usuario['biografia'])) : 'Usuario de la comunidad Trendly. Explorando nuevas experiencias y conectando con el mundo.'; ?>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['publicaciones']); ?></span>
                    <div class="stat-label">Posts</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['seguidores']); ?></span>
                    <div class="stat-label">Seguidores</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['siguiendo']); ?></span>
                    <div class="stat-label">Siguiendo</div>
                </div>
            </div>
            
            <!-- Acciones del perfil -->
            <div style="text-align: center;">
                <?php if ($usuario_actual_id != $usuario_id): ?>
                    <?php if ($ya_sigue): ?>
                        <form method="POST" action="seguir.php" style="display: inline;">
                            <input type="hidden" name="seguido_id" value="<?php echo $usuario_id; ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                            <button type="submit" class="action-btn danger">
                                <i class="bi bi-person-dash"></i>
                                Dejar de seguir
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="seguir.php" style="display: inline;">
                            <input type="hidden" name="seguido_id" value="<?php echo $usuario_id; ?>">
                            <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                            <button type="submit" class="action-btn primary">
                                <i class="bi bi-person-plus"></i>
                                Seguir
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <a href="mensajes.php?usuario=<?php echo $usuario_id; ?>" class="action-btn">
                        <i class="bi bi-chat"></i>
                        Mensaje
                    </a>
                <?php else: ?>
                    <a href="perfil.php" class="action-btn primary">
                        <i class="bi bi-pencil-square"></i>
                        Editar perfil
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección de publicaciones -->
        <div class="posts-section">
            <div class="posts-header">
                <h3><i class="bi bi-grid-3x3 me-2"></i>Publicaciones (<?php echo count($publicaciones_usuario); ?>)</h3>
            </div>

            <?php if (empty($publicaciones_usuario)): ?>
                <div class="empty-state">
                    <i class="bi bi-journal-text empty-icon"></i>
                    <h4>Sin publicaciones</h4>
                    <p>
                        <?php if ($usuario_actual_id == $usuario_id): ?>
                            Aún no has compartido ninguna publicación. ¡Comparte tu primera historia!
                        <?php else: ?>
                            <?php echo htmlspecialchars($usuario['nombre']); ?> aún no ha compartido ninguna publicación.
                        <?php endif; ?>
                    </p>
                    <?php if ($usuario_actual_id == $usuario_id): ?>
                        <a href="inicio.php" class="action-btn primary">
                            <i class="bi bi-plus-lg"></i>
                            Crear primera publicación
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($publicaciones_usuario as $publicacion): ?>
                    <div class="post-card">
                        <div class="d-flex align-items-center mb-3">
                            <img src="../<?php echo $usuario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                 alt="Perfil" 
                                 class="rounded-circle me-3" 
                                 style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--glass-border);">
                            <div>
                                <div style="color: var(--text-primary); font-weight: 600;"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                                <div style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('d M Y · H:i', strtotime($publicacion['fecha'])); ?></div>
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
                        
                        <div class="post-stats">
                            <span><i class="bi bi-heart-fill me-1 text-danger"></i><?php echo $publicacion['total_likes']; ?> me gusta</span>
                            <span><i class="bi bi-chat-fill me-1 text-info"></i><?php echo $publicacion['total_comentarios']; ?> comentarios</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
console.log('✅ Perfil cargado correctamente');
console.log('Usuario actual ID:', <?php echo $usuario_actual_id; ?>);
console.log('Usuario perfil ID:', <?php echo $usuario_id; ?>);
console.log('Ya sigue:', <?php echo $ya_sigue ? 'true' : 'false'; ?>);

// Función simple para mostrar toast
function showToast(message, type = 'info') {
    alert(message); // Simple alert por ahora
}
</script>

</body>
</html>

<?php
$conn->close();
?>