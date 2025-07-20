import express, { Request, Response, NextFunction } from 'express';
import path from 'path';
import fs from 'fs';
import axios from 'axios';
import helmet from 'helmet';
import cors from 'cors';
import bodyParser from 'body-parser';
import winston from 'winston';
import dotenv from 'dotenv';

// Load environment variables
dotenv.config();

// Types
interface AuthFormData {
  role: 'member' | 'guest' | 'event';
  email: string;
  floor?: string;
  memberName?: string;
  // UniFi parameters
  id: string;
  ap: string;
  t: string;
  url: string;
  ssid: string;
}

interface UniFiController {
  login(): Promise<void>;
  authorize(mac: string, minutes?: number): Promise<void>;
  logout(): Promise<void>;
}

// Logger setup
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.errors({ stack: true }),
    winston.format.json()
  ),
  transports: [
    new winston.transports.File({ filename: 'error.log', level: 'error' }),
    new winston.transports.File({ filename: 'combined.log' }),
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
});

class FrontierTowerCaptivePortal {
  private app: express.Application;
  private unifiController: any;
  
  constructor() {
    this.app = express();
    this.setupMiddleware();
    this.setupRoutes();
    this.initializeUniFiController();
  }

  private setupMiddleware(): void {
    // Security middleware
    this.app.use(helmet({
      contentSecurityPolicy: false // Disabled for inline styles in custom.html
    }));
    
    this.app.use(cors());
    this.app.use(bodyParser.urlencoded({ extended: true }));
    this.app.use(bodyParser.json());
    
    // Static files
    this.app.use(express.static(path.join(__dirname, '../public')));
    
    // Request logging
    this.app.use((req: Request, res: Response, next: NextFunction) => {
      logger.info(`${req.method} ${req.path}`, {
        ip: req.ip,
        userAgent: req.get('User-Agent')
      });
      next();
    });
  }

  private setupRoutes(): void {
    // Main captive portal route
    this.app.get('/', (req: Request, res: Response) => {
      this.serveBrandedHTML(req, res);
    });

    // Authorization endpoint
    this.app.post('/authorize', async (req: Request, res: Response) => {
      try {
        await this.handleAuthorization(req, res);
      } catch (error) {
        logger.error('Authorization error:', error);
        res.status(500).send('Internal server error');
      }
    });

    // Health check
    this.app.get('/health', (req: Request, res: Response) => {
      res.json({ status: 'healthy', timestamp: new Date().toISOString() });
    });

    // API endpoint for member autocomplete (for future enhancement)
    this.app.get('/api/members', (req: Request, res: Response) => {
      const query = req.query.q as string;
      // In production, this would query a real database
      const mockMembers = [
        'John Smith - 5th Floor',
        'Sarah Johnson - 3rd Floor', 
        'Michael Brown - 7th Floor',
        'Emily Davis - 2nd Floor',
        'David Wilson - 8th Floor'
      ];
      
      if (query) {
        const filtered = mockMembers.filter(member => 
          member.toLowerCase().includes(query.toLowerCase())
        );
        res.json(filtered);
      } else {
        res.json(mockMembers);
      }
    });
  }

  private serveBrandedHTML(req: Request, res: Response): void {
    const htmlPath = path.join(__dirname, '../public/custom.html');
    
    fs.readFile(htmlPath, 'utf8', (err: NodeJS.ErrnoException | null, html: string) => {
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

  private async handleAuthorization(req: Request, res: Response): Promise<void> {
    const formData: AuthFormData = req.body;
    
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
      
    } catch (error) {
      logger.error('Authorization failed:', error);
      res.status(500).send('Authorization failed. Please try again.');
    }
  }

  private validateFormData(data: AuthFormData): { isValid: boolean; errors: string[] } {
    const errors: string[] = [];

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

  private isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  private async logAuthorization(data: AuthFormData): Promise<void> {
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
        await axios.post(process.env.LOG_AUTH_WEBHOOK_URL, logData, {
          headers: { 'Content-Type': 'application/json' },
          timeout: 5000
        });
        logger.info('Webhook notification sent successfully');
      } catch (error) {
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

  private async authorizeWithUniFi(mac: string): Promise<void> {
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
      
    } catch (error) {
      logger.error('UniFi authorization failed:', error);
      throw error;
    } finally {
      try {
        await this.unifiController.logout();
      } catch (logoutError) {
        logger.warn('UniFi logout failed:', logoutError);
      }
    }
  }

  private initializeUniFiController(): void {
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

  public start(port: number = 4545): void {
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

export default FrontierTowerCaptivePortal;
