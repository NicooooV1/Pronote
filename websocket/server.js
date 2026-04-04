/**
 * Fronote WebSocket Server — Socket.IO avec auth JWT
 *
 * Channels :
 *   user:{userId}:{userType}  — Notifications personnelles
 *   class:{classeId}           — Notifications de classe
 *   role:{role}                — Notifications par rôle
 *   broadcast                  — Notifications globales
 *
 * Endpoints HTTP internes (appelés par le PHP) :
 *   POST /notify/message       — Nouveau message
 *   POST /notify/notification  — Notification générale
 *   POST /notify/grade         — Nouvelle note
 *   POST /notify/absence       — Nouvelle absence
 *   POST /notify/event         — Événement agenda
 *
 * Usage : node server.js
 * Config : .env (WEBSOCKET_PORT, WEBSOCKET_API_SECRET, JWT_SECRET)
 */

const path = require('path');

// Charger .env depuis la racine du projet
require('dotenv').config({ path: path.resolve(__dirname, '../.env') });

const http = require('http');
const { Server } = require('socket.io');
const jwt = require('jsonwebtoken');

const PORT = parseInt(process.env.WEBSOCKET_PORT || '3000', 10);
const API_SECRET = process.env.WEBSOCKET_API_SECRET || 'fronote_ws_secret';
const JWT_SECRET = process.env.JWT_SECRET || process.env.APP_KEY || 'fronote_jwt_default';

// ─── HTTP Server ────────────────────────────────────────────────
const server = http.createServer((req, res) => {
    // Endpoint santé
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            uptime: process.uptime(),
            connections: io.engine.clientsCount,
        }));
        return;
    }

    // Endpoints de notification (appelés par le PHP)
    if (req.method === 'POST' && req.url.startsWith('/notify/')) {
        // Vérifier le secret API
        const authHeader = req.headers['x-api-secret'] || '';
        if (authHeader !== API_SECRET) {
            res.writeHead(403, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Forbidden' }));
            return;
        }

        let body = '';
        req.on('data', (chunk) => body += chunk);
        req.on('end', () => {
            try {
                const data = JSON.parse(body);
                const channel = req.url.replace('/notify/', '');
                handleNotification(channel, data);
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true }));
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: 'Invalid JSON' }));
            }
        });
        return;
    }

    res.writeHead(404);
    res.end('Not found');
});

// ─── Socket.IO ──────────────────────────────────────────────────
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST'],
    },
    pingTimeout: 60000,
    pingInterval: 25000,
});

// Auth middleware — vérifier le JWT à la connexion
io.use((socket, next) => {
    const token = socket.handshake.auth?.token || socket.handshake.query?.token;
    if (!token) {
        return next(new Error('Authentication required'));
    }

    try {
        const decoded = jwt.verify(token, JWT_SECRET);
        socket.userId = decoded.sub || decoded.user_id;
        socket.userType = decoded.role || decoded.user_type || '';
        socket.userName = decoded.name || '';
        next();
    } catch (e) {
        next(new Error('Invalid token'));
    }
});

// Connexion
io.on('connection', (socket) => {
    const { userId, userType } = socket;
    const userRoom = `user:${userId}:${userType}`;

    // Rejoindre les rooms personnelles
    socket.join(userRoom);
    socket.join(`role:${userType}`);

    console.log(`[+] ${userType}#${userId} connected (${socket.id})`);

    // Rejoindre une classe (envoyé par le client après connexion)
    socket.on('join:class', (classeId) => {
        if (classeId) {
            socket.join(`class:${classeId}`);
        }
    });

    // Typing indicator pour la messagerie
    socket.on('typing', (data) => {
        if (data.conversationId) {
            socket.to(`conversation:${data.conversationId}`).emit('typing', {
                userId, userType, conversationId: data.conversationId,
            });
        }
    });

    // Rejoindre une conversation
    socket.on('join:conversation', (convId) => {
        if (convId) socket.join(`conversation:${convId}`);
    });

    socket.on('disconnect', () => {
        console.log(`[-] ${userType}#${userId} disconnected`);
    });
});

// ─── Notification dispatcher ────────────────────────────────────
function handleNotification(channel, data) {
    const targets = data.targets || [];
    const payload = {
        type: channel,
        ...data,
        timestamp: new Date().toISOString(),
    };

    // Envoi ciblé
    if (targets.length > 0) {
        targets.forEach((t) => {
            const room = `user:${t.user_id}:${t.user_type}`;
            io.to(room).emit(channel, payload);
        });
        return;
    }

    // Envoi par rôle
    if (data.role) {
        io.to(`role:${data.role}`).emit(channel, payload);
        return;
    }

    // Envoi par classe
    if (data.classe_id) {
        io.to(`class:${data.classe_id}`).emit(channel, payload);
        return;
    }

    // Broadcast global
    io.emit(channel, payload);
}

// ─── Start ──────────────────────────────────────────────────────
server.listen(PORT, () => {
    console.log(`Fronote WebSocket server running on port ${PORT}`);
    console.log(`Health check: http://localhost:${PORT}/health`);
});
