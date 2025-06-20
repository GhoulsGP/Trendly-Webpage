<?php
// filepath: /Applications/MAMP/htdocs/Trendly-macOS/paginas/notificaciones.php
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
$stmt->close();

// Marcar notificaciones como leídas si se hace clic en "marcar todas como leídas"
if (isset($_POST['marcar_leidas'])) {
    $sql_marcar = "UPDATE notificaciones SET leida = TRUE WHERE usuario_id = ?";
    $stmt = $conn->prepare($sql_marcar);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: notificaciones.php?success=marcadas");
    exit();
}

// Marcar una notificación específica como leída
if (isset($_POST['marcar_leida']) && isset($_POST['notificacion_id'])) {
    $notificacion_id = (int)$_POST['notificacion_id'];
    $sql_marcar_una = "UPDATE notificaciones SET leida = TRUE WHERE id = ? AND usuario_id = ?";
    $stmt = $conn->prepare($sql_marcar_una);
    $stmt->bind_param("ii", $notificacion_id, $usuario_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true]);
    exit();
}

// Obtener notificaciones del usuario
$sql_notificaciones = "
    SELECT n.id, n.tipo, n.mensaje, n.leida, n.fecha, n.publicacion_id,
           u.id as de_usuario_id, u.nombre as de_usuario, u.foto_perfil as de_foto,
           p.contenido as post_contenido, p.imagen as post_imagen
    FROM notificaciones n 
    JOIN usuarios u ON n.de_usuario_id = u.id 
    LEFT JOIN publicaciones p ON n.publicacion_id = p.id 
    WHERE n.usuario_id = ? 
    ORDER BY n.fecha DESC 
    LIMIT 50
";

$stmt = $conn->prepare($sql_notificaciones);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result_notificaciones = $stmt->get_result();
$stmt->close();

// Contar notificaciones no leídas
$sql_count = "SELECT COUNT(*) as total_no_leidas FROM notificaciones WHERE usuario_id = ? AND leida = FALSE";
$stmt = $conn->prepare($sql_count);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result_count = $stmt->get_result();
$count_data = $result_count->fetch_assoc();
$total_no_leidas = $count_data['total_no_leidas'];
$stmt->close();

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// Incluir header
include '../includes/header.php';
?>

<div class="notifications-container">
    <div class="container-fluid px-4">
        <div class="notifications-layout">
            
            <!-- Header de notificaciones -->
            <div class="notifications-header fade-in">
                <div class="header-content">
                    <div class="header-info">
                        <h1><i class="bi bi-bell-fill me-3"></i>Notificaciones</h1>
                        <p>Mantente al día con toda tu actividad</p>
                    </div>
                    
                    <div class="header-stats">
                        <div class="stat-badge">
                            <span class="badge-number"><?php echo $total_no_leidas; ?></span>
                            <span class="badge-label">Sin leer</span>
                        </div>
                        
                        <?php if ($total_no_leidas > 0): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="marcar_leidas" class="mark-all-btn">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Marcar todas como leídas
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Lista de notificaciones -->
            <div class="notifications-content">
                <div class="notifications-list">
                    <?php if ($result_notificaciones->num_rows > 0): ?>
                        <?php $notification_index = 0; ?>
                        <?php while ($notificacion = $result_notificaciones->fetch_assoc()): ?>
                            <div class="notification-item <?php echo !$notificacion['leida'] ? 'unread' : ''; ?> slide-up" 
                                 style="animation-delay: <?php echo ($notification_index * 0.1); ?>s"
                                 data-notification-id="<?php echo $notificacion['id']; ?>">
                                
                                <!-- Avatar y contenido -->
                                <div class="notification-avatar">
                                    <img src="../<?php echo $notificacion['de_foto'] ?: 'uploads/default.png'; ?>" 
                                         alt="Avatar" 
                                         class="avatar-img">
                                    <div class="notification-type-icon <?php echo $notificacion['tipo']; ?>-icon">
                                        <?php
                                        switch($notificacion['tipo']) {
                                            case 'like':
                                                echo '<i class="bi bi-heart-fill"></i>';
                                                break;
                                            case 'comentario':
                                                echo '<i class="bi bi-chat-fill"></i>';
                                                break;
                                            case 'seguidor':
                                                echo '<i class="bi bi-person-plus-fill"></i>';
                                                break;
                                            case 'mencion':
                                                echo '<i class="bi bi-at"></i>';
                                                break;
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="notification-content">
                                    <div class="notification-header">
                                        <div class="notification-text">
                                            <a href="perfil_usuario.php?id=<?php echo $notificacion['de_usuario_id']; ?>" 
                                               class="user-link">
                                                <?php echo htmlspecialchars($notificacion['de_usuario']); ?>
                                            </a>
                                            <span class="action-text">
                                                <?php
                                                switch($notificacion['tipo']) {
                                                    case 'like':
                                                        echo 'le gustó tu publicación';
                                                        break;
                                                    case 'comentario':
                                                        echo 'comentó tu publicación';
                                                        break;
                                                    case 'seguidor':
                                                        echo 'comenzó a seguirte';
                                                        break;
                                                    case 'mencion':
                                                        echo 'te mencionó en una publicación';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="notification-meta">
                                            <span class="notification-time">
                                                <?php echo timeAgo($notificacion['fecha']); ?>
                                            </span>
                                            <?php if (!$notificacion['leida']): ?>
                                                <span class="unread-dot"></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Preview del post si aplica -->
                                    <?php if ($notificacion['publicacion_id'] && ($notificacion['tipo'] == 'like' || $notificacion['tipo'] == 'comentario')): ?>
                                        <div class="post-preview" onclick="window.location.href='inicio.php#post-<?php echo $notificacion['publicacion_id']; ?>'">
                                            <?php if (!empty($notificacion['post_imagen'])): ?>
                                                <img src="../<?php echo $notificacion['post_imagen']; ?>" 
                                                     alt="Post" 
                                                     class="post-preview-img">
                                            <?php endif; ?>
                                            <div class="post-preview-text">
                                                <?php echo htmlspecialchars(substr($notificacion['post_contenido'], 0, 100)) . '...'; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="notification-actions">
                                    <?php if (!$notificacion['leida']): ?>
                                        <button class="action-btn mark-read-btn" 
                                                onclick="markAsRead(<?php echo $notificacion['id']; ?>)"
                                                title="Marcar como leída">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn delete-btn" 
                                            onclick="deleteNotification(<?php echo $notificacion['id']; ?>)"
                                            title="Eliminar notificación">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php $notification_index++; ?>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <!-- Estado vacío -->
                        <div class="empty-notifications slide-up">
                            <div class="empty-content">
                                <i class="bi bi-bell empty-icon"></i>
                                <h3>No tienes notificaciones</h3>
                                <p>Cuando alguien interactúe con tu contenido, aparecerá aquí</p>
                                <a href="inicio.php" class="back-to-feed-btn">
                                    <i class="bi bi-house-fill me-2"></i>
                                    Ir al inicio
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
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
        --like-color: #ff6b6b;
        --comment-color: #4ecdc4;
        --follow-color: #45b7d1;
        --mention-color: #f9ca24;
    }

    body {
        background: var(--dark);
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: 0;
        padding: 0;
    }

    .notifications-container {
        min-height: 100vh;
        background: var(--dark);
        padding-top: 80px;
    }

    .notifications-layout {
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem 0;
    }

    /* Header */
    .notifications-header {
        margin-bottom: 2rem;
    }

    .header-content {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-info h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }

    .header-info p {
        color: var(--text-secondary);
        margin: 0;
        font-size: 1rem;
    }

    .header-stats {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-badge {
        text-align: center;
        padding: 1rem;
        background: var(--gray-light);
        border-radius: 16px;
        min-width: 80px;
    }

    .badge-number {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .badge-label {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .mark-all-btn {
        background: var(--gradient-primary);
        border: none;
        border-radius: 12px;
        color: white;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
    }

    .mark-all-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    /* Lista de notificaciones */
    .notifications-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .notification-item {
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.3s ease;
        opacity: 0;
        transform: translateY(20px);
        cursor: pointer;
    }

    .notification-item.slide-up {
        animation: slideUpNotification 0.6s ease forwards;
    }

    @keyframes slideUpNotification {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    }

    .notification-item.unread {
        background: linear-gradient(135deg, var(--dark-secondary) 0%, rgba(102, 126, 234, 0.1) 100%);
        border-color: rgba(102, 126, 234, 0.3);
    }

    /* Avatar */
    .notification-avatar {
        position: relative;
        flex-shrink: 0;
    }

    .avatar-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--glass-border);
    }

    .notification-type-icon {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        border: 2px solid var(--dark-secondary);
    }

    .like-icon {
        background: var(--like-color);
        color: white;
    }

    .comentario-icon {
        background: var(--comment-color);
        color: white;
    }

    .seguidor-icon {
        background: var(--follow-color);
        color: white;
    }

    .mencion-icon {
        background: var(--mention-color);
        color: white;
    }

    /* Contenido */
    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }

    .notification-text {
        flex: 1;
    }

    .user-link {
        color: var(--text-primary);
        font-weight: 600;
        text-decoration: none;
        margin-right: 0.5rem;
    }

    .user-link:hover {
        color: var(--text-secondary);
    }

    .action-text {
        color: var(--text-secondary);
    }

    .notification-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .notification-time {
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .unread-dot {
        width: 8px;
        height: 8px;
        background: var(--like-color);
        border-radius: 50%;
    }

    /* Preview del post */
    .post-preview {
        background: var(--gray-light);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 0.75rem;
        display: flex;
        gap: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .post-preview:hover {
        background: var(--gray);
    }

    .post-preview-img {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .post-preview-text {
        color: var(--text-secondary);
        font-size: 0.9rem;
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    /* Acciones */
    .notification-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .action-btn {
        background: var(--gray-light);
        border: 1px solid var(--glass-border);
        color: var(--text-secondary);
        padding: 0.5rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn:hover {
        background: var(--accent);
        color: var(--text-primary);
    }

    .mark-read-btn:hover {
        background: var(--follow-color);
        color: white;
    }

    .delete-btn:hover {
        background: var(--like-color);
        color: white;
    }

    /* Estado vacío */
    .empty-notifications {
        text-align: center;
        padding: 4rem 2rem;
        background: var(--dark-secondary);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
    }

    .empty-icon {
        font-size: 4rem;
        color: var(--text-muted);
        margin-bottom: 1.5rem;
    }

    .empty-notifications h3 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .empty-notifications p {
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .back-to-feed-btn {
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

    .back-to-feed-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        color: white;
    }

    /* Animaciones */
    .fade-in {
        animation: fadeIn 0.8s ease forwards;
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

    /* Responsivo */
    @media (max-width: 768px) {
        .notifications-layout {
            padding: 1rem;
        }

        .header-content {
            flex-direction: column;
            gap: 1.5rem;
            text-align: center;
        }

        .header-stats {
            justify-content: center;
        }

        .notification-item {
            padding: 1rem;
        }

        .notification-actions {
            flex-direction: row;
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
    // Inicializar animaciones
    initializeAnimations();
    
    // Auto-marcar como leída al hacer click en la notificación
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // No marcar si se hace click en los botones de acción
            if (e.target.closest('.notification-actions')) {
                return;
            }
            
            const notificationId = this.getAttribute('data-notification-id');
            if (this.classList.contains('unread')) {
                markAsRead(notificationId, false);
            }
        });
    });
    
    // Mostrar mensaje de éxito si viene de URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'marcadas') {
        showToast('Todas las notificaciones han sido marcadas como leídas', 'success');
    }
});

// Marcar notificación como leída
function markAsRead(notificationId, showToastMessage = true) {
    fetch('notificaciones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `marcar_leida=1&notificacion_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
            notificationItem.classList.remove('unread');
            
            // Remover el punto de no leída
            const unreadDot = notificationItem.querySelector('.unread-dot');
            if (unreadDot) {
                unreadDot.remove();
            }
            
            // Remover el botón de marcar como leída
            const markReadBtn = notificationItem.querySelector('.mark-read-btn');
            if (markReadBtn) {
                markReadBtn.remove();
            }
            
            // Actualizar contador en header
            updateUnreadCount();
            
            if (showToastMessage) {
                showToast('Notificación marcada como leída', 'success');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al marcar notificación', 'error');
    });
}

// Eliminar notificación (simulado por ahora)
function deleteNotification(notificationId) {
    if (confirm('¿Estás seguro de que quieres eliminar esta notificación?')) {
        // Aquí implementarías la lógica para eliminar
        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
        notificationItem.style.animation = 'slideOut 0.3s ease forwards';
        
        setTimeout(() => {
            notificationItem.remove();
            showToast('Notificación eliminada', 'success');
            updateUnreadCount();
        }, 300);
    }
}

// Actualizar contador de no leídas
function updateUnreadCount() {
    const unreadItems = document.querySelectorAll('.notification-item.unread');
    const countElement = document.querySelector('.badge-number');
    const newCount = unreadItems.length;
    
    if (countElement) {
        countElement.textContent = newCount;
    }
    
    // Ocultar botón de marcar todas si no hay no leídas
    if (newCount === 0) {
        const markAllBtn = document.querySelector('.mark-all-btn');
        if (markAllBtn) {
            markAllBtn.style.display = 'none';
        }
    }
}

// Función para tiempo relativo
function timeAgo(dateString) {
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
    
    document.querySelectorAll('.notification-item, .slide-up').forEach(el => {
        observer.observe(el);
    });
}

// Animación de salida
const slideOutKeyframes = `
    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;

const style = document.createElement('style');
style.textContent = slideOutKeyframes;
document.head.appendChild(style);
</script>

<?php
// Función para tiempo relativo (PHP)
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Ahora mismo';
    if ($time < 3600) return floor($time/60) . 'm';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 604800) return floor($time/86400) . 'd';
    
    return date('d M Y', strtotime($datetime));
}

// Incluir el footer
include '../includes/footer.php';
?>