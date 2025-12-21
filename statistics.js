// Determine API URL based on environment
const STATS_API_URL = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
    ? 'game-sqlite.php'
    : (window.APP_CONFIG?.GAME_SERVER_URL ? window.APP_CONFIG.GAME_SERVER_URL + '/game-sqlite.php' : 'game-sqlite.php');

// Utility function to sanitize user inputs
function sanitizeInput(input) {
    if (input === null || input === undefined) return '';
    const tempDiv = document.createElement('div');
    tempDiv.innerText = String(input);
    return tempDiv.innerHTML.trim();
}

// Utility function to display error messages
function displayErrorMessage(message) {
    const gameMessageDiv = document.getElementById('game-message');
    if (gameMessageDiv) {
        gameMessageDiv.innerText = message;
        gameMessageDiv.style.display = 'block';
    }
}

// Fetch and display overall statistics
function fetchOverallStatistics() {
    fetch(`${STATS_API_URL}?endpoint=getOverallStatistics`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error fetching overall statistics: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            const overallStatsDiv = document.getElementById('overall-stats');
            if (data.error) {
                overallStatsDiv.innerHTML = `<h2>Overall Statistics</h2><p>Error: ${sanitizeInput(data.error)}</p>`;
                return;
            }
            
            overallStatsDiv.innerHTML = `
                <h2>Overall Statistics</h2>
                <p><strong>✅ Completed Games:</strong> ${sanitizeInput(data.completedGames || 0)}</p>
                <p><strong>🏆 Top Player:</strong> ${sanitizeInput(data.topPlayer)}</p>
            `;
        })
        .catch(error => {
            console.error(error);
            const overallStatsDiv = document.getElementById('overall-stats');
            overallStatsDiv.innerHTML = `<h2>Overall Statistics</h2><p>No statistics available yet. Play some games!</p>`;
        });
}

// Fetch and display game history
function fetchGameHistory() {
    fetch(`${STATS_API_URL}?endpoint=getGameHistory`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error fetching game history: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            const gameHistoryDiv = document.getElementById('game-history');
            
            if (data.error) {
                gameHistoryDiv.innerHTML = `<h2>Game History</h2><p>Error: ${sanitizeInput(data.error)}</p>`;
                return;
            }
            
            // Filter to only show completed games
            const completedGames = (data.games || []).filter(game => game.status === 'Completed');
            
            if (completedGames.length === 0) {
                gameHistoryDiv.innerHTML = `<h2>Game History</h2><p>No completed games yet. Finish a game to see it here!</p>`;
                return;
            }

            gameHistoryDiv.innerHTML = `
                <h2>Game History</h2>
                <ul class="game-list">
                    ${completedGames.map(game => {
                        return `
                            <li class="game-item" data-game-id="${sanitizeInput(game.game_id)}">
                                <button class="delete-x-btn" onclick="deleteGame(${sanitizeInput(game.game_id)}, event)" title="Delete game">×</button>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>${sanitizeInput(game.code)}</strong>
                                </div>
                                <div style="margin-top: 5px; font-size: 14px; color: #ccc;">
                                    ${game.winner ? `🏆 Winner: <strong>${sanitizeInput(game.winner)}</strong>` : ''}
                                    ${game.rounds ? ` | ${sanitizeInput(game.rounds)} rounds` : ''}
                                </div>
                            </li>
                        `;
                    }).join('')}
                </ul>
            `;

            // Add click event listeners to navigate to game details page
            document.querySelectorAll('.game-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.classList.contains('delete-x-btn')) return;
                    const gameId = item.getAttribute('data-game-id');
                    window.location.href = `game-details.html?id=${gameId}`;
                });
            });
        })
        .catch(error => {
            console.error(error);
            const gameHistoryDiv = document.getElementById('game-history');
            gameHistoryDiv.innerHTML = `<h2>Game History</h2><p>Failed to load game history.</p>`;
        });
}

// Delete a game with confirmation
function deleteGame(gameId, event) {
    event.stopPropagation();
    
    if (!confirm('Are you sure you want to delete this game? This cannot be undone.')) {
        return;
    }
    
    fetch(`${STATS_API_URL}?endpoint=deleteGame`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `gameId=${gameId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the item from DOM with animation
            const item = document.querySelector(`.game-item[data-game-id="${gameId}"]`);
            if (item) {
                item.style.transition = 'opacity 0.3s, height 0.3s, padding 0.3s, margin 0.3s';
                item.style.opacity = '0';
                item.style.height = '0';
                item.style.padding = '0';
                item.style.margin = '0';
                item.style.overflow = 'hidden';
                setTimeout(() => {
                    item.remove();
                    // Refresh stats
                    fetchOverallStatistics();
                }, 300);
            }
        } else {
            alert('Failed to delete game: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Failed to delete game');
    });
}

// Seed sample data for demo
function seedSampleData() {
    const btn = document.getElementById('seed-data-btn');
    const msg = document.getElementById('seed-message');
    
    btn.disabled = true;
    btn.innerText = 'Seeding...';
    msg.innerText = '';
    
    fetch(`${STATS_API_URL}?endpoint=seedSampleData`, {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                msg.style.color = '#22c55e';
                msg.innerText = `✅ ${data.message} (${data.gamesCreated} games created)`;
                
                // Refresh the statistics
                fetchOverallStatistics();
                fetchGameHistory();
            } else {
                msg.style.color = '#ef4444';
                msg.innerText = `❌ Error: ${data.error}`;
            }
        })
        .catch(error => {
            console.error(error);
            msg.style.color = '#ef4444';
            msg.innerText = '❌ Failed to seed sample data.';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'Seed Sample Data';
        });
}

// Initialize the statistics page
function initializeStatisticsPage() {
    fetchOverallStatistics();
    fetchGameHistory();
}

// Run the initialization function when the page loads
window.onload = initializeStatisticsPage;
