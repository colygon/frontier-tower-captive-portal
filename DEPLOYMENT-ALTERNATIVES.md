# Deployment Alternatives for Frontier Tower Captive Portal

## ❌ Why Vercel Won't Work

**Vercel is NOT compatible** with this PHP/LAMP stack application because:
- Vercel only supports static sites and serverless functions (Node.js, Python, Go)
- No PHP runtime environment
- No MySQL database support
- No persistent file system for sessions
- No server-side processing capabilities required for UniFi API

## ✅ Recommended Deployment Options

### 1. **DigitalOcean Droplet** (Recommended)
**Cost:** $6-12/month | **Difficulty:** Easy

```bash
# Create Ubuntu 22.04 droplet
# SSH into server and run:
git clone https://github.com/yourusername/frontier-tower-captive-portal.git
cd frontier-tower-captive-portal
sudo bash install.sh
```

**Pros:**
- Full LAMP stack support
- Easy one-click installation
- Affordable pricing
- Great documentation

### 2. **AWS Lightsail**
**Cost:** $5-10/month | **Difficulty:** Easy

- Create WordPress/LAMP instance
- Replace WordPress files with captive portal
- Run installation script

### 3. **Linode**
**Cost:** $5-12/month | **Difficulty:** Easy

- Similar to DigitalOcean
- Excellent performance
- Good documentation

### 4. **Shared Hosting** (Budget Option)
**Cost:** $3-8/month | **Difficulty:** Medium

**Compatible Providers:**
- **SiteGround** - Excellent PHP/MySQL support
- **A2 Hosting** - Fast SSD hosting
- **InMotion Hosting** - Business-grade hosting

**Setup Process:**
1. Upload files via FTP/cPanel File Manager
2. Create MySQL database through cPanel
3. Import `database/schema.sql`
4. Configure `config/config.php`

### 5. **VPS Providers**
**Cost:** $5-20/month | **Difficulty:** Medium

**Options:**
- **Vultr** - High performance
- **Hetzner** - European provider, great value
- **OVH** - Global provider
- **Contabo** - Budget-friendly

### 6. **Cloud Platforms**
**Cost:** Variable | **Difficulty:** Hard

**Google Cloud Platform:**
- Use Compute Engine with LAMP stack
- Cloud SQL for MySQL

**AWS:**
- EC2 instance with LAMP
- RDS for MySQL

**Azure:**
- Virtual Machine with LAMP
- Azure Database for MySQL

## 🚀 Quick Start Guide

### Option 1: DigitalOcean (Recommended)

1. **Create Account & Droplet**
   ```bash
   # Choose Ubuntu 22.04 LTS
   # Select $6/month basic plan
   # Add SSH key for security
   ```

2. **Deploy Application**
   ```bash
   ssh root@your-server-ip
   git clone https://github.com/yourusername/frontier-tower-captive-portal.git
   cd frontier-tower-captive-portal
   sudo bash install.sh
   ```

3. **Configure Domain**
   - Point your domain to server IP
   - Update config with your domain
   - Get SSL certificate with Let's Encrypt

### Option 2: Shared Hosting (Budget)

1. **Choose Provider** (SiteGround recommended)
2. **Upload Files**
   ```bash
   # Via FTP or cPanel File Manager
   # Upload all files to public_html/
   ```

3. **Setup Database**
   - Create MySQL database in cPanel
   - Import `database/schema.sql`
   - Update `config/config.php`

## 🔧 Configuration Steps

### 1. Domain Setup
```bash
# Update your domain's DNS:
# A Record: @ -> your-server-ip
# A Record: www -> your-server-ip
```

### 2. SSL Certificate
```bash
# For Let's Encrypt (free):
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### 3. UniFi Controller
```php
// Update config/config.php:
define('UNIFI_HOST', 'https://your-unifi-controller:8443');
define('UNIFI_USER', 'admin');
define('UNIFI_PASS', 'your-password');
```

### 4. UniFi Guest Portal Settings
- Login to UniFi Controller
- Settings → Guest Control
- Enable Guest Portal
- Set Portal URL: `https://yourdomain.com/`

## 💰 Cost Comparison

| Provider | Monthly Cost | Setup Difficulty | Performance |
|----------|-------------|------------------|-------------|
| DigitalOcean | $6-12 | Easy | Excellent |
| AWS Lightsail | $5-10 | Easy | Good |
| Shared Hosting | $3-8 | Medium | Fair |
| Linode | $5-12 | Easy | Excellent |
| Vultr | $6-20 | Medium | Excellent |

## 🎯 Recommendation

**For most users:** Start with **DigitalOcean Droplet**
- Easy setup with automated script
- Reliable performance
- Good documentation
- Affordable pricing
- Full control over server

**For budget-conscious:** Use **SiteGround shared hosting**
- Cheapest option
- Good PHP/MySQL support
- Less control but easier maintenance

## 📞 Need Help?

1. **DigitalOcean Tutorial:** [How to Install LAMP Stack](https://www.digitalocean.com/community/tutorials/how-to-install-linux-apache-mysql-php-lamp-stack-on-ubuntu-22-04)
2. **UniFi Documentation:** [Guest Portal Setup](https://help.ui.com/hc/en-us/articles/115000166827-UniFi-Guest-Portal-and-Hotspot-System)
3. **Let's Encrypt SSL:** [Certbot Instructions](https://certbot.eff.org/)

## 🔄 Migration from Development

If you've been testing locally:

1. Export your database:
   ```bash
   mysqldump -u root -p frontier_portal > backup.sql
   ```

2. Upload files and database to production server

3. Update config with production settings

4. Test UniFi integration

5. Update UniFi Controller portal URL
