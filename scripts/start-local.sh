#!/bin/bash
# Local Development Startup Script for 110 Card Game

echo "🃏 Starting 110 Card Game Local Development Environment"
echo "======================================================="

# Check if MAMP is running
if ! pgrep -x "mysqld" > /dev/null 2>&1; then
    echo "⚠️  MySQL doesn't appear to be running."
    echo "   Please start MAMP first, then run this script again."
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "⚠️  No .env file found. Creating from template..."
    if [ -f "env.example" ]; then
        cp env.example .env
        echo "   Created .env from env.example"
        echo "   Please update .env with your local MAMP credentials:"
        echo "   - DB_PORT=8889 (MAMP default)"
        echo "   - DB_PASSWORD=your_password"
    else
        echo "   ❌ env.example not found!"
        exit 1
    fi
fi

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo ""
echo "📂 Project directory: $PROJECT_DIR"
echo ""

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing PHP dependencies..."
    composer install
fi

echo "🌐 Starting PHP development server on http://localhost:8080"
echo "   (Press Ctrl+C to stop)"
echo ""

# Start WebSocket server in background
echo "🔌 Starting WebSocket server on ws://localhost:8081"
php websocket_server.php &
WS_PID=$!

# Start PHP built-in server
php -S localhost:8080 &
PHP_PID=$!

echo ""
echo "✅ Services started!"
echo "   - Game:      http://localhost:8080"
echo "   - WebSocket: ws://localhost:8081"
echo ""
echo "Press Ctrl+C to stop all services..."

# Cleanup function
cleanup() {
    echo ""
    echo "🛑 Stopping services..."
    kill $WS_PID 2>/dev/null
    kill $PHP_PID 2>/dev/null
    echo "👋 Goodbye!"
    exit 0
}

trap cleanup SIGINT SIGTERM

# Wait for processes
wait

