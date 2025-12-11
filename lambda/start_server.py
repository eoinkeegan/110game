"""
AWS Lambda function to start/stop EC2 game server.

This function is called from the static S3 site to wake up the game server.

Environment Variables Required:
- EC2_INSTANCE_ID: The ID of the EC2 instance to control (e.g., i-0123456789abcdef0)
- ALLOWED_ORIGINS: Comma-separated list of allowed origins for CORS (e.g., https://your-domain.com)

IAM Permissions Required:
- ec2:StartInstances
- ec2:StopInstances
- ec2:DescribeInstances
"""

import json
import os
import boto3
from botocore.exceptions import ClientError

ec2 = boto3.client('ec2')

def get_cors_headers():
    """Get CORS headers based on environment configuration."""
    allowed_origins = os.environ.get('ALLOWED_ORIGINS', '*')
    return {
        'Access-Control-Allow-Origin': allowed_origins,
        'Access-Control-Allow-Headers': 'Content-Type',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS'
    }

def lambda_handler(event, context):
    """Main Lambda handler."""
    
    # Handle preflight OPTIONS request
    if event.get('httpMethod') == 'OPTIONS':
        return {
            'statusCode': 200,
            'headers': get_cors_headers(),
            'body': ''
        }
    
    # Get instance ID from environment
    instance_id = os.environ.get('EC2_INSTANCE_ID')
    if not instance_id:
        return {
            'statusCode': 500,
            'headers': get_cors_headers(),
            'body': json.dumps({'error': 'EC2_INSTANCE_ID not configured'})
        }
    
    # Parse action from request
    try:
        if event.get('body'):
            body = json.loads(event['body'])
            action = body.get('action', 'status')
        else:
            # Check query parameters
            params = event.get('queryStringParameters') or {}
            action = params.get('action', 'status')
    except:
        action = 'status'
    
    try:
        if action == 'start':
            return start_instance(instance_id)
        elif action == 'stop':
            return stop_instance(instance_id)
        else:
            return get_instance_status(instance_id)
    except ClientError as e:
        return {
            'statusCode': 500,
            'headers': get_cors_headers(),
            'body': json.dumps({'error': str(e)})
        }

def get_instance_status(instance_id):
    """Get the current status of the EC2 instance."""
    response = ec2.describe_instances(InstanceIds=[instance_id])
    
    if not response['Reservations']:
        return {
            'statusCode': 404,
            'headers': get_cors_headers(),
            'body': json.dumps({'error': 'Instance not found'})
        }
    
    instance = response['Reservations'][0]['Instances'][0]
    state = instance['State']['Name']
    
    # Get public IP if available
    public_ip = instance.get('PublicIpAddress', None)
    
    return {
        'statusCode': 200,
        'headers': get_cors_headers(),
        'body': json.dumps({
            'status': state,
            'publicIp': public_ip,
            'instanceId': instance_id
        })
    }

def start_instance(instance_id):
    """Start the EC2 instance."""
    # First check current status
    response = ec2.describe_instances(InstanceIds=[instance_id])
    instance = response['Reservations'][0]['Instances'][0]
    current_state = instance['State']['Name']
    
    if current_state == 'running':
        return {
            'statusCode': 200,
            'headers': get_cors_headers(),
            'body': json.dumps({
                'message': 'Instance is already running',
                'status': 'running',
                'publicIp': instance.get('PublicIpAddress')
            })
        }
    
    if current_state not in ['stopped', 'stopping']:
        return {
            'statusCode': 400,
            'headers': get_cors_headers(),
            'body': json.dumps({
                'error': f'Cannot start instance in state: {current_state}',
                'status': current_state
            })
        }
    
    # Start the instance
    ec2.start_instances(InstanceIds=[instance_id])
    
    return {
        'statusCode': 200,
        'headers': get_cors_headers(),
        'body': json.dumps({
            'message': 'Instance starting',
            'status': 'pending',
            'estimatedTime': 60  # seconds
        })
    }

def stop_instance(instance_id):
    """Stop the EC2 instance."""
    response = ec2.describe_instances(InstanceIds=[instance_id])
    instance = response['Reservations'][0]['Instances'][0]
    current_state = instance['State']['Name']
    
    if current_state == 'stopped':
        return {
            'statusCode': 200,
            'headers': get_cors_headers(),
            'body': json.dumps({
                'message': 'Instance is already stopped',
                'status': 'stopped'
            })
        }
    
    if current_state != 'running':
        return {
            'statusCode': 400,
            'headers': get_cors_headers(),
            'body': json.dumps({
                'error': f'Cannot stop instance in state: {current_state}',
                'status': current_state
            })
        }
    
    # Stop the instance
    ec2.stop_instances(InstanceIds=[instance_id])
    
    return {
        'statusCode': 200,
        'headers': get_cors_headers(),
        'body': json.dumps({
            'message': 'Instance stopping',
            'status': 'stopping'
        })
    }

