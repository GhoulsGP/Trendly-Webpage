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

// Iniciar sesión al inicio
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el nombre del usuario desde la sesión
$usuario = $_SESSION['usuario'];

// Obtener el ID y la foto de perfil del usuario actual
$sql_usuario = "SELECT id, foto_perfil FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_data = $result->fetch_assoc();
$usuario_id = $usuario_data['id'];
$usuario_foto_perfil = $usuario_data['foto_perfil'];
$stmt->close();

// Obtener publicaciones de los usuarios seguidos
$sql_publicaciones = "SELECT p.id, p.contenido, p.imagen, p.fecha, u.id AS autor_id, u.nombre AS autor, 
        u.foto_perfil,
        (SELECT COUNT(*) FROM likes WHERE publicacion_id = p.id) AS total_likes,
        (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id) AS total_comentarios
        FROM publicaciones p 
        JOIN usuarios u ON p.usuario_id = u.id 
        WHERE p.usuario_id IN (
            SELECT seguido_id FROM seguidores 
            WHERE (seguidor_id = ? OR (seguidor_id = 0 AND usuario_id = ?))
        ) OR p.usuario_id = ?
        ORDER BY p.fecha DESC";
$stmt = $conn->prepare($sql_publicaciones);
$stmt->bind_param("iii", $usuario_id, $usuario_id, $usuario_id);
$stmt->execute();
$result_publicaciones = $stmt->get_result();
$stmt->close();

// Obtener usuarios disponibles para seguir
$sql_usuarios_disponibles = "SELECT id, nombre, foto_perfil FROM usuarios 
                             WHERE id != ? 
                             AND id NOT IN (
                                SELECT seguido_id FROM seguidores 
                                WHERE (seguidor_id = ? OR (seguidor_id = 0 AND usuario_id = ?))
                             )
                             LIMIT 5";
$stmt = $conn->prepare($sql_usuarios_disponibles);
$stmt->bind_param("iii", $usuario_id, $usuario_id, $usuario_id);
$stmt->execute();
$result_usuarios_disponibles = $stmt->get_result();
$stmt->close();

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// Mostrar mensajes de éxito o error
if (isset($_GET['success']) && $_GET['success'] == 'published') {
    echo '<div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast show align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i> Publicación creada.
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
          </div>';
}

if (isset($_GET['success']) && $_GET['success'] == 'followed') {
    echo '<div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast show align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle me-2"></i> Usuario seguido.
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
          </div>';
}

if (isset($_GET['error'])) {
    $error_message = '';
    switch ($_GET['error']) {
        case 'empty_content':
            $error_message = 'El contenido no puede estar vacío.';
            break;
        case 'upload_failed':
            $error_message = 'Error al subir la imagen.';
            break;
        case 'file_too_large':
            $error_message = 'Imagen demasiado grande.';
            break;
        case 'invalid_file_type':
            $error_message = 'Formato de imagen no válido.';
            break;
        case 'database':
            $error_message = 'Error al guardar la publicación.';
            break;
        default:
            $error_message = 'Ha ocurrido un error.';
    }
    
    echo '<div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast show align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-circle me-2"></i> ' . $error_message . '
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
          </div>';
}

// Incluir header.php
include '../includes/header.php';
?>

<!-- Contenido principal -->
<div class="container content-container">
    <div class="row">
        <!-- Feed principal -->
        <div class="col-lg-8">
            <!-- Formulario de publicación -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="crear_publicacion.php" method="POST" enctype="multipart/form-data">
                        <div class="d-flex gap-3">
                            <img src="../<?php echo $usuario_foto_perfil ?: 'uploads/default.png'; ?>" 
                                 alt="Perfil" 
                                 class="rounded-circle align-self-start"
                                 style="width: 42px; height: 42px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <textarea name="contenido" class="form-control post-input" 
                                          placeholder="¿Qué está pasando?" rows="2" required></textarea>
                                
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <label for="imagen" class="image-upload-label">
                                            <i class="bi bi-image"></i>
                                        </label>
                                        <input type="file" id="imagen" name="imagen" class="d-none" accept="image/*">
                                        <span id="file-selected" class="small text-muted ms-2"></span>
                                    </div>
                                    <button type="submit" class="btn post-btn">
                                        Publicar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Feed de publicaciones -->
            <?php if ($result_publicaciones->num_rows > 0): ?>
                <?php while ($row = $result_publicaciones->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-body">
                            <!-- Encabezado de la publicación -->
                            <div class="d-flex align-items-center">
                                <a href="perfil_usuario.php?id=<?php echo $row['autor_id']; ?>">
                                    <img src="../<?php echo $row['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                         alt="Perfil" 
                                         class="post-author-avatar me-3">
                                </a>
                                <div>
                                    <a href="perfil_usuario.php?id=<?php echo $row['autor_id']; ?>" 
                                       class="text-decoration-none">
                                        <div class="post-author-name"><?php echo htmlspecialchars($row['autor']); ?></div>
                                    </a>
                                    <div class="post-time">
                                        <?php echo date('d M Y', strtotime($row['fecha'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contenido de la publicación -->
                            <div class="post-content"><?php echo nl2br(htmlspecialchars($row['contenido'])); ?></div>
                            
                            <!-- Imagen de la publicación (si existe) -->
                            <?php if (!empty($row['imagen'])): ?>
                                <img src="../<?php echo $row['imagen']; ?>" 
                                     alt="Imagen" 
                                     class="post-image">
                            <?php endif; ?>
                            
                            <!-- Contador de interacciones -->
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span><?php echo $row['total_likes']; ?> me gusta</span>
                                <span><?php echo $row['total_comentarios']; ?> comentarios</span>
                            </div>
                            
                            <!-- Botones de interacción -->
                            <div class="d-flex gap-2">
                                <form method="POST" action="" class="no-loading" style="display: inline;">
                                    <input type="hidden" name="publicacion_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="toggle_like" class="btn btn-link p-0 no-loading">
                                        <button type="button"
                                        class="btn post-action-btn flex-grow-1 like-btn"
                                        data-post-id="<?php echo $row['id']; ?>"
                                        data-user-id="<?php echo $usuario_id; ?>">
                                    <i class="bi bi-heart me-1"></i>
                                    <span class="like-text">Me gusta</span>
                                    <span class="like-count">(<?php echo $row['total_likes']; ?>)</span>
                                </button>
                                </button>
                                </form>
                                <button type="button"
                                        class="btn post-action-btn flex-grow-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#comentarioModal<?php echo $row['id']; ?>">
                                    <i class="bi bi-chat me-1"></i> Comentar
                                </button>
                                <button type="button"
                                        class="btn post-action-btn flex-grow-1"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#comentarios<?php echo $row['id']; ?>"
                                        aria-expanded="false">
                                    <i class="bi bi-chevron-down me-1"></i>
                                    Ver comentarios
                                </button>
                            </div>
                            
                            <!-- Área de comentarios colapsable -->
                            <div class="collapse mt-3" id="comentarios<?php echo $row['id']; ?>">
                                <div class="pt-3 border-top border-secondary">
                                    <form method="POST" action="" class="no-loading">
                                        <input type="hidden" name="publicacion_id" value="<?php echo $row['id']; ?>">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="comentario" placeholder="Añadir un comentario...">
                                            <button class="btn btn-outline-secondary no-loading" type="submit">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <?php
                                    $publicacion_id = $row['id'];
                                    $sql_comentarios = "SELECT c.id AS comentario_id, c.contenido, c.fecha, u.id AS autor_id, u.nombre AS autor, u.foto_perfil
                                                        FROM comentarios c 
                                                        JOIN usuarios u ON c.usuario_id = u.id 
                                                        WHERE c.publicacion_id = $publicacion_id 
                                                        ORDER BY c.fecha DESC";
                                    $result_comentarios = $conn->query($sql_comentarios);

                                    if ($result_comentarios->num_rows > 0): ?>
                                        <?php while ($comentario = $result_comentarios->fetch_assoc()): ?>
                                            <div class="d-flex gap-2 mb-3">
                                                <a href="perfil_usuario.php?id=<?php echo $comentario['autor_id']; ?>">
                                                    <img src="../<?php echo $comentario['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                                         alt="Perfil" 
                                                         class="comment-avatar">
                                                </a>
                                                <div class="comment-bubble flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <a href="perfil_usuario.php?id=<?php echo $comentario['autor_id']; ?>" 
                                                           class="text-decoration-none">
                                                            <span class="comment-author"><?php echo htmlspecialchars($comentario['autor']); ?></span>
                                                        </a>
                                                        <?php if ($comentario['autor_id'] == $usuario_id || $row['autor_id'] == $usuario_id): ?>
                                                            <form method="POST" action="eliminar_comentario.php" class="d-inline">
                                                                <input type="hidden" name="comentario_id" value="<?php echo $comentario['comentario_id']; ?>">
                                                                <input type="hidden" name="publicacion_id" value="<?php echo $publicacion_id; ?>">
                                                                <button type="submit" class="btn btn-link text-muted p-0 border-0" 
                                                                        onclick="return confirm('¿Eliminar este comentario?');">
                                                                    <i class="bi bi-x"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="comment-text"><?php echo htmlspecialchars($comentario['contenido']); ?></div>
                                                    <div class="comment-time mt-1">
                                                        <?php echo date('d M Y', strtotime($comentario['fecha'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <p class="text-muted mb-0">No hay comentarios aún</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal para comentar -->
                    <div class="modal fade" id="comentarioModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Comentar</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="comentar.php">
                                    <div class="modal-body">
                                        <input type="hidden" name="publicacion_id" value="<?php echo $row['id']; ?>">
                                        <div class="mb-3">
                                            <textarea class="form-control post-input" 
                                                      name="comentario" 
                                                      rows="3" 
                                                      placeholder="Escribe tu comentario" 
                                                      required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn post-btn" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn post-btn">Publicar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card text-center">
                    <div class="card-body py-5">
                        <i class="bi bi-inbox display-4 mb-3 text-muted"></i>
                        <h4>Sin publicaciones</h4>
                        <p class="text-muted">Sigue a más personas para ver su contenido</p>
                        <a href="explorar.php" class="btn post-btn mt-2">
                            Explorar
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Columna lateral -->
        <div class="col-lg-4 mt-4 mt-lg-0">
            <!-- Perfil -->
            <div class="card mb-4">
                <div class="card-body text-center py-4">
                    <img src="../<?php echo $usuario_foto_perfil ?: 'uploads/default.png'; ?>" 
                         alt="Perfil" 
                         class="profile-avatar mb-3">
                    <h5><?php echo htmlspecialchars($usuario); ?></h5>
                    <p class="text-muted mb-3">@<?php echo strtolower(str_replace(' ', '', $usuario)); ?></p>
                    <a href="perfil.php" class="btn post-btn w-100">Ver perfil</a>
                </div>
            </div>
            
            <!-- Tendencias -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="m-0">Tendencias</h6>
                </div>
                <div class="card-body p-0">
                    <div class="trend-item">
                        <div class="trend-category">Tecnología</div>
                        <div class="trend-tag">#MacOS</div>
                        <div class="trend-posts">1.5K publicaciones</div>
                    </div>
                    <div class="trend-item">
                        <div class="trend-category">Entretenimiento</div>
                        <div class="trend-tag">#TrendlyApp</div>
                        <div class="trend-posts">852 publicaciones</div>
                    </div>
                    <div class="trend-item">
                        <div class="trend-category">Música</div>
                        <div class="trend-tag">#NuevosLanzamientos</div>
                        <div class="trend-posts">620 publicaciones</div>
                    </div>
                    <div class="p-3 text-center">
                        <a href="tendencias.php">Ver más</a>
                    </div>
                </div>
            </div>
            
            <!-- Sugerencias -->
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0">Sugerencias</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($result_usuarios_disponibles->num_rows > 0): ?>
                        <?php while ($usuario_disponible = $result_usuarios_disponibles->fetch_assoc()): ?>
                            <div class="suggestion-item d-flex align-items-center">
                                <a href="perfil_usuario.php?id=<?php echo $usuario_disponible['id']; ?>">
                                    <img src="../<?php echo $usuario_disponible['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                         alt="Perfil" 
                                         class="suggestion-avatar me-3">
                                </a>
                                <div class="flex-grow-1">
                                    <a href="perfil_usuario.php?id=<?php echo $usuario_disponible['id']; ?>" 
                                       class="suggestion-name d-block text-decoration-none">
                                        <?php echo htmlspecialchars($usuario_disponible['nombre']); ?>
                                    </a>
                                    <span class="suggestion-text">Sugerido para ti</span>
                                </div>
                                <form method="POST" action="seguir.php">
                                    <input type="hidden" name="seguido_id" value="<?php echo $usuario_disponible['id']; ?>">
                                    <button type="submit" class="follow-btn">
                                        Seguir
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No hay sugerencias disponibles</p>
                        </div>
                    <?php endif; ?>
                    <div class="p-3 text-center">
                        <a href="explorar.php">Ver más</a>
                    </div>
                </div>
            </div>
            
            <!-- GitHub -->
            <div class="card mt-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #333; border-radius: 8px;">
                            <i class="bi bi-github fs-4 text-white"></i>
                        </div>
                        <div class="ms-3">
                            <div class="suggestion-name">O meu GitHub</div>
                            <div class="suggestion-text">
                                <a href="https://github.com/GhoulsGP" target="_blank">github.com/GhoulsGP</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer minimalista -->
            <div class="mini-footer mt-4">
                <a href="#">Acerca de</a>
                <a href="#">Términos</a>
                <a href="#">Privacidad</a>
                <span>© 2025</span>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --dark: #121212;
        --dark-secondary: #1e1e1e;
        --gray-light: #2d2d2d;
        --gray: #333333;
        --gray-dark: #1a1a1a;
        --text-primary: #ffffff;
        --text-secondary: #e0e0e0;
        --text-muted: #b0b0b0;
        --accent: #565656;
        --accent-hover: #6e6e6e;
        --shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        --glass-border: rgba(255, 255, 255, 0.1);
        --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    body {
        background: var(--dark);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .content-container {
        padding-top: 2rem;
        min-height: 100vh;
    }

    /* Cards */
    .card {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        margin-bottom: 1.5rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
    }

    .card-header {
        background: transparent;
        border-bottom: 1px solid var(--glass-border);
        color: var(--text-primary);
        font-weight: 600;
        padding: 1.5rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Avatares */
    .post-author-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .comment-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .suggestion-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--glass-border);
    }

    /* Nombres y textos */
    .post-author-name,
    .comment-author,
    .suggestion-name {
        color: var(--text-primary) !important;
        font-weight: 600;
        text-decoration: none !important;
    }

    h1, h2, h3, h4, h5, h6 {
        color: var(--text-primary) !important;
    }

    .card-body h5 {
        color: var(--text-primary) !important;
        font-weight: 600;
    }

    .card-header h6 {
        color: var(--text-primary) !important;
        font-weight: 600;
    }

    /* También para el título "Sin publicaciones" */
    .card-body h4 {
        color: var(--text-primary) !important;
    }

    .post-time,
    .comment-time,
    .suggestion-text {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .post-content,
    .comment-text {
        color: var(--text-primary) !important;
        margin: 1rem 0;
        line-height: 1.5;
    }

    /* Imágenes de publicaciones */
    .post-image {
        width: 100%;
        max-height: 400px;
        object-fit: cover;
        border-radius: 16px;
        margin: 1rem 0;
        box-shadow: var(--shadow);
    }

    /* Formularios */
    .post-input,
    .form-control {
        background: var(--gray-light) !important;
        border: 1px solid var(--glass-border) !important;
        border-radius: 12px !important;
        color: var(--text-primary) !important;
        padding: 0.75rem 1rem;
        resize: none;
    }

    .post-input:focus,
    .form-control:focus {
        background: var(--gray-light) !important;
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 0.2rem rgba(86, 86, 86, 0.25) !important;
        color: var(--text-primary) !important;
    }

    .post-input::placeholder,
    .form-control::placeholder {
        color: var(--text-muted) !important;
    }

    /* Botones */
    .post-btn,
    .btn-primary {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        border: none;
        color: var(--text-primary);
        padding: 0.6rem 1.5rem;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .post-btn:hover,
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--accent-hover), var(--accent));
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: var(--text-primary);
    }

    .post-action-btn {
        background: transparent;
        border: 1px solid var(--glass-border);
        color: var(--text-secondary);
        padding: 0.5rem 1rem;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .post-action-btn:hover:not(:disabled) {
        background: var(--gray-light);
        color: var(--text-primary);
        transform: translateY(-2px);
        border-color: var(--accent);
    }

    .follow-btn {
        background: var(--accent);
        border: none;
        color: var(--text-primary);
        padding: 0.4rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .follow-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
    }

    /* Botones de like */
    .like-btn.liked {
        background-color: rgba(255, 107, 107, 0.2) !important;
        color: #ff6b6b !important;
        border: 1px solid rgba(255, 107, 107, 0.3) !important;
    }

    .like-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Upload de imagen */
    .image-upload-label {
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .image-upload-label:hover {
        color: var(--text-primary);
        background: var(--gray-light);
    }

    /* Comentarios */
    .comment-bubble {
        background: var(--gray-light);
        border-radius: 12px;
        padding: 0.75rem 1rem;
    }

    /* Tendencias */
    .trend-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--glass-border);
        transition: all 0.3s ease;
    }

    .trend-item:hover {
        background: var(--gray-light);
    }

    .trend-item:last-child {
        border-bottom: none;
    }

    .trend-category {
        color: var(--text-secondary) !important;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .trend-tag {
        color: var(--text-primary) !important;
        font-weight: 600;
        margin: 0.25rem 0;
    }

    .trend-posts {
        color: var(--text-secondary) !important;
        font-size: 0.85rem;
    }

    /* Sugerencias */
    .suggestion-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--glass-border);
        transition: all 0.3s ease;
    }

    .suggestion-item:hover {
        background: var(--gray-light);
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    /* Modales */
    .modal-content {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        backdrop-filter: blur(10px);
    }

    .modal-header {
        border-bottom: 1px solid var(--glass-border);
        background: transparent;
    }

    .modal-footer {
        border-top: 1px solid var(--glass-border);
        background: transparent;
    }

    .modal-title {
        color: var(--text-primary) !important;
    }

    .modal-body {
        color: var(--text-primary) !important;
    }

    /* Enlaces */
    a {
        color: var(--text-secondary) !important;
        text-decoration: none !important;
        transition: color 0.3s ease;
    }

    a:hover {
        color: var(--text-primary) !important;
    }

    /* Footer mini */
    .mini-footer {
        padding: 1rem 0;
        text-align: center;
        border-top: 1px solid var(--glass-border);
    }

    .mini-footer a {
        color: var(--text-muted);
        margin: 0 0.5rem;
        font-size: 0.85rem;
    }

    .mini-footer span {
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    /* Estados de texto */
    .text-muted {
        color: var(--text-muted) !important;
    }

    .small {
        color: var(--text-secondary);
    }

    /* Toasts */
    .toast {
        background: var(--dark-secondary) !important;
        border: 1px solid var(--glass-border) !important;
        border-radius: 16px !important;
        backdrop-filter: blur(10px);
    }

    .toast-body {
        color: var(--text-primary) !important;
    }

    /* Responsivo */
    @media (max-width: 768px) {
        .content-container {
            padding-top: 1rem;
        }
        
        .card {
            border-radius: 16px;
            margin-bottom: 1rem;
        }
        
        .card-body {
            padding: 1rem;
        }
    }

    /* Animaciones */
    .card,
    .post-action-btn,
    .follow-btn,
    .trend-item,
    .suggestion-item {
        transition: all 0.3s ease;
    }

    /* Scrollbar personalizado */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--dark);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--accent);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--accent-hover);
    }
</style>

<script>
// Variables globales
const currentUserId = <?php echo $usuario_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar botones de like
    initializeLikeButtons();
    
    // Verificar estado inicial de likes
    checkInitialLikeStatus();
    
    // Auto-ocultar los mensajes
    setTimeout(function() {
        const toasts = document.querySelectorAll('.toast.show');
        toasts.forEach(toast => {
            const bsToast = new bootstrap.Toast(toast);
            bsToast.hide();
        });
    }, 5000);
});

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
    // Deshabilitar el botón temporalmente
    button.disabled = true;
    
    fetch('like_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId,
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateLikeButton(button, data.liked, data.total_likes);
            showToast(data.liked ? 'Te gusta esta publicación' : 'Ya no te gusta', 'success');
        } else {
            showToast('Error al procesar like: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error de conexión', 'error');
    })
    .finally(() => {
        // Rehabilitar el botón
        button.disabled = false;
    });
}

function updateLikeButton(button, liked, totalLikes) {
    const icon = button.querySelector('i');
    const likeText = button.querySelector('.like-text');
    const likeCount = button.querySelector('.like-count');
    
    if (liked) {
        // Usuario dio like
        button.classList.add('liked');
        icon.className = 'bi bi-heart-fill me-1';
        likeText.textContent = 'Te gusta';
        button.style.color = '#ff6b6b';
    } else {
        // Usuario quitó like
        button.classList.remove('liked');
        icon.className = 'bi bi-heart me-1';
        likeText.textContent = 'Me gusta';
        button.style.color = '';
    }
    
    likeCount.textContent = `(${totalLikes})`;
}

function checkInitialLikeStatus() {
    document.querySelectorAll('.like-btn').forEach(button => {
        const postId = button.getAttribute('data-post-id');
        const userId = button.getAttribute('data-user-id');
        
        fetch('check_like_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                post_id: postId,
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.liked) {
                button.classList.add('liked');
                button.querySelector('i').className = 'bi bi-heart-fill me-1';
                button.querySelector('.like-text').textContent = 'Te gusta';
                button.style.color = '#ff6b6b';
            }
        })
        .catch(error => console.error('Error checking like status:', error));
    });
}

function showToast(message, type = 'info') {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const iconClass = type === 'success' ? 'bi-check-circle' : 
                     type === 'error' ? 'bi-exclamation-circle' : 
                     'bi-info-circle';
    
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-dark border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${iconClass} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Limpiar el toast después de que se oculte
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Script para mostrar el nombre del archivo seleccionado
document.getElementById('imagen').addEventListener('change', function() {
    const fileName = this.files[0]?.name;
    document.getElementById('file-selected').textContent = fileName || '';
});
</script>

<!-- Estilos adicionales para el botón de like -->
<style>
.like-btn.liked {
    background-color: rgba(255, 107, 107, 0.2) !important;
    color: #ff6b6b !important;
    border: 1px solid rgba(255, 107, 107, 0.3) !important;
}

.like-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.post-action-btn {
    transition: all 0.3s ease;
}

.post-action-btn:hover:not(:disabled) {
    transform: translateY(-2px);
}
</style>


<?php
// Incluir el footer
include '../includes/footer.php';

// ✅ INCLUIR LA FUNCIÓN DE NOTIFICACIONES
include 'crear_notificacion.php';

// Procesar comentarios
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comentario'], $_POST['publicacion_id'])) {
    $comentario = trim($_POST['comentario']);
    $publicacion_id = (int)$_POST['publicacion_id'];
    
    if (!empty($comentario)) {
        // Obtener el autor del post
        $sql_post = "SELECT usuario_id FROM publicaciones WHERE id = ?";
        $stmt_post = $conn->prepare($sql_post);
        $stmt_post->bind_param("i", $publicacion_id);
        $stmt_post->execute();
        $result_post = $stmt_post->get_result();
        $post_data = $result_post->fetch_assoc();
        $post_author_id = $post_data['usuario_id'];
        $stmt_post->close();
        
        // Insertar comentario
        $sql_comentario = "INSERT INTO comentarios (publicacion_id, usuario_id, contenido) VALUES (?, ?, ?)";
        $stmt_comentario = $conn->prepare($sql_comentario);
        $stmt_comentario->bind_param("iis", $publicacion_id, $usuario_id, $comentario);
        
        if ($stmt_comentario->execute()) {
            // 🚨 CREAR NOTIFICACIÓN DE COMENTARIO
            crearNotificacion($conn, $post_author_id, 'comentario', $usuario_id, $publicacion_id);
            
            header("Location: inicio.php?success=comentario_agregado");
        } else {
            header("Location: inicio.php?error=error_comentario");
        }
        $stmt_comentario->close();
    }
}