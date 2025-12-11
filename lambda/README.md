# AWS Lambda - Game Server Controller

This Lambda function controls the EC2 game server (start/stop/status).

## Setup Instructions

### 1. Create the Lambda Function

1. Go to AWS Lambda Console → Create Function
2. Choose "Author from scratch"
3. Function name: `110game-server-controller`
4. Runtime: Python 3.11 (or latest)
5. Architecture: x86_64
6. Click "Create function"

### 2. Add the Code

1. Copy the contents of `start_server.py`
2. Paste into the Lambda code editor
3. Click "Deploy"

### 3. Configure Environment Variables

In the Lambda console, go to Configuration → Environment variables:

| Key | Value |
|-----|-------|
| `EC2_INSTANCE_ID` | Your EC2 instance ID (e.g., `i-0123456789abcdef0`) |
| `ALLOWED_ORIGINS` | Your S3 website URL (e.g., `https://game.your-domain.com`) |

### 4. Set Up IAM Permissions

Attach this policy to the Lambda execution role:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ec2:StartInstances",
                "ec2:StopInstances",
                "ec2:DescribeInstances"
            ],
            "Resource": "*"
        }
    ]
}
```

For better security, replace `"Resource": "*"` with your specific instance ARN:
```json
"Resource": "arn:aws:ec2:REGION:ACCOUNT_ID:instance/INSTANCE_ID"
```

### 5. Create API Gateway

1. Go to API Gateway Console → Create API
2. Choose "HTTP API" (simpler and cheaper)
3. Name: `110game-api`
4. Add integration: Lambda → Select your function
5. Configure routes:
   - `GET /server` → Lambda function
   - `POST /server` → Lambda function
6. Deploy to a stage (e.g., `prod`)
7. Note your API endpoint URL (e.g., `https://abc123.execute-api.us-east-1.amazonaws.com/prod`)

### 6. Update Frontend Configuration

Edit `app-config.js`:

```javascript
const APP_CONFIG = {
    LAMBDA_URL: 'https://abc123.execute-api.us-east-1.amazonaws.com/prod/server',
    // ... other config
};
```

## API Reference

### GET /server?action=status
Returns current instance status.

**Response:**
```json
{
    "status": "running|stopped|pending|stopping",
    "publicIp": "1.2.3.4",
    "instanceId": "i-0123456789abcdef0"
}
```

### POST /server
Start or stop the instance.

**Request:**
```json
{
    "action": "start"
}
```

**Response:**
```json
{
    "message": "Instance starting",
    "status": "pending",
    "estimatedTime": 60
}
```

## Cost

- Lambda: First 1M requests/month FREE
- API Gateway: First 1M requests/month FREE
- Typical usage (< 100 requests/month): **$0.00**

