~~need to update the logic around following suit. if you have either a 5 of Trumps, a Jack of Trumps, a Joker or an Ace of Hearts, you do not have to play it, as long as a card of higher value has not been played before yours.~~ ✅ FIXED


Replace https://your-backend-domain.com with the actual domain of your backend.
Ensure your backend is configured to support HTTPS.

## manual testing

Here’s a step-by-step guide to manually test the functionality you’ve built so far for the **110 Card Game**. These steps cover both backend and frontend features.

---

## **1. Game Creation and Joining**

### **Step 1.1: Create a Game**
1. Open your browser and navigate to the game interface (`index.html`).
2. Enter a player name in the "Enter your name" field.
3. Click the "Create Game" button.
4. **Expected Result:**
   - A unique game code is displayed.
   - A success message appears (e.g., "Game created! Your game code is: ABC123").
   - The game is stored in the database (`games` table).

### **Step 1.2: Join a Game**
1. Open the game interface in a new browser tab or window.
2. Enter a player name in the "Enter your name" field.
3. Enter the game code from Step 1.1 in the "Enter game code" field.
4. Click the "Join Game" button.
5. **Expected Result:**
   - The player is added to the game.
   - A success message appears (e.g., "You have joined the game!").
   - The player is stored in the database (`players` table).

### **Step 1.3: Attempt to Join with an Invalid Game Code**
1. Enter a player name and an invalid game code (e.g., "INVALID").
2. Click the "Join Game" button.
3. **Expected Result:**
   - An error message appears (e.g., "Invalid game code. Please try again.").

---

## **2. Dealing and Initial Game State**

### **Step 2.1: Deal Cards**
1. After all players have joined, trigger the "Deal Cards" action (this might be a button or an automatic backend process).
2. **Expected Result:**
   - Each player receives 5 cards.
   - The Kitty contains 5 cards.
   - The game state is updated in the database (`game_state` table).

### **Step 2.2: Verify Initial Game State**
1. Check the database (`game_state` table) to ensure the initial game state is stored correctly.
2. **Expected Result:**
   - The `state` column contains JSON data with the dealt cards, Kitty, and player information.

---

## **3. Bidding Logic**

### **Step 3.1: Start Bidding**
1. Trigger the "Start Bidding" action (this might be a button or an automatic backend process).
2. **Expected Result:**
   - The bidding phase begins.
   - The first bidder is displayed (e.g., "Player 1, it's your turn to bid.").

### **Step 3.2: Place a Valid Bid**
1. As the current bidder, enter a valid bid amount and submit it.
2. **Expected Result:**
   - The bid is recorded.
   - The next bidder is displayed.

### **Step 3.3: Attempt an Invalid Bid**
1. Enter an invalid bid amount (e.g., lower than the current bid).
2. **Expected Result:**
   - An error message appears (e.g., "Invalid bid. Please enter a higher amount.").

---

## **4. Kitty and Trump Selection**

### **Step 4.1: Select the Kitty and Trump Suit**
1. After the bidding phase ends, the highest bidder selects the Kitty and a trump suit.
2. **Expected Result:**
   - The Kitty cards are added to the highest bidder's hand.
   - The trump suit is displayed (e.g., "Trump suit: Hearts").
   - The game state is updated in the database.

### **Step 4.2: Attempt to Select as a Non-Highest Bidder**
1. Try to select the Kitty or trump suit as a non-highest bidder.
2. **Expected Result:**
   - An error message appears (e.g., "You are not authorized to select the Kitty or trump suit.").

---

## **5. Trick Gameplay Logic**

### **Step 5.1: Play a Valid Card**
1. As the current player, select a valid card from your hand and play it.
2. **Expected Result:**
   - The card is removed from your hand.
   - The card is displayed in the trick area.
   - The game state is updated in the database.

### **Step 5.2: Attempt to Play an Invalid Card**
1. Try to play a card that is not in your hand or does not follow suit (if applicable).
2. **Expected Result:**
   - An error message appears (e.g., "Invalid card. Please follow suit.").

### **Step 5.3: Complete a Trick**
1. Continue playing cards until the trick is complete.
2. **Expected Result:**
   - The winner of the trick is displayed (e.g., "Player 2 wins the trick.").
   - The game state is updated in the database.

---

## **6. Scoring and Round Completion**

### **Step 6.1: Score a Trick**
1. After a trick is completed, check the scores.
2. **Expected Result:**
   - The winner's score is incremented.
   - The game state is updated in the database.

### **Step 6.2: Complete a Round**
1. Continue playing tricks until the round is complete.
2. **Expected Result:**
   - Scores are updated based on bid success/failure and bonus points.
   - The game state is updated in the database.

---

## **7. Game Completion and Winner Determination**

### **Step 7.1: Reach 110 Points**
1. Continue playing rounds until a player reaches 110 points.
2. **Expected Result:**
   - The game ends.
   - The winner is displayed (e.g., "Player 3 wins the game!").
   - The game state is updated in the database.

### **Step 7.2: Attempt to Continue After Game Ends**
1. Try to play a card or start a new round after the game ends.
2. **Expected Result:**
   - An error message appears (e.g., "The game is over.").

---

## **8. Statistics Page**

### **Step 8.1: View Overall Statistics**
1. Navigate to the statistics page (`statistics.html`).
2. **Expected Result:**
   - Total games played and the player with the most wins are displayed.

### **Step 8.2: View Game History**
1. Check the game history section.
2. **Expected Result:**
   - A list of games is displayed, including game ID, winner, and date.

### **Step 8.3: View Game Details**
1. Click on a game in the history list.
2. **Expected Result:**
   - Round-by-round details for the selected game are displayed.

---

## **9. WebSocket Real-Time Updates**

### **Step 9.1: Verify Real-Time Updates**
1. Open the game interface in multiple browser tabs or windows.
2. Perform an action (e.g., play a card) in one tab.
3. **Expected Result:**
   - The action is reflected in real-time in all other tabs.

---

## **10. Error Handling**

### **Step 10.1: Test Invalid Inputs**
1. Enter invalid inputs (e.g., empty fields, invalid game codes).
2. **Expected Result:**
   - Appropriate error messages are displayed.

### **Step 10.2: Test Network Errors**
1. Disconnect from the internet and try to perform an action.
2. **Expected Result:**
   - An error message appears (e.g., "Network error. Please try again later.").

---

### Notes:
- Use browser developer tools (e.g., Chrome DevTools) to monitor network requests and responses.
- Check the database (`games`, `players`, `game_state` tables) to verify that data is being stored and updated correctly.
- Test on multiple devices and browsers to ensure compatibility.

Let me know if you need further clarification or additional test scenarios!

## automated testing


Test Cases for Backend (PHPUnit)
1. Game Creation and Joining
Test Case 1.1: Create a game and verify the game code is unique.
Input: Call /createGame endpoint.
Expected Output: A unique 6-character alphanumeric game code is returned.
Test Case 1.2: Join a game with a valid game code and player name.
Input: Call /joinGame with valid gameCode and playerName.
Expected Output: Player is added to the game, and a success message is returned.
Test Case 1.3: Attempt to join a game with an invalid game code.
Input: Call /joinGame with an invalid gameCode.
Expected Output: Error message indicating the game code is invalid.
2. Dealing and Initial Game State
Test Case 2.1: Deal cards to players and verify the correct number of cards.
Input: Call /dealCards for a game with 4 players.
Expected Output: Each player receives 5 cards, and the Kitty contains 5 cards.
Test Case 2.2: Verify the initial game state is stored in the database.
Input: Call /dealCards.
Expected Output: The game_state table is updated with the initial game state.
3. Bidding Logic
Test Case 3.1: Start bidding and verify the initial bidding state.
Input: Call /startBidding.
Expected Output: The game_state table is updated with the initial bid and bidder.
Test Case 3.2: Process a valid bid and verify the state is updated.
Input: Call /processBid with a valid bid amount.
Expected Output: The bid is recorded, and the next bidder is updated.
Test Case 3.3: Attempt an invalid bid (e.g., lower than the current bid).
Input: Call /processBid with an invalid bid amount.
Expected Output: Error message indicating the bid is invalid.
4. Kitty and Trump Selection
Test Case 4.1: Verify the highest bidder can select the Kitty and trump suit.
Input: Call /selectKittyAndTrump with valid selectedCards and trumpSuit.
Expected Output: The Kitty is added to the player's hand, and the trump suit is updated in the game state.
Test Case 4.2: Attempt to select the Kitty and trump suit as a non-highest bidder.
Input: Call /selectKittyAndTrump as a non-highest bidder.
Expected Output: Error message indicating the player is not authorized.
5. Trick Gameplay Logic
Test Case 5.1: Play a valid card and verify the game state is updated.
Input: Call /playCard with a valid card.
Expected Output: The card is removed from the player's hand and added to the current trick.
Test Case 5.2: Attempt to play an invalid card (e.g., not in hand or not following suit).
Input: Call /playCard with an invalid card.
Expected Output: Error message indicating the card is invalid.
Test Case 5.3: Verify the trick winner is determined correctly.
Input: Complete a trick and call determineTrickWinner.
Expected Output: The correct winner is identified based on the card ranking rules.
6. Scoring and Round Completion
Test Case 6.1: Score a trick and verify the player's score is updated.
Input: Call scoreTrick with a valid trickWinnerId.
Expected Output: The player's score is incremented in the game state.
Test Case 6.2: Complete a round and verify the scores are calculated correctly.
Input: Call scoreRound.
Expected Output: Scores are updated based on bid success/failure and bonus points.
7. Game Completion and Winner Determination
Test Case 7.1: Verify the game ends when a player reaches 110 points.
Input: Call determineGameWinner after a round.
Expected Output: The game state is updated with the winner.
Test Case 7.2: Attempt to continue the game after a winner is determined.
Input: Call /playCard after the game is completed.
Expected Output: Error message indicating the game is over.
8. Statistics Page
Test Case 8.1: Retrieve overall statistics.
Input: Call /getOverallStatistics.
Expected Output: JSON response with total games played and the player with the most wins.
Test Case 8.2: Retrieve game history.
Input: Call /getGameHistory.
Expected Output: JSON response with a list of games, including game ID, winner, and date.
Test Case 8.3: Retrieve game details.
Input: Call /getGameDetails with a valid gameId.
Expected Output: JSON response with round-by-round details.
Test Cases for Frontend (Jest)
1. Game Creation and Joining
Test Case 1.1: Verify the game creation form sends a valid request.
Input: Simulate form submission with valid inputs.
Expected Output: The fetch() call is made to /createGame, and the game code is displayed.
Test Case 1.2: Verify the game joining form sends a valid request.
Input: Simulate form submission with valid inputs.
Expected Output: The fetch() call is made to /joinGame, and a success message is displayed.
2. Dealing and Initial Game State
Test Case 2.1: Verify the player's hand is displayed correctly.
Input: Mock the response from /getGameState.
Expected Output: The player-hands div is updated with card images.
3. Bidding Logic
Test Case 3.1: Verify the bidding UI displays valid bid options.
Input: Mock the response from /getGameState with bidding data.
Expected Output: The bidding-area div displays valid bid buttons.
Test Case 3.2: Verify the bid buttons send valid requests.
Input: Simulate a button click for a valid bid.
Expected Output: The fetch() call is made to /processBid.
4. Kitty and Trump Selection
Test Case 4.1: Verify the Kitty and trump selection UI is displayed.
Input: Mock the response from /getGameState after bidding is complete.
Expected Output: The Kitty cards and trump suit options are displayed.
5. Trick Gameplay Logic
Test Case 5.1: Verify the trick area displays played cards.
Input: Mock the response from /getGameState with trick data.
Expected Output: The trick-area div is updated with played cards.
Test Case 5.2: Verify the player's hand cards send valid play requests.
Input: Simulate a card click.
Expected Output: The fetch() call is made to /playCard.
6. Statistics Page
Test Case 6.1: Verify the overall statistics are displayed.
Input: Mock the response from /getOverallStatistics.
Expected Output: The overall-stats div is updated with statistics.
Test Case 6.2: Verify the game history is displayed.
Input: Mock the response from /getGameHistory.
Expected Output: The game-history div is updated with a list of games.
Test Case 6.3: Verify game details are displayed when a game is clicked.
Input: Simulate a click on a game in the game-history list.
Expected Output: The game-details div is updated with round-by-round details.
Tools and Frameworks:
Backend Testing: PHPUnit
Frontend Testing: Jest
Let me know if you need further clarification or additional test cases!