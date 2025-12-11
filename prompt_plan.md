# Core Purpose:

A web application for your extended family (4-10 players) to play the Irish card game 110 online.

## Essential Features:

### Unique Game Code/Link & Automatic Player Count:

One player creates a game, and the application generates a unique code or link.
The game automatically accommodates players who join via the link, up to a maximum of 10.
The target score is fixed at 110, and rule variations are out of scope for this initial version.

### Accurate Game Rule Replication:

Implementation of all 110 game rules (dealing, bidding, Kitty, trick gameplay, scoring, winning).
UI/UX for card interaction, bidding, and game flow.

### Statistics Tracking (Nice-to-Have):
Game winners, round-by-round breakdowns, and total games played.
Dedicated "Statistics" page with overall statistics and game history.

## Technology Stack:

Frontend: HTML, CSS, JavaScript
Backend: PHP
Database: MySQL
Web Server: Apache
Hosting: AWS (Route 53, S3, EC2)
Websockets for real time communication.

### Key Considerations:

WebSockets for real-time communication.
Security (input validation, HTTPS).
Scalability.
Error handling and logging.
Database design.

Project Blueprint: 110 Card Game Web Application

Phase 1: Foundation and Basic Setup

Environment Setup:
Set up AWS environment (Route 53, S3, EC2).
Install Apache, PHP, and MySQL on EC2.
Configure DNS with Route 53.
Basic HTML Structure:
Create the core HTML structure for the game interface.
Include basic CSS styling for layout.
Backend Setup (PHP):
Create a basic PHP file to handle game logic.
Set up MySQL database and create tables for games and players.
Phase 2: Core Gameplay Logic

Game Creation and Joining:
Implement PHP logic for generating unique game codes/links.
Implement logic for players to join games using the code/link.
Store game and player data in the database.
Dealing and Initial Game State:
Implement PHP functions for dealing cards and creating the "Kitty."
Store initial game state in the database.
Bidding Logic:
Implement PHP logic for handling bidding, including turn management and bid validation.
Update game state in the database.
Kitty and Trump Selection:
Implement PHP logic for the highest bidder to take the Kitty and declare trump.
Implement logic for discarding and drawing cards.
Update game state in the database.
Trick Gameplay Logic:
Implement PHP logic for handling card plays, following suit, and determining trick winners.
Implement card ranking logic.
Update game state in the database.
Scoring and Round Completion:
Implement PHP logic for scoring tricks and rounds, including bonus points.
Update player scores and game state in the database.
Game Completion and Winner Determination:
Implement PHP logic for determining the game winner based on reaching 110 points.
Update game state in the database.
Phase 3: User Interface and Real-time Updates

Card Display and Interaction (JavaScript):
Implement JavaScript functions for displaying player hands and played cards.
Implement card selection and play functionality.
Bidding UI (JavaScript):
Implement JavaScript functions for displaying bidding options and handling user input.
Trump Suit and Kitty Display (JavaScript):
Implement JavaScript functions for displaying the trump suit and Kitty.
WebSockets Integration:
Implement WebSockets for real-time updates between players.
Update the UI in real-time based on game state changes.
Statistics Page:
Create the HTML structure and JavaScript logic for the statistics page.
Implement PHP logic for retrieving and displaying game statistics.

Phase 4: Refinement and Deployment

Error Handling and Logging:
Implement robust error handling and logging in both PHP and JavaScript.
Security Enhancements:
Implement input validation and sanitization.
Configure HTTPS.
Testing and Debugging:
Thoroughly test the application and address any bugs.
Deployment and Optimization:
Deploy the application to AWS.
Optimize performance and scalability.

Iterative Chunks and Steps

Chunk 1: Basic Environment and HTML Setup

Step 1.1: Set up EC2 instance, install Apache, PHP, and MySQL.
Step 1.1.1: Launch EC2 instance with appropriate OS.
Step 1.1.2: Connect to EC2 instance via SSH.
Step 1.1.3: Install Apache, PHP, and MySQL.
Step 1.1.4: Configure Apache and PHP.
Step 1.2: Create basic HTML structure for the game.
Step 1.2.1: Create index.html with basic HTML tags (head, body).
Step 1.2.2: Add placeholders for game elements (player hands, trick area, etc.).
Step 1.2.3: Create basic css file to style index.html.
Step 1.3: Configure AWS Route53.
Step 1.3.1: Create a hosted zone in Route53.
Step 1.3.2: Configure an A record pointing to the EC2 instance's public IP.
Step 1.3.3: Configure an S3 bucket for static content.
Chunk 2: Game Creation and Joining Logic

Step 2.1: Create PHP file for game logic (game.php).
Step 2.1.1: Create game.php file.
Step 2.1.2: Implement basic connection to MySQL database.
Step 2.2: Implement game creation logic.
Step 2.2.1: Create a function to generate a unique game code.
Step 2.2.2: Create a database table for games (game_id, code, state).
Step 2.2.3: Implement PHP logic to create a new game record.
Step 2.3: Implement game joining logic.
Step 2.3.1: Create a database table for players (player_id, game_id, name).
Step 2.3.2: Implement PHP logic to add players to a game.
Step 2.3.3: Create a form in index.html for players to enter their name and game code.
Step 2.4: Connect the frontend to the backend.
Step 2.4.1: Use JavaScript to send game creation/joining requests to game.php.
Step 2.4.2: Update index.html with dynamic content based on PHP responses.
Chunk 3: Dealing and Initial Game State

Step 3.1: Implement PHP functions for dealing cards.
Step 3.1.1: Create a function to generate a deck of cards.
Step 3.1.2: Create a function to deal 5 cards to each player and the Kitty.
Step 3.1.3: Create a database table to hold the cards of each player and the kitty.
Step 3.2: Store initial game state in the database.
Step 3.2.1: Update the game state in the database with dealt cards.
Step 3.2.2: Create a game_state table to hold the current state of the game.
Step 3.3: Display initial game state in the frontend.
Step 3.3.1: Use JavaScript to fetch player hands from the backend.
Step 3.3.2: Display player hands in index.html using card images.


Prompt 1: Setup EC2 instance and install Apache, PHP, and MySQL

Plaintext

Create a shell script that performs the following steps on an Ubuntu 20.04 EC2 instance:
1.  Update the package list.
2.  Install Apache2, PHP 7.4, and MySQL server.
3.  Secure the MySQL installation.
4.  Configure Apache to enable PHP processing.
5.  Create a basic php file called info.php that calls phpinfo();

Prompt 2: Create basic HTML structure for the game

Plaintext

Create an HTML file named `index.html` with the following structure:
1.  A `<head>` section with a title "110 Card Game."
2.  A `<body>` section with placeholders for:
    * Player hands (a `div` with id "player-hands").
    * The trick area (a `div` with id "trick-area").
    * The trump suit display (a `div` with id "trump-suit").
    * The bidding area (a `div` with id "bidding-area").
3. Create a seperate css file named style.css that styles the divs created in index.html.

Prompt 3: Configure AWS Route 53 and create an S3 bucket.

Plaintext

Using the AWS CLI, provide the commands to:
1. Create a hosted zone in Route 53 for the domain "example.com".
2. Create an A record pointing to the EC2 instance's public IP address.
3. Create an S3 bucket named "110-game-static-assets".

Prompt 4: Create PHP file for game logic (game.php) and connect to MySQL

Plaintext

Create a PHP file named `game.php` that:
1. Establishes a connection to a MySQL database with the following credentials:
    * Host: localhost
    * Username: your_username
    * Password: your_password
    * Database: 110_game
2. Handles potential connection errors and returns an appropriate error message.
3. Creates a basic endpoint that returns a "Hello World" JSON response.

Prompt 5: Implement game creation logic

Plaintext

Modify the `game.php` file to:
1. Create a function `generateGameCode()` that generates a unique 6-character alphanumeric game code.
2. Create a MySQL database table named `games` with the following columns:
    * `game_id` (INT, AUTO_INCREMENT, PRIMARY KEY)
    * `code` (VARCHAR(6), UNIQUE)
    * `state` (TEXT) - JSON format to hold the game state.
3. Create an endpoint `/createGame` that:
    * Calls `generateGameCode()`.
    * Inserts a new game record into the `games` table.
    * Returns the generated game code as a JSON response.

test with curl -X POST "http://localhost:8888/110game/game.php?endpoint=createGame"

Prompt 6: Implement game joining logic

Plaintext

Modify the `game.php` file to:
1. Create a MySQL database table named `players` with the following columns:
    * `player_id` (INT, AUTO_INCREMENT, PRIMARY KEY)
    * `game_id` (INT, FOREIGN KEY referencing `games.game_id`)
    * `name` (VARCHAR(255))
2. Create an endpoint `/joinGame` that:
    * Receives a game code and player name as POST parameters.
    * Checks if the game code exists in the `games` table.
    * Inserts a new player record into the `players` table.
    * Returns a success message or an error message as a JSON response.

sample game ID to test: SLFX8E
test with curl -X POST -d "gameCode=YOUR_GAME_CODE&playerName=YOUR_PLAYER_NAME" "http://localhost:8888/110game/game.php?endpoint=joinGame"

curl -X POST -d "gameCode=SLFX8E&playerName=test_player1" "http://localhost:8888/110game/game.php?endpoint=joinGame"

Prompt 7: Create a form in index.html for players to enter their name and game code

Plaintext

Modify `index.html` to:
1. Add a form with two input fields:
    * One for the player's name (text input).
    * One for the game code (text input).
2. Add two buttons:
    * One for creating a new game.
    * One for joining an existing game.
3. Add a div with an id of "game-message" to display server response messages.
4. Add javascript functions to handle the form submission and send requests to the game.php backend.

Prompt 8: Connect the frontend to the backend

Plaintext

Modify `index.html` and the associated JavaScript to:
1. Use JavaScript's `fetch()` API to send POST requests to `/createGame` and `/joinGame` endpoints in `game.php`.
2. Display the server's JSON response in the `game-message` div.
3. On successful game creation, display the generated game code.
4. On successful game joining, hide the form and display a "Waiting for other players..." message.

Prompt 9: Implement PHP functions for dealing cards

Plaintext

Modify `game.php` to:
1. Create a function `generateDeck()` that returns an array representing a standard 52-card deck plus a joker.
2. Create a function `dealCards($gameId)` that:
    * Fetches the list of players for the given game ID.
    * Generates a deck of cards.
    * Deals 5 cards to each player and the Kitty.
    * Stores the cards in a new MySQL table `cards` with columns:
        * `card_id` (INT, AUTO_INCREMENT, PRIMARY KEY)
        * `game_id` (INT, FOREIGN KEY referencing `games.game_id`)
        * `player_id` (INT, FOREIGN KEY referencing `players.player_id`, NULL for Kitty)
        * `card` (VARCHAR(3)) - e.g., "H10" for 10 of Hearts.
    * Updates the `games` table's `state` column with the initial game state (JSON).
3. Create an endpoint `/dealCards` that calls `dealCards($gameId)`.

Prompt 10: Store initial game state in the database

Plaintext

Modify `game.php` to:
1. Create a MySQL table `game_state` with the following columns:
    * `game_id` (INT, PRIMARY KEY, FOREIGN KEY referencing `games.game_id`)
    * `state` (TEXT) - JSON representing the game state.
2. Update the `dealCards()` function to:
    * Store the initial game state in the `game_state` table, including player hands, Kitty, and current player turn.
    * Store the cards in the cards table.

Prompt 11: Display initial game state in the frontend

Plaintext

Modify `index.html` and the associated JavaScript to:
1. Create a function to fetch the player's hand from the `/getGameState` endpoint in `game.php` (create this endpoint).
2. Display the player's hand as card images in the `player-hands` div.
3. Display the cards using an image naming convention. For example "H10.png" should be the 10 of hearts.
4. Add card images to the S3 bucket and update the index.html to refer to the S3 bucket.

Alright, let's continue with the next set of prompts.

Prompt 12: Implement bidding logic (PHP)

Plaintext

Modify `game.php` to:
1. Create a function `startBidding($gameId)` that:
    * Sets the initial bidding state in the `game_state` table (current bid, current bidder, valid bid options).
    * Determines the first bidder (player to the left of the dealer).
2. Create an endpoint `/startBidding` that calls `startBidding($gameId)`.
3. Create a function `processBid($gameId, $playerId, $bidAmount)` that:
    * Validates the bid amount against the current bidding rules.
    * Updates the `game_state` table with the new bid and current bidder.
    * Determines the next bidder or ends the bidding if all players have passed or the maximum bid is reached.
4. Create an endpoint `/processBid` that calls `processBid($gameId, $playerId, $bidAmount)`.

Prompt 13: Bidding UI (JavaScript)

Plaintext

Modify `index.html` and the associated JavaScript to:
1. Create a function to fetch the current bidding state from the `/getGameState` endpoint.
2. Display the current bid, current bidder, and valid bid options in the `bidding-area` div.
3. Create buttons for each valid bid option and a "Pass" button.
4. Add event listeners to the buttons to send bid requests to the `/processBid` endpoint.
5. Disable bid buttons that are not valid for the current player.

Prompt 14: Kitty and Trump Selection (PHP)

Plaintext

Modify `game.php` to:
1. Create a function `selectKittyAndTrump($gameId, $playerId, $selectedCards, $trumpSuit)` that:
    * Verifies that the player is the highest bidder.
    * Removes the Kitty cards from the `cards` table.
    * Updates the player's hand in the `cards` table with the selected cards.
    * Updates the `game_state` table with the selected trump suit.
2. Create an endpoint `/selectKittyAndTrump` that calls `selectKittyAndTrump($gameId, $playerId, $selectedCards, $trumpSuit)`.
3. Modify the getGameState endpoint to return the kitty cards to the highest bidder.

Prompt 15: Kitty Display and Selection (JavaScript)

Plaintext

Modify `index.html` and the associated JavaScript to:
1. When the bidding is complete and the player is the highest bidder, display the Kitty cards in the `player-hands` div.
2. Allow the player to select 5 cards from their hand and the Kitty.
3. Create a dropdown or set of buttons for the player to select the trump suit.
4. Create a "Confirm" button to send the selected cards and trump suit to the `/selectKittyAndTrump` endpoint.

Prompt 16: Trick Gameplay Logic (PHP)

Plaintext

Modify `game.php` to:
1. Create a function `playCard($gameId, $playerId, $card)` that:
    * Validates that the player is allowed to play the card (following suit, etc.).
    * Removes the card from the player's hand in the `cards` table.
    * Adds the card to the trick in the `game_state` table.
    * Determines the next player to play.
2. Create an endpoint `/playCard` that calls `playCard($gameId, $playerId, $card)`.
3. Create a function `determineTrickWinner($gameId)` that:
    * Determines the winner of the trick based on the card ranking rules.
    * Updates the `game_state` table with the trick winner.
4. Create a function `startNewTrick($gameId)` that resets the trick data in the game state.

Prompt 17: Card Display and Interaction (JavaScript)

Plaintext

Modify `index.html` and the associated JavaScript to:
1. Display the cards played in the current trick in the `trick-area` div.
2. Highlight the player whose turn it is to play.
3. Add event listeners to the player's hand cards to send play requests to the `/playCard` endpoint.
4. Visually represent the cards in the trick area.
5. Visually represent the trump suit.

Prompt 18: Scoring and Round Completion (PHP)

Plaintext

Modify `game.php` to:
1. Create a function `scoreTrick($gameId, $trickWinnerId)` that:
    * Updates the player's score in the `game_state` table based on the trick winner.
2. Create a function `scoreRound($gameId)` that:
    * Calculates the final scores for the round, including bonus points and bid success/failure.
    * Updates the player scores in the `game_state` table.
3. Modify `determineTrickWinner()` to call `scoreTrick()` after determining the winner.
4. Modify the playcard endpoint to call `scoreRound()` when all 5 tricks are complete.

Prompt 19: Game Completion and Winner Determination (PHP)

Plaintext

Modify `game.php` to:
1. Create a function `determineGameWinner($gameId)` that:
    * Checks if any player has reached 110 points.
    * If so, determines the winner and updates the `game_state` table.
2. Modify `scoreRound()` to call `determineGameWinner()` after scoring each round.
3. Modify the getGameState endpoint to return the game winner.

Prompt 20: WebSockets Integration (JavaScript and PHP)

Plaintext

1. Implement a WebSocket server in PHP to handle real-time communication.
2. Modify `index.html` and the associated JavaScript to:
    * Connect to the WebSocket server.
    * Send and receive game state updates via WebSockets.
    * Update the UI in real-time based on game state changes.
3. Modify `game.php` to:
    * Send game state updates to the WebSocket server after each game action (card play, bid, etc.).

Alright, let's continue with the remaining prompts, focusing on the statistics page, error handling, security, and deployment.

Prompt 21: Statistics Page (HTML and JavaScript)

Plaintext

Create a new HTML file named `statistics.html` with the following structure:
1.  A `<head>` section with a title "110 Game Statistics."
2.  A `<body>` section with:
    * A `div` with id "overall-stats" to display overall statistics.
    * A `div` with id "game-history" to display a list of games.
    * A `div` with id "game-details" to display round-by-round details.
3. Create a javascript file called statistics.js that will handle the statistics logic.

Prompt 22: Statistics Page (PHP - Backend Logic)

Plaintext

Modify `game.php` to:
1. Create a function `getOverallStatistics()` that queries the database to retrieve:
    * The player with the most wins.
    * The total number of games played.
2. Create a function `getGameHistory()` that queries the database to retrieve a list of all games played, including game ID, winner, and date/time.
3. Create a function `getGameDetails($gameId)` that queries the database to retrieve round-by-round details for a specific game.
4. Create endpoints `/getOverallStatistics`, `/getGameHistory`, and `/getGameDetails` that call the respective functions and return the results as JSON.

Prompt 23: Statistics Page (JavaScript - Frontend Logic)

Plaintext

Modify `statistics.js` to:
1. Use `fetch()` to retrieve overall statistics from `/getOverallStatistics` and display them in the `overall-stats` div.
2. Use `fetch()` to retrieve the game history from `/getGameHistory` and display it as a list in the `game-history` div.
3. Add event listeners to the game list items to fetch and display game details from `/getGameDetails` in the `game-details` div when clicked.
4. Format the data retrieved from the server, and display it in a user-friendly way.

Prompt 24: Error Handling and Logging (PHP)

Plaintext

Modify `game.php` to:
1. Implement try-catch blocks around database queries and other critical operations.
2. Log errors to a file or database table, including error messages, timestamps, and relevant data.
3. Return appropriate error messages as JSON responses to the frontend.

Prompt 25: Error Handling and Logging (JavaScript)

Plaintext

Modify `index.html` and `statistics.js` to:
1. Handle errors returned by the backend (e.g., display error messages in the `game-message` div).
2. Use `console.error()` to log JavaScript errors.
3. Add a visual display for errors, to prevent errors from only being visible in the console.

Prompt 26: Security Enhancements (PHP)

Plaintext

Modify `game.php` to:
1. Implement input validation and sanitization for all user inputs (game codes, player names, bids, etc.).
2. Use prepared statements for database queries to prevent SQL injection.
3. Implement HTTPS for all communication.

Prompt 27: Security Enhancements (JavaScript)

Plaintext

Modify `index.html` and `statistics.js` to:
1. Sanitize user inputs before sending them to the backend.
2. Use HTTPS for all requests to the backend.

Prompt 28: Testing and Debugging

Plaintext

Create a set of test cases for the following functionalities:
1. Game creation and joining.
2. Dealing and initial game state.
3. Bidding logic.
4. Kitty and trump selection.
5. Trick gameplay logic.
6. Scoring and round completion.
7. Game completion and winner determination.
8. Statistics page.
Use PHPUnit for backend testing and a JavaScript testing framework (e.g., Jest) for frontend testing.

Prompt 29: Deployment and Optimization

Plaintext

Provide instructions on how to:
1. Deploy the PHP application and MySQL database to the EC2 instance.
2. Configure Apache to serve the application.
3. Configure Route 53 to point the domain to the EC2 instance.
4. Configure S3 for static assets.
5. Optimize the application for performance (e.g., caching, database indexing).
6. Configure a cron job or other method to clear old game data.

Prompt 30: Websocket Server Configuration (PHP)

Plaintext

Provide instructions on how to:
1. Install and configure a websocket server on the EC2 instance.
2. Modify the php backend to communicate with the websocket server.
3. Provide an example of how the php backend would send a JSON message to the websocket server.

You got it! Let's build on the previous prompts and add a few more to refine the project.

Prompt 31: Integrate Websocket Client (JavaScript)

Plaintext

Modify `index.html` and the associated JavaScript to:
1. Establish a WebSocket connection to the server.
2. Implement functions to handle incoming WebSocket messages (game state updates, etc.).
3. Update the UI in real-time based on the received WebSocket messages.
4. Implement functions to send messages to the server (card plays, bids, etc.).
5. Ensure that the websocket client handles connection loss, and attempts to reconnect.

Prompt 32: Database Indexing

Plaintext

Provide SQL commands to add appropriate indexes to the following tables in the MySQL database:
1. games
2. players
3. cards
4. game_state
Consider the most common queries and use cases when determining which columns to index.

Prompt 33: Caching Static Assets (S3 and CloudFront)

Plaintext

Provide instructions on how to:
1. Configure CloudFront to serve the static assets from the S3 bucket.
2. Set appropriate caching headers for the static assets.
3. Invalidate the CloudFront cache when static assets are updated.
4. Explain the benefits of using CloudFront for static asset delivery.

Prompt 34: Database Backups

Plaintext

Provide instructions on how to:
1. Create a script to perform regular backups of the MySQL database.
2. Schedule the backups using cron.
3. Store the backups in a secure location (e.g., S3).
4. Provide instructions on how to restore the database from a backup.

Prompt 35: Logging Best Practices (PHP)

Plaintext

Modify the PHP error logging to:
1. Use a logging library (e.g., Monolog) instead of writing to a file directly.
2. Implement different log levels (debug, info, warning, error, critical).
3. Log relevant context information (user ID, game ID, etc.).
4. Configure log rotation to prevent log files from growing too large.
5. Provide instructions on how to view and analyze the logs.

Prompt 36: Logging Best Practices (JavaScript)

Plaintext

Modify the JavaScript logging to:
1. Implement a centralized logging function.
2. Use different log levels (debug, info, warning, error).
3. Log relevant context information (user ID, game ID, etc.).
4. Implement a mechanism to send JavaScript logs to the server for analysis (e.g., via a dedicated API endpoint).

Prompt 37: Session Management (PHP)

Plaintext

Modify the PHP backend to:
1. Implement session management to track user sessions.
2. Store session data in the database or a caching system (e.g., Redis).
3. Use secure session cookies.
4. Implement session timeout and logout functionality.
5. Use session data to track the current player's game, and prevent unauthorized access.

Prompt 38: Input Validation Best Practices (PHP)

Plaintext

Modify the PHP input validation to:
1. Use a validation library (e.g., Respect/Validation) to validate user inputs.
2. Implement specific validation rules for each input field (e.g., regular expressions, data types).
3. Return detailed error messages to the frontend when validation fails.
4. Use a whitelist approach to input validation.

Prompt 39: Input Validation Best Practices (JavaScript)

Plaintext

Modify the JavaScript input validation to:
1. Implement client-side input validation to prevent invalid data from being sent to the server.
2. Use regular expressions and other validation techniques to validate user inputs.
3. Provide clear error messages to the user when validation fails.
4. Use the same validation rules as the server-side validation.

Prompt 40: Code Refactoring and Optimization

Plaintext

Review the entire codebase and:
1. Refactor the code to improve readability, maintainability, and performance.
2. Identify and eliminate code duplication.
3. Optimize database queries and other performance-critical sections.
4. Add comments and documentation to the code.
5. Ensure compliance with coding standards and best practices.


Followups:
Local Development Environment Setup

1. Install Required Software:

XAMPP or WAMP (Windows), MAMP (macOS), or LAMP (Linux):
These packages provide a local server environment with Apache, PHP, and MySQL.   
Download and install the appropriate package for your operating system.
Text Editor or IDE:
Choose a text editor or integrated development environment (IDE) like VS Code, Sublime Text, or PHPStorm.
Web Browser:
Use a modern web browser like Chrome, Firefox, or Edge for testing.
2. Configure Local Server:

Start Apache and MySQL:
Open the XAMPP/WAMP/MAMP control panel and start the Apache and MySQL services.
Verify Installation:
Open your web browser and navigate to http://localhost. You should see the default XAMPP/WAMP/MAMP page.
Create Project Directory:
Create a new directory for your project within the Apache htdocs folder (e.g., C:\xampp\htdocs\110game).
Create info.php:
Create a file called info.php inside your project directory, and add the following code:
PHP

<?php
phpinfo();
?>
Navigate to http://localhost/110game/info.php in your browser. If you see the PHP information page, PHP is working correctly.
3. Set up MySQL Database:

Access phpMyAdmin:
Open your web browser and navigate to http://localhost/phpmyadmin.
Create Database:
Create a new database named 110_game.
Create Database User:
Create a user that has all permissions to the 110_game database.
4. Local Development Workflow:

Code Development:
Write your HTML, CSS, JavaScript, and PHP code in your project directory.
Testing:
Open your web browser and navigate to http://localhost/110game/index.html (or the appropriate file) to test your application.
Test your php files by navigating to them in the browser.
Debugging:
Use browser developer tools (e.g., Chrome DevTools) to debug JavaScript and frontend issues.   
Use PHP's error_log() function or a debugging tool like Xdebug to debug PHP code.
Use phpmyadmin to view the database.   
5. Moving to the Cloud (AWS):

Once your application is working locally, you can deploy it to AWS:

Set up EC2 Instance:
Launch an EC2 instance with the same operating system as your local environment (e.g., Ubuntu).
Install Apache, PHP, and MySQL:
Install the same software versions as your local environment.
Configure Apache and MySQL:
Match the configuration settings from your local environment.
Transfer Files:
Use SFTP or SCP to transfer your project files from your local machine to the EC2 instance.   
Import Database:
Export your local MySQL database and import it into the EC2 instance's MySQL server.   
Configure DNS (Route 53):
Point your domain name to the EC2 instance's public IP address.   
Test and Debug:
Thoroughly test your application on the EC2 instance and address any issues.
Configure S3 and CloudFront:
Move your static files to the S3 bucket, and configure cloudfront.