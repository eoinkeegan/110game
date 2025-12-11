# 🚀 On-Demand Deployment Guide

This guide covers deploying the 110 Card Game with **on-demand server startup** - the cheapest option for low-usage games.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    ALWAYS ON (~$0.50/month)                     │
│                                                                 │
│   ┌─────────────────┐         ┌────────────────────────────┐   │
│   │   S3 Bucket     │         │  Lambda + API Gateway      │   │
│   │                 │         │                            │   │
│   │  index.html     │  Click  │  start_server.py           │   │
│   │  style.css      │ ──────► │  - Start EC2               │   │
│   │  app-config.js  │         │  - Stop EC2                │   │
│   │                 │         │  - Get status              │   │
│   └─────────────────┘         └────────────────────────────┘   │
│                                           │                    │
└───────────────────────────────────────────│────────────────────┘
                                            │
                    ┌───────────────────────┘
                    │ Starts/Stops
                    ▼
┌───────────────────────────────────────────────────────────────┐
│                ON-DEMAND (~$0.01/hour when running)            │
│                                                                │
│   ┌──────────────────────────────────────────────────────┐    │
│   │                      EC2 Instance                     │    │
│   │                                                       │    │
│   │  Apache + PHP                                         │    │
│   │  game-sqlite.php (SQLite database)                   │    │
│   │  websocket_server.php                                │    │
│   │  auto-shutdown.sh (stops after 2 hours idle)         │    │
│   │                                                       │    │
│   └──────────────────────────────────────────────────────┘    │
│                                                                │
└────────────────────────────────────────────────────────────────┘
```

## Cost Breakdown

| Component | When Running | Monthly Est. |
|-----------|--------------|--------------|
| S3 (static hosting) | Always | ~$0.50 |
| Lambda + API Gateway | Always | ~$0 (free tier) |
| EC2 t3.micro | Only when playing | ~$0.01/hour |

**Example month (4 game nights, 3 hours each):**
- 12 hours × $0.01 = **$0.12**
- S3 + Lambda = **~$0.50**
- **Total: ~$0.62/month**

**Months with no games: ~$0.50/month**

---

## Step 1: Set Up S3 Static Website

1. **Create S3 Bucket**
   - Name: `110game-static` (or your domain name)
   - Region: Your preferred region
   - Uncheck "Block all public access"

2. **Enable Static Website Hosting**
   - Properties → Static website hosting → Enable
   - Index document: `index.html`

3. **Set Bucket Policy** (for public access)
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Effect": "Allow",
               "Principal": "*",
               "Action": "s3:GetObject",
               "Resource": "arn:aws:s3:::110game-static/*"
           }
       ]
   }
   ```

4. **Upload Static Files**
   ```
   index.html
   style.css
   app-config.js
   statistics.html
   statistics.js
   ```

---

## Step 2: Set Up EC2 Game Server

1. **Launch EC2 Instance**
   - AMI: Amazon Linux 2023
   - Type: t3.micro
   - Security Group: Allow 22 (SSH), 80 (HTTP), 443 (HTTPS), 8081 (WebSocket)

2. **Install Software**
   ```bash
   sudo yum update -y
   sudo yum install -y httpd php php-pdo php-sqlite git
   sudo systemctl start httpd
   sudo systemctl enable httpd
   ```

3. **Deploy Game Files**
   ```bash
   cd /var/www
   sudo git clone https://github.com/YOUR_USERNAME/110game.git html
   sudo chown -R apache:apache html
   ```

4. **Create .env File**
   ```bash
   sudo nano /var/www/html/.env
   ```
   ```env
   APP_ENV=production
   WS_HOST=0.0.0.0
   WS_PORT=8081
   ```

5. **Set Up WebSocket Service**
   ```bash
   sudo nano /etc/systemd/system/websocket.service
   ```
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
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable websocket
   sudo systemctl start websocket
   ```

6. **Set Up Auto-Shutdown Cron**
   ```bash
   sudo crontab -e
   ```
   Add:
   ```
   */5 * * * * /var/www/html/scripts/auto-shutdown.sh
   ```

7. **Add IAM Role to EC2** (for auto-shutdown)
   - Create IAM role with policy:
     ```json
     {
         "Version": "2012-10-17",
         "Statement": [{
             "Effect": "Allow",
             "Action": "ec2:StopInstances",
             "Resource": "arn:aws:ec2:REGION:ACCOUNT:instance/INSTANCE_ID"
         }]
     }
     ```
   - Attach role to EC2 instance

8. **Note the Instance ID**
   - You'll need this for the Lambda function

---

## Step 3: Set Up Lambda Function

1. **Create Lambda Function**
   - Name: `110game-server-controller`
   - Runtime: Python 3.11
   - Copy code from `lambda/start_server.py`

2. **Add Environment Variables**
   | Key | Value |
   |-----|-------|
   | `EC2_INSTANCE_ID` | `i-0123456789abcdef0` (your EC2 ID) |
   | `ALLOWED_ORIGINS` | `https://your-s3-bucket.s3.amazonaws.com` |

3. **Attach IAM Policy**
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [{
           "Effect": "Allow",
           "Action": [
               "ec2:StartInstances",
               "ec2:StopInstances",
               "ec2:DescribeInstances"
           ],
           "Resource": "*"
       }]
   }
   ```

4. **Create API Gateway**
   - Create HTTP API
   - Add routes:
     - `GET /server` → Lambda
     - `POST /server` → Lambda
   - Deploy to stage `prod`
   - Note your API URL

---

## Step 4: Configure Frontend

Edit `app-config.js` before uploading to S3:

```javascript
const APP_CONFIG = {
    // Your API Gateway URL
    LAMBDA_URL: 'https://abc123.execute-api.us-east-1.amazonaws.com/prod/server',
    
    // Your EC2 public DNS or Elastic IP
    GAME_SERVER_URL: 'http://ec2-1-2-3-4.compute-1.amazonaws.com',
    
    // WebSocket URL
    WS_URL: 'ws://ec2-1-2-3-4.compute-1.amazonaws.com:8081',
    
    DEBUG: false
};
```

Upload the updated file to S3.

---

## Step 5: (Optional) Set Up Custom Domain

1. **Request SSL Certificate** in ACM (for your domain)

2. **Create CloudFront Distribution**
   - Origin: Your S3 bucket
   - Alternate domain: `game.your-domain.com`
   - SSL Certificate: Your ACM certificate

3. **Update DNS**
   - Point `game.your-domain.com` to CloudFront

4. **Update app-config.js**
   - Set `ALLOWED_ORIGINS` in Lambda to `https://game.your-domain.com`

---

## Testing

1. Visit your S3 website URL
2. You should see "Game Server is Sleeping" with a wake-up button
3. Click "Wake Up Server"
4. Wait ~60 seconds for server to start
5. Game should load automatically
6. After 2 hours of inactivity, server auto-stops

---

## Troubleshooting

### Server won't start
- Check Lambda logs in CloudWatch
- Verify EC2 instance ID is correct
- Check IAM permissions

### Server starts but game won't load
- Check EC2 security group allows port 80
- Check Apache is running: `sudo systemctl status httpd`
- Check error logs: `tail /var/log/httpd/error_log`

### WebSocket not connecting
- Check security group allows port 8081
- Check WebSocket service: `sudo systemctl status websocket`

### Auto-shutdown not working
- Check cron is running: `sudo systemctl status crond`
- Check IAM role attached to EC2
- Check shutdown script logs: `cat /var/log/110game-autoshutdown.log`

---

## Quick Commands

```bash
# SSH to server
ssh -i ~/110game-key.pem ec2-user@YOUR_EC2_IP

# Check services
sudo systemctl status httpd
sudo systemctl status websocket

# View logs
tail -f /var/log/httpd/error_log
tail -f /var/log/110game-autoshutdown.log

# Manually stop server
sudo shutdown -h now
```

