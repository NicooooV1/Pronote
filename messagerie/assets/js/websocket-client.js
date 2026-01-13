/**
 * Client WebSocket pour la messagerie Pronote
 * Gère la connexion temps réel et les événements
 */

class WebSocketClient {
    constructor() {
        this.socket = null;
        this.connected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
    }
    
    /**
     * Initialiser la connexion WebSocket
     */
    init(wsUrl, token) {
        if (!wsUrl || !token) {
            console.warn('WebSocket: URL ou token manquant');
            return;
        }
        
        try {
            this.socket = io(wsUrl, {
                auth: { token },
                transports: ['websocket', 'polling'],
                reconnection: true,
                reconnectionDelay: this.reconnectDelay,
                reconnectionAttempts: this.maxReconnectAttempts
            });
            
            this.setupEventHandlers();
        } catch (error) {
            console.error('WebSocket init error:', error);
        }
    }
    
    /**
     * Configuration des gestionnaires d'événements
     */
    setupEventHandlers() {
        this.socket.on('connect', () => {
            console.log('✅ WebSocket connecté');
            this.connected = true;
            this.reconnectAttempts = 0;
            
            // Rejoindre le canal utilisateur automatiquement
            const userId = document.body.dataset.userId;
            if (userId) {
                this.socket.emit('joinUser', userId);
            }
        });
        
        this.socket.on('disconnect', (reason) => {
            console.warn('WebSocket déconnecté:', reason);
            this.connected = false;
        });
        
        this.socket.on('connect_error', (error) => {
            console.error('Erreur de connexion WebSocket:', error);
            this.reconnectAttempts++;
            
            if (this.reconnectAttempts >= this.maxReconnectAttempts) {
                console.warn('WebSocket: Nombre max de tentatives atteint, fallback au polling');
                // Activer le fallback polling si nécessaire
                if (typeof enablePollingFallback === 'function') {
                    enablePollingFallback();
                }
            }
        });
    }
    
    /**
     * Rejoindre une conversation
     */
    joinConversation(convId) {
        if (this.socket && this.connected) {
            this.socket.emit('joinConversation', convId);
        }
    }
    
    /**
     * Quitter une conversation
     */
    leaveConversation(convId) {
        if (this.socket && this.connected) {
            this.socket.emit('leaveConversation', convId);
        }
    }
    
    /**
     * Rejoindre un canal de classe
     */
    joinClass(classeId) {
        if (this.socket && this.connected) {
            this.socket.emit('joinClass', classeId);
        }
    }
    
    /**
     * Écouter un événement
     */
    on(event, callback) {
        if (this.socket) {
            this.socket.on(event, callback);
        }
    }
    
    /**
     * Se désabonner d'un événement
     */
    off(event, callback) {
        if (this.socket) {
            this.socket.off(event, callback);
        }
    }
}

// Instance globale
window.wsClient = new WebSocketClient();
