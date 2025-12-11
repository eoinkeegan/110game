#!/bin/bash
#
# Auto-shutdown script for 110 Card Game Server
# 
# This script monitors server activity and shuts down the instance
# after a period of inactivity to save costs.
#
# Install as a cron job:
#   sudo crontab -e
#   Add: */5 * * * * /var/www/html/scripts/auto-shutdown.sh
#
# Or run as a systemd timer for more control.

# Configuration
IDLE_THRESHOLD_MINUTES=120  # Shutdown after 2 hours of inactivity
ACTIVITY_FILE="/tmp/110game_last_activity"
LOG_FILE="/var/log/110game-autoshutdown.log"

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Check if the game database has been modified recently
check_database_activity() {
    DB_FILE="/var/www/html/data/game.db"
    
    if [ ! -f "$DB_FILE" ]; then
        return 1  # No database = no activity
    fi
    
    # Get file modification time in minutes
    CURRENT_TIME=$(date +%s)
    DB_MOD_TIME=$(stat -c %Y "$DB_FILE" 2>/dev/null || stat -f %m "$DB_FILE" 2>/dev/null)
    
    if [ -z "$DB_MOD_TIME" ]; then
        return 1
    fi
    
    AGE_MINUTES=$(( (CURRENT_TIME - DB_MOD_TIME) / 60 ))
    
    if [ $AGE_MINUTES -lt $IDLE_THRESHOLD_MINUTES ]; then
        return 0  # Active
    fi
    
    return 1  # Idle
}

# Check if there are active WebSocket connections
check_websocket_activity() {
    # Count established connections on WebSocket port
    WS_CONNECTIONS=$(netstat -an 2>/dev/null | grep ":8081.*ESTABLISHED" | wc -l)
    
    if [ "$WS_CONNECTIONS" -gt 0 ]; then
        return 0  # Active
    fi
    
    return 1  # Idle
}

# Check Apache access logs for recent requests
check_http_activity() {
    ACCESS_LOG="/var/log/httpd/access_log"
    
    if [ ! -f "$ACCESS_LOG" ]; then
        ACCESS_LOG="/var/log/apache2/access.log"  # Ubuntu
    fi
    
    if [ ! -f "$ACCESS_LOG" ]; then
        return 1
    fi
    
    # Check for requests in last IDLE_THRESHOLD_MINUTES
    RECENT_REQUESTS=$(find "$ACCESS_LOG" -mmin -$IDLE_THRESHOLD_MINUTES 2>/dev/null)
    
    if [ -n "$RECENT_REQUESTS" ]; then
        # Actually check log content for game-related requests
        GAME_REQUESTS=$(tail -1000 "$ACCESS_LOG" | grep -c "game.*\.php")
        if [ "$GAME_REQUESTS" -gt 0 ]; then
            return 0  # Active
        fi
    fi
    
    return 1  # Idle
}

# Main logic
main() {
    log "Checking server activity..."
    
    # Check various activity indicators
    if check_database_activity; then
        log "Database activity detected - server is active"
        echo $(date +%s) > "$ACTIVITY_FILE"
        exit 0
    fi
    
    if check_websocket_activity; then
        log "WebSocket connections detected - server is active"
        echo $(date +%s) > "$ACTIVITY_FILE"
        exit 0
    fi
    
    if check_http_activity; then
        log "HTTP activity detected - server is active"
        echo $(date +%s) > "$ACTIVITY_FILE"
        exit 0
    fi
    
    # No activity detected - check how long
    if [ -f "$ACTIVITY_FILE" ]; then
        LAST_ACTIVITY=$(cat "$ACTIVITY_FILE")
    else
        # First run - create activity file
        echo $(date +%s) > "$ACTIVITY_FILE"
        log "First run - creating activity tracking file"
        exit 0
    fi
    
    CURRENT_TIME=$(date +%s)
    IDLE_SECONDS=$((CURRENT_TIME - LAST_ACTIVITY))
    IDLE_MINUTES=$((IDLE_SECONDS / 60))
    
    log "Server idle for $IDLE_MINUTES minutes (threshold: $IDLE_THRESHOLD_MINUTES)"
    
    if [ $IDLE_MINUTES -ge $IDLE_THRESHOLD_MINUTES ]; then
        log "IDLE THRESHOLD REACHED - Initiating shutdown!"
        
        # Optional: Send notification before shutdown
        # curl -X POST "https://your-webhook-url" -d "message=Game server shutting down due to inactivity"
        
        # Shutdown the instance
        # Using AWS CLI to stop instance (requires IAM role with ec2:StopInstances permission)
        INSTANCE_ID=$(curl -s http://169.254.169.254/latest/meta-data/instance-id)
        
        if [ -n "$INSTANCE_ID" ]; then
            log "Stopping instance: $INSTANCE_ID"
            aws ec2 stop-instances --instance-ids "$INSTANCE_ID"
        else
            log "Could not determine instance ID - falling back to system shutdown"
            sudo shutdown -h now "110 Game Server: Auto-shutdown due to inactivity"
        fi
    fi
}

main "$@"

