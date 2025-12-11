# 🚀 Deployment Guide: 110 Card Game

This guide walks you through deploying the 110 Card Game from localhost to AWS for public access.

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [GitHub Setup](#github-setup)
3. [AWS Deployment Options](#aws-deployment-options)
4. [Option A: EC2 + RDS (Recommended)](#option-a-ec2--rds-recommended)
5. [Option B: Lightsail (Simpler Alternative)](#option-b-lightsail-simpler-alternative)
6. [Domain & SSL Setup](#domain--ssl-setup)
7. [WebSocket Configuration](#websocket-configuration)
8. [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before starting, ensure you have:
- [ ] AWS Account with billing enabled
- [ ] Domain name (you mentioned you already own one)
- [ ] GitHub account
- [ ] Local development environment working

---

## GitHub Setup

### 1. Create GitHub Repository

```bash
# Navigate to your project
cd /Users/ekeegan/Development/110game

# Initialize git
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: 110 Card Game"
```

### 2. Create Repository on GitHub

1. Go to [github.com/new](https://github.com/new)
2. Name: `110game` (or your preferred name)
3. Set to **Private** (contains game logic)
4. Don't initialize with README (you already have one)

### 3. Push to GitHub

```bash
# Add remote (replace YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/110game.git

# Push code
git branch -M main
git push -u origin main
```

### 4. Create Local Environment File

```bash
# Create your local .env file (copy from example)
cp env.example .env

# Edit with your local MAMP settings
# DB_HOST=localhost
# DB_PORT=8889
# DB_PASSWORD=gutsiestmoveieversaw
```

---

## AWS Deployment Options

| Option | Cost | Complexity | Best For |
|--------|------|------------|----------|
| **EC2 + RDS** | ~$15-30/mo | Medium | Full control, scalability |
| **Lightsail** | ~$10-20/mo | Low | Simple, predictable pricing |
| **Elastic Beanstalk** | ~$20-40/mo | Medium | Managed deployments |

**Recommended: EC2 + RDS** for WebSocket support and flexibility.

---

## Option A: EC2 + RDS (Recommended)

### Step 1: Create RDS MySQL Database

1. **Go to AWS RDS Console** → Create Database
2. **Settings**:
   - Engine: MySQL 8.0
   - Template: Free Tier (or Production if expecting traffic)
   - DB Instance: `db.t3.micro` (free tier eligible)
   - DB Identifier: `110game-db`
   - Master username: `admin`
   - Master password: (create a strong password, save it!)
3. **Connectivity**:
   - VPC: Default
   - Public access: **No** (more secure)
   - Create new Security Group: `110game-db-sg`
4. **Additional**:
   - Initial database name: `game_110`

**Wait for the database to be created** (~5-10 minutes)

### Step 2: Create EC2 Instance

1. **Go to EC2 Console** → Launch Instance
2. **Settings**:
   - Name: `110game-server`
   - AMI: **Amazon Linux 2023** or **Ubuntu 22.04 LTS**
   - Instance type: `t3.micro` (free tier) or `t3.small`
   - Key pair: Create new → `110game-key` (download and save the .pem file!)
3. **Network Settings**:
   - Allow SSH (port 22)
   - Allow HTTP (port 80)
   - Allow HTTPS (port 443)
   - Add custom TCP: port **8081** (WebSocket)
4. **Storage**: 20 GB gp3

**Launch the instance!**

### Step 3: Configure Security Groups

1. **EC2 Security Group** → Edit inbound rules:
   - SSH (22): Your IP only
   - HTTP (80): 0.0.0.0/0
   - HTTPS (443): 0.0.0.0/0
   - Custom TCP (8081): 0.0.0.0/0 (WebSocket)

2. **RDS Security Group** → Edit inbound rules:
   - MySQL (3306): EC2 Security Group ID

### Step 4: Connect to EC2 & Install Software

```bash
# Connect via SSH (replace with your instance's public IP)
ssh -i ~/Downloads/110game-key.pem ec2-user@YOUR_EC2_PUBLIC_IP

# Update system
sudo yum update -y  # Amazon Linux
# OR
sudo apt update && sudo apt upgrade -y  # Ubuntu

# Install required packages
# For Amazon Linux 2023:
sudo yum install -y httpd php php-mysqli php-json php-mbstring git

# For Ubuntu:
sudo apt install -y apache2 php php-mysql php-json php-mbstring git composer

# Start Apache
sudo systemctl start httpd  # Amazon Linux
sudo systemctl enable httpd
# OR
sudo systemctl start apache2  # Ubuntu
sudo systemctl enable apache2
```

### Step 5: Install Composer (for WebSocket server)

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 6: Deploy Your Code

```bash
# Clone your repository
cd /var/www
sudo git clone https://github.com/YOUR_USERNAME/110game.git html
# OR if private repo, use personal access token:
sudo git clone https://YOUR_TOKEN@github.com/YOUR_USERNAME/110game.git html

# Set permissions
sudo chown -R apache:apache /var/www/html  # Amazon Linux
# OR
sudo chown -R www-data:www-data /var/www/html  # Ubuntu

# Install PHP dependencies
cd /var/www/html
sudo composer install --no-dev
```

### Step 7: Configure Environment Variables

```bash
# Create production .env file
sudo nano /var/www/html/.env
```

Add the following (replace with your actual values):

```env
DB_HOST=your-rds-endpoint.region.rds.amazonaws.com
DB_PORT=3306
DB_USER=admin
DB_PASSWORD=your-rds-password
DB_NAME=game_110

WS_HOST=0.0.0.0
WS_PORT=8081

APP_ENV=production
APP_DEBUG=false
APP_DOMAIN=your-domain.com
```

### Step 8: Configure Apache

```bash
# Edit Apache config
sudo nano /etc/httpd/conf/httpd.conf  # Amazon Linux
# OR
sudo nano /etc/apache2/sites-available/000-default.conf  # Ubuntu
```

For Amazon Linux, find and modify:
```apache
<Directory "/var/www/html">
    AllowOverride All
    Require all granted
</Directory>
```

Restart Apache:
```bash
sudo systemctl restart httpd  # Amazon Linux
# OR
sudo systemctl restart apache2  # Ubuntu
```

### Step 9: Initialize Database

```bash
# Connect to RDS from EC2
mysql -h your-rds-endpoint.region.rds.amazonaws.com -u admin -p

# In MySQL, create the database tables
CREATE DATABASE IF NOT EXISTS game_110;
USE game_110;

# The tables will be auto-created by game.php on first request
```

### Step 10: Setup WebSocket Server as Service

```bash
# Create systemd service file
sudo nano /etc/systemd/system/websocket.service
```

Add:
```ini
[Unit]
Description=110 Game WebSocket Server
After=network.target

[Service]
Type=simple
User=apache
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php /var/www/html/websocket_server.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable websocket
sudo systemctl start websocket

# Check status
sudo systemctl status websocket
```

---

## Option B: Lightsail (Simpler Alternative)

AWS Lightsail provides a simpler, all-in-one solution:

1. **Create Lightsail Instance**:
   - Go to [Lightsail Console](https://lightsail.aws.amazon.com)
   - Create instance → Linux → **LAMP (PHP 8)**
   - Plan: $10/month (2 GB RAM recommended for WebSocket)

2. **Create Lightsail Database**:
   - Databases → Create → MySQL
   - Plan: $15/month

3. **Connect and Deploy**:
   - Click "Connect using SSH" in Lightsail console
   - Follow Steps 6-10 from Option A above

---

## Domain & SSL Setup

### Point Your Domain to EC2

1. **Get your EC2 Elastic IP**:
   - EC2 Console → Elastic IPs → Allocate → Associate with your instance

2. **Update DNS**:
   - Go to your domain registrar
   - Add/update A record: `@` → Your Elastic IP
   - Add A record: `www` → Your Elastic IP

### Install SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
# Amazon Linux 2023:
sudo yum install -y certbot python3-certbot-apache

# Ubuntu:
sudo apt install -y certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo systemctl enable certbot-renew.timer
```

### Update app-config.js for Production

Edit `/var/www/html/app-config.js`:

```javascript
const APP_CONFIG = {
    WS_URL: 'wss://your-domain.com:8081',
    API_BASE: '',
    DEBUG: false
};
```

---

## WebSocket Configuration

### For Production with SSL

WebSocket over SSL (wss://) requires additional configuration:

**Option 1: Use Nginx as Reverse Proxy** (Recommended)

```bash
# Install Nginx
sudo yum install -y nginx  # Amazon Linux
# OR
sudo apt install -y nginx  # Ubuntu

# Configure Nginx
sudo nano /etc/nginx/conf.d/websocket.conf
```

Add:
```nginx
upstream websocket {
    server 127.0.0.1:8081;
}

server {
    listen 443 ssl;
    server_name your-domain.com;

    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    location /ws {
        proxy_pass http://websocket;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
}
```

Update `app-config.js`:
```javascript
WS_URL: 'wss://your-domain.com/ws'
```

---

## Troubleshooting

### Common Issues

**1. Database Connection Failed**
```bash
# Test database connection
mysql -h YOUR_RDS_ENDPOINT -u admin -p

# Check EC2 security group allows outbound MySQL
# Check RDS security group allows inbound from EC2
```

**2. WebSocket Not Connecting**
```bash
# Check if WebSocket server is running
sudo systemctl status websocket

# Check logs
sudo journalctl -u websocket -f

# Test locally
php /var/www/html/websocket_server.php
```

**3. Permissions Errors**
```bash
# Fix Apache permissions
sudo chown -R apache:apache /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod 600 /var/www/html/.env
```

**4. SSL Certificate Issues**
```bash
# Renew manually
sudo certbot renew --dry-run
```

---

## Cost Estimate

| Service | Monthly Cost |
|---------|-------------|
| EC2 t3.small | ~$15 |
| RDS db.t3.micro | ~$15 |
| Elastic IP | Free (when attached) |
| Data Transfer | ~$1-5 |
| **Total** | **~$31-35/month** |

Free tier eligible for first 12 months:
- EC2: 750 hours t3.micro
- RDS: 750 hours db.t3.micro

---

## Deployment Checklist

- [ ] GitHub repository created and code pushed
- [ ] AWS RDS database created
- [ ] AWS EC2 instance launched
- [ ] Security groups configured
- [ ] PHP & Apache installed
- [ ] Code deployed to EC2
- [ ] Environment variables configured
- [ ] Database initialized
- [ ] WebSocket server running as service
- [ ] Domain pointed to EC2
- [ ] SSL certificate installed
- [ ] Tested all game features
- [ ] Shared URL with friends & family! 🎉

---

## Quick Reference Commands

```bash
# SSH to server
ssh -i ~/path/to/110game-key.pem ec2-user@YOUR_IP

# Deploy updates
cd /var/www/html && sudo git pull

# Restart services
sudo systemctl restart httpd
sudo systemctl restart websocket

# View logs
sudo tail -f /var/log/httpd/error_log
sudo journalctl -u websocket -f
```

---

## Need Help?

If you run into issues:
1. Check CloudWatch logs in AWS Console
2. Review Apache error logs: `/var/log/httpd/error_log`
3. Check WebSocket status: `sudo systemctl status websocket`
4. Verify security group rules allow traffic

