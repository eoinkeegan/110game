#!/bin/bash
# Production Deployment Script for 110 Card Game
# Run this on your AWS EC2 server to deploy updates

set -e  # Exit on any error

echo "🚀 Deploying 110 Card Game"
echo "=========================="

# Configuration
APP_DIR="/var/www/html"
WEB_USER="apache"  # Change to www-data for Ubuntu

# Navigate to app directory
cd $APP_DIR

# Pull latest changes
echo "📥 Pulling latest code from GitHub..."
sudo git pull origin main

# Install/update dependencies
echo "📦 Installing dependencies..."
sudo composer install --no-dev --optimize-autoloader

# Set permissions
echo "🔒 Setting permissions..."
sudo chown -R $WEB_USER:$WEB_USER $APP_DIR
sudo chmod -R 755 $APP_DIR
sudo chmod 600 $APP_DIR/.env

# Restart services
echo "🔄 Restarting services..."
sudo systemctl restart httpd 2>/dev/null || sudo systemctl restart apache2
sudo systemctl restart websocket

echo ""
echo "✅ Deployment complete!"
echo ""
echo "Services status:"
sudo systemctl status httpd --no-pager -l 2>/dev/null || sudo systemctl status apache2 --no-pager -l
sudo systemctl status websocket --no-pager -l

