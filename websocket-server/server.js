/**
 * Serveur WebSocket pour Pronote
 * Gère les connexions temps réel et la diffusion d'événements
 */

const http = require('http');
const express = require('express');
const socketio = require('socket.io');
const jwt = require('jsonwebtoken');

const app = express();
const server = http.createServer(app);

// Configuration Socket.IO avec CORS
const io = socketio(server, {
    cors: {
        origin: process.env.ALLOWED_ORIGINS ? process.env.ALLOWED_ORIGINS.split(',') : '*',
        credentials: true
    },
    pingTimeout: 60000,
    pingInterval: 25000
});

// Middleware pour parser JSON
app.use(express.json());

// Secret JWT (à configurer via variable d'environnement)
const JWT_SECRET = process.env.JWT_SECRET || 'change_this_secret_in_production';

// Stockage des connexions actives par utilisateur
const activeConnections = new Map();

/**
 * Middleware d'authentification Socket.IO
 */
io.use((socket, next) => {
    const token = socket.handshake.auth.token || socket.handshake.query.token;
    
    if (!token) {
        return next(new Error('Authentication required'));
    }
    
    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        socket.userId = decoded.userId;
        socket.userType = decoded.userType;
        next();
    } catch (err) {
        next(new Error('Invalid token'));
    }
});

/**
 * Gestion des connexions WebSocket
 */
io.on('connection', (socket) => {
    console.log(`Client connecté: ${socket.userId} (${socket.userType})`);
    
    // Enregistrer la connexion
    if (!activeConnections.has(socket.userId)) {
        activeConnections.set(socket.userId, new Set());
    }
    activeConnections.get(socket.userId).add(socket.id);
    
    // Joindre le canal personnel de l'utilisateur
    socket.join(`user_${socket.userId}`);
    
    // Joindre une conversation
    socket.on('joinConversation', (convId) => {
        socket.join(`conv_${convId}`);
        console.log(`User ${socket.userId} joined conversation ${convId}`);
    });
    
    // Quitter une conversation
    socket.on('leaveConversation', (convId) => {
        socket.leave(`conv_${convId}`);
        console.log(`User ${socket.userId} left conversation ${convId}`);
    });
    
    // Joindre un canal de classe
    socket.on('joinClass', (classeId) => {
        socket.join(`class_${classeId}`);
        console.log(`User ${socket.userId} joined class ${classeId}`);
    });
    
    // Gestion de la déconnexion
    socket.on('disconnect', () => {
        console.log(`Client déconnecté: ${socket.userId}`);
        const userSockets = activeConnections.get(socket.userId);
        if (userSockets) {
            userSockets.delete(socket.id);
            if (userSockets.size === 0) {
                activeConnections.delete(socket.userId);
            }
        }
    });
});

/**
 * Route HTTP: Nouveau message dans une conversation
 */
app.post('/notify/message', (req, res) => {
    const { convId, message, secret } = req.body;
    
    // Vérification du secret partagé
    if (secret !== process.env.API_SECRET) {
        return res.status(403).json({ error: 'Unauthorized' });
    }
    
    if (!convId || !message) {
        return res.status(400).json({ error: 'Missing convId or message' });
    }
    
    // Diffuser aux clients de la conversation
    io.to(`conv_${convId}`).emit('newMessage', message);
    console.log(`Message diffusé à conversation ${convId}`);
    
    res.json({ success: true, recipients: io.sockets.adapter.rooms.get(`conv_${convId}`)?.size || 0 });
});

/**
 * Route HTTP: Notification personnelle
 */
app.post('/notify/notification', (req, res) => {
    const { userId, data, secret } = req.body;
    
    if (secret !== process.env.API_SECRET) {
        return res.status(403).json({ error: 'Unauthorized' });
    }
    
    if (!userId || !data) {
        return res.status(400).json({ error: 'Missing userId or data' });
    }
    
    // Diffuser à l'utilisateur
    io.to(`user_${userId}`).emit('notification', data);
    console.log(`Notification envoyée à user ${userId}`);
    
    res.json({ success: true, connected: activeConnections.has(userId) });
});

/**
 * Route HTTP: Nouvelle note
 */
app.post('/notify/grade', (req, res) => {
    const { eleveId, gradeData, secret } = req.body;
    
    if (secret !== process.env.API_SECRET) {
        return res.status(403).json({ error: 'Unauthorized' });
    }
    
    io.to(`user_${eleveId}`).emit('newGrade', gradeData);
    console.log(`Nouvelle note diffusée à élève ${eleveId}`);
    
    res.json({ success: true });
});

/**
 * Route HTTP: Nouvelle absence
 */
app.post('/notify/absence', (req, res) => {
    const { eleveId, absenceData, secret } = req.body;
    
    if (secret !== process.env.API_SECRET) {
        return res.status(403).json({ error: 'Unauthorized' });
    }
    
    io.to(`user_${eleveId}`).emit('newAbsence', absenceData);
    console.log(`Nouvelle absence diffusée à élève ${eleveId}`);
    
    res.json({ success: true });
});

/**
 * Route HTTP: Événement d'agenda
 */
app.post('/notify/event', (req, res) => {
    const { targetType, targetId, eventData, secret } = req.body;
    
    if (secret !== process.env.API_SECRET) {
        return res.status(403).json({ error: 'Unauthorized' });
    }
    
    const channel = targetType === 'class' ? `class_${targetId}` : `user_${targetId}`;
    io.to(channel).emit('newEvent', eventData);
    console.log(`Événement diffusé à ${channel}`);
    
    res.json({ success: true });
});

/**
 * Route health check
 */
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        connections: activeConnections.size,
        uptime: process.uptime()
    });
});

// Démarrage du serveur
const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`✅ Serveur WebSocket démarré sur le port ${PORT}`);
    console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
});

// Gestion des erreurs
process.on('uncaughtException', (err) => {
    console.error('Uncaught Exception:', err);
});

process.on('unhandledRejection', (err) => {
    console.error('Unhandled Rejection:', err);
});
