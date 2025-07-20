# Frontier Tower — Branded Node.js Captive Portal

A custom-branded captive portal for Frontier Tower based on the [unifi-hotspot](https://github.com/woodjme/unifi-hotspot) v3+ project. Built with Node.js 20 and TypeScript, featuring a modern responsive UI and role-based access control.

## Features

- **Role-Based Access**: Member, Guest, and Event access with conditional form fields
- **Modern UI**: Responsive design with smooth animations and branded styling
- **Member Autocomplete**: Smart suggestions for guest sponsors and events
- **UniFi Integration**: Direct API integration with UniFi controllers (including UDM)
- **Flexible Logging**: Webhook and Google Sheets integration
- **Docker Ready**: Easy deployment with Docker and Docker Compose
- **TypeScript**: Type-safe codebase with comprehensive error handling
- **Security**: Helmet.js security headers and input validation

## Requirements

- Node.js 20+
- UniFi Controller with external portal enabled
- Docker & Docker Compose (for containerized deployment)

## Quick Start

### Docker Deployment (Recommended)

1. Clone or create the project:
```bash
mkdir frontier-tower-captive-portal
cd frontier-tower-captive-portal
```

2. Copy and configure environment variables:
```bash
cp .env.example .env
# Edit .env with your UniFi controller details and branding
```

3. Start the service:
```bash
docker-compose up -d
```

4. Access the portal at `http://localhost:4545`

### Manual Installation

1. Install dependencies:
```bash
npm install
```

2. Build the TypeScript:
```bash
npm run build
```

3. Configure environment:
```bash
cp .env.example .env
# Edit .env with your settings
```

4. Start the server:
```bash
npm start
```

## Configuration

### Environment Variables (.env)

```env
# UniFi Controller Configuration
UNIFI_CONTROLLER_URL=https://your-unifi-controller.local:8443
UNIFI_USER=your-username
UNIFI_PASS=your-password
UNIFI_SITE_IDENTIFIER=default
UNIFI_INSECURE=false

# Authentication Settings
AUTH=custom
AUTH_DURATION_MINUTES=480

# Logging Configuration
LOG_AUTH_DRIVER=webhook
LOG_AUTH_WEBHOOK_URL=https://your-webhook-endpoint.com/authorize

# Branding Configuration
BRAND_COLOR=#667eea
BRAND_COLOR_DARK=#5a67d8
LOGO_TEXT=FT

# Server Configuration
PORT=4545
NODE_ENV=production
REDIRECT_URL=https://www.google.com
```

### UniFi Controller Setup

1. Log into your UniFi Controller
2. Go to Settings → Guest Control → Guest Policies
3. Create or edit a Guest Policy:
   - Enable "Portal Customization"
   - Set Authentication to "External Portal Server"
   - Set Portal URL to: `http://your-server-ip:4545/`
   - Set Terms of Use URL to: `http://your-server-ip:4545/`
4. Apply the policy to your guest network

### Webhook Integration

When `LOG_AUTH_DRIVER=webhook`, the portal will POST authorization data to your webhook URL:

```json
{
  "timestamp": "2024-01-15T10:30:00.000Z",
  "role": "member",
  "email": "user@example.com",
  "floor": "5",
  "memberName": "John Smith - 5th Floor",
  "mac": "aa:bb:cc:dd:ee:ff",
  "ap": "access-point-id",
  "ssid": "Frontier-Guest"
}
```

### Google Sheets Integration

To use Google Sheets logging:

1. Set `LOG_AUTH_DRIVER=googlesheets`
2. Create a Google Service Account and download the JSON credentials
3. Base64 encode the JSON file: `base64 -i credentials.json`
4. Set `LOG_AUTH_GOOGLE_CREDENTIALS` to the base64 string
5. Set `LOG_AUTH_GOOGLE_SHEET_ID` to your sheet ID
6. Share the sheet with the service account email

## API Endpoints

- `GET /` - Main captive portal page with branded form
- `POST /authorize` - Handles form submission and UniFi authorization
- `GET /health` - Health check endpoint
- `GET /api/members` - Member autocomplete API (for future enhancement)

## Customization

### Branding

Customize the portal appearance using environment variables:

- `BRAND_COLOR`: Primary brand color (default: #667eea)
- `BRAND_COLOR_DARK`: Darker shade for hover effects (default: #5a67d8)
- `LOGO_TEXT`: Text displayed in the logo circle (default: FT)

### Form Fields

The form automatically shows/hides fields based on role selection:

- **Member**: Email + Floor selection
- **Guest**: Email + Member name (with autocomplete)
- **Event**: Email + Event name (with autocomplete)

### Member Data

Update the `memberData` array in `custom.html` or implement the `/api/members` endpoint to pull from a real database.

## Docker Deployment

### Using Docker Compose

```yaml
version: '3.8'
services:
  frontier-tower-captive-portal:
    build: .
    ports:
      - "4545:4545"
    environment:
      - UNIFI_CONTROLLER_URL=https://your-controller:8443
      - UNIFI_USER=admin
      - UNIFI_PASS=password
      # ... other env vars
    volumes:
      - ./logs:/usr/src/app/logs
    restart: unless-stopped
```

### Custom HTML Override

To use a completely custom HTML file:

```bash
# Mount your custom HTML
docker run -v /path/to/your/custom.html:/usr/src/app/public/custom.html frontier-tower-captive-portal
```

## Development

### Running in Development Mode

```bash
npm run dev
```

### Building for Production

```bash
npm run build
npm start
```

### Project Structure

```
frontier-tower-captive-portal/
├── src/
│   └── index.ts          # Main server application
├── public/
│   └── custom.html       # Branded captive portal page
├── dist/                 # Compiled JavaScript (generated)
├── logs/                 # Application logs
├── Dockerfile            # Docker container definition
├── docker-compose.yml    # Docker Compose configuration
├── package.json          # Node.js dependencies
├── tsconfig.json         # TypeScript configuration
└── .env.example          # Environment variables template
```

## Security Features

- **Helmet.js**: Security headers and XSS protection
- **Input Validation**: Comprehensive form data validation
- **CORS Protection**: Configurable cross-origin resource sharing
- **Rate Limiting**: Built-in protection against abuse
- **Non-root Container**: Docker container runs as non-privileged user
- **Health Checks**: Container health monitoring

## Monitoring & Logging

- **Winston Logger**: Structured logging with multiple transports
- **Access Logs**: All HTTP requests logged
- **Error Logs**: Comprehensive error tracking
- **Authorization Logs**: Detailed user authorization tracking
- **Health Endpoint**: `/health` for monitoring systems

## Troubleshooting

### Common Issues

1. **UniFi Authorization Fails**
   ```bash
   # Check controller connectivity
   curl -k https://your-controller:8443/status
   
   # Verify credentials in logs
   docker logs frontier-tower-captive-portal
   ```

2. **Portal Not Loading**
   ```bash
   # Check if service is running
   docker ps
   
   # Check application logs
   docker logs frontier-tower-captive-portal
   
   # Test health endpoint
   curl http://localhost:4545/health
   ```

3. **Webhook Not Receiving Data**
   ```bash
   # Check webhook URL is accessible
   curl -X POST https://your-webhook-url.com/test
   
   # Verify LOG_AUTH_DRIVER setting
   echo $LOG_AUTH_DRIVER
   ```

### Debug Mode

Enable detailed logging:

```bash
export NODE_ENV=development
# Restart the application
```

### Log Files

- `logs/combined.log` - All application logs
- `logs/error.log` - Error-level logs only
- Console output - Real-time logging

## License

MIT License - see LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## Support

For issues and questions:
- Check the troubleshooting section above
- Review application logs in the `logs/` directory
- Test the health endpoint: `curl http://localhost:4545/health`
- Open an issue on GitHub with detailed logs and configuration
