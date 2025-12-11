/**
 * @jest-environment jsdom
 */

import { fetchOverallStatistics, fetchGameHistory } from '../statistics.js';

describe('Game Creation and Joining', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="game-message"></div>
            <form id="game-form">
                <input type="text" id="player-name" placeholder="Enter your name">
                <input type="text" id="game-code" placeholder="Enter game code">
                <button id="create-game">Create Game</button>
                <button id="join-game">Join Game</button>
            </form>
        `;
    });

    test('Displays error if player name is missing', () => {
        const createGameButton = document.getElementById('create-game');
        createGameButton.click();

        const gameMessage = document.getElementById('game-message');
        expect(gameMessage.innerText).toBe('Please enter your name.');
    });

    test('Sends fetch request on game creation', async () => {
        global.fetch.mockResolvedValueOnce({
            json: async () => ({ gameCode: 'ABC123' }),
        });

        const playerNameInput = document.getElementById('player-name');
        playerNameInput.value = 'Test Player';

        const createGameButton = document.getElementById('create-game');
        createGameButton.click();

        expect(global.fetch).toHaveBeenCalledWith('game.php?endpoint=createGame', {
            method: 'POST',
        });

        const gameMessage = document.getElementById('game-message');
        expect(gameMessage.innerText).toBe('Game created! Your game code is: ABC123');
    });
});