import { fetchOverallStatistics, fetchGameHistory, fetchGameDetails } from '../statistics.js';

describe('Statistics Page', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="overall-stats"></div>
            <div id="game-history"></div>
            <div id="game-details"></div>
            <div id="game-message"></div>
        `;
    });

    test('Fetches and displays overall statistics', async () => {
        global.fetch.mockResolvedValueOnce({
            json: async () => ({
                totalGames: 42,
                topPlayer: 'Alice',
            }),
        });

        await fetchOverallStatistics();

        const overallStatsDiv = document.getElementById('overall-stats');
        expect(overallStatsDiv.innerHTML).toContain('Total Games Played: 42');
        expect(overallStatsDiv.innerHTML).toContain('Player with Most Wins: Alice');
    });

    test('Fetches and displays game history', async () => {
        global.fetch.mockResolvedValueOnce({
            json: async () => ({
                games: [
                    { game_id: 1, winner: 'Alice', date: '2025-04-11T14:00:00Z' },
                    { game_id: 2, winner: 'Bob', date: '2025-04-10T16:30:00Z' },
                ],
            }),
        });

        await fetchGameHistory();

        const gameHistoryDiv = document.getElementById('game-history');
        expect(gameHistoryDiv.innerHTML).toContain('Game ID: 1');
        expect(gameHistoryDiv.innerHTML).toContain('Winner: Alice');
        expect(gameHistoryDiv.innerHTML).toContain('Game ID: 2');
        expect(gameHistoryDiv.innerHTML).toContain('Winner: Bob');
    });

    test('Fetches and displays game details', async () => {
        global.fetch.mockResolvedValueOnce({
            json: async () => ({
                rounds: [
                    { roundNumber: 1, winner: 'Alice', points: 30 },
                    { roundNumber: 2, winner: 'Bob', points: 20 },
                ],
            }),
        });

        await fetchGameDetails(1);

        const gameDetailsDiv = document.getElementById('game-details');
        expect(gameDetailsDiv.innerHTML).toContain('Round 1: Winner: Alice, Points: 30');
        expect(gameDetailsDiv.innerHTML).toContain('Round 2: Winner: Bob, Points: 20');
    });
});