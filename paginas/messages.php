<?php
// Iniciamos sesión antes de cualquier output
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el nombre del usuario desde la sesión
$usuario = $_SESSION['usuario'];

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

// 🆕 VERIFICAR Y AGREGAR COLUMNAS SI NO EXISTEN
// Verificar ultimo_acceso
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'ultimo_acceso'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN ultimo_acceso TIMESTAMP NULL DEFAULT NULL");
}

// Verificar en_linea
$result = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'en_linea'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE usuarios ADD COLUMN en_linea TINYINT(1) DEFAULT 0");
}

// 🆕 ACTUALIZAR ESTADO EN LÍNEA DEL USUARIO ACTUAL
$sql_update_online = "UPDATE usuarios SET en_linea = 1, ultimo_acceso = NOW() WHERE nombre = ?";
$stmt = $conn->prepare($sql_update_online);
if ($stmt) {
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->close();
}

// Verificar si la tabla mensajes existe, si no, crearla
$result = $conn->query("SHOW TABLES LIKE 'mensajes'");
if ($result->num_rows == 0) {
    // La tabla no existe, la creamos
    $sql_create_table = "CREATE TABLE `mensajes` (
        `id` int NOT NULL AUTO_INCREMENT,
        `sender_id` int NOT NULL,
        `receiver_id` int NOT NULL,
        `mensaje` text NOT NULL,
        `tipo_mensaje` ENUM('texto', 'imagen', 'audio') DEFAULT 'texto',
        `archivo_url` VARCHAR(255) NULL,
        `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `leido` tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (!$conn->query($sql_create_table)) {
        die("Error al crear la tabla mensajes: " . $conn->error);
    }
}

// Verificar si existen las columnas nuevas, si no, agregarlas
$columns_check = $conn->query("SHOW COLUMNS FROM mensajes LIKE 'tipo_mensaje'");
if ($columns_check->num_rows == 0) {
    $conn->query("ALTER TABLE mensajes ADD COLUMN tipo_mensaje ENUM('texto', 'imagen', 'audio') DEFAULT 'texto'");
    $conn->query("ALTER TABLE mensajes ADD COLUMN archivo_url VARCHAR(255) NULL");
}

// Obtener el ID del usuario actual
$sql_usuario = "SELECT id, foto_perfil FROM usuarios WHERE nombre = ?";
$stmt = $conn->prepare($sql_usuario);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result_usuario = $stmt->get_result();
$usuario_data = $result_usuario->fetch_assoc();
$usuario_id = $usuario_data['id'];
$usuario_foto_perfil = $usuario_data['foto_perfil'];
$stmt->close();

// 🆕 PROCESAR ENVÍO DE MENSAJES (TEXTO, IMAGEN, AUDIO)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['receiver_id'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message'] ?? '');
    $tipo_mensaje = 'texto';
    $archivo_url = null;
    
    // 📸 PROCESAR IMAGEN
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $tipo_mensaje = 'imagen';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 10 * 1024 * 1024) // 10MB max
        {
            $upload_dir = '../uploads/messages/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = $usuario_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $archivo_url = 'uploads/messages/' . $new_filename;
                $message = $message ?: '📷 Imagen'; // Texto por defecto si no hay mensaje
            }
        }
    }
    
    // 🎙️ PROCESAR AUDIO
    if (isset($_FILES['audio']) && $_FILES['audio']['error'] == 0) {
        $tipo_mensaje = 'audio';
        $allowed_types = ['audio/webm', 'audio/mp4', 'audio/wav', 'audio/ogg'];
        $file_type = $_FILES['audio']['type'];
        $file_size = $_FILES['audio']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= 50 * 1024 * 1024) // 50MB max
        {
            $upload_dir = '../uploads/messages/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = 'webm'; // Normalizar extensión
            $new_filename = $usuario_id . '_audio_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['audio']['tmp_name'], $upload_path)) {
                $archivo_url = 'uploads/messages/' . $new_filename;
                $message = '🎙️ Audio'; // Texto por defecto
            }
        }
    }
    
    // Solo insertar si hay contenido
    if (!empty($message) || !empty($archivo_url)) {
        $sql_insert = "INSERT INTO mensajes (sender_id, receiver_id, mensaje, tipo_mensaje, archivo_url) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("iisss", $usuario_id, $receiver_id, $message, $tipo_mensaje, $archivo_url);
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: messages.php?chat_with=".$receiver_id);
    exit();
}

// 🆕 FUNCIÓN MEJORADA PARA DETERMINAR SI UN USUARIO ESTÁ EN LÍNEA
function estaEnLinea($ultimo_acceso, $en_linea) {
    // Si no está marcado como en línea, definitivamente está offline
    if (!$en_linea) return false;
    
    // Si no hay registro de último acceso, asumir offline
    if (!$ultimo_acceso) return false;
    
    try {
        $ahora = new DateTime();
        $ultimo = new DateTime($ultimo_acceso);
        $diferencia = $ahora->getTimestamp() - $ultimo->getTimestamp();
        
        // Considerar en línea solo si fue visto en los últimos 2 minutos
        return $diferencia < 120; // 2 minutos = 120 segundos
    } catch (Exception $e) {
        return false;
    }
}

// 🆕 FUNCIÓN PARA MOSTRAR ÚLTIMO ACCESO PRECISO
function mostrarUltimoAcceso($ultimo_acceso, $en_linea) {
    if (estaEnLinea($ultimo_acceso, $en_linea)) {
        return '<span class="status-text online">En línea</span>';
    }
    
    if (!$ultimo_acceso) {
        return '<span class="status-text offline">Desconectado</span>';
    }
    
    try {
        $ahora = new DateTime();
        $ultimo = new DateTime($ultimo_acceso);
        $diferencia = $ahora->diff($ultimo);
        
        // Si fue hoy, mostrar hora exacta
        if ($ultimo->format('Y-m-d') === $ahora->format('Y-m-d')) {
            return '<span class="status-text offline">Últ. vez ' . $ultimo->format('H:i') . '</span>';
        }
        
        // Si fue ayer
        if ($ultimo->format('Y-m-d') === $ahora->modify('-1 day')->format('Y-m-d')) {
            return '<span class="status-text offline">Ayer ' . $ultimo->format('H:i') . '</span>';
        }
        
        // Si fue hace pocos días
        if ($diferencia->days <= 7) {
            $dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $dia_semana = $dias[$ultimo->format('w')];
            return '<span class="status-text offline">' . $dia_semana . ' ' . $ultimo->format('H:i') . '</span>';
        }
        
        // Si fue hace más tiempo
        return '<span class="status-text offline">' . $ultimo->format('d/m/Y H:i') . '</span>';
        
    } catch (Exception $e) {
        return '<span class="status-text offline">Desconectado</span>';
    }
}

// 🆕 LIMPIAR USUARIOS INACTIVOS AL CARGAR LA PÁGINA (reducido a 2 minutos)
$sql_cleanup = "UPDATE usuarios SET en_linea = 0 WHERE ultimo_acceso < DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND en_linea = 1";
$conn->query($sql_cleanup);

// Marcamos que la conexión a la BD no fue creada por header.php
$header_created_conn = false;

// 🆕 OBTENER CONVERSACIONES CON ESTADO EN LÍNEA
$sql_conversaciones = "
    SELECT 
        u.id, 
        u.nombre, 
        u.foto_perfil,
        u.en_linea,
        u.ultimo_acceso,
        (SELECT CASE 
            WHEN tipo_mensaje = 'imagen' THEN CONCAT('📷 ', mensaje)
            WHEN tipo_mensaje = 'audio' THEN CONCAT('🎙️ ', mensaje)
            ELSE mensaje
         END FROM mensajes 
         WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) 
         ORDER BY fecha DESC LIMIT 1) as ultimo_mensaje,
        (SELECT fecha FROM mensajes 
         WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) 
         ORDER BY fecha DESC LIMIT 1) as ultima_fecha,
        (SELECT COUNT(*) FROM mensajes 
         WHERE sender_id = u.id AND receiver_id = ? AND leido = 0) as no_leidos
    FROM mensajes m 
    JOIN usuarios u ON (m.sender_id = u.id OR m.receiver_id = u.id) 
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
    GROUP BY u.id
    ORDER BY ultima_fecha DESC
";
$stmt = $conn->prepare($sql_conversaciones);
$stmt->bind_param("iiiiiiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id, $usuario_id);
$stmt->execute();
$result_conversaciones = $stmt->get_result();
$stmt->close();

// Incluir el encabezado
include '../includes/header.php';
?>

<!-- Estilos específicos para la página de mensajes - NUEVO DISEÑO -->
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
        --online-color: #22c55e;
        --offline-color: #6b7280;
        --read-color: #1d9bf0;
        --unread-color: #6b7280;
        --recording-color: #ef4444;
    }

    /* 🆕 Indicador de estado en línea */
    .status-indicator {
        position: relative;
        display: inline-block;
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        position: absolute;
        bottom: 2px;
        right: 2px;
        border: 2px solid var(--dark-secondary);
    }

    .status-dot.online {
        background-color: var(--online-color);
    }

    .status-dot.offline {
        background-color: var(--offline-color);
    }

    .status-text.online {
        color: var(--online-color);
    }

    .status-text.offline {
        color: var(--offline-color);
    }

    /* 🆕 NUEVO CONTENEDOR PRINCIPAL */
    .messages-app {
        height: calc(100vh - 140px);
        background: var(--dark-secondary);
        border-radius: 12px;
        overflow: hidden;
        position: relative;
        margin-top: 1rem;
    }

    /* 🆕 VISTA DE CONVERSACIONES (SIDEBAR) */
    .conversations-view {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--dark-secondary);
        z-index: 20;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .conversations-view.hide-mobile {
        transform: translateX(-100%);
    }

    .conversations-header {
        padding: 1rem;
        border-bottom: 1px solid var(--gray);
        background: var(--dark-secondary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .conversations-title {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .conversations-list {
        flex: 1;
        overflow-y: auto;
        background: var(--dark-secondary);
    }

    /* 🆕 VISTA DE CHAT */
    .chat-view {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--dark);
        z-index: 10;
        display: flex;
        flex-direction: column;
        transform: translateX(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .chat-view.show-mobile {
        transform: translateX(0);
    }

    /* ELEMENTOS DE CONVERSACIÓN */
    .conversation-item {
        display: flex;
        align-items: center;
        padding: 1rem;
        gap: 12px;
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--gray-dark);
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        position: relative;
    }

    .conversation-item:hover {
        background-color: var(--gray-light);
        color: var(--text-primary);
        text-decoration: none;
    }

    .conversation-item.active {
        background-color: var(--gray);
    }

    .avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
    }

    .conversation-info {
        flex: 1;
        min-width: 0;
    }

    .conversation-name {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 4px;
        font-size: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .conversation-preview {
        color: var(--text-muted);
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .conversation-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
    }

    .conversation-time {
        color: var(--text-muted);
        font-size: 0.8rem;
        white-space: nowrap;
    }

    .unread-badge {
        background-color: var(--accent);
        color: var(--text-primary);
        font-size: 0.75rem;
        padding: 3px 8px;
        border-radius: 12px;
        min-width: 20px;
        text-align: center;
        font-weight: 600;
    }

    /* HEADER DEL CHAT */
    .chat-header {
        padding: 1rem;
        border-bottom: 1px solid var(--gray);
        display: flex;
        align-items: center;
        gap: 12px;
        background-color: var(--dark-secondary);
        flex-shrink: 0;
    }

    .back-button {
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: 1.3rem;
        padding: 0.5rem;
        margin-right: 0.5rem;
        cursor: pointer;
        transition: color 0.2s ease;
        border-radius: 8px;
        display: none;
    }

    .back-button:hover {
        background: var(--gray-light);
    }

    .chat-header-info {
        flex: 1;
        min-width: 0;
    }

    .chat-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .chat-status {
        font-size: 0.85rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
    }

    .chat-action {
        background-color: var(--gray);
        color: var(--text-secondary);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        text-decoration: none;
        flex-shrink: 0;
    }

    .chat-action:hover {
        background-color: var(--accent);
        color: var(--text-primary);
        text-decoration: none;
    }

    /* ÁREA DE MENSAJES */
    .messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        background: var(--dark);
        min-height: 0;
    }

    .date-divider {
        align-self: center;
        margin: 1.5rem 0;
    }

    .date-badge {
        background-color: var(--gray);
        color: var(--text-secondary);
        font-size: 0.8rem;
        padding: 6px 14px;
        border-radius: 16px;
    }

    .message {
        max-width: 80%;
        margin-bottom: 1.2rem;
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .message.sent {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .message.received {
        align-self: flex-start;
    }

    .message-bubble {
        background-color: var(--gray);
        padding: 0.8rem 1.1rem;
        border-radius: 20px;
        position: relative;
        max-width: 100%;
        word-wrap: break-word;
    }

    .message.sent .message-bubble {
        background-color: var(--accent);
        border-bottom-right-radius: 6px;
    }

    .message.received .message-bubble {
        background-color: var(--gray-light);
        border-bottom-left-radius: 6px;
    }

    .message-text {
        margin: 0;
        color: var(--text-primary);
        font-size: 1rem;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .message-time {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        font-size: 0.75rem;
        margin-top: 6px;
        color: var(--text-muted);
    }

    /* Ticks de lectura */
    .message-status {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .tick-icon {
        font-size: 0.75rem;
        transition: color 0.3s ease;
    }

    .tick-icon.delivered {
        color: var(--unread-color);
    }

    .tick-icon.read {
        color: var(--read-color);
    }

    /* ÁREA DE INPUT */
    .message-input-container {
        padding: 1rem;
        border-top: 1px solid var(--gray);
        background-color: var(--dark-secondary);
        flex-shrink: 0;
    }

    .media-input-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .media-button {
        background: var(--gray);
        border: none;
        color: var(--text-secondary);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .media-button:hover {
        background: var(--accent);
        color: var(--text-primary);
    }

    .media-button.recording {
        background: var(--recording-color);
        color: white;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.08); }
        100% { transform: scale(1); }
    }

    .message-input-form {
        display: flex;
        align-items: flex-end;
        gap: 10px;
    }

    .message-input {
        flex: 1;
        background-color: var(--gray-light);
        border: 1px solid var(--gray);
        color: var(--text-primary);
        border-radius: 24px;
        padding: 0.8rem 1.2rem;
        font-size: 1rem;
        resize: none;
        max-height: 120px;
        overflow-y: auto;
        min-height: 44px;
    }

    .message-input:focus {
        outline: none;
        border-color: var(--accent);
        background-color: var(--gray-light);
    }

    .send-button {
        background-color: var(--accent);
        color: var(--text-primary);
        border: none;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .send-button:hover {
        background-color: var(--accent-hover);
        transform: scale(1.05);
    }

    /* ELEMENTOS MULTIMEDIA */
    .message-image {
        max-width: 280px;
        max-height: 320px;
        border-radius: 16px;
        cursor: pointer;
        transition: transform 0.2s ease;
        margin-bottom: 0.5rem;
        width: 100%;
        height: auto;
    }

    .message-image:hover {
        transform: scale(1.02);
    }

    .message-audio {
        background: var(--gray-dark);
        border-radius: 24px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 220px;
        margin-bottom: 0.5rem;
        max-width: 100%;
    }

    .audio-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
    }

    .audio-play-btn {
        background: var(--accent);
        border: none;
        color: var(--text-primary);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 0.9rem;
        flex-shrink: 0;
    }

    .audio-play-btn:hover {
        background: var(--accent-hover);
    }

    .audio-waveform {
        flex: 1;
        height: 24px;
        background: var(--gray);
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        min-width: 0;
    }

    .audio-progress {
        height: 100%;
        background: var(--accent);
        border-radius: 12px;
        transition: width 0.1s ease;
        width: 0%;
    }

    .audio-duration {
        font-size: 0.85rem;
        color: var(--text-muted);
        min-width: 40px;
        flex-shrink: 0;
    }

    /* GRABACIÓN */
    .recording-indicator {
        display: none;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: var(--recording-color);
        border-radius: 24px;
        color: white;
        font-size: 0.9rem;
        margin-bottom: 12px;
        animation: pulse 1.5s infinite;
    }

    .recording-indicator.active {
        display: flex;
    }

    .recording-time {
        font-weight: 600;
    }

    /* MODAL IMAGEN */
    .image-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.95);
        justify-content: center;
        align-items: center;
    }

    .image-modal.active {
        display: flex;
    }

    .modal-image {
        max-width: 90%;
        max-height: 90%;
        border-radius: 12px;
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 2rem;
        font-weight: bold;
        cursor: pointer;
        transition: opacity 0.2s ease;
    }

    .modal-close:hover {
        opacity: 0.7;
    }

    /* ESTADO VACÍO */
    .empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 3rem 2rem;
    }

    .empty-icon {
        font-size: 4rem;
        color: var(--gray);
        margin-bottom: 1.5rem;
    }

    .empty-title {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 0.8rem;
        color: var(--text-primary);
    }

    .empty-text {
        color: var(--text-muted);
        max-width: 320px;
        font-size: 1.1rem;
        line-height: 1.5;
    }

    .file-input {
        display: none;
    }

    /* SCROLLBAR */
    .conversations-list::-webkit-scrollbar,
    .messages-container::-webkit-scrollbar {
        width: 6px;
    }

    .conversations-list::-webkit-scrollbar-track,
    .messages-container::-webkit-scrollbar-track {
        background: transparent;
    }

    .conversations-list::-webkit-scrollbar-thumb,
    .messages-container::-webkit-scrollbar-thumb {
        background: var(--gray);
        border-radius: 3px;
    }

    /* 🆕 RESPONSIVE MEJORADO Y ESTABLE */
    @media (min-width: 769px) {
        .messages-app {
            display: flex;
        }
        
        .conversations-view {
            position: static;
            width: 350px;
            transform: none;
            border-right: 1px solid var(--gray);
        }
        
        .chat-view {
            position: static;
            transform: none;
            flex: 1;
        }
        
        .back-button {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .messages-app {
            margin-top: 0;
            height: calc(100vh - 80px);
            border-radius: 0;
        }
        
        .back-button {
            display: inline-flex;
        }
        
        .conversations-header {
            padding: 0.8rem 1rem;
        }
        
        .conversations-title {
            font-size: 1.1rem;
        }
        
        .conversation-item {
            padding: 0.8rem 1rem;
        }
        
        .avatar {
            width: 44px;
            height: 44px;
        }
        
        .avatar-sm {
            width: 30px;
            height: 30px;
        }
        
        .conversation-name {
            font-size: 0.95rem;
        }
        
        .conversation-preview {
            font-size: 0.85rem;
        }
        
        .chat-header {
            padding: 0.8rem 1rem;
        }
        
        .chat-title {
            font-size: 1rem;
        }
        
        .chat-status {
            font-size: 0.8rem;
        }
        
        .messages-container {
            padding: 0.8rem;
        }
        
        .message {
            max-width: 90%;
        }
        
        .message-image {
            max-width: 220px;
            max-height: 280px;
        }
        
        .message-audio {
            min-width: 200px;
        }
        
        .message-input-container {
            padding: 0.8rem;
        }
        
        .media-button {
            width: 36px;
            height: 36px;
            font-size: 0.9rem;
        }
        
        .send-button {
            width: 40px;
            height: 40px;
        }
        
        .message-input {
            font-size: 0.95rem;
            padding: 0.7rem 1rem;
        }
    }

    @media (max-width: 480px) {
        .conversations-header {
            padding: 0.6rem 0.8rem;
        }
        
        .conversation-item {
            padding: 0.6rem 0.8rem;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
        }
        
        .conversation-name {
            font-size: 0.9rem;
        }
        
        .conversation-preview {
            font-size: 0.8rem;
        }
        
        .message {
            max-width: 95%;
        }
        
        .message-image {
            max-width: 200px;
            max-height: 240px;
        }
        
        .message-input {
            font-size: 0.9rem;
        }
        
        .modal-close {
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
        }
    }
</style>

<div class="messages-app">
    <!-- 🆕 VISTA DE CONVERSACIONES -->
    <div class="conversations-view" id="conversationsView">
        <div class="conversations-header">
            <h6 class="conversations-title">Mensajes</h6>
        </div>
        <div class="conversations-list">
            <?php if ($result_conversaciones->num_rows > 0): ?>
                <?php while ($row = $result_conversaciones->fetch_assoc()): ?>
                    <?php $esta_en_linea = estaEnLinea($row['ultimo_acceso'], $row['en_linea']); ?>
                    <!-- 🔧 ENLACES NORMALES PARA DESKTOP, ONCLICK SOLO PARA MÓVIL -->
                    <a href="?chat_with=<?php echo $row['id']; ?>" 
                       class="conversation-item <?php echo (isset($_GET['chat_with']) && $_GET['chat_with'] == $row['id']) ? 'active' : ''; ?>"
                       onclick="handleConversationClick(event, <?php echo $row['id']; ?>)">
                        <div class="status-indicator">
                            <img src="../<?php echo $row['foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                 alt="Avatar" 
                                 class="avatar">
                            <div class="status-dot <?php echo $esta_en_linea ? 'online' : 'offline'; ?>"></div>
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name"><?php echo htmlspecialchars($row['nombre']); ?></div>
                            <div class="conversation-preview"><?php echo htmlspecialchars($row['ultimo_mensaje'] ?: 'Sin mensajes'); ?></div>
                        </div>
                        <div class="conversation-meta">
                            <div class="conversation-time">
                                <?php 
                                    if($row['ultima_fecha']) {
                                        $date = new DateTime($row['ultima_fecha']);
                                        echo $date->format('H:i'); 
                                    }
                                ?>
                            </div>
                            <?php if ($row['no_leidos'] > 0): ?>
                                <div class="unread-badge"><?php echo $row['no_leidos']; ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-4 text-center">
                    <p class="text-muted">No tienes conversaciones</p>
                    <small class="d-block mt-3">Visita perfiles para iniciar chats</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 🆕 VISTA DE CHAT -->
    <div class="chat-view <?php echo isset($_GET['chat_with']) ? 'show-mobile' : ''; ?>" id="chatView">
        <?php if (isset($_GET['chat_with'])): ?>
            <?php
            $chat_with = intval($_GET['chat_with']);
            
            // MARCAR MENSAJES COMO LEÍDOS
            $sql_mark_read = "UPDATE mensajes SET leido = 1 
                             WHERE sender_id = ? AND receiver_id = ? AND leido = 0";
            $stmt = $conn->prepare($sql_mark_read);
            $stmt->bind_param("ii", $chat_with, $usuario_id);
            $stmt->execute();
            $stmt->close();
            
            // OBTENER MENSAJES
            $sql_chat = "SELECT m.*, u.nombre AS sender_name, u.foto_perfil AS sender_foto_perfil
                         FROM mensajes m 
                         JOIN usuarios u ON m.sender_id = u.id 
                         WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                            OR (m.sender_id = ? AND m.receiver_id = ?) 
                         ORDER BY m.fecha ASC";
            $stmt = $conn->prepare($sql_chat);
            $stmt->bind_param("iiii", $usuario_id, $chat_with, $chat_with, $usuario_id);
            $stmt->execute();
            $result_chat = $stmt->get_result();
            $stmt->close();

            // OBTENER DATOS DEL RECEPTOR
            $sql_receiver = "SELECT nombre, foto_perfil, en_linea, ultimo_acceso FROM usuarios WHERE id = ?";
            $stmt = $conn->prepare($sql_receiver);
            $stmt->bind_param("i", $chat_with);
            $stmt->execute();
            $receiver_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $receiver_name = $receiver_data['nombre'];
            $receiver_foto_perfil = $receiver_data['foto_perfil'];
            $receiver_esta_en_linea = estaEnLinea($receiver_data['ultimo_acceso'], $receiver_data['en_linea']);
            ?>
            
            <div class="chat-header">
                <button class="back-button" onclick="closeChat()">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="status-indicator">
                    <img src="../<?php echo $receiver_foto_perfil ?: 'uploads/default.png'; ?>" 
                         alt="Avatar" 
                         class="avatar">
                    <div class="status-dot <?php echo $receiver_esta_en_linea ? 'online' : 'offline'; ?>"></div>
                </div>
                <div class="chat-header-info">
                    <h6 class="chat-title"><?php echo htmlspecialchars($receiver_name); ?></h6>
                    <div class="chat-status">
                        <?php echo mostrarUltimoAcceso($receiver_data['ultimo_acceso'], $receiver_data['en_linea']); ?>
                    </div>
                </div>
                <a href="perfil_usuario.php?id=<?php echo $chat_with; ?>" class="chat-action">
                    <i class="bi bi-person"></i>
                </a>
            </div>
            
            <div class="messages-container" id="messagesContainer">
                <?php 
                $prev_date = null;
                $messages_by_date = array();
                
                // Organizar mensajes por fecha
                while ($row = $result_chat->fetch_assoc()) {
                    $message_date = date('Y-m-d', strtotime($row['fecha']));
                    if (!isset($messages_by_date[$message_date])) {
                        $messages_by_date[$message_date] = array();
                    }
                    $messages_by_date[$message_date][] = $row;
                }
                
                foreach ($messages_by_date as $date => $messages):
                    $date_obj = new DateTime($date);
                    $today = new DateTime();
                    $yesterday = new DateTime('-1 day');
                    
                    if ($date_obj->format('Y-m-d') === $today->format('Y-m-d')) {
                        $display_date = 'Hoy';
                    } elseif ($date_obj->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                        $display_date = 'Ayer';
                    } else {
                        $display_date = $date_obj->format('d/m/Y');
                    }
                ?>
                    <div class="date-divider">
                        <span class="date-badge"><?php echo $display_date; ?></span>
                    </div>
                    
                    <?php foreach ($messages as $row): ?>
                        <div class="message <?php echo $row['sender_id'] == $usuario_id ? 'sent' : 'received'; ?>">
                            <img src="../<?php echo $row['sender_foto_perfil'] ?: 'uploads/default.png'; ?>" 
                                 alt="Avatar" 
                                 class="avatar-sm">
                            <div class="message-bubble">
                                
                                <!-- CONTENIDO MULTIMEDIA -->
                                <?php if ($row['tipo_mensaje'] == 'imagen' && $row['archivo_url']): ?>
                                    <img src="../<?php echo $row['archivo_url']; ?>" 
                                         alt="Imagen" 
                                         class="message-image" 
                                         onclick="openImageModal(this.src)">
                                <?php endif; ?>
                                
                                <?php if ($row['tipo_mensaje'] == 'audio' && $row['archivo_url']): ?>
                                    <div class="message-audio">
                                        <div class="audio-controls">
                                            <button class="audio-play-btn" onclick="toggleAudio(this)" data-src="../<?php echo $row['archivo_url']; ?>">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                            <div class="audio-waveform">
                                                <div class="audio-progress"></div>
                                            </div>
                                            <span class="audio-duration">0:00</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- TEXTO DEL MENSAJE -->
                                <?php if (!empty($row['mensaje'])): ?>
                                    <p class="message-text"><?php echo nl2br(htmlspecialchars($row['mensaje'])); ?></p>
                                <?php endif; ?>
                                
                                <div class="message-time">
                                    <span><?php echo date('H:i', strtotime($row['fecha'])); ?></span>
                                    
                                    <?php if ($row['sender_id'] == $usuario_id): ?>
                                        <div class="message-status">
                                            <?php if ($row['leido'] == 1): ?>
                                                <i class="bi bi-check2-all tick-icon read" title="Leído"></i>
                                            <?php else: ?>
                                                <i class="bi bi-check2-all tick-icon delivered" title="Entregado"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- ÁREA DE INPUT -->
            <div class="message-input-container">
                <!-- Indicador de grabación -->
                <div class="recording-indicator" id="recordingIndicator">
                    <i class="bi bi-mic-fill"></i>
                    <span>Grabando audio...</span>
                    <span class="recording-time" id="recordingTime">0:00</span>
                </div>
                
                <!-- Botones multimedia -->
                <div class="media-input-container">
                    <button type="button" class="media-button no-loading" onclick="document.getElementById('imageInput').click()">
                        <i class="bi bi-image"></i>
                    </button>
                    <button type="button" class="media-button" id="audioButton" onclick="toggleRecording()">
                        <i class="bi bi-mic"></i>
                    </button>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" class="message-input-form no-loading" id="messageForm">
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_with; ?>">
                    <input type="file" id="imageInput" name="image" accept="image/*" class="file-input" onchange="previewAndSend(this)">
                    <input type="file" id="audioInput" name="audio" accept="audio/*" class="file-input">
                    
                    <textarea class="form-control message-input" 
                              name="message" 
                              placeholder="Escribe un mensaje..." 
                              rows="1"></textarea>
                    <button type="submit" class="send-button">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-chat-square-text empty-icon"></i>
                <h4 class="empty-title">Tus mensajes</h4>
                <p class="empty-text">Selecciona una conversación para comenzar a chatear</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para mostrar imágenes -->
<div class="image-modal" id="imageModal" onclick="closeImageModal()">
    <span class="modal-close">&times;</span>
    <img class="modal-image" id="modalImage" src="">
</div>

<script>
    // Variables globales
    let mediaRecorder;
    let audioChunks = [];
    let isRecording = false;
    let recordingTimer;
    let recordingSeconds = 0;
    let currentAudio = null;
    let isMobile = window.innerWidth <= 768;

    document.addEventListener('DOMContentLoaded', function() {
        // Detectar cambios de tamaño
        window.addEventListener('resize', function() {
            isMobile = window.innerWidth <= 768;
            initializeLayout();
        });

        // Inicializar layout
        initializeLayout();

        // Scroll al final del chat
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Auto-expandir textarea
        const textarea = document.querySelector('.message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
                if (this.scrollHeight > 120) {
                    this.style.overflow = 'auto';
                }
            });
            
            // Enviar con Enter
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.form.submit();
                }
            });
        }
        
        // 🆕 ACTUALIZAR ESTADO EN LÍNEA CADA 15 SEGUNDOS
        setInterval(function() {
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'keep_alive=1'
            }).catch(err => {
                console.log('Error actualizando estado:', err);
            });
        }, 15000); // Cada 15 segundos

        // 🆕 LIMPIAR USUARIOS INACTIVOS CADA 30 SEGUNDOS
        setInterval(function() {
            fetch('update_online_status.php', {
                method: 'GET'
            }).catch(err => {
                console.log('Error en cleanup:', err);
            });
        }, 30000); // Cada 30 segundos

        // 🆕 RECARGAR ESTADO DE CONVERSACIONES CADA MINUTO
        setInterval(function() {
            // Solo recargar si no estamos en un chat específico
            if (!new URLSearchParams(window.location.search).get('chat_with')) {
                location.reload();
            }
        }, 60000); // Cada minuto
    });

    // 🆕 INICIALIZAR LAYOUT SEGÚN PANTALLA
    function initializeLayout() {
        const conversationsView = document.getElementById('conversationsView');
        const chatView = document.getElementById('chatView');
        
        if (!isMobile) {
            // DESKTOP: Siempre mostrar ambas vistas
            conversationsView.classList.remove('hide-mobile');
            chatView.classList.remove('show-mobile');
        } else {
            // MÓVIL: Mostrar según URL
            const urlParams = new URLSearchParams(window.location.search);
            const chatWith = urlParams.get('chat_with');
            
            if (chatWith) {
                conversationsView.classList.add('hide-mobile');
                chatView.classList.add('show-mobile');
            } else {
                conversationsView.classList.remove('hide-mobile');
                chatView.classList.remove('show-mobile');
            }
        }
    }

    // 🆕 MANEJAR CLICK EN CONVERSACIÓN
    function handleConversationClick(event, userId) {
        if (isMobile) {
            // EN MÓVIL: Prevenir navegación normal y usar JavaScript
            event.preventDefault();
            openChatMobile(userId);
        }
        // EN DESKTOP: Dejar que funcione la navegación normal (href)
    }

    // 🆕 ABRIR CHAT EN MÓVIL
    function openChatMobile(userId) {
        // Mostrar vista de chat
        document.getElementById('conversationsView').classList.add('hide-mobile');
        document.getElementById('chatView').classList.add('show-mobile');
        
        // Actualizar URL y recargar para obtener contenido del chat
        const newUrl = `${window.location.pathname}?chat_with=${userId}`;
        window.location.href = newUrl;
    }

    // 🆕 CERRAR CHAT (SOLO MÓVIL)
    function closeChat() {
        if (isMobile) {
            document.getElementById('conversationsView').classList.remove('hide-mobile');
            document.getElementById('chatView').classList.remove('show-mobile');
            
            // Actualizar URL
            const newUrl = window.location.pathname;
            window.history.pushState({}, '', newUrl);
        }
    }

    // 🆕 MANEJAR BOTÓN ATRÁS DEL NAVEGADOR
    window.addEventListener('popstate', function(event) {
        if (isMobile) {
            const urlParams = new URLSearchParams(window.location.search);
            const chatWith = urlParams.get('chat_with');
            
            if (!chatWith) {
                closeChat();
            }
        }
    });

    // 📸 FUNCIONES PARA IMÁGENES
    function previewAndSend(input) {
        if (input.files && input.files[0]) {
            document.getElementById('messageForm').submit();
        }
    }

    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('active');
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('active');
    }

    // 🎙️ FUNCIONES PARA AUDIO
    async function toggleRecording() {
        if (!isRecording) {
            await startRecording();
        } else {
            stopRecording();
        }
    }

    async function startRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = event => {
                audioChunks.push(event.data);
            };
            
            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                sendAudioMessage(audioBlob);
            };
            
            mediaRecorder.start();
            isRecording = true;
            recordingSeconds = 0;
            
            const audioButton = document.getElementById('audioButton');
            const recordingIndicator = document.getElementById('recordingIndicator');
            
            if (audioButton) audioButton.classList.add('recording');
            if (recordingIndicator) recordingIndicator.classList.add('active');
            
            recordingTimer = setInterval(() => {
                recordingSeconds++;
                const minutes = Math.floor(recordingSeconds / 60);
                const seconds = recordingSeconds % 60;
                const timeElement = document.getElementById('recordingTime');
                if (timeElement) {
                    timeElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
            }, 1000);
            
        } catch (error) {
            console.error('Error al acceder al micrófono:', error);
            alert('No se pudo acceder al micrófono. Verifica los permisos.');
        }
    }

    function stopRecording() {
        if (mediaRecorder && isRecording) {
            mediaRecorder.stop();
            mediaRecorder.stream.getTracks().forEach(track => track.stop());
            isRecording = false;
            
            const audioButton = document.getElementById('audioButton');
            const recordingIndicator = document.getElementById('recordingIndicator');
            
            if (audioButton) audioButton.classList.remove('recording');
            if (recordingIndicator) recordingIndicator.classList.remove('active');
            if (recordingTimer) clearInterval(recordingTimer);
        }
    }

    function sendAudioMessage(audioBlob) {
        const receiverInput = document.querySelector('input[name="receiver_id"]');
        if (!receiverInput) return;
        
        const formData = new FormData();
        formData.append('audio', audioBlob, 'audio_message.webm');
        formData.append('receiver_id', receiverInput.value);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (response.ok) {
                location.reload();
            }
        }).catch(error => {
            console.error('Error enviando audio:', error);
        });
    }

    // 🔊 REPRODUCIR AUDIOS
    function toggleAudio(button) {
        const audioSrc = button.dataset.src;
        const icon = button.querySelector('i');
        const progressBar = button.parentElement.querySelector('.audio-progress');
        const durationSpan = button.parentElement.querySelector('.audio-duration');
        
        if (currentAudio && !currentAudio.paused) {
            currentAudio.pause();
            icon.className = 'bi bi-play-fill';
            currentAudio = null;
        } else {
            if (currentAudio) {
                currentAudio.pause();
                document.querySelectorAll('.audio-play-btn i').forEach(i => {
                    i.className = 'bi bi-play-fill';
                });
            }
            
            currentAudio = new Audio(audioSrc);
            icon.className = 'bi bi-pause-fill';
            
            currentAudio.addEventListener('loadedmetadata', () => {
                const duration = Math.floor(currentAudio.duration);
                const minutes = Math.floor(duration / 60);
                const seconds = duration % 60;
                durationSpan.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            });
            
            currentAudio.addEventListener('timeupdate', () => {
                const progress = (currentAudio.currentTime / currentAudio.duration) * 100;
                progressBar.style.width = progress + '%';
            });
            
            currentAudio.addEventListener('ended', () => {
                icon.className = 'bi bi-play-fill';
                progressBar.style.width = '0%';
                currentAudio = null;
            });
            
            currentAudio.play().catch(error => {
                console.error('Error reproduciendo audio:', error);
                icon.className = 'bi bi-play-fill';
                currentAudio = null;
            });
        }
    }

    // Marcar como desconectado al cerrar
    window.addEventListener('beforeunload', function() {
        // Usar sendBeacon para mejor compatibilidad
        if (navigator.sendBeacon) {
            navigator.sendBeacon('update_online_status.php', 'offline=1');
        } else {
            // Fallback para navegadores más antiguos
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'offline=1',
                keepalive: true
            }).catch(() => {});
        }
    });

    // 🆕 DETECTAR CUANDO LA PESTAÑA PIERDE EL FOCO
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // Usuario cambió de pestaña o minimizó
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'offline=1',
                keepalive: true
            }).catch(() => {});
        } else if (document.visibilityState === 'visible') {
            // Usuario regresó a la pestaña
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'keep_alive=1'
            }).catch(() => {});
        }
    });

    // 🆕 DETECTAR INACTIVIDAD DEL USUARIO
    let inactivityTimer;
    let isUserActive = true;

    function resetInactivityTimer() {
        clearTimeout(inactivityTimer);
        
        if (!isUserActive) {
            // Usuario regresó, marcar como activo
            isUserActive = true;
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'keep_alive=1'
            }).catch(() => {});
        }
        
        // Si no hay actividad en 1 minuto, marcar como offline
        inactivityTimer = setTimeout(() => {
            isUserActive = false;
            fetch('update_online_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'offline=1',
                keepalive: true
            }).catch(() => {});
        }, 60000); // 1 minuto
    }

    // Eventos para detectar actividad del usuario
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer, true);
    });

    // Inicializar el timer
    resetInactivityTimer();
</script>

<?php include '../includes/footer.php'; ?>