#!/bin/bash

# Frontier Tower Captive Portal - Automated DigitalOcean Deployment
# Run this script on your DigitalOcean droplet after creation

set -e

echo "🚀 Frontier Tower Captive Portal - Automated Deployment"
echo "======================================================"
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "\n${BLUE}==== $1 ====${NC}"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root (use sudo)"
    exit 1
fi

# Get server IP
SERVER_IP=$(curl -s ifconfig.me)
print_status "Server IP detected: $SERVER_IP"

print_step "Step 1: System Update"
print_status "Updating system packages..."
apt update && apt upgrade -y

print_step "Step 2: Install LAMP Stack"
print_status "Installing Apache, MySQL, PHP..."
DEBIAN_FRONTEND=noninteractive apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml libapache2-mod-php8.1

print_status "Enabling Apache modules..."
a2enmod rewrite ssl headers expires deflate

print_step "Step 3: Secure MySQL Installation"
print_status "Configuring MySQL security..."
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'RootPass123!';"
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

print_step "Step 4: Create SSL Certificate"
print_status "Creating self-signed SSL certificate..."
mkdir -p /etc/ssl/certs /etc/ssl/private
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/captive-portal.key \
    -out /etc/ssl/certs/captive-portal.crt \
    -subj "/C=US/ST=CA/L=San Francisco/O=Frontier Tower/CN=$SERVER_IP"

chmod 600 /etc/ssl/private/captive-portal.key
chmod 644 /etc/ssl/certs/captive-portal.crt

print_step "Step 5: Clone Application"
print_status "Cloning Frontier Tower Captive Portal..."
cd /var/www/html
if [ -d "frontier-tower-captive-portal" ]; then
    rm -rf frontier-tower-captive-portal
fi
git clone https://github.com/colygon/frontier-tower-captive-portal.git
cd frontier-tower-captive-portal

print_step "Step 6: Database Setup"
print_status "Creating database and user..."
DB_PASSWORD=$(openssl rand -base64 32)
mysql -u root -pRootPass123! -e "CREATE DATABASE IF NOT EXISTS frontier_portal;"
mysql -u root -pRootPass123! -e "CREATE USER IF NOT EXISTS 'captive_user'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
mysql -u root -pRootPass123! -e "GRANT ALL PRIVILEGES ON frontier_portal.* TO 'captive_user'@'localhost';"
mysql -u root -pRootPass123! -e "FLUSH PRIVILEGES;"

print_status "Importing database schema..."
mysql -u root -pRootPass123! frontier_portal < database/schema.sql

print_step "Step 7: Configure Application"
print_status "Setting up configuration file..."
cp config/config.sample.php config/config.php

# Update database credentials in config
sed -i "s/define('DB_USER', 'your_db_user');/define('DB_USER', 'captive_user');/" config/config.php
sed -i "s/define('DB_PASS', 'your_secure_password');/define('DB_PASS', '$DB_PASSWORD');/" config/config.php
sed -i "s/define('DEBUG_MODE', false);/define('DEBUG_MODE', true);/" config/config.php

print_step "Step 8: Set File Permissions"
print_status "Setting proper file permissions..."
chown -R www-data:www-data /var/www/html/frontier-tower-captive-portal
chmod -R 755 /var/www/html/frontier-tower-captive-portal
chmod 600 /var/www/html/frontier-tower-captive-portal/config/config.php

print_step "Step 9: Configure Apache"
print_status "Setting up Apache virtual host..."

# Create Apache virtual host configuration
cat > /etc/apache2/sites-available/frontier-portal.conf << EOF
<VirtualHost *:80>
    ServerName $SERVER_IP
    DocumentRoot /var/www/html/frontier-tower-captive-portal
    Redirect permanent / https://$SERVER_IP/
</VirtualHost>

<VirtualHost *:443>
    ServerName $SERVER_IP
    DocumentRoot /var/www/html/frontier-tower-captive-portal
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/captive-portal.crt
    SSLCertificateKeyFile /etc/ssl/private/captive-portal.key
    
    # Security Headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    <Directory "/var/www/html/frontier-tower-captive-portal">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        <Files "config.php">
            Require all denied
        </Files>
        
        <Files "*.sql">
            Require all denied
        </Files>
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/captive-portal_error.log
    CustomLog \${APACHE_LOG_DIR}/captive-portal_access.log combined
</VirtualHost>
EOF

# Enable site and disable default
a2ensite frontier-portal.conf
a2dissite 000-default.conf

print_step "Step 10: Configure Firewall"
print_status "Setting up UFW firewall..."
ufw --force enable
ufw allow ssh
ufw allow http
ufw allow https

print_step "Step 11: Install Security Tools"
print_status "Installing fail2ban for additional security..."
apt install -y fail2ban

print_step "Step 12: Restart Services"
print_status "Restarting Apache and MySQL..."
systemctl restart apache2
systemctl restart mysql
systemctl enable apache2
systemctl enable mysql

print_step "Step 13: Final Configuration"
print_status "Adding server IP to hosts file..."
echo "127.0.0.1 $SERVER_IP" >> /etc/hosts

# Create info file with credentials
cat > /root/captive-portal-info.txt << EOF
=== Frontier Tower Captive Portal - Deployment Complete ===

🌐 Portal URL: https://$SERVER_IP/
🔧 Admin Panel: https://$SERVER_IP/admin/

📊 Default Admin Credentials:
   Username: admin
   Password: admin123
   ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!

🗄️  Database Information:
   Database: frontier_portal
   Username: captive_user
   Password: $DB_PASSWORD
   
📝 Configuration File: /var/www/html/frontier-tower-captive-portal/config/config.php

🔧 Next Steps:
1. Access admin panel and change default password
2. Update UniFi Controller settings in config.php:
   - UNIFI_HOST: Your UniFi Controller URL
   - UNIFI_USER: Your UniFi admin username  
   - UNIFI_PASS: Your UniFi admin password
3. Configure your UniFi Controller:
   - Settings → Guest Control → Enable Guest Portal
   - Portal URL: https://$SERVER_IP/
4. Test the complete user flow

📋 Log Files:
   - Apache Error: /var/log/apache2/captive-portal_error.log
   - Apache Access: /var/log/apache2/captive-portal_access.log

🔒 Security Notes:
   - Firewall (UFW) is enabled
   - Fail2ban is installed
   - SSL certificate is self-signed (get Let's Encrypt for production)
   - Change all default passwords!

EOF

print_step "🎉 DEPLOYMENT COMPLETE!"
echo
print_status "Portal URL: https://$SERVER_IP/"
print_status "Admin Panel: https://$SERVER_IP/admin/"
print_warning "Default admin: admin/admin123 - CHANGE IMMEDIATELY!"
echo
print_status "Full deployment details saved to: /root/captive-portal-info.txt"
echo
print_status "Next steps:"
echo "  1. Visit https://$SERVER_IP/admin/ and change admin password"
echo "  2. Update UniFi Controller settings in config.php"
echo "  3. Configure your UniFi Controller guest portal"
echo "  4. Test the complete user flow"
echo
print_status "🚀 Your Frontier Tower Captive Portal is ready!"
