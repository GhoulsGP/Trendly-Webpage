<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/explorar.php
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

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario actual
$usuario = $_SESSION['usuario'];
$sql_usuario = "SELECT id, foto_perfil FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_data = $result->fetch_assoc();
$usuario_id = $usuario_data['id'];
$usuario_foto_perfil = $usuario_data['foto_perfil'];
$stmt->close();

// Obtener parámetros de búsqueda y filtros
$busqueda = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? 'todo';
$orden = $_GET['orden'] ?? 'recientes';

// Construir consulta base
$where_conditions = [];
$params = [];
$types = "";

// Si hay búsqueda, agregar condición
if (!empty($busqueda)) {
    $where_conditions[] = "(p.contenido LIKE ? OR u.nombre LIKE ?)";
    $search_term = "%$busqueda%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Filtro por categoría (simulado con hashtags)
if ($categoria !== 'todo') {
    switch ($categoria) {
        case 'tecnologia':
            $where_conditions[] = "p.contenido LIKE '%#tech%' OR p.contenido LIKE '%#tecnologia%' OR p.contenido LIKE '%#MacOS%'";
            break;
        case 'arte':
            $where_conditions[] = "p.contenido LIKE '%#arte%' OR p.contenido LIKE '%#design%' OR p.contenido LIKE '%#creatividad%'";
            break;
        case 'musica':
            $where_conditions[] = "p.contenido LIKE '%#música%' OR p.contenido LIKE '%#music%' OR p.imagen IS NOT NULL";
            break;
        case 'deportes':
            $where_conditions[] = "p.contenido LIKE '%#deporte%' OR p.contenido LIKE '%#fitness%' OR p.contenido LIKE '%#sports%'";
            break;
    }
}

// Construir ORDER BY
$order_by = "p.fecha DESC";
switch ($orden) {
    case 'populares':
        $order_by = "total_likes DESC, p.fecha DESC";
        break;
    case 'comentarios':
        $order_by = "total_comentarios DESC, p.fecha DESC";
        break;
    case 'recientes':
    default:
        $order_by = "p.fecha DESC";
        break;
}

// Consulta principal
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql_publicaciones = "
    SELECT p.id, p.contenido, p.imagen, p.fecha, 
           u.id AS autor_id, u.nombre AS autor, u.foto_perfil,
           (SELECT COUNT(*) FROM likes WHERE publicacion_id = p.id) AS total_likes,
           (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id) AS total_comentarios,
           (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) AS seguidores_autor
    FROM publicaciones p 
    JOIN usuarios u ON p.usuario_id = u.id 
    $where_clause
    ORDER BY $order_by
    LIMIT 50
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql_publicaciones);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result_publicaciones = $stmt->get_result();
    $stmt->close();
} else {
    $result_publicaciones = $conn->query($sql_publicaciones);
}

// Obtener tendencias populares
$sql_tendencias = "
    SELECT 
        SUBSTRING_INDEX(SUBSTRING_INDEX(p.contenido, '#', -1), ' ', 1) AS hashtag,
        COUNT(*) AS count,
        'trending' as categoria
    FROM publicaciones p 
    WHERE p.contenido LIKE '%#%' 
    AND p.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY hashtag 
    HAVING hashtag != '' AND hashtag NOT LIKE '%\\n%'
    ORDER BY count DESC 
    LIMIT 8
";
$result_tendencias = $conn->query($sql_tendencias);

// Obtener usuarios sugeridos
$sql_usuarios_populares = "
    SELECT u.id, u.nombre, u.foto_perfil, u.biografia,
           (SELECT COUNT(*) FROM seguidores WHERE seguido_id = u.id) AS total_seguidores,
           (SELECT COUNT(*) FROM publicaciones WHERE usuario_id = u.id) AS total_publicaciones
    FROM usuarios u 
    WHERE u.id != ? 
    AND u.id NOT IN (
        SELECT seguido_id FROM seguidores WHERE seguidor_id = ?
    )
    ORDER BY total_seguidores DESC, total_publicaciones DESC
    LIMIT 6
";
$stmt = $conn->prepare($sql_usuarios_populares);
$stmt->bind_param("ii", $usuario_id, $usuario_id);
$stmt->execute();
$result_usuarios_populares = $stmt->get_result();
$stmt->close();

// Obtener estadísticas generales
$sql_stats = "
    SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM publicaciones WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as posts_hoy,
        (SELECT COUNT(*) FROM publicaciones) as total_posts,
        (SELECT COUNT(*) FROM likes WHERE fecha >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as likes_hoy
";
$result_stats = $conn->query($sql_stats);
$stats = $result_stats->fetch_assoc();

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// Incluir header
include '../includes/header.php';
?>

<div class="explore-container">
    <div class="container-fluid px-4">
        <div class="explore-layout">
            <!-- Header de exploración -->
            <div class="explore-header fade-in">
                <div class="explore-hero">
                    <h1><i class="bi bi-compass me-3"></i>Explorar</h1>
                    <p>Descubre contenido increíble y conecta con nuevas personas</p>
                </div>
                
                <!-- Estadísticas en vivo -->
                <div class="live-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_usuarios']); ?></div>
                            <div class="stat-label">Usuarios</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['posts_hoy']); ?></div>
                            <div class="stat-label">Posts hoy</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['likes_hoy']); ?></div>
                            <div class="stat-label">Likes hoy</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?php echo number_format($stats['total_posts']); ?></div>
                            <div class="stat-label">Total posts</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="explore-content">
                <!-- Sidebar con filtros -->
                <div class="explore-sidebar">
                    <!-- Búsqueda avanzada -->
                    <div class="search-section slide-up">
                        <div class="section-card">
                            <h3><i class="bi bi-search me-2"></i>Búsqueda</h3>
                            <form method="GET" class="search-form">
                                <div class="search-input-group">
                                    <input type="text" name="q" value="<?php echo htmlspecialchars($busqueda); ?>" 
                                           placeholder="Buscar usuarios, contenido..." 
                                           class="search-input">
                                    <button type="submit" class="search-btn">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                                
                                <div class="filter-group">
                                    <label>Categoría</label>
                                    <select name="categoria" class="filter-select">
                                        <option value="todo" <?php echo $categoria === 'todo' ? 'selected' : ''; ?>>Todas</option>
                                        <option value="tecnologia" <?php echo $categoria === 'tecnologia' ? 'selected' : ''; ?>>Tecnología</option>
                                        <option value="arte" <?php echo $categoria === 'arte' ? 'selected' : ''; ?>>Arte</option>
                                        <option value="musica" <?php echo $categoria === 'musica' ? 'selected' : ''; ?>>Música</option>
                                        <option value="deportes" <?php echo $categoria === 'deportes' ? 'selected' : ''; ?>>Deportes</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label>Ordenar por</label>
                                    <select name="orden" class="filter-select">
                                        <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                                        <option value="populares" <?php echo $orden === 'populares' ? 'selected' : ''; ?>>Más populares</option>
                                        <option value="comentarios" <?php echo $orden === 'comentarios' ? 'selected' : ''; ?>>Más comentados</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="filter-apply-btn">
                                    <i class="bi bi-funnel me-2"></i>Aplicar filtros
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Tendencias del momento -->
                    <div class="trending-section slide-up">
                        <div class="section-card">
                            <h3><i class="bi bi-lightning-fill me-2"></i>Tendencias</h3>
                            <div class="trending-list">
                                <?php if ($result_tendencias->num_rows > 0): ?>
                                    <?php $trend_position = 1; ?>
                                    <?php while ($tendencia = $result_tendencias->fetch_assoc()): ?>
                                        <div class="trending-item">
                                            <div class="trend-position"><?php echo $trend_position; ?></div>
                                            <div class="trend-info">
                                                <div class="trend-hashtag">#<?php echo htmlspecialchars($tendencia['hashtag']); ?></div>
                                                <div class="trend-count"><?php echo $tendencia['count']; ?> publicaciones</div>
                                            </div>
                                            <div class="trend-status">
                                                <i class="bi bi-arrow-up trending-up"></i>
                                            </div>
                                        </div>
                                        <?php $trend_position++; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-trends">
                                        <i class="bi bi-graph-up-arrow"></i>
                                        <p>No hay tendencias activas</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Usuarios destacados -->
                    <div class="featured-users-section slide-up">
                        <div class="section-card">
                            <h3><i class="bi bi-star-fill me-2"></i>Usuarios destacados</h3>
                            <div class="featured-users-list">
                                <?php if ($result_usuarios_populares->num_rows > 0): ?>
                                    <?php while ($usuario_popular = $result_usuarios_populares->fetch_assoc()): ?>
                                        <div class="featured-user-item">
                                            <a href="perfil_usuario.php?id=<?php echo $usuario_popular['id']; ?>">
                                                <img src="../<?php echo $usuario_popular['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                                     alt="Avatar" 
                                                     class="featured-user-avatar">
                                            </a>
                                            <div class="featured-user-info">
                                                <a href="perfil_usuario.php?id=<?php echo $usuario_popular['id']; ?>" 
                                                   class="featured-user-name">
                                                    <?php echo htmlspecialchars($usuario_popular['nombre']); ?>
                                                </a>
                                                <div class="featured-user-stats">
                                                    <span><?php echo number_format($usuario_popular['total_seguidores']); ?> seguidores</span>
                                                </div>
                                                <div class="featured-user-bio">
                                                    <?php echo htmlspecialchars(substr($usuario_popular['biografia'] ?: 'Usuario destacado de la comunidad', 0, 50)) . '...'; ?>
                                                </div>
                                            </div>
                                            <form method="POST" action="seguir.php" class="follow-form">
                                                <input type="hidden" name="seguido_id" value="<?php echo $usuario_popular['id']; ?>">
                                                <button type="submit" class="follow-btn-mini">
                                                    <i class="bi bi-person-plus"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-users">
                                        <i class="bi bi-people"></i>
                                        <p>No hay usuarios destacados</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido principal - Feed de exploración -->
                <div class="explore-main">
                    <!-- Resultados header -->
                    <div class="results-header slide-up">
                        <?php if (!empty($busqueda)): ?>
                            <h2>Resultados para "<?php echo htmlspecialchars($busqueda); ?>"</h2>
                        <?php else: ?>
                            <h2>Descubre contenido</h2>
                        <?php endif; ?>
                        
                        <div class="results-info">
                            <?php 
                            $total_results = $result_publicaciones->num_rows;
                            echo $total_results . " publicacion" . ($total_results != 1 ? "es" : "") . " encontrada" . ($total_results != 1 ? "s" : "");
                            ?>
                        </div>
                    </div>

                    <!-- Grid de publicaciones -->
                    <div class="explore-grid">
                        <?php if ($result_publicaciones->num_rows > 0): ?>
                            <?php $post_index = 0; ?>
                            <?php while ($publicacion = $result_publicaciones->fetch_assoc()): ?>
                                <div class="explore-post-card slide-up" style="animation-delay: <?php echo ($post_index * 0.1); ?>s">
                                    <!-- Header del post -->
                                    <div class="post-header">
                                        <div class="post-author-info">
                                            <a href="perfil_usuario.php?id=<?php echo $publicacion['autor_id']; ?>">
                                                <img src="../<?php echo $publicacion['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                                     alt="Avatar" 
                                                     class="post-author-avatar">
                                            </a>
                                            <div class="post-author-details">
                                                <a href="perfil_usuario.php?id=<?php echo $publicacion['autor_id']; ?>" 
                                                   class="post-author-name">
                                                    <?php echo htmlspecialchars($publicacion['autor']); ?>
                                                </a>
                                                <div class="post-author-meta">
                                                    <span class="post-time"><?php echo date('d M Y', strtotime($publicacion['fecha'])); ?></span>
                                                    <span class="post-separator">•</span>
                                                    <span class="post-followers"><?php echo number_format($publicacion['seguidores_autor']); ?> seguidores</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="post-actions">
                                            <button class="post-menu-btn" onclick="showPostMenu(<?php echo $publicacion['id']; ?>)">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Contenido del post -->
                                    <div class="post-content">
                                        <div class="post-text">
                                            <?php echo nl2br(htmlspecialchars($publicacion['contenido'])); ?>
                                        </div>
                                        
                                        <?php if (!empty($publicacion['imagen'])): ?>
                                            <div class="post-image-container">
                                                <img src="../<?php echo $publicacion['imagen']; ?>" 
                                                     alt="Imagen del post" 
                                                     class="post-image"
                                                     onclick="showImageModal('<?php echo $publicacion['imagen']; ?>')">
                                                <div class="image-overlay">
                                                    <i class="bi bi-zoom-in"></i>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Footer del post -->
                                    <div class="post-footer">
                                        <div class="post-stats">
                                            <div class="stat-item">
                                                <i class="bi bi-heart-fill text-danger"></i>
                                                <span><?php echo number_format($publicacion['total_likes']); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <i class="bi bi-chat-fill text-info"></i>
                                                <span><?php echo number_format($publicacion['total_comentarios']); ?></span>
                                            </div>
                                        </div>

                                        <div class="post-interactions">
                                            <button class="interaction-btn like-btn" 
                                                    data-post-id="<?php echo $publicacion['id']; ?>"
                                                    data-user-id="<?php echo $usuario_id; ?>">
                                                <i class="bi bi-heart"></i>
                                            </button>
                                            <button class="interaction-btn comment-btn" 
                                                    onclick="showCommentModal(<?php echo $publicacion['id']; ?>)">
                                                <i class="bi bi-chat"></i>
                                            </button>
                                            <button class="interaction-btn share-btn" 
                                                    onclick="sharePost(<?php echo $publicacion['id']; ?>)">
                                                <i class="bi bi-share"></i>
                                            </button>
                                            <a href="inicio.php" class="interaction-btn view-btn" title="Ver en inicio">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php $post_index++; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <!-- Estado vacío -->
                            <div class="empty-explore slide-up">
                                <div class="empty-explore-content">
                                    <i class="bi bi-search empty-icon"></i>
                                    <h3>No se encontraron resultados</h3>
                                    <p>Intenta ajustar tus filtros de búsqueda o explora diferente contenido.</p>
                                    <div class="empty-suggestions">
                                        <a href="?categoria=tecnologia" class="suggestion-tag">#Tecnología</a>
                                        <a href="?categoria=arte" class="suggestion-tag">#Arte</a>
                                        <a href="?categoria=musica" class="suggestion-tag">#Música</a>
                                        <a href="?categoria=deportes" class="suggestion-tag">#Deportes</a>
                                    </div>
                                    <a href="inicio.php" class="back-to-home-btn">
                                        <i class="bi bi-house-fill me-2"></i>
                                        Volver al inicio
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver imagen completa -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" 
                        data-bs-dismiss="modal" style="z-index: 1000;"></button>
                <img id="modalImage" src="" alt="Imagen" class="w-100 rounded">
            </div>
        </div>
    </div>
</div>

<!-- Modal para comentarios -->
<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comentarios</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="commentModalBody">
                <!-- Contenido dinámico -->
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
        --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    body {
        background: var(--dark);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: 0;
        padding: 0;
    }

    .explore-container {
        min-height: 100vh;
        background: var(--dark);
        padding-top: 80px;
    }

    .explore-layout {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 0;
    }

    /* Header de exploración */
    .explore-header {
        margin-bottom: 3rem;
    }

    .explore-hero {
        text-align: center;
        margin-bottom: 3rem;
    }

    .explore-hero h1 {
        font-size: 3rem;
        font-weight: 800;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .explore-hero p {
        font-size: 1.2rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Estadísticas en vivo */
    .live-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        background: var(--gradient-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    .stat-label {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 0.25rem;
    }

    /* Layout principal */
    .explore-content {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 2rem;
    }

    /* Sidebar */
    .explore-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        position: sticky;
        top: 100px;
        height: fit-content;
    }

    .section-card {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
    }

    .section-card h3 {
        color: var(--text-primary);
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
    }

    /* Búsqueda */
    .search-input-group {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .search-input {
        width: 100%;
        background: var(--gray-light);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 0.75rem 3rem 0.75rem 1rem;
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 0.2rem rgba(86, 86, 86, 0.25);
    }

    .search-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--accent);
        border: none;
        border-radius: 8px;
        color: var(--text-primary);
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .search-btn:hover {
        background: var(--accent-hover);
    }

    .filter-group {
        margin-bottom: 1rem;
    }

    .filter-group label {
        display: block;
        color: var(--text-secondary);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .filter-select {
        width: 100%;
        background: var(--gray-light);
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        padding: 0.5rem;
        color: var(--text-primary);
        font-size: 0.9rem;
    }

    .filter-apply-btn {
        width: 100%;
        background: var(--gradient-primary);
        border: none;
        border-radius: 12px;
        color: white;
        padding: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 1rem;
    }

    .filter-apply-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    /* Tendencias */
    .trending-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .trending-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--gray-light);
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .trending-item:hover {
        background: var(--gray);
        transform: translateX(5px);
    }

    .trend-position {
        width: 32px;
        height: 32px;
        background: var(--gradient-secondary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .trend-info {
        flex: 1;
    }

    .trend-hashtag {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .trend-count {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .trending-up {
        color: #22c55e;
        font-size: 1.2rem;
    }

    /* Usuarios destacados */
    .featured-users-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .featured-user-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: var(--gray-light);
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .featured-user-item:hover {
        background: var(--gray);
        transform: translateY(-2px);
    }

    .featured-user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .featured-user-info {
        flex: 1;
        min-width: 0;
    }

    .featured-user-name {
        color: var(--text-primary);
        font-weight: 600;
        text-decoration: none;
        display: block;
        margin-bottom: 0.25rem;
    }

    .featured-user-stats {
        color: var(--text-muted);
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }

    .featured-user-bio {
        color: var(--text-secondary);
        font-size: 0.8rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .follow-btn-mini {
        background: var(--accent);
        border: none;
        border-radius: 8px;
        color: var(--text-primary);
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .follow-btn-mini:hover {
        background: var(--accent-hover);
        transform: scale(1.1);
    }

    /* Contenido principal */
    .explore-main {
        padding: 0 1rem;
    }

    .results-header {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .results-header h2 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
    }

    .results-info {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* Grid de publicaciones */
    .explore-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .explore-post-card {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(30px);
    }

    .explore-post-card.slide-up {
        animation: slideUpPost 0.6s ease forwards;
    }

    @keyframes slideUpPost {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .explore-post-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    }

    .post-header {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .post-author-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .post-author-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .post-author-name {
        color: var(--text-primary);
        font-weight: 600;
        text-decoration: none;
        margin-bottom: 0.25rem;
        display: block;
    }

    .post-author-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    .post-separator {
        color: var(--text-muted);
    }

    .post-menu-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .post-menu-btn:hover {
        background: var(--gray-light);
        color: var(--text-primary);
    }

    .post-content {
        padding: 0 1.5rem 1.5rem;
    }

    .post-text {
        color: var(--text-primary);
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .post-image-container {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        cursor: pointer;
    }

    .post-image {
        width: 100%;
        height: 250px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .post-image-container:hover .post-image {
        transform: scale(1.05);
    }

    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .post-image-container:hover .image-overlay {
        opacity: 1;
    }

    .image-overlay i {
        color: white;
        font-size: 2rem;
    }

    .post-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--glass-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .post-stats {
        display: flex;
        gap: 1rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .post-interactions {
        display: flex;
        gap: 0.5rem;
    }

    .interaction-btn {
        background: transparent;
        border: 1px solid var(--glass-border);
        color: var(--text-secondary);
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .interaction-btn:hover {
        background: var(--gray-light);
        color: var(--text-primary);
        transform: translateY(-2px);
    }

    .interaction-btn.like-btn.liked {
        color: #ff6b6b;
        border-color: rgba(255, 107, 107, 0.3);
        background: rgba(255, 107, 107, 0.1);
    }

    /* Estado vacío */
    .empty-explore {
        grid-column: 1 / -1;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
    }

    .empty-explore-content {
        text-align: center;
        max-width: 500px;
    }

    .empty-icon {
        font-size: 4rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .empty-explore h3 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .empty-explore p {
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .empty-suggestions {
        display: flex;
        justify-content: center;
        gap: 0.75rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .suggestion-tag {
        background: var(--gray-light);
        color: var(--text-secondary);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .suggestion-tag:hover {
        background: var(--accent);
        color: var(--text-primary);
        transform: translateY(-2px);
    }

    .back-to-home-btn {
        background: var(--gradient-primary);
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        transition: all 0.3s ease;
    }

    .back-to-home-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: white;
    }

    /* Animaciones */
    .fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    .slide-up {
        animation: slideUp 0.6s ease forwards;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Estados vacíos */
    .empty-trends, .empty-users {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--text-muted);
    }

    .empty-trends i, .empty-users i {
        font-size: 2rem;
        margin-bottom: 1rem;
        display: block;
    }

    /* Responsivo */
    @media (max-width: 1200px) {
        .explore-content {
            grid-template-columns: 300px 1fr;
        }
    }

    @media (max-width: 768px) {
        .explore-layout {
            padding: 1rem;
        }

        .explore-hero h1 {
            font-size: 2rem;
            flex-direction: column;
            gap: 0.5rem;
        }

        .live-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .stat-card {
            padding: 1.5rem;
        }

        .explore-content {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .explore-sidebar {
            position: static;
        }

        .explore-grid {
            grid-template-columns: 1fr;
        }

        .results-header {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
    }

    /* Asegurar que todos los títulos sean visibles */
    h1, h2, h3, h4, h5, h6 {
        color: var(--text-primary) !important;
    }
</style>

<script>
// Variables globales
const currentUserId = <?php echo $usuario_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades
    initializeLikeButtons();
    checkInitialLikeStatus();
    initializeAnimations();
    
    // Auto-submit para filtros
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
});

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
            // Actualizar el botón
            const heartIcon = button.querySelector('i');
            if (data.liked) {
                button.classList.add('liked');
                heartIcon.className = 'bi bi-heart-fill';
            } else {
                button.classList.remove('liked');
                heartIcon.className = 'bi bi-heart';
            }
            
            // Actualizar contador en stats
            const postCard = button.closest('.explore-post-card');
            const likesCount = postCard.querySelector('.stat-item span');
            if (likesCount) {
                likesCount.textContent = data.total_likes;
            }
            
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
                button.querySelector('i').className = 'bi bi-heart-fill';
            }
        })
        .catch(error => console.error('Error checking like status:', error));
    });
}

// Mostrar imagen en modal
function showImageModal(imageSrc) {
    document.getElementById('modalImage').src = '../' + imageSrc;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

// Mostrar modal de comentarios
function showCommentModal(postId) {
    document.getElementById('commentModalBody').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('commentModal'));
    modal.show();
    
    // Aquí podrías cargar los comentarios vía AJAX si tienes el endpoint
    setTimeout(() => {
        document.getElementById('commentModalBody').innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-chat-dots" style="font-size: 3rem; color: var(--text-muted);"></i>
                <h5 class="mt-3">Funcionalidad de comentarios</h5>
                <p class="text-muted">Esta funcionalidad se puede implementar conectando con tu sistema de comentarios.</p>
                <a href="inicio.php" class="btn btn-primary">Ver en inicio</a>
            </div>
        `;
    }, 1000);
}

// Compartir post
function sharePost(postId) {
    if (navigator.share) {
        navigator.share({
            title: 'Publicación en Trendly',
            text: 'Mira esta increíble publicación',
            url: window.location.origin + '/inicio.php'
        });
    } else {
        const url = window.location.origin + '/inicio.php';
        navigator.clipboard.writeText(url).then(() => {
            showToast('Enlace copiado al portapapeles', 'success');
        });
    }
}

// Menú de post
function showPostMenu(postId) {
    showToast('Funcionalidad de menú en desarrollo', 'info');
}

// Mostrar toasts
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

// Inicializar animaciones
function initializeAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('slide-up');
            }
        });
    });
    
    document.querySelectorAll('.explore-post-card, .slide-up').forEach(el => {
        observer.observe(el);
    });
}
</script>

<?php
// Incluir el footer
include '../includes/footer.php';
?>