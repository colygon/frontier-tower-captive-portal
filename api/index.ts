import { VercelRequest, VercelResponse } from '@vercel/node';
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

// Logger setup
const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.errors({ stack: true }),
    winston.format.json()
  ),
  transports: [
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
});

// Create Express app
const app = express();

// Security middleware
app.use(helmet({
  contentSecurityPolicy: false // Disabled for inline styles in custom.html
}));

app.use(cors());
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());

// Request logging
app.use((req: Request, res: Response, next: NextFunction) => {
  logger.info(`${req.method} ${req.path}`, {
    ip: req.ip,
    userAgent: req.get('User-Agent')
  });
  next();
});

// Serve branded HTML
function serveBrandedHTML(req: Request, res: Response): void {
  const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontier Tower - WiFi Access</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: ${process.env.BRAND_COLOR || '#667eea'};
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: white;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: ${process.env.BRAND_COLOR || '#667eea'};
        }

        .role-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .role-btn {
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .role-btn:hover {
            border-color: ${process.env.BRAND_COLOR || '#667eea'};
        }

        .role-btn.active {
            background: ${process.env.BRAND_COLOR || '#667eea'};
            color: white;
            border-color: ${process.env.BRAND_COLOR || '#667eea'};
        }

        .conditional-field {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .conditional-field.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: ${process.env.BRAND_COLOR || '#667eea'};
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: ${process.env.BRAND_COLOR_DARK || '#5a67d8'};
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .role-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">${process.env.LOGO_TEXT || 'FT'}</div>
        <h1>Welcome to Frontier Tower</h1>
        <p class="subtitle">Please provide your information to access WiFi</p>

        <form id="authForm" method="POST" action="/api">
            <div class="form-group">
                <label>I am a:</label>
                <div class="role-buttons">
                    <button type="button" class="role-btn" data-role="member">Member</button>
                    <button type="button" class="role-btn" data-role="guest">Guest</button>
                    <button type="button" class="role-btn" data-role="event">Event</button>
                </div>
                <input type="hidden" id="role" name="role" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                <div class="error" id="emailError"></div>
            </div>

            <div class="form-group conditional-field" id="memberFields">
                <label for="floor">Floor Number</label>
                <select id="floor" name="floor">
                    <option value="">Select Floor</option>
                    <option value="1">1st Floor</option>
                    <option value="2">2nd Floor</option>
                    <option value="3">3rd Floor</option>
                    <option value="4">4th Floor</option>
                    <option value="5">5th Floor</option>
                    <option value="6">6th Floor</option>
                    <option value="7">7th Floor</option>
                    <option value="8">8th Floor</option>
                    <option value="9">9th Floor</option>
                    <option value="10">10th Floor</option>
                    <option value="penthouse">Penthouse</option>
                </select>
            </div>

            <div class="form-group conditional-field" id="guestFields">
                <label for="memberName">Member Name or Event Name</label>
                <input type="text" id="memberName" name="memberName" placeholder="Start typing member name or event name...">
            </div>

            <button type="submit" class="submit-btn" id="submitBtn" disabled>
                Connect to WiFi
            </button>

            <!-- Hidden fields for UniFi -->
            <input type="hidden" name="id" value="${req.query.id || ''}">
            <input type="hidden" name="ap" value="${req.query.ap || ''}">
            <input type="hidden" name="t" value="${req.query.t || ''}">
            <input type="hidden" name="url" value="${req.query.url || ''}">
            <input type="hidden" name="ssid" value="${req.query.ssid || ''}">
        </form>

        <div class="footer">
            <p>Secure WiFi access for Frontier Tower residents and guests</p>
        </div>
    </div>

    <script>
        // Role selection
        const roleButtons = document.querySelectorAll('.role-btn');
        const roleInput = document.getElementById('role');
        const memberFields = document.getElementById('memberFields');
        const guestFields = document.getElementById('guestFields');
        const submitBtn = document.getElementById('submitBtn');

        roleButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                roleButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const role = btn.dataset.role;
                roleInput.value = role;

                memberFields.classList.remove('show');
                guestFields.classList.remove('show');

                if (role === 'member') {
                    memberFields.classList.add('show');
                } else if (role === 'guest' || role === 'event') {
                    guestFields.classList.add('show');
                }

                validateForm();
            });
        });

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('input', validateForm);

        // Form validation
        function validateForm() {
            const role = roleInput.value;
            const email = emailInput.value;
            const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
            
            let isValid = role && email && emailRegex.test(email);

            if (role === 'guest' || role === 'event') {
                const memberName = document.getElementById('memberName').value;
                isValid = isValid && memberName.trim().length > 0;
            }

            submitBtn.disabled = !isValid;
        }

        validateForm();
    </script>
</body>
</html>`;

  res.setHeader('Content-Type', 'text/html');
  res.send(htmlContent);
}

// Routes
app.get('/', serveBrandedHTML);

app.post('/', async (req: Request, res: Response) => {
  try {
    const formData: AuthFormData = req.body;
    
    logger.info('Authorization request received:', {
      role: formData.role,
      email: formData.email,
      mac: formData.id
    });

    // Validate form data
    if (!formData.role || !formData.email || !formData.id) {
      return res.status(400).send('Missing required fields');
    }

    // Log the authorization attempt
    const logData = {
      timestamp: new Date().toISOString(),
      role: formData.role,
      email: formData.email,
      floor: formData.floor,
      memberName: formData.memberName,
      mac: formData.id,
      ap: formData.ap,
      ssid: formData.ssid
    };

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

    // For demo purposes, we'll simulate UniFi authorization
    // In production, you would integrate with your actual UniFi controller here
    
    // Redirect to original URL or success page
    const redirectUrl = formData.url || process.env.REDIRECT_URL || 'https://www.google.com';
    res.redirect(redirectUrl);
    
  } catch (error) {
    logger.error('Authorization failed:', error);
    res.status(500).send('Authorization failed. Please try again.');
  }
});

// Health check
app.get('/health', (req: Request, res: Response) => {
  res.json({ status: 'healthy', timestamp: new Date().toISOString() });
});

// Export for Vercel
export default app;
