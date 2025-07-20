# Frontier Tower Captive Portal - Deployment Guide

## Quick Start

### Automated Installation (Recommended)
```bash
# Clone or copy the project files to your server
sudo bash install.sh
```

### Manual Installation

#### 1. System Requirements
- Ubuntu 20.04+ or Debian 11+
- Apache 2.4+ or Nginx 1.18+
- PHP 7.4+ (recommended PHP 8.1)
- MySQL 8.0+ or MariaDB 10.5+
- SSL certificate (Let's Encrypt recommended for production)

#### 2. Install LAMP Stack
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache, MySQL, PHP
sudo apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml libapache2-mod-php8.1

# Enable Apache modules
sudo a2enmod rewrite ssl headers expires deflate
```

#### 3. Database Setup
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -e "CREATE DATABASE frontier_portal;"
sudo mysql -e "CREATE USER 'captive_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON frontier_portal.* TO 'captive_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Import database schema
mysql -u captive_user -p frontier_portal < database/schema.sql
```

#### 4. Application Setup
```bash
# Copy files to web directory
sudo cp -r . /var/www/html/frontier-tower-captive-portal/

# Set permissions
sudo chown -R www-data:www-data /var/www/html/frontier-tower-captive-portal/
sudo chmod -R 755 /var/www/html/frontier-tower-captive-portal/
sudo chmod 600 /var/www/html/frontier-tower-captive-portal/config/config.php
```

#### 5. SSL Certificate
```bash
# For production with Let's Encrypt
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d your-domain.com

# For development (self-signed)
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/captive-portal.key \
    -out /etc/ssl/certs/captive-portal.crt
```

#### 6. Apache Configuration
```bash
# Copy virtual host configuration
sudo cp config/apache-vhost.conf /etc/apache2/sites-available/frontier-portal.conf

# Enable site
sudo a2ensite frontier-portal.conf
sudo a2dissite 000-default.conf

# Restart Apache
sudo systemctl restart apache2
```

#### 7. Configuration
Edit `/var/www/html/frontier-tower-captive-portal/config/config.php`:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'frontier_portal');
define('DB_USER', 'captive_user');
define('DB_PASS', 'your_secure_password');

// UniFi Controller Configuration
define('UNIFI_HOST', 'https://your-unifi-controller.local:8443');
define('UNIFI_USER', 'your-unifi-admin');
define('UNIFI_PASS', 'your-unifi-password');
define('UNIFI_SITE', 'default');
define('UNIFI_VERSION', 'UDMP-unifiOS'); // or 'v4', 'v5', etc.

// Site Configuration
define('SITE_NAME', 'Your WiFi Network Name');
define('DEBUG_MODE', false); // Set to false in production
```

## UniFi Controller Setup

### 1. Guest Portal Configuration
1. Access UniFi Controller web interface
2. Go to Settings → Guest Control
3. Enable Guest Portal
4. Set Authentication to "External Portal Server"
5. Set Portal URL to: `https://your-domain.com/`
6. Set Terms of Use URL to: `https://your-domain.com/terms.html` (optional)

### 2. RADIUS Configuration (if using RADIUS)
1. Go to Settings → Profiles → RADIUS
2. Add new RADIUS profile
3. Set server IP to your captive portal server
4. Configure shared secret

### 3. WiFi Network Configuration
1. Create or edit your guest WiFi network
2. Set Security to "Open" or "WPA2 Personal"
3. Enable Guest Policy
4. Set Guest Portal to your configured portal

## Security Hardening

### 1. Firewall Configuration
```bash
# UFW (Ubuntu Firewall)
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
```

### 2. Fail2Ban Setup
```bash
sudo apt install fail2ban
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Edit /etc/fail2ban/jail.local
# Add custom rules for captive portal
```

### 3. Admin Panel Security
- Change default admin password immediately
- Consider IP restrictions for admin access
- Enable two-factor authentication (custom implementation)
- Regular security updates

### 4. Database Security
- Use strong passwords
- Limit database user permissions
- Regular backups
- Consider encryption at rest

## Monitoring and Maintenance

### 1. Log Files
- Apache: `/var/log/apache2/captive-portal_*.log`
- Application: Check `DEBUG_MODE` in config
- MySQL: `/var/log/mysql/error.log`

### 2. Database Maintenance
```bash
# Regular backup
mysqldump -u captive_user -p frontier_portal > backup_$(date +%Y%m%d).sql

# Cleanup old guest records (optional)
mysql -u captive_user -p -e "DELETE FROM guests WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" frontier_portal
```

### 3. Performance Optimization
- Enable PHP OPcache
- Configure MySQL query cache
- Use CDN for static assets
- Implement Redis for session storage (advanced)

## Troubleshooting

### Common Issues

1. **UniFi API Connection Failed**
   - Check UniFi Controller URL and credentials
   - Verify SSL certificate trust
   - Check network connectivity

2. **Database Connection Error**
   - Verify database credentials
   - Check MySQL service status
   - Review database permissions

3. **Permission Denied Errors**
   - Check file ownership: `sudo chown -R www-data:www-data /var/www/html/frontier-tower-captive-portal/`
   - Verify directory permissions: `sudo chmod -R 755 /var/www/html/frontier-tower-captive-portal/`

4. **SSL Certificate Issues**
   - Verify certificate paths in Apache/Nginx config
   - Check certificate validity: `openssl x509 -in /path/to/cert.crt -text -noout`

### Debug Mode
Enable debug mode in `config/config.php`:
```php
define('DEBUG_MODE', true);
```

This will show detailed error messages and API responses.

## Support

For issues and support:
1. Check log files for error messages
2. Verify UniFi Controller configuration
3. Test database connectivity
4. Review Apache/Nginx configuration

## Production Checklist

- [ ] SSL certificate installed and valid
- [ ] Debug mode disabled
- [ ] Strong database passwords
- [ ] Admin password changed from default
- [ ] Firewall configured
- [ ] Fail2Ban installed
- [ ] Regular backups scheduled
- [ ] Log rotation configured
- [ ] UniFi Controller properly configured
- [ ] Guest network tested end-to-end
