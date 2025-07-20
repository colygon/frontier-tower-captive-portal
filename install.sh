#!/bin/bash

# Frontier Tower Captive Portal Installation Script
# Run with sudo: sudo bash install.sh

set -e

echo "=== Frontier Tower Captive Portal Installation ==="
echo

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Update system
echo "Updating system packages..."
apt update && apt upgrade -y

# Install LAMP stack
echo "Installing Apache, MySQL, PHP..."
apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml libapache2-mod-php8.1

# Enable Apache modules
echo "Enabling Apache modules..."
a2enmod rewrite ssl headers expires deflate

# Install SSL certificate (self-signed for development)
echo "Creating SSL certificate..."
mkdir -p /etc/ssl/certs /etc/ssl/private
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/captive-portal.key \
    -out /etc/ssl/certs/captive-portal.crt \
    -subj "/C=US/ST=CA/L=San Francisco/O=Frontier Tower/CN=captive.frontiertower.local"

# Set permissions
chmod 600 /etc/ssl/private/captive-portal.key
chmod 644 /etc/ssl/certs/captive-portal.crt

# Copy application files
echo "Copying application files..."
INSTALL_DIR="/var/www/html/frontier-tower-captive-portal"
mkdir -p "$INSTALL_DIR"
cp -r ./* "$INSTALL_DIR/"

# Set proper permissions
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 600 "$INSTALL_DIR/config/config.php"

# Configure Apache virtual host
echo "Configuring Apache virtual host..."
cp "$INSTALL_DIR/config/apache-vhost.conf" /etc/apache2/sites-available/frontier-portal.conf
a2ensite frontier-portal.conf
a2dissite 000-default.conf

# Setup MySQL database
echo "Setting up MySQL database..."
mysql -e "CREATE DATABASE IF NOT EXISTS frontier_portal;"
mysql -e "CREATE USER IF NOT EXISTS 'captive_user'@'localhost' IDENTIFIED BY 'secure_password_change_me';"
mysql -e "GRANT ALL PRIVILEGES ON frontier_portal.* TO 'captive_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import database schema
mysql frontier_portal < "$INSTALL_DIR/database/schema.sql"

# Update configuration file
echo "Updating configuration..."
sed -i "s/define('DB_USER', 'root');/define('DB_USER', 'captive_user');/" "$INSTALL_DIR/config/config.php"
sed -i "s/define('DB_PASS', '');/define('DB_PASS', 'secure_password_change_me');/" "$INSTALL_DIR/config/config.php"

# Restart services
echo "Restarting services..."
systemctl restart apache2
systemctl restart mysql

# Enable services on boot
systemctl enable apache2
systemctl enable mysql

# Add to hosts file for testing
echo "127.0.0.1 captive.frontiertower.local" >> /etc/hosts

echo
echo "=== Installation Complete! ==="
echo
echo "Next steps:"
echo "1. Update UniFi Controller settings in $INSTALL_DIR/config/config.php"
echo "2. Change database password from default"
echo "3. Access admin panel at: https://captive.frontiertower.local/admin/"
echo "4. Default admin credentials: admin / admin123 (CHANGE IMMEDIATELY)"
echo "5. Configure your UniFi Controller to redirect to: https://captive.frontiertower.local/"
echo
echo "For production deployment:"
echo "- Get a proper SSL certificate"
echo "- Configure firewall rules"
echo "- Set up log rotation"
echo "- Enable fail2ban for security"
echo
echo "Portal URL: https://captive.frontiertower.local/"
echo "Admin URL: https://captive.frontiertower.local/admin/"
