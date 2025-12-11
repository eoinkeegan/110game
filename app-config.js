/**
 * Frontend Configuration for 110 Card Game
 * 
 * This file configures the game for different deployment environments.
 * 
 * For LOCAL development: Use defaults (no changes needed)
 * For PRODUCTION: Update LAMBDA_URL after deploying Lambda + API Gateway
 */

const APP_CONFIG = {
    // Lambda function URL for server control (start/stop/status)
    // Set this after deploying your Lambda + API Gateway
    // Example: 'https://abc123.execute-api.us-east-1.amazonaws.com/prod/server'
    LAMBDA_URL: '',  // Empty = assume server is always on (local dev mode)
    
    // Game server URL (where game.php runs)
    // For local development: leave empty (uses same origin)
    // For production S3 + EC2: set to your EC2 domain
    // Example: 'https://game.your-domain.com'
    GAME_SERVER_URL: '',
    
    // WebSocket server URL
    // Automatically determined based on protocol and hostname
    WS_URL: (function() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const host = window.location.hostname;
        const port = 8081;
        return `${protocol}//${host}:${port}`;
    })(),
    
    // Enable debug logging
    DEBUG: window.location.hostname === 'localhost',
    
    // Server startup configuration
    SERVER_STARTUP: {
        // How long to wait between status checks (ms)
        POLL_INTERVAL: 3000,
        // Maximum time to wait for server to start (ms)
        MAX_WAIT_TIME: 120000,
        // How long to wait for health check after EC2 reports running (ms)
        HEALTH_CHECK_DELAY: 10000
    }
};

// Make config globally available
window.APP_CONFIG = APP_CONFIG;

/**
 * Check if the game server is running
 * @returns {Promise<{running: boolean, publicIp?: string}>}
 */
window.checkServerStatus = async function() {
    // If no Lambda URL configured, assume server is always on (local dev)
    if (!APP_CONFIG.LAMBDA_URL) {
        try {
            const response = await fetch(getApiUrl('health'), { 
                method: 'GET',
                signal: AbortSignal.timeout(5000)
            });
            return { running: response.ok };
        } catch (e) {
            return { running: false };
        }
    }
    
    try {
        const response = await fetch(`${APP_CONFIG.LAMBDA_URL}?action=status`);
        const data = await response.json();
        return {
            running: data.status === 'running',
            status: data.status,
            publicIp: data.publicIp
        };
    } catch (e) {
        console.error('Failed to check server status:', e);
        return { running: false, error: e.message };
    }
};

/**
 * Start the game server
 * @returns {Promise<{success: boolean, message: string}>}
 */
window.startGameServer = async function() {
    if (!APP_CONFIG.LAMBDA_URL) {
        return { success: false, message: 'Server control not configured' };
    }
    
    try {
        const response = await fetch(APP_CONFIG.LAMBDA_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'start' })
        });
        const data = await response.json();
        return {
            success: response.ok,
            message: data.message || data.error,
            estimatedTime: data.estimatedTime
        };
    } catch (e) {
        console.error('Failed to start server:', e);
        return { success: false, message: e.message };
    }
};

/**
 * Stop the game server
 * @returns {Promise<{success: boolean, message: string}>}
 */
window.stopGameServer = async function() {
    if (!APP_CONFIG.LAMBDA_URL) {
        return { success: false, message: 'Server control not configured' };
    }
    
    try {
        const response = await fetch(APP_CONFIG.LAMBDA_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'stop' })
        });
        const data = await response.json();
        return { success: response.ok, message: data.message || data.error };
    } catch (e) {
        console.error('Failed to stop server:', e);
        return { success: false, message: e.message };
    }
};

/**
 * Get the full API URL for a given endpoint
 * @param {string} endpoint - The API endpoint (e.g., 'health', 'createGame')
 * @returns {string} Full URL
 */
window.getApiUrl = function(endpoint) {
    const base = APP_CONFIG.GAME_SERVER_URL || '';
    // In production (with Lambda), use SQLite version
    // In local development, use original MySQL version
    const phpFile = APP_CONFIG.LAMBDA_URL ? 'game-sqlite.php' : 'game.php';
    return `${base}/${phpFile}?endpoint=${endpoint}`;
};

/**
 * Wait for server to be ready
 * @param {function} onProgress - Callback with progress updates
 * @returns {Promise<boolean>} True if server is ready
 */
window.waitForServerReady = async function(onProgress) {
    const startTime = Date.now();
    const { POLL_INTERVAL, MAX_WAIT_TIME, HEALTH_CHECK_DELAY } = APP_CONFIG.SERVER_STARTUP;
    
    let serverRunning = false;
    
    while (Date.now() - startTime < MAX_WAIT_TIME) {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(95, (elapsed / MAX_WAIT_TIME) * 100);
        
        // Check EC2 status
        const status = await checkServerStatus();
        
        if (status.running && !serverRunning) {
            serverRunning = true;
            onProgress?.({ 
                phase: 'booting', 
                progress: 70, 
                message: 'Server running, waiting for services...' 
            });
            // Wait a bit for Apache/PHP to start
            await new Promise(r => setTimeout(r, HEALTH_CHECK_DELAY));
        }
        
        if (serverRunning) {
            // Check if game API is responding
            try {
                const healthCheck = await fetch(getApiUrl('health'), {
                    signal: AbortSignal.timeout(5000)
                });
                if (healthCheck.ok) {
                    onProgress?.({ phase: 'ready', progress: 100, message: 'Server ready!' });
                    return true;
                }
            } catch (e) {
                // API not ready yet
            }
            onProgress?.({ 
                phase: 'starting', 
                progress: 70 + (elapsed / MAX_WAIT_TIME) * 25, 
                message: 'Starting game services...' 
            });
        } else {
            onProgress?.({ 
                phase: 'booting', 
                progress, 
                message: `Starting server... (${Math.round(elapsed/1000)}s)` 
            });
        }
        
        await new Promise(r => setTimeout(r, POLL_INTERVAL));
    }
    
    return false;
};
