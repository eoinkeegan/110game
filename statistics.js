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
                                    <span>
                                        <strong>Game #${sanitizeInput(game.game_id)}</strong>
                                        (${sanitizeInput(game.code)})
                                    </span>
                                </div>
                                <div style="margin-top: 5px; font-size: 14px; color: #ccc;">
                                    ${game.winner ? `🏆 Winner: <strong>${sanitizeInput(game.winner)}</strong>` : ''}
                                    ${game.rounds ? ` | ${sanitizeInput(game.rounds)} rounds played` : ''}
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

// Fetch and display game details
function fetchGameDetails(gameId) {
    fetch(`${STATS_API_URL}?endpoint=getGameDetails&gameId=${sanitizeInput(gameId)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error fetching game details for Game ID ${gameId}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            const gameDetailsDiv = document.getElementById('game-details');
            
            if (data.error) {
                gameDetailsDiv.innerHTML = `<h2>Game Details</h2><p>Error: ${sanitizeInput(data.error)}</p>`;
                return;
            }
            
            if (!data.rounds || data.rounds.length === 0) {
                gameDetailsDiv.innerHTML = `
                    <h2>Game Details</h2>
                    <h3>Game #${sanitizeInput(gameId)}</h3>
                    <p>No round details available for this game. (Game may still be in progress)</p>
                `;
                return;
            }
            
            // Build winner display
            let winnerHtml = '';
            if (data.gameWinner) {
                winnerHtml = `<p style="color: #22c55e; font-size: 1.1em;">🏆 Winner: <strong>${sanitizeInput(data.gameWinner)}</strong></p>`;
            }
            
            // Build final scores
            let scoresHtml = '';
            if (data.finalScores && Object.keys(data.finalScores).length > 0) {
                const sortedScores = Object.entries(data.finalScores)
                    .sort((a, b) => b[1] - a[1])
                    .map(([pid, score]) => {
                        const name = data.playerNames[pid] || `Player ${pid}`;
                        return `<span style="margin-right: 15px;">${sanitizeInput(name)}: <strong>${score}</strong></span>`;
                    }).join('');
                scoresHtml = `<p style="margin-bottom: 15px;">Final Scores: ${sortedScores}</p>`;
            }

            gameDetailsDiv.innerHTML = `
                <h2>Game Details - Game #${sanitizeInput(gameId)}</h2>
                ${winnerHtml}
                ${scoresHtml}
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                    <thead>
                        <tr style="border-bottom: 2px solid #d4af37; text-align: left;">
                            <th style="padding: 8px;">Round</th>
                            <th style="padding: 8px;">Bid Winner</th>
                            <th style="padding: 8px;">Bid</th>
                            <th style="padding: 8px;">Trump</th>
                            <th style="padding: 8px;">Result</th>
                            <th style="padding: 8px;">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.rounds.map(round => {
                            const forcedTag = round.forcedBid ? ' <span style="color: #eab308; font-size: 0.8em;">(forced)</span>' : '';
                            const resultText = round.bidMade === true ? '✅ Made' : (round.bidMade === false ? '❌ Failed' : '-');
                            const resultColor = round.bidMade === true ? '#22c55e' : (round.bidMade === false ? '#ef4444' : '#888');
                            const trumpColor = round.trumpSuit === '♥' || round.trumpSuit === '♦' ? '#ef4444' : '#fff';
                            const points = round.bidWinnerPoints !== undefined ? (round.bidWinnerPoints >= 0 ? '+' + round.bidWinnerPoints : round.bidWinnerPoints) : '-';
                            const pointsColor = round.bidWinnerPoints >= 0 ? '#22c55e' : '#ef4444';
                            
                            return `
                                <tr style="border-bottom: 1px solid #444;">
                                    <td style="padding: 8px;">${sanitizeInput(round.roundNumber)}</td>
                                    <td style="padding: 8px;">${sanitizeInput(round.bidWinner)}${forcedTag}</td>
                                    <td style="padding: 8px;">${sanitizeInput(round.bid) || '-'}</td>
                                    <td style="padding: 8px; color: ${trumpColor}; font-size: 1.2em;">${round.trumpSuit || '-'}</td>
                                    <td style="padding: 8px; color: ${resultColor};">${resultText}</td>
                                    <td style="padding: 8px; color: ${pointsColor};">${points}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
        })
        .catch(error => {
            console.error(error);
            const gameDetailsDiv = document.getElementById('game-details');
            gameDetailsDiv.innerHTML = `<h2>Game Details</h2><p>Failed to load details for Game #${sanitizeInput(gameId)}.</p>`;
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
