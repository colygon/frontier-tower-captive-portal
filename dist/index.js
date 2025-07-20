"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = __importDefault(require("express"));
const path_1 = __importDefault(require("path"));
const fs_1 = __importDefault(require("fs"));
const axios_1 = __importDefault(require("axios"));
const helmet_1 = __importDefault(require("helmet"));
const cors_1 = __importDefault(require("cors"));
const body_parser_1 = __importDefault(require("body-parser"));
const winston_1 = __importDefault(require("winston"));
const dotenv_1 = __importDefault(require("dotenv"));
// Load environment variables
dotenv_1.default.config();
// Logger setup
const logger = winston_1.default.createLogger({
    level: 'info',
    format: winston_1.default.format.combine(winston_1.default.format.timestamp(), winston_1.default.format.errors({ stack: true }), winston_1.default.format.json()),
    transports: [
        new winston_1.default.transports.File({ filename: 'error.log', level: 'error' }),
        new winston_1.default.transports.File({ filename: 'combined.log' }),
        new winston_1.default.transports.Console({
            format: winston_1.default.format.simple()
        })
    ]
});
class FrontierTowerCaptivePortal {
    constructor() {
        this.app = (0, express_1.default)();
        this.setupMiddleware();
        this.setupRoutes();
        this.initializeUniFiController();
    }
    setupMiddleware() {
        // Security middleware
        this.app.use((0, helmet_1.default)({
            contentSecurityPolicy: false // Disabled for inline styles in custom.html
        }));
        this.app.use((0, cors_1.default)());
        this.app.use(body_parser_1.default.urlencoded({ extended: true }));
        this.app.use(body_parser_1.default.json());
        // Static files
        this.app.use(express_1.default.static(path_1.default.join(__dirname, '../public')));
        // Request logging
        this.app.use((req, res, next) => {
            logger.info(`${req.method} ${req.path}`, {
                ip: req.ip,
                userAgent: req.get('User-Agent')
            });
            next();
        });
    }
    setupRoutes() {
        // Main captive portal route
        this.app.get('/', (req, res) => {
            this.serveBrandedHTML(req, res);
        });
        // Authorization endpoint
        this.app.post('/authorize', async (req, res) => {
            try {
                await this.handleAuthorization(req, res);
            }
            catch (error) {
                logger.error('Authorization error:', error);
                res.status(500).send('Internal server error');
            }
        });
        // Health check
        this.app.get('/health', (req, res) => {
            res.json({ status: 'healthy', timestamp: new Date().toISOString() });
        });
        // API endpoint for member autocomplete (for future enhancement)
        this.app.get('/api/members', (req, res) => {
            const query = req.query.q;
            // In production, this would query a real database
            const mockMembers = [
                'John Smith - 5th Floor',
                'Sarah Johnson - 3rd Floor',
                'Michael Brown - 7th Floor',
                'Emily Davis - 2nd Floor',
                'David Wilson - 8th Floor'
            ];
            if (query) {
                const filtered = mockMembers.filter(member => member.toLowerCase().includes(query.toLowerCase()));
                res.json(filtered);
            }
            else {
                res.json(mockMembers);
            }
        });
    }
    serveBrandedHTML(req, res) {
        const htmlPath = path_1.default.join(__dirname, '../public/custom.html');
        fs_1.default.readFile(htmlPath, 'utf8', (err, html) => {
            if (err) {
                logger.error('Error reading custom.html:', err);
                res.status(500).send('Error loading page');
                return;
            }
            // Inject branding from environment variables
            const brandedHTML = html
                .replace('{{BRAND_COLOR}}', process.env.BRAND_COLOR || '#667eea')
                .replace('{{BRAND_COLOR_DARK}}', process.env.BRAND_COLOR_DARK || '#5a67d8')
                .replace('{{LOGO_TEXT}}', process.env.LOGO_TEXT || 'FT');
            res.send(brandedHTML);
        });
    }
    async handleAuthorization(req, res) {
        const formData = req.body;
        logger.info('Authorization request received:', {
            role: formData.role,
            email: formData.email,
            floor: formData.floor,
            memberName: formData.memberName,
            mac: formData.id
        });
        // Validate form data
        const validation = this.validateFormData(formData);
        if (!validation.isValid) {
            res.status(400).send(`Validation error: ${validation.errors.join(', ')}`);
            return;
        }
        try {
            // Log the authorization attempt
            await this.logAuthorization(formData);
            // Authorize with UniFi controller
            await this.authorizeWithUniFi(formData.id);
            // Redirect to original URL or success page
            const redirectUrl = formData.url || process.env.REDIRECT_URL || 'https://www.google.com';
            logger.info('Authorization successful:', {
                mac: formData.id,
                email: formData.email,
                redirectUrl
            });
            res.redirect(redirectUrl);
        }
        catch (error) {
            logger.error('Authorization failed:', error);
            res.status(500).send('Authorization failed. Please try again.');
        }
    }
    validateFormData(data) {
        const errors = [];
        // Required fields
        if (!data.role || !['member', 'guest', 'event'].includes(data.role)) {
            errors.push('Valid role is required');
        }
        if (!data.email || !this.isValidEmail(data.email)) {
            errors.push('Valid email is required');
        }
        if (!data.id) {
            errors.push('Device ID is required');
        }
        // Role-specific validation
        if ((data.role === 'guest' || data.role === 'event') && !data.memberName?.trim()) {
            errors.push('Member name or event name is required for guests');
        }
        return {
            isValid: errors.length === 0,
            errors
        };
    }
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    async logAuthorization(data) {
        const logData = {
            timestamp: new Date().toISOString(),
            role: data.role,
            email: data.email,
            floor: data.floor,
            memberName: data.memberName,
            mac: data.id,
            ap: data.ap,
            ssid: data.ssid
        };
        // Log to file
        logger.info('User authorized:', logData);
        // Send to webhook if configured
        if (process.env.LOG_AUTH_DRIVER === 'webhook' && process.env.LOG_AUTH_WEBHOOK_URL) {
            try {
                await axios_1.default.post(process.env.LOG_AUTH_WEBHOOK_URL, logData, {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 5000
                });
                logger.info('Webhook notification sent successfully');
            }
            catch (error) {
                logger.error('Failed to send webhook notification:', error);
            }
        }
        // Send to Google Sheets if configured
        if (process.env.LOG_AUTH_DRIVER === 'googlesheets') {
            // This would implement Google Sheets integration
            // For now, just log that it would be sent
            logger.info('Would send to Google Sheets:', logData);
        }
    }
    async authorizeWithUniFi(mac) {
        if (!this.unifiController) {
            throw new Error('UniFi controller not initialized');
        }
        const username = process.env.UNIFI_USER;
        const password = process.env.UNIFI_PASS;
        const site = process.env.UNIFI_SITE_IDENTIFIER || 'default';
        const authDuration = parseInt(process.env.AUTH_DURATION_MINUTES || '480'); // Default 8 hours
        try {
            // Login to UniFi controller
            await this.unifiController.login(username, password);
            // Authorize the device for specified duration
            await this.unifiController.authorizeGuest(site, mac, authDuration);
            logger.info(`Device ${mac} authorized for ${authDuration} minutes`);
        }
        catch (error) {
            logger.error('UniFi authorization failed:', error);
            throw error;
        }
        finally {
            try {
                await this.unifiController.logout();
            }
            catch (logoutError) {
                logger.warn('UniFi logout failed:', logoutError);
            }
        }
    }
    initializeUniFiController() {
        const { Controller } = require('node-unifi');
        const controllerUrl = process.env.UNIFI_CONTROLLER_URL;
        const username = process.env.UNIFI_USER;
        const password = process.env.UNIFI_PASS;
        const site = process.env.UNIFI_SITE_IDENTIFIER || 'default';
        if (!controllerUrl || !username || !password) {
            logger.warn('UniFi controller credentials not provided. Authorization will fail.');
            return;
        }
        // Parse URL to get host and port
        const url = new URL(controllerUrl);
        const host = url.hostname;
        const port = parseInt(url.port) || (url.protocol === 'https:' ? 8443 : 8080);
        this.unifiController = new Controller({
            host: host,
            port: port,
            sslverify: process.env.UNIFI_INSECURE !== 'true'
        });
        logger.info('UniFi controller initialized', {
            url: controllerUrl,
            site: site,
            username: username
        });
    }
    start(port = 4545) {
        this.app.listen(port, () => {
            logger.info(`Frontier Tower Captive Portal running on port ${port}`);
            logger.info('Environment:', {
                nodeEnv: process.env.NODE_ENV,
                authDriver: process.env.LOG_AUTH_DRIVER,
                unifiUrl: process.env.UNIFI_CONTROLLER_URL,
                brandColor: process.env.BRAND_COLOR
            });
        });
    }
}
// Start the server
if (require.main === module) {
    const portal = new FrontierTowerCaptivePortal();
    const port = parseInt(process.env.PORT || '4545');
    portal.start(port);
}
exports.default = FrontierTowerCaptivePortal;
//# sourceMappingURL=index.js.map