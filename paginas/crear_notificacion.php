<?php
function crearNotificacion($conn, $usuario_id, $tipo, $de_usuario_id, $publicacion_id = null) {
    // No crear notificación para uno mismo
    if ($usuario_id == $de_usuario_id) {
        return false;
    }
    
    $mensaje = '';
    switch ($tipo) {
        case 'like':
            $mensaje = 'le gustó tu publicación';
            break;
        case 'comentario':
            $mensaje = 'comentó tu publicación';
            break;
        case 'seguidor':
            $mensaje = 'comenzó a seguirte';
            break;
        case 'mencion':
            $mensaje = 'te mencionó en una publicación';
            break;
    }
    
    $sql = "INSERT INTO notificaciones (usuario_id, tipo, de_usuario_id, publicacion_id, mensaje) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issis", $usuario_id, $tipo, $de_usuario_id, $publicacion_id, $mensaje);
    
    return $stmt->execute();
}
?>