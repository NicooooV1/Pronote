<?php
/**
 * Gestion des notifications WebSocket
 * Permet au back-end PHP d'émettre des événements vers le serveur WS
 */

namespace API\Core;

class WebSocket {
    private static $wsUrl;
    private static $apiSecret;
    private static $enabled = true;
    
    /**
     * Initialiser la configuration WebSocket
     */
    public static function init() {
        self::$wsUrl = getenv('WEBSOCKET_URL') ?: 'http://localhost:3000';
        self::$apiSecret = getenv('WEBSOCKET_API_SECRET') ?: '';
        self::$enabled = getenv('WEBSOCKET_ENABLED') !== 'false';
    }
    
    /**
     * Envoyer une notification HTTP au serveur WS
     */
    private static function sendNotification($endpoint, $data) {
        if (!self::$enabled) {
            return ['success' => false, 'message' => 'WebSocket disabled'];
        }
        
        if (!self::$apiSecret) {
            error_log('WebSocket: API_SECRET not configured');
            return ['success' => false, 'message' => 'API_SECRET missing'];
        }
        
        $data['secret'] = self::$apiSecret;
        
        $ch = curl_init(self::$wsUrl . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 2, // Timeout court pour ne pas bloquer
            CURLOPT_CONNECTTIMEOUT => 1
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("WebSocket notification error: $error");
            return ['success' => false, 'message' => $error];
        }
        
        if ($httpCode !== 200) {
            error_log("WebSocket notification failed: HTTP $httpCode");
            return ['success' => false, 'message' => "HTTP $httpCode"];
        }
        
        return ['success' => true, 'response' => json_decode($response, true)];
    }
    
    /**
     * Notifier un nouveau message dans une conversation
     */
    public static function notifyNewMessage($convId, $messageData) {
        return self::sendNotification('/notify/message', [
            'convId' => $convId,
            'message' => $messageData
        ]);
    }
    
    /**
     * Notifier un utilisateur (notification personnelle)
     */
    public static function notifyUser($userId, $data) {
        return self::sendNotification('/notify/notification', [
            'userId' => $userId,
            'data' => $data
        ]);
    }
    
    /**
     * Notifier une nouvelle note
     */
    public static function notifyNewGrade($eleveId, $gradeData) {
        return self::sendNotification('/notify/grade', [
            'eleveId' => $eleveId,
            'gradeData' => $gradeData
        ]);
    }
    
    /**
     * Notifier une nouvelle absence
     */
    public static function notifyNewAbsence($eleveId, $absenceData) {
        return self::sendNotification('/notify/absence', [
            'eleveId' => $eleveId,
            'absenceData' => $absenceData
        ]);
    }
    
    /**
     * Notifier un événement d'agenda
     */
    public static function notifyNewEvent($targetType, $targetId, $eventData) {
        return self::sendNotification('/notify/event', [
            'targetType' => $targetType,
            'targetId' => $targetId,
            'eventData' => $eventData
        ]);
    }
    
    /**
     * Générer un token JWT pour l'authentification WebSocket
     */
    public static function generateToken($userId, $userType): ?string
    {
        $jwtSecret = getenv('JWT_SECRET') ?: getenv('WEBSOCKET_API_SECRET');

        if (!$jwtSecret) {
            error_log('WebSocket: JWT_SECRET not configured');
            return null;
        }

        $payload = [
            'iss'      => 'fronote',
            'sub'      => (int) $userId,
            'userId'   => (int) $userId,
            'userType' => $userType,
            'iat'      => time(),
            'exp'      => time() + 86400,
        ];

        if (class_exists(\Firebase\JWT\JWT::class)) {
            return \Firebase\JWT\JWT::encode($payload, $jwtSecret, 'HS256');
        }

        // Fallback manuel (si vendor/ absent — dépréciable après composer install)
        $header = rtrim(strtr(base64_encode('{"typ":"JWT","alg":"HS256"}'), '+/', '-_'), '=');
        $body   = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig    = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $jwtSecret, true)), '+/', '-_'), '=');
        return "$header.$body.$sig";
    }
}

// Initialiser au chargement
WebSocket::init();
