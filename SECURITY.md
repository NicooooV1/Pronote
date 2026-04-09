# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 1.4.x   | :white_check_mark: |
| < 1.4   | :x:                |

## Reporting a Vulnerability

**Do NOT open a public issue for security vulnerabilities.**

Instead, please report security issues by emailing the project maintainers directly. Include:

1. Description of the vulnerability
2. Steps to reproduce
3. Potential impact
4. Suggested fix (if any)

We will acknowledge your report within 48 hours and provide a timeline for a fix.

## Security Measures

Fronote implements the following security measures:

### Authentication & Authorization
- RBAC (Role-Based Access Control) with 6 user types
- Progressive rate limiting on login (exponential backoff)
- Optional 2FA (TOTP-based)
- Remember-me tokens with secure storage
- Session fixation protection
- Force password change on first login

### CSRF Protection
- Token bucket with rotation via `API\Core\CSRF`
- All POST forms include CSRF tokens
- AJAX requests include `X-CSRF-TOKEN` header

### Content Security Policy
- Strict CSP with nonce-based script/style loading
- `frame-ancestors 'none'` (no iframing)
- `form-action 'self'`

### Input Validation
- Prepared statements for all SQL queries (PDO)
- HTML escaping via `e()` / `htmlspecialchars()`
- File upload validation (type, size, extension)

### Marketplace Security
- SHA-256 integrity verification for downloaded packages
- Static analysis scanner (`API/Security/ModuleScanner.php`)
- Blocked dangerous functions: `eval`, `exec`, `system`, `shell_exec`, etc.
- Quarantine system for suspicious modules
- Automatic backup before module installation

### WebSocket Security
- JWT-based authentication for WebSocket connections
- Token rotation every 20 minutes
- Rate limiting: 30 events/min per connection
- Room membership verification via HTTP callback
- Heartbeat with 90-second timeout

### Headers
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- HSTS in production (HTTPS only)

## Dependencies

- Font Awesome (CDN with SRI)
- Socket.IO client (CDN with SRI)
- No server-side Composer dependencies (zero supply chain risk)
