/**
 * Fronote WebSocket Server — Socket.IO avec auth JWT
 *
 * Channels :
 *   user:{userId}:{userType}  — Notifications personnelles
 *   class:{classeId}           — Notifications de classe
 *   role:{role}                — Notifications par role
 *   conversation:{convId}      — Messages / typing
 *   admin:metrics              — Live admin dashboard
 *   broadcast                  — Notifications globales
 *
 * Endpoints HTTP internes (appeles par le PHP) :
 *   POST /notify/message       — Nouveau message
 *   POST /notify/notification  — Notification generale
 *   POST /notify/grade         — Nouvelle note
 *   POST /notify/absence       — Nouvelle absence
 *   POST /notify/event         — Evenement agenda
 *   POST /notify/badge         — Mise a jour badge sidebar
 *   GET  /health               — Sante du serveur
 *   GET  /metrics              — Statistiques admin
 *
 * Usage : node server.js
 * Config : .env (WEBSOCKET_PORT, WEBSOCKET_API_SECRET, JWT_SECRET, WSS_CERT_PATH, WSS_KEY_PATH)
 */

const path = require('path');
const fs = require('fs');

// Charger .env depuis la racine du projet
require('dotenv').config({ path: path.resolve(__dirname, '../.env') });

const http = require('http');
const https = require('https');
const { Server } = require('socket.io');
const jwt = require('jsonwebtoken');

const PORT = parseInt(process.env.WEBSOCKET_PORT || '3000', 10);
const API_SECRET = process.env.WEBSOCKET_API_SECRET || 'fronote_ws_secret';
const JWT_SECRET = process.env.JWT_SECRET || process.env.APP_KEY || 'fronote_jwt_default';

// ─── TLS/WSS Support ───────────────────────────────────────────
const certPath = process.env.WSS_CERT_PATH;
const keyPath = process.env.WSS_KEY_PATH;
let server;

if (certPath && keyPath && fs.existsSync(certPath) && fs.existsSync(keyPath)) {
    server = https.createServer({
        cert: fs.readFileSync(certPath),
        key: fs.readFileSync(keyPath),
    }, handleHttp);
    console.log('[WSS] TLS enabled');
} else {
    server = http.createServer(handleHttp);
}

// ─── Rate limiting ─────────────────────────────────────────────
const rateLimits = new Map(); // socketId -> { count, resetAt }
const RATE_LIMIT_MAX = 30; // events per minute
const RATE_LIMIT_WINDOW = 60000;

function checkRateLimit(socketId) {
    const now = Date.now();
    let entry = rateLimits.get(socketId);
    if (!entry || now > entry.resetAt) {
        entry = { count: 0, resetAt: now + RATE_LIMIT_WINDOW };
        rateLimits.set(socketId, entry);
    }
    entry.count++;
    return entry.count <= RATE_LIMIT_MAX;
}

// Clean expired rate limit entries periodically
setInterval(() => {
    const now = Date.now();
    for (const [id, entry] of rateLimits) {
        if (now > entry.resetAt) rateLimits.delete(id);
    }
}, 30000);

// ─── Metrics ───────────────────────────────────────────────────
const metrics = {
    totalConnections: 0,
    totalEvents: 0,
    totalNotifications: 0,
    startTime: Date.now(),
    eventsPerSecond: 0,
    connectionLog: [], // last 50 connections
};

let eventsThisSecond = 0;
setInterval(() => {
    metrics.eventsPerSecond = eventsThisSecond;
    eventsThisSecond = 0;
    // Emit metrics to admin room
    io.to('admin:metrics').emit('metrics', {
        connections: io.engine.clientsCount,
        eventsPerSecond: metrics.eventsPerSecond,
        totalEvents: metrics.totalEvents,
        uptime: Math.floor((Date.now() - metrics.startTime) / 1000),
    });
}, 1000);

// ─── HTTP Handler ──────────────────────────────────────────────
function handleHttp(req, res) {
    // CORS
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Api-Secret');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    // Health endpoint
    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'ok',
            uptime: Math.floor(process.uptime()),
            connections: io.engine.clientsCount,
            memory: Math.round(process.memoryUsage().heapUsed / 1024 / 1024),
        }));
        return;
    }

    // Metrics endpoint (admin only, requires API secret)
    if (req.method === 'GET' && req.url === '/metrics') {
        if (req.headers['x-api-secret'] !== API_SECRET) {
            res.writeHead(403);
            res.end('Forbidden');
            return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });

        // Room breakdown
        const rooms = {};
        for (const [roomName, sockets] of io.sockets.adapter.rooms) {
            if (!roomName.includes(':')) continue;
            rooms[roomName] = sockets.size;
        }

        res.end(JSON.stringify({
            connections: io.engine.clientsCount,
            totalConnections: metrics.totalConnections,
            totalEvents: metrics.totalEvents,
            totalNotifications: metrics.totalNotifications,
            eventsPerSecond: metrics.eventsPerSecond,
            uptime: Math.floor(process.uptime()),
            memory: Math.round(process.memoryUsage().heapUsed / 1024 / 1024),
            rooms,
            recentConnections: metrics.connectionLog.slice(-20),
        }));
        return;
    }

    // Notification endpoints (called by PHP)
    if (req.method === 'POST' && req.url.startsWith('/notify/')) {
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
                metrics.totalNotifications++;
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
}

// ─── Socket.IO ──────────────────────────────────────────────────
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST'],
    },
    pingTimeout: 60000,
    pingInterval: 25000,
    maxHttpBufferSize: 1e6, // 1MB max payload
});

// Auth middleware — verify JWT
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
        socket.tokenExp = decoded.exp || 0;
        next();
    } catch (e) {
        next(new Error('Invalid token'));
    }
});

// Connection handler
io.on('connection', (socket) => {
    const { userId, userType } = socket;
    const userRoom = `user:${userId}:${userType}`;

    // Join personal rooms
    socket.join(userRoom);
    socket.join(`role:${userType}`);

    metrics.totalConnections++;
    metrics.connectionLog.push({
        userId, userType,
        action: 'connect',
        ip: socket.handshake.address,
        at: new Date().toISOString(),
    });
    if (metrics.connectionLog.length > 50) metrics.connectionLog.shift();

    console.log(`[+] ${userType}#${userId} connected (${socket.id})`);

    // ─── Heartbeat ──────────────────────────────────────────
    let lastHeartbeat = Date.now();
    const heartbeatCheck = setInterval(() => {
        if (Date.now() - lastHeartbeat > 90000) {
            console.log(`[!] ${userType}#${userId} heartbeat timeout`);
            socket.disconnect(true);
        }
    }, 30000);

    socket.on('heartbeat', () => {
        lastHeartbeat = Date.now();
        socket.emit('heartbeat:ack');
    });

    // ─── Token refresh ──────────────────────────────────────
    socket.on('token:refresh', (newToken) => {
        try {
            const decoded = jwt.verify(newToken, JWT_SECRET);
            socket.tokenExp = decoded.exp || 0;
            socket.emit('token:refreshed');
        } catch (e) {
            socket.emit('token:error', 'Invalid refresh token');
        }
    });

    // ─── Join class (with rate limit) ───────────────────────
    socket.on('join:class', (classeId) => {
        if (!checkRateLimit(socket.id)) return;
        if (classeId) socket.join(`class:${classeId}`);
    });

    // ─── Join conversation ──────────────────────────────────
    socket.on('join:conversation', (convId) => {
        if (!checkRateLimit(socket.id)) return;
        if (convId) socket.join(`conversation:${convId}`);
    });

    // ─── Typing indicator ───────────────────────────────────
    socket.on('typing', (data) => {
        if (!checkRateLimit(socket.id)) return;
        metrics.totalEvents++;
        eventsThisSecond++;
        if (data.conversationId) {
            socket.to(`conversation:${data.conversationId}`).emit('typing', {
                userId, userType, conversationId: data.conversationId,
            });
        }
    });

    // ─── Admin metrics room ─────────────────────────────────
    socket.on('join:admin', () => {
        if (userType === 'administrateur') {
            socket.join('admin:metrics');
        }
    });

    // ─── Generic event tracking ─────────────────────────────
    socket.onAny((eventName) => {
        metrics.totalEvents++;
        eventsThisSecond++;
        if (!checkRateLimit(socket.id)) {
            console.log(`[!] Rate limit exceeded for ${userType}#${userId}`);
            socket.emit('error', { message: 'Rate limit exceeded' });
            socket.disconnect(true);
        }
    });

    socket.on('disconnect', () => {
        clearInterval(heartbeatCheck);
        rateLimits.delete(socket.id);
        metrics.connectionLog.push({
            userId, userType,
            action: 'disconnect',
            at: new Date().toISOString(),
        });
        if (metrics.connectionLog.length > 50) metrics.connectionLog.shift();
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

    // Targeted send
    if (targets.length > 0) {
        targets.forEach((t) => {
            const room = `user:${t.user_id}:${t.user_type}`;
            io.to(room).emit(channel, payload);
        });
        return;
    }

    // By role
    if (data.role) {
        io.to(`role:${data.role}`).emit(channel, payload);
        return;
    }

    // By class
    if (data.classe_id) {
        io.to(`class:${data.classe_id}`).emit(channel, payload);
        return;
    }

    // Global broadcast
    io.emit(channel, payload);
}

// ─── Start ──────────────────────────────────────────────────────
server.listen(PORT, () => {
    const proto = certPath ? 'wss' : 'ws';
    console.log(`Fronote WebSocket server running on ${proto}://localhost:${PORT}`);
    console.log(`Health check: http://localhost:${PORT}/health`);
    console.log(`Metrics: http://localhost:${PORT}/metrics`);
});
