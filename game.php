<?php
header('Content-Type: application/json');

// Load configuration
$config = require __DIR__ . '/config.php';

// Allow HTTP for local testing
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    $isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);
    $isDevelopment = $config['app']['env'] === 'development';
    if (!$isLocalhost && !$isDevelopment) {
        echo json_encode(['error' => 'HTTPS is required for secure communication.']);
        exit();
    }
}

// Database credentials from config
$host = $config['database']['host'] . ':' . $config['database']['port'];
$username = $config['database']['user'];
$password = $config['database']['password'];
$database = $config['database']['name'];

// Establish a connection to the MySQL database
try {
    $conn = new mysqli($host, $username, $password, $database);

    // Check for connection errors
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
} catch (Exception $e) {
    logError($e->getMessage());
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

// Function to log errors to a file
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents('error_log.txt', $logMessage, FILE_APPEND);
}

// Function to validate and sanitize input
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'string':
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        case 'gameCode':
            return preg_match('/^[A-Z0-9]{6}$/', $input) ? $input : null;
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Function to generate a unique 6-character alphanumeric game code
function generateGameCode() {
    return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
}

// Function to generate a standard 52-card deck plus a joker
function generateDeck() {
    $suits = ['H', 'D', 'C', 'S'];
    $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $deck = [];

    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = $suit . $rank;
        }
    }

    $deck[] = 'Joker'; // Add the joker
    shuffle($deck); // Shuffle the deck
    return $deck;
}

// Function to check if a card is a reneging card (can be held back when following suit)
// Reneging cards: Joker, 5 of Trumps, Jack of Trumps, Ace of Hearts
function isRenegingCard($card, $trumpSuit) {
    if ($card === 'Joker') {
        return true;
    }
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    // 5 of Trumps
    if ($cardSuit === $trumpSuit && $cardRank === '5') {
        return true;
    }
    
    // Jack of Trumps
    if ($cardSuit === $trumpSuit && $cardRank === 'J') {
        return true;
    }
    
    // Ace of Hearts (always a reneging card regardless of trump suit)
    if ($cardSuit === 'H' && $cardRank === 'A') {
        return true;
    }
    
    return false;
}

// Function to get the rank of a reneging card for comparison
// Higher number = higher rank: 5 of Trump (4) > Jack of Trump (3) > Joker (2) > Ace of Hearts (1)
function getRenegingCardRank($card, $trumpSuit) {
    $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
    $cardRank = ($card === 'Joker') ? null : substr($card, 1);
    
    // 5 of Trumps - highest reneging card
    if ($cardSuit === $trumpSuit && $cardRank === '5') {
        return 4;
    }
    
    // Jack of Trumps
    if ($cardSuit === $trumpSuit && $cardRank === 'J') {
        return 3;
    }
    
    // Joker
    if ($card === 'Joker') {
        return 2;
    }
    
    // Ace of Hearts - lowest reneging card
    if ($cardSuit === 'H' && $cardRank === 'A') {
        return 1;
    }
    
    return 0; // Not a reneging card
}

// Function to get the highest reneging card rank played in the current trick
function getHighestRenegingCardPlayed($currentTrick, $trumpSuit) {
    $highestRank = 0;
    foreach ($currentTrick as $playedCard) {
        $rank = getRenegingCardRank($playedCard['card'], $trumpSuit);
        if ($rank > $highestRank) {
            $highestRank = $rank;
        }
    }
    return $highestRank;
}

// Function to check if player must follow suit (accounting for reneging cards)
// Returns true if player has cards they MUST play of the lead suit
function mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick) {
    $highestRenegingPlayed = getHighestRenegingCardPlayed($currentTrick, $trumpSuit);
    
    foreach ($playerCards as $card) {
        // Skip Joker - it has no suit
        if ($card === 'Joker') {
            continue;
        }
        
        $cardSuit = substr($card, 0, 1);
        
        // Check if this card is of the lead suit
        if ($cardSuit === $leadSuit) {
            // Check if it's a reneging card
            if (isRenegingCard($card, $trumpSuit)) {
                $cardRenegingRank = getRenegingCardRank($card, $trumpSuit);
                // Player must play this reneging card only if a HIGHER one was played
                if ($highestRenegingPlayed > $cardRenegingRank) {
                    return true;
                }
                // Otherwise, this card can be reneged (held back)
            } else {
                // Not a reneging card, so player MUST follow suit
                return true;
            }
        }
    }
    
    return false; // Player has no cards they must play of the lead suit
}

// Function to deal cards to players and the Kitty
function dealCards($gameId) {
    global $conn;

    // Fetch the list of players for the given game ID
    $playersQuery = $conn->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $playersQuery->bind_param('i', $gameId);
    $playersQuery->execute();
    $playersResult = $playersQuery->get_result();
    $players = $playersResult->fetch_all(MYSQLI_ASSOC);

    // Check if there are players in the game
    if (empty($players)) {
        throw new Exception("No players found for game ID: $gameId");
    }

    // Generate a deck of cards
    $deck = generateDeck();

    // Create the cards table if it doesn't exist
    $createCardsTableQuery = "
    CREATE TABLE IF NOT EXISTS cards (
        card_id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT,
        player_id INT,
        card VARCHAR(10),
        FOREIGN KEY (game_id) REFERENCES games(game_id),
        FOREIGN KEY (player_id) REFERENCES players(player_id)
    )";
    $conn->query($createCardsTableQuery);

    // Create the game_state table if it doesn't exist
    $createGameStateTableQuery = "
    CREATE TABLE IF NOT EXISTS game_state (
        game_id INT PRIMARY KEY,
        state TEXT,
        FOREIGN KEY (game_id) REFERENCES games(game_id)
    )";
    $conn->query($createGameStateTableQuery);

    // Deal 5 cards to each player and the Kitty
    $cardsPerPlayer = 5;
    $kitty = array_splice($deck, 0, $cardsPerPlayer);
    
    // Fetch existing state to preserve finalScores, dealer, roundNumber, etc.
    $existingStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $existingStateQuery->bind_param('i', $gameId);
    $existingStateQuery->execute();
    $existingStateResult = $existingStateQuery->get_result();
    $existingStateRow = $existingStateResult->fetch_assoc();
    
    // Start with existing state or empty array
    $gameState = $existingStateRow ? json_decode($existingStateRow['state'], true) : [];
    
    // Update with new deal data (preserves finalScores, dealer, roundNumber, etc.)
    $gameState['kitty'] = $kitty;
    $gameState['players'] = [];
    $gameState['currentTurn'] = null;

    foreach ($players as $index => $player) {
        $playerId = $player['player_id'];
        $playerCards = array_splice($deck, 0, $cardsPerPlayer);
        $gameState['players'][$playerId] = $playerCards;

        // Set the first player as the current turn
        if ($index === 0) {
            $gameState['currentTurn'] = $playerId;
        }

        foreach ($playerCards as $card) {
            $insertCardQuery = $conn->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, ?, ?)");
            $insertCardQuery->bind_param('iis', $gameId, $playerId, $card);
            $insertCardQuery->execute();
        }
    }

    // Store the Kitty cards
    foreach ($kitty as $card) {
        $insertCardQuery = $conn->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, NULL, ?)");
        $insertCardQuery->bind_param('is', $gameId, $card);
        $insertCardQuery->execute();
    }

    // Store the game state (preserving existing properties)
    $gameStateJson = json_encode($gameState);
    $insertGameStateQuery = $conn->prepare("INSERT INTO game_state (game_id, state) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE state = VALUES(state)");
    $insertGameStateQuery->bind_param('is', $gameId, $gameStateJson);
    if (!$insertGameStateQuery->execute()) {
        error_log("Error inserting game state: " . $conn->error);
        throw new Exception("Failed to insert game state.");
    }
}

// Function to start the bidding phase
function startBidding($gameId) {
    global $conn;

    // Fetch the current game state (from dealCards)
    $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $gameStateQuery->bind_param('i', $gameId);
    $gameStateQuery->execute();
    $gameStateResult = $gameStateQuery->get_result();
    $existingState = $gameStateResult->fetch_assoc();
    
    $state = $existingState ? json_decode($existingState['state'], true) : [];

    // Fetch the players for the game
    $playersQuery = $conn->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $playersQuery->bind_param('i', $gameId);
    $playersQuery->execute();
    $playersResult = $playersQuery->get_result();
    $players = $playersResult->fetch_all(MYSQLI_ASSOC);

    if (empty($players)) {
        throw new Exception("No players found for game ID: $gameId");
    }

    // Determine the first bidder (player to the left of the dealer)
    $playerIds = array_column($players, 'player_id');
    $dealerIndex = $state['dealer'] ?? 0;
    $firstBidderIndex = ($dealerIndex + 1) % count($playerIds);
    $firstBidder = $playerIds[$firstBidderIndex];

    // Merge bidding state with existing state
    $state['phase'] = 'bidding';
    $state['currentBid'] = 0;
    $state['currentBidder'] = $firstBidder;
    $state['highestBidder'] = null;
    $state['validBids'] = [15, 20, 25, 30];
    $state['passedPlayers'] = [];
    $state['biddingOver'] = false;
    $state['dealerMustBid'] = false;
    $state['dealerCanMatch'] = false;
    $state['forcedDealerBid'] = false;
    
    // Initialize round tracking if this is the first round
    if (!isset($state['roundNumber'])) {
        $state['roundNumber'] = 1;
    }
    if (!isset($state['dealer'])) {
        $state['dealer'] = 0; // First player is initial dealer
    }
    if (!isset($state['finalScores'])) {
        $state['finalScores'] = [];
        // Initialize all players with 0 score
        foreach ($playerIds as $pid) {
            $state['finalScores'][$pid] = 0;
        }
    }

    // Update the game_state table with the merged state
    $updatedStateJson = json_encode($state);
    $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateGameStateQuery->execute();
}

// Function to process a bid
function processBid($gameId, $playerId, $bidAmount) {
    global $conn;

    // Fetch the current game state
    $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $gameStateQuery->bind_param('i', $gameId);
    $gameStateQuery->execute();
    $gameStateResult = $gameStateQuery->get_result();
    $gameState = $gameStateResult->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Validate it's this player's turn to bid
    if ($state['currentBidder'] != $playerId) {
        throw new Exception("It's not your turn to bid.");
    }

    $playerIds = array_keys($state['players']);
    
    // Check if this player is the dealer
    $dealerIndex = $state['dealer'] ?? 0;
    $dealerPlayerId = $playerIds[$dealerIndex];
    $isDealer = ($playerId == $dealerPlayerId);
    
    // Handle passing (bidAmount = 0)
    if ($bidAmount == 0) {
        // Check if dealer is trying to pass when everyone else has passed and no one has bid
        // Dealer must bid at least 15 in this case
        $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
        $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
        $noBidsYet = ($state['currentBid'] ?? 0) == 0;
        
        if ($isDealer && $allOthersPassed && $noBidsYet) {
            throw new Exception("Everyone passed - dealer must bid at least 15.");
        }
        
        // Track if this was a forced dealer bid (for history)
        $state['forcedDealerBid'] = false;
        
        // Add player to passed list
        if (!in_array($playerId, $state['passedPlayers'])) {
            $state['passedPlayers'][] = $playerId;
        }
    } else {
        // Validate the bid amount
        // Dealer can match the current bid, others must bid higher
        $dealerMatched = false;
        if ($isDealer && $bidAmount == $state['currentBid'] && $state['currentBid'] > 0) {
            // Dealer is matching - this is allowed and wins the bid immediately
            $dealerMatched = true;
        } elseif ($bidAmount <= $state['currentBid']) {
        throw new Exception("Bid must be higher than the current bid: {$state['currentBid']}");
    }
        
        // Valid bid increments: 15, 20, 25, 30 (in 5s)
        if ($bidAmount < 15 || $bidAmount > 30 || $bidAmount % 5 !== 0) {
            throw new Exception("Invalid bid amount. Bid must be 15, 20, 25, or 30.");
        }
        
        // Check if this is a forced dealer bid (everyone else passed, no bids yet)
        $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
        $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
        $noBidsYet = ($state['currentBid'] ?? 0) == 0;
        $state['forcedDealerBid'] = ($isDealer && $allOthersPassed && $noBidsYet);

    // Update the bidding state
    $state['currentBid'] = $bidAmount;
        $state['highestBidder'] = $playerId;
        
        // If dealer matched, bidding ends immediately - dealer wins!
        if ($dealerMatched) {
            $state['biddingOver'] = true;
            $state['phase'] = 'kitty';
            $state['currentBidder'] = $playerId;
            $state['dealerCanMatch'] = false;
            
            // Update the game state and return early
            $updatedStateJson = json_encode($state);
            $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
            $updateGameStateQuery->execute();
            
            try {
                sendGameStateToWebSocket($state);
            } catch (Exception $e) {
                error_log("WebSocket error: " . $e->getMessage());
            }
            return; // Exit early - bidding is over
        }
        
        // Update valid bids (only bids higher than current)
        $state['validBids'] = array_values(array_filter([15, 20, 25, 30], function($b) use ($bidAmount) {
            return $b > $bidAmount;
        }));
    }

    // Find next active bidder (skip passed players)
    $currentIndex = array_search($playerId, $playerIds);
    $nextBidder = null;
    $checked = 0;
    
    while ($checked < count($playerIds)) {
        $currentIndex = ($currentIndex + 1) % count($playerIds);
        $candidateId = $playerIds[$currentIndex];
        $checked++;
        
        if (!in_array($candidateId, $state['passedPlayers'])) {
            $nextBidder = $candidateId;
            break;
        }
    }

    // Check if bidding is over (all but one passed, or we cycled back to highest bidder)
    $activeBidders = array_diff($playerIds, $state['passedPlayers']);
    
    if (count($activeBidders) === 1 && $state['highestBidder'] !== null) {
        // Bidding is over - one player left with a bid
        $state['biddingOver'] = true;
        $state['phase'] = 'kitty';
        $state['currentBidder'] = $state['highestBidder'];
    } elseif (count($activeBidders) === 0) {
        // Everyone passed - redeal needed (or first player must bid minimum)
        throw new Exception("All players passed. Game needs to be redealt.");
    } elseif ($nextBidder === $state['highestBidder']) {
        // Cycled back to highest bidder - bidding is over
        $state['biddingOver'] = true;
        $state['phase'] = 'kitty';
        $state['currentBidder'] = $state['highestBidder'];
    } else {
        $state['currentBidder'] = $nextBidder;
        
        // Check if the next bidder is the dealer - they can match the current bid
        $nextBidderIsDealer = ($nextBidder == $dealerPlayerId);
        if ($nextBidderIsDealer && $state['currentBid'] > 0) {
            // Add the current bid as a "match" option for the dealer
            $state['dealerCanMatch'] = true;
            // Include current bid in valid bids for dealer
            if (!in_array($state['currentBid'], $state['validBids'])) {
                array_unshift($state['validBids'], $state['currentBid']);
            }
        } else {
            $state['dealerCanMatch'] = false;
        }
        
        // Check if dealer must bid (everyone else passed and no bids yet)
        if ($nextBidderIsDealer) {
            $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
            $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
            $noBidsYet = ($state['currentBid'] ?? 0) == 0;
            $state['dealerMustBid'] = ($allOthersPassed && $noBidsYet);
        } else {
            $state['dealerMustBid'] = false;
        }
    }

    // Update the game state
    $updatedStateJson = json_encode($state);
    $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateGameStateQuery->execute();

    // Send the updated game state to the WebSocket server
    try {
    sendGameStateToWebSocket($state);
    } catch (Exception $e) {
        // Log but don't fail if WebSocket isn't available
        error_log("WebSocket error: " . $e->getMessage());
    }
}

// Function to select the Kitty and Trump suit
function selectKittyAndTrump($gameId, $playerId, $selectedCards, $trumpSuit) {
    global $conn;

    // Fetch the current game state
    $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $gameStateQuery->bind_param('i', $gameId);
    $gameStateQuery->execute();
    $gameStateResult = $gameStateQuery->get_result();
    $gameState = $gameStateResult->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Verify that the player is the highest bidder
    if ($state['highestBidder'] != $playerId) {
        throw new Exception("Player is not the highest bidder.");
    }
    
    // Validate exactly 5 cards selected
    if (count($selectedCards) !== 5) {
        throw new Exception("You must select exactly 5 cards to keep.");
    }

    // Remove the Kitty cards from the cards table
    $deleteKittyQuery = $conn->prepare("DELETE FROM cards WHERE game_id = ? AND player_id IS NULL");
    $deleteKittyQuery->bind_param('i', $gameId);
    $deleteKittyQuery->execute();

    // Remove the player's current hand
    $deleteHandQuery = $conn->prepare("DELETE FROM cards WHERE game_id = ? AND player_id = ?");
    $deleteHandQuery->bind_param('ii', $gameId, $playerId);
    $deleteHandQuery->execute();

    // Insert the selected cards as the player's new hand
    foreach ($selectedCards as $card) {
        $insertCardQuery = $conn->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, ?, ?)");
        $insertCardQuery->bind_param('iis', $gameId, $playerId, $card);
        $insertCardQuery->execute();
    }

    // Convert trump suit to single letter format if needed
    $trumpSuitLetter = $trumpSuit;
    if (strlen($trumpSuit) > 1) {
        $suitMap = ['Hearts' => 'H', 'Diamonds' => 'D', 'Clubs' => 'C', 'Spades' => 'S'];
        $trumpSuitLetter = $suitMap[$trumpSuit] ?? $trumpSuit[0];
    }

    // Update the game state for trick phase
    $state['trumpSuit'] = $trumpSuitLetter;
    $state['phase'] = 'trick';
    
    // Player to the LEFT of the bid winner leads the first trick
    $playerIds = array_keys($state['players']);
    $bidWinnerIndex = array_search($playerId, $playerIds);
    $firstLeaderIndex = ($bidWinnerIndex + 1) % count($playerIds);
    $state['currentTurn'] = $playerIds[$firstLeaderIndex];
    
    $state['currentTrick'] = [];
    $state['tricksPlayed'] = 0;
    $state['scores'] = [];
    
    // Refresh players' hands in state from database
    $playerIds = array_keys($state['players']);
    foreach ($playerIds as $pid) {
        $cardsQuery = $conn->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
        $cardsQuery->bind_param('ii', $gameId, $pid);
        $cardsQuery->execute();
        $cardsResult = $cardsQuery->get_result();
        $cards = array_column($cardsResult->fetch_all(MYSQLI_ASSOC), 'card');
        $state['players'][$pid] = $cards;
    }
    
    $updatedStateJson = json_encode($state);
    $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateGameStateQuery->execute();

    // Send the updated game state to the WebSocket server
    try {
    sendGameStateToWebSocket($state);
    } catch (Exception $e) {
        error_log("WebSocket error: " . $e->getMessage());
    }
}

// Function to play a card
function playCard($gameId, $playerId, $card) {
    global $conn;

    // Fetch the current game state
    $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $gameStateQuery->bind_param('i', $gameId);
    $gameStateQuery->execute();
    $gameStateResult = $gameStateQuery->get_result();
    $gameState = $gameStateResult->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Clear trick complete state when a new card is played
    $state['trickComplete'] = false;
    $state['lastCompletedTrick'] = null;

    // Validate that it's the player's turn (use == for loose comparison due to JSON type differences)
    if ($state['currentTurn'] != $playerId) {
        throw new Exception("It's not your turn to play.");
    }

    // Validate that the player has the card in their hand
    $playerCardsQuery = $conn->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
    $playerCardsQuery->bind_param('ii', $gameId, $playerId);
    $playerCardsQuery->execute();
    $playerCardsResult = $playerCardsQuery->get_result();
    $playerCards = array_column($playerCardsResult->fetch_all(MYSQLI_ASSOC), 'card');

    if (!in_array($card, $playerCards)) {
        throw new Exception("You don't have this card in your hand.");
    }

    // Validate following suit (if applicable)
    if (!empty($state['currentTrick'])) {
        $leadCard = $state['currentTrick'][0]['card'];
        $leadSuit = substr($leadCard, 0, 1); // Suit of the first card in the trick
        $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
        $trumpSuit = $state['trumpSuit'] ?? null;
        
        // Check if player is trying to play a card that doesn't match the lead suit
        // Playing a trump is always allowed (trumping is not reneging)
        $isPlayingTrump = ($cardSuit === $trumpSuit) || ($card === 'Joker') || isRenegingCard($card, $trumpSuit);
        
        if ($cardSuit !== $leadSuit && !$isPlayingTrump) {
            // Player is not following suit AND not playing trump
            // Check if they must follow suit (has non-reneging cards of lead suit)
            if (mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $state['currentTrick'])) {
                throw new Exception("No reneging - you must follow suit!");
            }
        }
    }

    // Remove the card from the player's hand in database
    $removeCardQuery = $conn->prepare("DELETE FROM cards WHERE game_id = ? AND player_id = ? AND card = ?");
    $removeCardQuery->bind_param('iis', $gameId, $playerId, $card);
    $removeCardQuery->execute();
    
    // Also remove from state
    $state['players'][$playerId] = array_values(array_filter($state['players'][$playerId], function($c) use ($card) {
        return $c !== $card;
    }));

    // Add the card to the current trick
    $state['currentTrick'][] = ['playerId' => $playerId, 'card' => $card];

    // Track highest card played in the round (for 5-point bonus)
    $trumpSuit = $state['trumpSuit'] ?? 'H';
    $cardRank = getCardRankValue($card, $trumpSuit, $trumpSuit); // Use trump as lead for absolute ranking
    
    if (!isset($state['highestCardInRound']) || $cardRank > ($state['highestCardRank'] ?? 0)) {
        $state['highestCardInRound'] = $card;
        $state['highestCardPlayer'] = $playerId;
        $state['highestCardRank'] = $cardRank;
        $state['highestCardTrick'] = ($state['tricksPlayed'] ?? 0) + 1; // Track which trick it was played in
    }

    $playerIds = array_keys($state['players']);
    $numPlayers = count($playerIds);
    
    // Check if all players have played in this trick
    if (count($state['currentTrick']) >= $numPlayers) {
        // Trick is complete - determine winner
        // First, save current state to database
        $updatedStateJson = json_encode($state);
        $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
        $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
        $updateGameStateQuery->execute();
        
        // Now determine the trick winner (this updates state in DB)
        determineTrickWinner($gameId);
        
        // Fetch updated state after determineTrickWinner
        $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $gameStateQuery->bind_param('i', $gameId);
        $gameStateQuery->execute();
        $gameStateResult = $gameStateQuery->get_result();
        $updatedGameState = $gameStateResult->fetch_assoc();
        $state = json_decode($updatedGameState['state'], true);
        
        // Check if round is complete (5 tricks)
        if (($state['tricksPlayed'] ?? 0) >= 5) {
            scoreRound($gameId);
            
            // Fetch state again after scoring
            $gameStateQuery->execute();
            $gameStateResult = $gameStateQuery->get_result();
            $updatedGameState = $gameStateResult->fetch_assoc();
            $state = json_decode($updatedGameState['state'], true);
        }
    } else {
        // Determine the next player
    $currentIndex = array_search($playerId, $playerIds);
        $nextIndex = ($currentIndex + 1) % $numPlayers;
    $state['currentTurn'] = $playerIds[$nextIndex];

    // Update the game state
    $updatedStateJson = json_encode($state);
    $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateGameStateQuery->execute();
    }

    // Send the updated game state to the WebSocket server
    try {
    sendGameStateToWebSocket($state);
    } catch (Exception $e) {
        error_log("WebSocket error: " . $e->getMessage());
    }
}

// Function to send game state updates to the WebSocket server
function sendGameStateToWebSocket($gameState) {
    global $config;
    $wsHost = $config['websocket']['host'] ?? 'localhost';
    $wsPort = $config['websocket']['port'] ?? '8081';
    
    // Suppress warning with @ - WebSocket is optional, polling handles updates
    $socket = @stream_socket_client("tcp://{$wsHost}:{$wsPort}", $errno, $errorMessage, 1);

    if (!$socket) {
        // WebSocket not available - this is fine, polling will handle updates
        return;
    }

    $message = json_encode([
        'type' => 'gameStateUpdate',
        'payload' => $gameState
    ]);

    @fwrite($socket, $message);
    @fclose($socket);
}

// Function to check if a card is effectively trump
function isCardTrump($card, $trumpSuit) {
    if ($card === 'Joker') return true;
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    // Ace of Hearts is always trump
    if ($cardSuit === 'H' && $cardRank === 'A') return true;
    
    // Card is of the trump suit
    return $cardSuit === $trumpSuit;
}

// Function to get the rank value of a card for comparison
// Higher value = better card
function getCardRankValue($card, $trumpSuit, $leadSuit) {
    if ($card === 'Joker') {
        return 1000 + 13; // Third highest trump
    }
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    // Trump ranking (high values, 1000+)
    // 5 of Trump = 1015, J of Trump = 1014, Joker = 1013, A of Hearts = 1012
    // A of Trump = 1011 (if not hearts), K = 1010, Q = 1009, 10 = 1008...
    
    if (isCardTrump($card, $trumpSuit)) {
        // Special trump cards
        if ($cardSuit === $trumpSuit && $cardRank === '5') {
            return 1015; // Highest trump - 5 of trump
        }
        if ($cardSuit === $trumpSuit && $cardRank === 'J') {
            return 1014; // Second highest - Jack of trump
        }
        // Joker handled above (1013)
        if ($cardSuit === 'H' && $cardRank === 'A') {
            return 1012; // Fourth highest - Ace of Hearts (always trump)
        }
        if ($cardSuit === $trumpSuit && $cardRank === 'A') {
            return 1011; // Fifth highest - Ace of trump (if not hearts)
        }
        
        // Regular trump cards: K, Q, 10, 9, 8, 7, 6, 4, 3, 2
        $trumpRanks = ['K' => 1010, 'Q' => 1009, '10' => 1008, '9' => 1007, 
                       '8' => 1006, '7' => 1005, '6' => 1004, '4' => 1003, 
                       '3' => 1002, '2' => 1001];
        return $trumpRanks[$cardRank] ?? 1000;
    }
    
    // Non-trump cards that match lead suit (500-512)
    // Ranking: K, Q, J, 10, 9, 8, 7, 6, 5, 4, 3, 2, A (Ace is lowest!)
    if ($cardSuit === $leadSuit) {
        $leadRanks = ['K' => 512, 'Q' => 511, 'J' => 510, '10' => 509, 
                      '9' => 508, '8' => 507, '7' => 506, '6' => 505, 
                      '5' => 504, '4' => 503, '3' => 502, '2' => 501, 'A' => 500];
        return $leadRanks[$cardRank] ?? 500;
    }
    
    // Cards not matching lead suit or trump (cannot win)
    return 0;
}

// Function to determine the winner of the trick
function determineTrickWinner($gameId) {
    global $conn;

    // Fetch the current game state
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    if (empty($state['currentTrick'])) {
        throw new Exception("No cards in current trick");
    }

    $trumpSuit = $state['trumpSuit'];
    $leadCard = $state['currentTrick'][0]['card'];
    
    // Determine lead suit (for following purposes)
    // Note: If a trump card leads, lead suit is trump
    if (isCardTrump($leadCard, $trumpSuit)) {
        $leadSuit = $trumpSuit;
    } else {
        $leadSuit = substr($leadCard, 0, 1);
    }
    
    // Find the winning card
    $winningCard = $state['currentTrick'][0];
    $winningRank = getCardRankValue($winningCard['card'], $trumpSuit, $leadSuit);
    
    foreach ($state['currentTrick'] as $playedCard) {
        $cardRank = getCardRankValue($playedCard['card'], $trumpSuit, $leadSuit);
        
        if ($cardRank > $winningRank) {
            $winningCard = $playedCard;
            $winningRank = $cardRank;
        }
    }

    $state['trickWinner'] = $winningCard['playerId'];
    $state['lastTrickWinner'] = $winningCard['playerId'];
    
    // Store the completed trick for display before clearing
    $state['lastCompletedTrick'] = $state['currentTrick'];
    $state['trickComplete'] = true;
    
    // Track trick winners for display during round
    if (!isset($state['trickWinners'])) {
        $state['trickWinners'] = [];
    }
    $trickNumber = ($state['tricksPlayed'] ?? 0) + 1;
    $state['trickWinners'][] = [
        'trick' => $trickNumber,
        'winner' => $winningCard['playerId'],
        'winningCard' => $winningCard['card']
    ];

    // Increment the trick winner's score DIRECTLY in state (don't call separate function)
    $trickWinnerId = $winningCard['playerId'];
    if (!isset($state['scores'][$trickWinnerId])) {
        $state['scores'][$trickWinnerId] = 0;
    }
    $state['scores'][$trickWinnerId] += 1;

    // Reset the current trick and set next player
    $state['currentTrick'] = [];
    $state['currentTurn'] = $winningCard['playerId']; // Winner leads next trick
    $state['tricksPlayed'] = ($state['tricksPlayed'] ?? 0) + 1;

    // Update the game state in the database (includes score update)
    $updatedStateJson = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateQuery->execute();
    
    return $winningCard['playerId'];
}

// Function to start a new trick
function startNewTrick($gameId) {
    global $conn;

    // Fetch the current game state
    $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $gameStateQuery->bind_param('i', $gameId);
    $gameStateQuery->execute();
    $gameStateResult = $gameStateQuery->get_result();
    $gameState = $gameStateResult->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Reset the trick data
    $state['currentTrick'] = [];
    $state['currentTurn'] = $state['trickWinner']; // Start with the winner of the last trick

    // Update the game state
    $updatedStateJson = json_encode($state);
    $updateGameStateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateGameStateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateGameStateQuery->execute();
}

// Function to score a trick
function scoreTrick($gameId, $trickWinnerId) {
    global $conn;

    // Fetch the current game state
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Increment the trick winner's score
    if (!isset($state['scores'][$trickWinnerId])) {
        $state['scores'][$trickWinnerId] = 0;
    }
    $state['scores'][$trickWinnerId] += 1;

    // Update the game state in the database
    $updatedState = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedState, $gameId);
    $updateQuery->execute();
}

// Function to determine the game winner
// Rules:
// 1. First to reach 110 (by trick order) wins
// 2. Top trump bonus counts in the trick it was played
// 3. Bid winner must complete round - if they fail, they lose points and are removed from consideration
// 4. If no one else hit 110, game continues
function determineGameWinner($gameId) {
    global $conn;

    // Fetch the current game state
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    $playerIds = array_keys($state['players']);
    $trickWinners = $state['trickWinners'] ?? [];
    $highestCardPlayer = $state['highestCardPlayer'] ?? null;
    $highestCardTrick = $state['highestCardTrick'] ?? 5; // Default to last trick if not set
    $bidWinner = $state['highestBidder'] ?? null;
    $roundSummary = $state['roundSummary'] ?? null;
    $bidMade = $roundSummary['bidMade'] ?? true;
    $previousScores = []; // Scores at start of round
    
    // Get previous scores (before this round's points were added)
    foreach ($playerIds as $pid) {
        $roundPoints = $roundSummary['roundPoints'][$pid] ?? 0;
        $previousScores[$pid] = ($state['finalScores'][$pid] ?? 0) - $roundPoints;
    }
    
    // Check if bid winner forfeits top trump bonus (>= 85 at round start and played highest trump)
    $bidWinnerStartScore = $previousScores[$bidWinner] ?? 0;
    $bidWinnerForfeitsBonus = ($bidWinnerStartScore >= 85) && ($highestCardPlayer == $bidWinner);
    
    // Track running score and when each player first hit 110
    $firstTo110 = []; // [playerId => trickNumber]
    
    // Build running scores trick by trick
    $runningScores = $previousScores;
    $tricksWonByPlayer = [];
    foreach ($playerIds as $pid) {
        $tricksWonByPlayer[$pid] = 0;
    }
    
    foreach ($trickWinners as $trickInfo) {
        $trickNum = $trickInfo['trick'];
        $winner = $trickInfo['winner'];
        
        // Add 5 points to trick winner
        $tricksWonByPlayer[$winner] = ($tricksWonByPlayer[$winner] ?? 0) + 1;
        $runningScores[$winner] = ($runningScores[$winner] ?? 0) + 5;
        
        // Check if top trump bonus applies to this trick
        foreach ($playerIds as $pid) {
            $scoreWithBonus = $runningScores[$pid];
            
            // Top trump bonus applies from the trick it was played (unless forfeited by bid winner)
            $canGetBonus = ($pid == $highestCardPlayer && $trickNum >= $highestCardTrick);
            if ($pid == $bidWinner && $bidWinnerForfeitsBonus) {
                $canGetBonus = false; // Bid winner with >= 85 forfeits top trump
            }
            
            if ($canGetBonus) {
                $scoreWithBonus += 5;
            }
            
            // Check if this player hit 110
            if ($scoreWithBonus >= 110 && !isset($firstTo110[$pid])) {
                $firstTo110[$pid] = $trickNum;
            }
        }
    }
    
    // Now determine winner based on rules
    $winner = null;
    
    if (!empty($firstTo110)) {
        // Sort by trick number (first to reach 110)
        asort($firstTo110);
        
        foreach ($firstTo110 as $playerId => $trickNum) {
            // If this is the bid winner, check if they made their bid
            if ($playerId == $bidWinner) {
                if ($bidMade) {
                    // Bid winner made their bid and reached 110 first - they win!
                    $winner = $playerId;
            break;
        }
                // Bid winner failed - skip them (they lose points)
                continue;
            }
            
            // Non-bid winner reached 110 first - they win!
            $winner = $playerId;
            break;
        }
    }
    
    if ($winner !== null) {
        $state['gameWinner'] = $winner;
        $state['phase'] = 'game_over';
    }

    // Update the game state in the database
    $updatedState = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedState, $gameId);
    $updateQuery->execute();
}

// Function to score a round
function scoreRound($gameId) {
    global $conn;

    // Fetch the current game state
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Initialize finalScores if not set
    if (!isset($state['finalScores'])) {
        $state['finalScores'] = [];
    }

    // Get the bid winner and their bid
    $bidWinner = $state['highestBidder'] ?? null;
    $winningBid = $state['currentBid'] ?? 0;
    
    // In 110, scoring works as follows:
    // - Each trick is worth 5 points
    // - Bid winner: if they make their bid (tricks*5 + bonus >= bid), they get actual points earned
    //               if they fail, they lose their bid amount
    // - Other players: get 5 points per trick won + bonus if applicable
    
    $playerIds = array_keys($state['players']);
    
    // Get highest card info first - bonus counts towards making bid
    $highestCardPlayer = $state['highestCardPlayer'] ?? null;
    $highestCard = $state['highestCardInRound'] ?? null;
    
    // Get bid winner's score at start of round (before this round's points)
    $bidWinnerStartScore = $state['finalScores'][$bidWinner] ?? 0;
    
    // Rule: If bid winner starts with >= 85 points AND plays the top trump, they don't get the bonus
    $bidWinnerForfeitsBonus = ($bidWinnerStartScore >= 85) && ($highestCardPlayer == $bidWinner);
    
    // Calculate bid winner's total points (tricks + bonus if applicable)
    // Look up tricks won by scanning through scores array with loose comparison
    $bidWinnerTricks = 0;
    foreach ($state['scores'] as $pid => $tricks) {
        if ($pid == $bidWinner) {
            $bidWinnerTricks = $tricks;
            break;
        }
    }
    $bidWinnerTrickPoints = $bidWinnerTricks * 5;
    
    // Check if bid winner also has the highest card bonus (but may forfeit it)
    $bidWinnerHasBonus = false;
    if ($highestCardPlayer !== null && !$bidWinnerForfeitsBonus) {
        $bidWinnerHasBonus = ($highestCardPlayer == $bidWinner);
    }
    $bidWinnerBonus = $bidWinnerHasBonus ? 5 : 0;
    
    $bidWinnerTotalPoints = $bidWinnerTrickPoints + $bidWinnerBonus;
    $bidWinnerMadeBid = ($bidWinnerTotalPoints >= $winningBid);
    
    foreach ($playerIds as $playerId) {
        // Look up tricks won with loose comparison
        $tricksWon = 0;
        foreach ($state['scores'] as $pid => $tricks) {
            if ($pid == $playerId) {
                $tricksWon = $tricks;
                break;
            }
        }
        $trickPoints = $tricksWon * 5;
        
        // Check if player has bonus (bid winner may forfeit if >= 85 at round start)
        $hasBonus = ($highestCardPlayer !== null && $playerId == $highestCardPlayer);
        if ($playerId == $bidWinner && $bidWinnerForfeitsBonus) {
            $hasBonus = false; // Bid winner with >= 85 forfeits top trump bonus
        }
        
        if (!isset($state['finalScores'][$playerId])) {
            $state['finalScores'][$playerId] = 0;
        }
        
        if ($playerId == $bidWinner) {
            // Bid winner scoring - bonus counts towards total (unless forfeited)
            $totalPoints = $trickPoints + ($hasBonus ? 5 : 0);
            if ($bidWinnerMadeBid) {
                // Made the bid - get actual points earned (tricks + bonus)
                $state['finalScores'][$playerId] += $totalPoints;
        } else {
                // Failed the bid - lose the bid amount
                $state['finalScores'][$playerId] -= $winningBid;
            }
        } else {
            // Non-bid winners get 5 points per trick + bonus if applicable
            $state['finalScores'][$playerId] += $trickPoints;
            if ($hasBonus) {
                $state['finalScores'][$playerId] += 5;
            }
        }
    }
    
    // Build tricksWon with all players (including those with 0 tricks)
    $tricksWonByPlayer = [];
    foreach ($playerIds as $pid) {
        $tricksWonByPlayer[$pid] = 0;
        foreach ($state['scores'] as $scorePid => $tricks) {
            if ($scorePid == $pid) {
                $tricksWonByPlayer[$pid] = $tricks;
                break;
            }
        }
    }
    
    // Build round summary for display
    $roundSummary = [
        'bidWinner' => $bidWinner,
        'bid' => $winningBid,
        'tricksWon' => $tricksWonByPlayer,
        'roundPoints' => [],
        'totalScores' => $state['finalScores'],
        'highestCardPlayer' => $highestCardPlayer,
        'highestCard' => $highestCard,
        'bidWinnerForfeitsBonus' => $bidWinnerForfeitsBonus
    ];
    
    // Calculate points earned this round for each player
    foreach ($playerIds as $pid) {
        // Look up tricks won with loose comparison
        $tricksWon = 0;
        foreach ($state['scores'] as $scorePid => $tricks) {
            if ($scorePid == $pid) {
                $tricksWon = $tricks;
                break;
            }
        }
        $trickPoints = $tricksWon * 5;
        
        // Check if player has bonus (bid winner may forfeit if >= 85 at round start)
        $hasBonus = ($highestCardPlayer !== null && $pid == $highestCardPlayer);
        if ($pid == $bidWinner && $bidWinnerForfeitsBonus) {
            $hasBonus = false;
        }
        
        if ($pid == $bidWinner) {
            // Bid winner: get actual points if made, lose bid if failed
            $totalPoints = $trickPoints + ($hasBonus ? 5 : 0);
            if ($bidWinnerMadeBid) {
                // Made bid: get actual points earned
                $roundSummary['roundPoints'][$pid] = $totalPoints;
                $roundSummary['bidMade'] = true;
            } else {
                // Failed bid: lose the full bid amount
                $roundSummary['roundPoints'][$pid] = -$winningBid;
                $roundSummary['bidMade'] = false;
            }
        } else {
            // Non-bid winners get trick points + bonus
            $bonusPoints = $hasBonus ? 5 : 0;
            $roundSummary['roundPoints'][$pid] = $trickPoints + $bonusPoints;
        }
        
        // Track bonus separately for display (only if not forfeited)
        if ($hasBonus) {
            $roundSummary['bonusPlayer'] = $pid;
        }
    }
    
    // Reset highest card tracking for next round
    $state['highestCardInRound'] = null;
    $state['highestCardPlayer'] = null;
    $state['highestCardRank'] = null;
    $state['highestCardTrick'] = null;
    
    // Clear trick complete flags so round summary shows
    $state['trickComplete'] = false;
    $state['lastCompletedTrick'] = null;
    
    $state['roundSummary'] = $roundSummary;
    $state['phase'] = 'round_summary';
    
    // Store round history
    if (!isset($state['roundHistory'])) {
        $state['roundHistory'] = [];
    }
    $state['roundHistory'][] = [
        'bidWinner' => $bidWinner,
        'bid' => $winningBid,
        'bidMade' => $bidWinnerMadeBid,
        'forcedBid' => $state['forcedDealerBid'] ?? false,
        'trumpSuit' => $state['trumpSuit'] ?? null,
        'roundPoints' => $roundSummary['roundPoints'] ?? [],
        'scores' => $state['scores'],
        'finalScores' => $state['finalScores']
    ];
    
    // Save state with round summary (don't reset yet - wait for user to continue)
    $updatedStateJson = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateQuery->execute();
}

// Function to continue to next round after viewing summary
function continueToNextRound($gameId) {
    global $conn;
    
    // Fetch current state
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();
    
    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }
    
    $state = json_decode($gameState['state'], true);
    $playerIds = array_keys($state['players']);
    
    // Reset for next round
    $state['scores'] = [];
    $state['tricksPlayed'] = 0;
    $state['currentTrick'] = [];
    $state['roundSummary'] = null;
    $state['trickWinners'] = [];
    
    // Rotate dealer for next round
    $currentDealer = $state['dealer'] ?? 0;
    $numPlayers = count($playerIds);
    $nextDealerIndex = ($currentDealer + 1) % $numPlayers;
    $state['dealer'] = $nextDealerIndex;
    $state['roundNumber'] = ($state['roundNumber'] ?? 1) + 1;

    // Update the game state in the database
    $updatedState = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedState, $gameId);
    $updateQuery->execute();

    // Check for a game winner
    determineGameWinner($gameId);
    
    // If no winner, start a new round
    $query->execute();
    $result = $query->get_result();
    $updatedGameState = $result->fetch_assoc();
    $updatedState = json_decode($updatedGameState['state'], true);
    
    if (!isset($updatedState['gameWinner'])) {
        // Start new round - deal cards and start bidding
        startNewRound($gameId);
    }
}

// Function to start a new round (after previous round completes)
function startNewRound($gameId) {
    global $conn;
    
    // Clear old cards
    $clearCardsQuery = $conn->prepare("DELETE FROM cards WHERE game_id = ?");
    $clearCardsQuery->bind_param('i', $gameId);
    $clearCardsQuery->execute();
    
    // Deal new cards
    dealCards($gameId);
    
    // Start bidding with rotated dealer
    // Fetch current state to get dealer position
    $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $query->bind_param('i', $gameId);
    $query->execute();
    $result = $query->get_result();
    $gameState = $result->fetch_assoc();
    $state = json_decode($gameState['state'], true);
    
    $playerIds = array_keys($state['players']);
    $dealerIndex = $state['dealer'] ?? 0;
    
    // First bidder is to the left of the dealer
    $firstBidderIndex = ($dealerIndex + 1) % count($playerIds);
    $firstBidder = $playerIds[$firstBidderIndex];
    
    // Reset bidding state (preserve finalScores and roundNumber)
    $preservedFinalScores = $state['finalScores'] ?? [];
    $preservedRoundNumber = $state['roundNumber'] ?? 1;
    $preservedDealer = $state['dealer'] ?? 0;
    
    $state['phase'] = 'bidding';
    $state['currentBid'] = 0;
    $state['currentBidder'] = $firstBidder;
    $state['highestBidder'] = null;
    $state['validBids'] = [15, 20, 25, 30];
    $state['passedPlayers'] = [];
    $state['biddingOver'] = false;
    $state['trumpSuit'] = null;
    $state['dealerMustBid'] = false;
    $state['dealerCanMatch'] = false;
    $state['forcedDealerBid'] = false;
    
    // Ensure preserved values are maintained
    $state['finalScores'] = $preservedFinalScores;
    $state['roundNumber'] = $preservedRoundNumber;
    $state['dealer'] = $preservedDealer;
    
    $updatedStateJson = json_encode($state);
    $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $updateQuery->bind_param('si', $updatedStateJson, $gameId);
    $updateQuery->execute();
}

// Create the games table if it doesn't exist
$createGamesTableQuery = "
CREATE TABLE IF NOT EXISTS games (
    game_id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(6) UNIQUE,
    state TEXT
)";
$conn->query($createGamesTableQuery);

// Create the players table if it doesn't exist
$createPlayersTableQuery = "
CREATE TABLE IF NOT EXISTS players (
    player_id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT,
    name VARCHAR(255),
    FOREIGN KEY (game_id) REFERENCES games(game_id)
)";
$conn->query($createPlayersTableQuery);

// Endpoint to create a new game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'createGame') {
    try {
        $playerName = isset($_POST['playerName']) ? sanitizeInput($_POST['playerName'], 'string') : null;
        
        if (!$playerName) {
            throw new Exception('Player name is required to create a game.');
        }
        
        $gameCode = generateGameCode();
        $insertGameQuery = $conn->prepare("INSERT INTO games (code, state) VALUES (?, '{}')");
        $insertGameQuery->bind_param('s', $gameCode);

        if ($insertGameQuery->execute()) {
            $gameId = $conn->insert_id;
            
            // Add the creator as the first player
            $insertPlayerQuery = $conn->prepare("INSERT INTO players (game_id, name) VALUES (?, ?)");
            $insertPlayerQuery->bind_param('is', $gameId, $playerName);
            
            if ($insertPlayerQuery->execute()) {
                $playerId = $conn->insert_id;
                $response = [
                    'success' => true,
                    'gameCode' => $gameCode,
                    'gameId' => $gameId,
                    'playerId' => $playerId
                ];
            } else {
                throw new Exception('Failed to add player to game: ' . $conn->error);
            }
            $insertPlayerQuery->close();
        } else {
            throw new Exception('Failed to create game: ' . $conn->error);
        }

        echo json_encode($response);
        $insertGameQuery->close();
    } catch (Exception $e) {
        logError($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Endpoint to join a game
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'joinGame') {
    try {
        $gameCode = sanitizeInput($_POST['gameCode'], 'gameCode');
        $playerName = sanitizeInput($_POST['playerName'], 'string');

        if (!$gameCode || !$playerName) {
            throw new Exception('Invalid input: game code or player name is invalid.');
        }

        // Check if the game code exists
        $checkGameQuery = $conn->prepare("SELECT game_id FROM games WHERE code = ?");
        $checkGameQuery->bind_param('s', $gameCode);
        $checkGameQuery->execute();
        $checkGameQuery->store_result();

        if ($checkGameQuery->num_rows > 0) {
            $checkGameQuery->bind_result($gameId);
            $checkGameQuery->fetch();

            // Insert the new player record
            $insertPlayerQuery = $conn->prepare("INSERT INTO players (game_id, name) VALUES (?, ?)");
            $insertPlayerQuery->bind_param('is', $gameId, $playerName);

            if ($insertPlayerQuery->execute()) {
                $playerId = $conn->insert_id; // Get the newly created player ID
                $response = [
                    'success' => true,
                    'message' => 'Player joined the game successfully',
                    'playerId' => $playerId,
                    'gameId' => $gameId
                ];
            } else {
                throw new Exception('Failed to join game: ' . $conn->error);
            }

            $insertPlayerQuery->close();
        } else {
            throw new Exception('Invalid game code');
        }

        echo json_encode($response);
        $checkGameQuery->close();
    } catch (Exception $e) {
        logError($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Endpoint to deal cards
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'dealCards') {
    // Check for gameId in POST or GET
    $gameId = $_POST['gameId'] ?? $_GET['gameId'] ?? null;

    if ($gameId) {
        try {
            dealCards($gameId);
            $response = array('success' => 'Cards dealt successfully');
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Endpoint to start bidding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'startBidding') {
    if (isset($_POST['gameId'])) {
        $gameId = $_POST['gameId'];
        try {
            startBidding($gameId);
            $response = array('success' => 'Bidding started successfully');
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Endpoint to process a bid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'processBid') {
    if (isset($_POST['gameId'], $_POST['playerId']) && isset($_POST['bidAmount'])) {
        $gameId = sanitizeInput($_POST['gameId'], 'int');
        $playerId = sanitizeInput($_POST['playerId'], 'int');
        $bidAmount = intval($_POST['bidAmount']); // intval handles 0 correctly for pass

        if (!$gameId || !$playerId) {
            echo json_encode(['error' => 'Invalid input: gameId or playerId is invalid.']);
            exit();
        }

        try {
            processBid($gameId, $playerId, $bidAmount);
            $response = ['success' => 'Bid processed successfully'];
        } catch (Exception $e) {
            logError($e->getMessage());
            $response = ['error' => $e->getMessage()];
        }
    } else {
        $response = ['error' => 'Missing parameters (gameId, playerId, bidAmount)'];
    }
    echo json_encode($response);
}

// Endpoint to select the Kitty and Trump suit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'selectKittyAndTrump') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['selectedCards'], $_POST['trumpSuit'])) {
        $gameId = $_POST['gameId'];
        $playerId = $_POST['playerId'];
        $selectedCards = json_decode($_POST['selectedCards'], true); // Expecting JSON array
        $trumpSuit = $_POST['trumpSuit'];

        try {
            selectKittyAndTrump($gameId, $playerId, $selectedCards, $trumpSuit);
            $response = array('success' => 'Kitty and trump suit selected successfully');
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing parameters (gameId, playerId, selectedCards, trumpSuit)');
    }
    echo json_encode($response);
}

// Endpoint to fetch cards for a game
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getCards') {
    if (isset($_GET['gameId'])) {
        $gameId = $_GET['gameId'];
        $cardsQuery = $conn->prepare("SELECT player_id, card FROM cards WHERE game_id = ?");
        $cardsQuery->bind_param('i', $gameId);
        $cardsQuery->execute();
        $cardsResult = $cardsQuery->get_result();
        $cards = $cardsResult->fetch_all(MYSQLI_ASSOC);

        $response = array('cards' => $cards);
        $cardsQuery->close();
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Endpoint to get players for a game
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getPlayers') {
    if (isset($_GET['gameId'])) {
        $gameId = $_GET['gameId'];
        $playersQuery = $conn->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
        $playersQuery->bind_param('i', $gameId);
        $playersQuery->execute();
        $playersResult = $playersQuery->get_result();
        $players = $playersResult->fetch_all(MYSQLI_ASSOC);

        $response = array('players' => $players);
        $playersQuery->close();
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Endpoint to verify a session (for page refresh recovery)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'verifySession') {
    $gameId = isset($_GET['gameId']) ? sanitizeInput($_GET['gameId'], 'int') : null;
    $playerId = isset($_GET['playerId']) ? sanitizeInput($_GET['playerId'], 'int') : null;
    
    if (!$gameId || !$playerId) {
        echo json_encode(['valid' => false, 'error' => 'Missing gameId or playerId']);
        exit();
    }
    
    // Check if the game exists
    $gameQuery = $conn->prepare("SELECT game_id, code FROM games WHERE game_id = ?");
    $gameQuery->bind_param('i', $gameId);
    $gameQuery->execute();
    $gameResult = $gameQuery->get_result();
    $game = $gameResult->fetch_assoc();
    
    if (!$game) {
        echo json_encode(['valid' => false, 'error' => 'Game not found']);
        exit();
    }
    
    // Check if the player exists in this game
    $playerQuery = $conn->prepare("SELECT player_id, name FROM players WHERE player_id = ? AND game_id = ?");
    $playerQuery->bind_param('ii', $playerId, $gameId);
    $playerQuery->execute();
    $playerResult = $playerQuery->get_result();
    $player = $playerResult->fetch_assoc();
    
    if (!$player) {
        echo json_encode(['valid' => false, 'error' => 'Player not found in game']);
        exit();
    }
    
    echo json_encode([
        'valid' => true,
        'gameCode' => $game['code'],
        'playerName' => $player['name']
    ]);
    exit();
}

// Modify the getGameState endpoint to return the Kitty cards to the highest bidder
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getGameState') {
    if (isset($_GET['gameId'])) {
        $gameId = $_GET['gameId'];
        $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $gameStateQuery->bind_param('i', $gameId);
        $gameStateQuery->execute();
        $gameStateResult = $gameStateQuery->get_result();
        $gameState = $gameStateResult->fetch_assoc();

        if ($gameState) {
            $state = json_decode($gameState['state'], true);
            
            // Fetch player names and add to state
            $playersQuery = $conn->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
            $playersQuery->bind_param('i', $gameId);
            $playersQuery->execute();
            $playersResult = $playersQuery->get_result();
            $playerNames = [];
            while ($player = $playersResult->fetch_assoc()) {
                $playerNames[$player['player_id']] = $player['name'];
            }
            $state['playerNames'] = $playerNames;
            
            // Always refresh player hands from database during trick phase
            if (isset($state['phase']) && $state['phase'] === 'trick') {
                foreach (array_keys($playerNames) as $pid) {
                    $cardsQuery = $conn->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
                    $cardsQuery->bind_param('ii', $gameId, $pid);
                    $cardsQuery->execute();
                    $cardsResult = $cardsQuery->get_result();
                    $cards = array_column($cardsResult->fetch_all(MYSQLI_ASSOC), 'card');
                    $state['players'][$pid] = $cards;
                }
            }

            // Fetch the Kitty cards if the player is the highest bidder
            $requestPlayerId = isset($_GET['playerId']) ? $_GET['playerId'] : null;
            if (isset($state['highestBidder']) && $requestPlayerId && $state['highestBidder'] == $requestPlayerId) {
                $kittyQuery = $conn->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id IS NULL");
                $kittyQuery->bind_param('i', $gameId);
                $kittyQuery->execute();
                $kittyResult = $kittyQuery->get_result();
                $kittyCards = $kittyResult->fetch_all(MYSQLI_ASSOC);

                $state['kitty'] = array_column($kittyCards, 'card');
            }

            // Include the game winner if the game is over
            if (isset($state['gameWinner'])) {
                $state['winner'] = $state['gameWinner'];
            }

            echo json_encode($state);
        } else {
            echo json_encode(array('error' => 'Game state not found for the given game ID.'));
        }
    } else {
        echo json_encode(array('error' => 'Missing gameId parameter.'));
    }
}

// Endpoint to play a card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'playCard') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['card'])) {
        $gameId = $_POST['gameId'];
        $playerId = $_POST['playerId'];
        $card = $_POST['card'];

        try {
            playCard($gameId, $playerId, $card);

            // Fetch the updated game state
            $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $query->bind_param('i', $gameId);
            $query->execute();
            $result = $query->get_result();
            $gameState = $result->fetch_assoc();

            if (!$gameState) {
                throw new Exception("Game state not found for game ID: $gameId");
            }

            $state = json_decode($gameState['state'], true);

            // If round summary, include full state so frontend can display immediately
            if (($state['phase'] ?? '') === 'round_summary') {
                // Also fetch player names
                $playersQuery = $conn->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
                $playersQuery->bind_param('i', $gameId);
                $playersQuery->execute();
                $playersResult = $playersQuery->get_result();
                $playerNames = [];
                while ($player = $playersResult->fetch_assoc()) {
                    $playerNames[$player['player_id']] = $player['name'];
                }
                $state['playerNames'] = $playerNames;
                
                $response = array('success' => 'Card played successfully', 'phase' => 'round_summary', 'gameState' => $state);
            } else {
                $response = array('success' => 'Card played successfully', 'phase' => $state['phase'] ?? 'trick');
            }
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing parameters (gameId, playerId, card)');
    }
    echo json_encode($response);
}

// Endpoint to clear trick complete state
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'clearTrickComplete') {
    if (isset($_POST['gameId'])) {
        $gameId = intval($_POST['gameId']);
        
        try {
            $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $query->bind_param('i', $gameId);
            $query->execute();
            $result = $query->get_result();
            $gameState = $result->fetch_assoc();
            
            if (!$gameState) {
                throw new Exception("Game state not found for game ID: $gameId");
            }
            
            $state = json_decode($gameState['state'], true);
            $state['trickComplete'] = false;
            $state['lastCompletedTrick'] = null;
            
            $updatedStateJson = json_encode($state);
            $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $updateQuery->bind_param('si', $updatedStateJson, $gameId);
            $updateQuery->execute();
            
            $response = array('success' => 'Trick complete state cleared');
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Endpoint to send emoji reaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'sendReaction') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['emoji'])) {
        $gameId = intval($_POST['gameId']);
        $playerId = intval($_POST['playerId']);
        $emoji = htmlspecialchars($_POST['emoji']);
        
        // Get player name
        $playerQuery = $conn->prepare("SELECT name FROM players WHERE player_id = ?");
        $playerQuery->bind_param('i', $playerId);
        $playerQuery->execute();
        $playerResult = $playerQuery->get_result();
        $player = $playerResult->fetch_assoc();
        $playerName = $player['name'] ?? 'Unknown';
        
        // Store reaction in game state for other players to see
        $query = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $query->bind_param('i', $gameId);
        $query->execute();
        $result = $query->get_result();
        $gameState = $result->fetch_assoc();
        
        if ($gameState) {
            $state = json_decode($gameState['state'], true);
            $state['lastReaction'] = [
                'playerId' => $playerId,
                'playerName' => $playerName,
                'emoji' => $emoji,
                'timestamp' => time()
            ];
            
            $updatedStateJson = json_encode($state);
            $updateQuery = $conn->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $updateQuery->bind_param('si', $updatedStateJson, $gameId);
            $updateQuery->execute();
            
            // Try to send via WebSocket
            try {
                sendGameStateToWebSocket(['type' => 'reaction', 'playerId' => $playerId, 'playerName' => $playerName, 'emoji' => $emoji]);
            } catch (Exception $e) {
                // WebSocket not available, polling will handle it
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing parameters']);
    }
    exit;
}

// Endpoint to continue to next round after viewing summary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'continueRound') {
    if (isset($_POST['gameId'])) {
        $gameId = intval($_POST['gameId']);
        
        try {
            continueToNextRound($gameId);
            $response = array('success' => 'Next round started');
        } catch (Exception $e) {
            $response = array('error' => $e->getMessage());
        }
    } else {
        $response = array('error' => 'Missing gameId parameter');
    }
    echo json_encode($response);
}

// Function to get overall statistics
function getOverallStatistics() {
    global $conn;

    try {
        // Get all games with their state from game_state table
        $gamesQuery = "SELECT g.game_id, gs.state FROM games g LEFT JOIN game_state gs ON g.game_id = gs.game_id";
        $gamesResult = $conn->query($gamesQuery);
        
        if (!$gamesResult) {
            throw new Exception('Failed to fetch games: ' . $conn->error);
        }
        
        $winCounts = [];
        $totalCompleted = 0;
        
        while ($game = $gamesResult->fetch_assoc()) {
            if (!$game['state']) continue;
            
            $state = json_decode($game['state'], true);
            if (isset($state['gameWinner']) || isset($state['winnerName'])) {
                $totalCompleted++;
                
                // Get winner name
                $winnerName = $state['winnerName'] ?? null;
                if (!$winnerName && isset($state['gameWinner'])) {
                    // Look up player name
                    $playerQuery = $conn->prepare("SELECT name FROM players WHERE player_id = ?");
                    $playerQuery->bind_param('i', $state['gameWinner']);
                    $playerQuery->execute();
                    $playerResult = $playerQuery->get_result();
                    $player = $playerResult->fetch_assoc();
                    $winnerName = $player['name'] ?? 'Unknown';
                }
                
                if ($winnerName) {
                    $winCounts[$winnerName] = ($winCounts[$winnerName] ?? 0) + 1;
                }
            }
        }
        
        // Find top player
        $topPlayer = 'N/A';
        $topWins = 0;
        foreach ($winCounts as $name => $wins) {
            if ($wins > $topWins) {
                $topWins = $wins;
                $topPlayer = $name . " ({$wins} wins)";
            }
        }
        
        // Get total games (including in-progress)
        $totalGamesQuery = "SELECT COUNT(*) AS totalGames FROM games";
        $totalGamesResult = $conn->query($totalGamesQuery);
        $totalGames = $totalGamesResult->fetch_assoc();

        return [
            'topPlayer' => $topPlayer,
            'totalGames' => $totalGames['totalGames'] ?? 0,
            'completedGames' => $totalCompleted
        ];
    } catch (Exception $e) {
        logError($e->getMessage());
        throw $e;
    }
}

// Function to get game history
function getGameHistory() {
    global $conn;

    try {
        // Get all games with state from game_state table
        $gamesQuery = "SELECT g.game_id, g.code, gs.state FROM games g LEFT JOIN game_state gs ON g.game_id = gs.game_id ORDER BY g.game_id DESC";
        $gamesResult = $conn->query($gamesQuery);
        
        if (!$gamesResult) {
            throw new Exception('Failed to fetch game history: ' . $conn->error);
        }
        
        $games = [];
        while ($game = $gamesResult->fetch_assoc()) {
            $state = $game['state'] ? json_decode($game['state'], true) : [];
            
            // Get winner name
            $winnerName = $state['winnerName'] ?? null;
            if (!$winnerName && isset($state['gameWinner'])) {
                $playerQuery = $conn->prepare("SELECT name FROM players WHERE player_id = ?");
                $playerQuery->bind_param('i', $state['gameWinner']);
                $playerQuery->execute();
                $playerResult = $playerQuery->get_result();
                $player = $playerResult->fetch_assoc();
                $winnerName = $player['name'] ?? null;
            }
            
            $games[] = [
                'game_id' => $game['game_id'],
                'code' => $game['code'],
                'winner' => $winnerName,
                'status' => isset($state['gameWinner']) ? 'Completed' : ($state['phase'] ?? 'In Progress'),
                'rounds' => count($state['roundHistory'] ?? [])
            ];
        }

        return ['games' => $games];
    } catch (Exception $e) {
        logError($e->getMessage());
        throw $e;
    }
}

// Function to get game details
function getGameDetails($gameId) {
    global $conn;

    try {
        // Query to get the game state
        $gameStateQuery = $conn->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $gameStateQuery->bind_param('i', $gameId);
        $gameStateQuery->execute();
        $gameStateResult = $gameStateQuery->get_result();
        $gameState = $gameStateResult->fetch_assoc();

        if (!$gameState) {
            throw new Exception("Game state not found for game ID: $gameId");
        }

        $state = json_decode($gameState['state'], true);

        // Fetch player names
        $playersQuery = $conn->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
        $playersQuery->bind_param('i', $gameId);
        $playersQuery->execute();
        $playersResult = $playersQuery->get_result();
        $playerNames = [];
        while ($player = $playersResult->fetch_assoc()) {
            $playerNames[$player['player_id']] = $player['name'];
        }

        // Extract round-by-round details from roundHistory
        $rounds = [];
        if (isset($state['roundHistory'])) {
            foreach ($state['roundHistory'] as $roundNumber => $round) {
                $bidWinnerId = $round['bidWinner'];
                $bidWinnerName = $playerNames[$bidWinnerId] ?? 'Player ' . $bidWinnerId;
                
                // Get bid winner's points for this round
                $bidWinnerPoints = $round['roundPoints'][$bidWinnerId] ?? 0;
                
                // Format trump suit
                $trumpSuit = $round['trumpSuit'] ?? null;
                $trumpSymbols = ['H' => '♥', 'D' => '♦', 'C' => '♣', 'S' => '♠'];
                $trumpDisplay = $trumpSuit ? ($trumpSymbols[$trumpSuit] ?? $trumpSuit) : 'N/A';
                
                $rounds[] = [
                    'roundNumber' => $roundNumber + 1,
                    'bidWinner' => $bidWinnerName,
                    'bid' => $round['bid'] ?? null,
                    'bidMade' => $round['bidMade'] ?? null,
                    'forcedBid' => $round['forcedBid'] ?? false,
                    'trumpSuit' => $trumpDisplay,
                    'bidWinnerPoints' => $bidWinnerPoints,
                    'roundPoints' => $round['roundPoints'] ?? [],
                    'finalScores' => $round['finalScores'] ?? []
                ];
            }
        }
        
        // Include game winner if exists
        $gameWinner = null;
        if (isset($state['gameWinner'])) {
            $gameWinner = $playerNames[$state['gameWinner']] ?? 'Player ' . $state['gameWinner'];
        }

        return [
            'rounds' => $rounds,
            'gameWinner' => $gameWinner,
            'finalScores' => $state['finalScores'] ?? [],
            'playerNames' => $playerNames
        ];
    } catch (Exception $e) {
        logError($e->getMessage());
        throw $e; // Re-throw the exception to be handled by the caller
    }
}

// Endpoint to get overall statistics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getOverallStatistics') {
    try {
        $statistics = getOverallStatistics();
        echo json_encode($statistics);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Endpoint to get game history
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getGameHistory') {
    try {
        $history = getGameHistory();
        echo json_encode($history);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Endpoint to get game details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['endpoint'] === 'getGameDetails') {
    if (isset($_GET['gameId'])) {
        $gameId = $_GET['gameId'];
        try {
            $details = getGameDetails($gameId);
            echo json_encode($details);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter.']);
    }
}

// Endpoint to seed sample statistics data for demo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['endpoint'] === 'seedSampleData') {
    try {
        // Create sample completed games
        $samplePlayers = ['Alice', 'Bob', 'Charlie', 'Diana'];
        $sampleGames = [
            ['winner' => 'Alice', 'date' => '2025-11-20 14:30:00'],
            ['winner' => 'Bob', 'date' => '2025-11-21 16:00:00'],
            ['winner' => 'Alice', 'date' => '2025-11-22 19:00:00'],
            ['winner' => 'Charlie', 'date' => '2025-11-24 15:30:00'],
            ['winner' => 'Alice', 'date' => '2025-11-25 20:00:00'],
        ];
        
        foreach ($sampleGames as $game) {
            // Create game
            $gameCode = generateGameCode();
            $insertGameQuery = $conn->prepare("INSERT INTO games (code, state) VALUES (?, ?)");
            
            // Create game state with winner
            $winnerIndex = array_search($game['winner'], $samplePlayers);
            $gameState = json_encode([
                'gameWinner' => $winnerIndex + 1000, // Placeholder player IDs
                'phase' => 'completed',
                'finalScores' => [
                    ($winnerIndex + 1000) => 110,
                    (($winnerIndex + 1) % 4 + 1000) => 75,
                    (($winnerIndex + 2) % 4 + 1000) => 60,
                    (($winnerIndex + 3) % 4 + 1000) => 45
                ],
                'roundHistory' => [
                    ['roundNumber' => 1, 'bidWinner' => $winnerIndex + 1000, 'bid' => 20],
                    ['roundNumber' => 2, 'bidWinner' => ($winnerIndex + 1) % 4 + 1000, 'bid' => 15],
                    ['roundNumber' => 3, 'bidWinner' => $winnerIndex + 1000, 'bid' => 25],
                ]
            ]);
            
            $insertGameQuery->bind_param('ss', $gameCode, $gameState);
            $insertGameQuery->execute();
            $gameId = $conn->insert_id;
            
            // Add players to this game
            foreach ($samplePlayers as $index => $playerName) {
                $playerId = $index + 1000 + ($gameId * 10); // Unique player IDs per game
                $insertPlayerQuery = $conn->prepare("INSERT INTO players (game_id, name) VALUES (?, ?)");
                $insertPlayerQuery->bind_param('is', $gameId, $playerName);
                $insertPlayerQuery->execute();
                
                // Update game state with actual player ID if this is the winner
                if ($playerName === $game['winner']) {
                    $actualPlayerId = $conn->insert_id;
                    $gameState = json_decode($gameState, true);
                    $gameState['gameWinner'] = $actualPlayerId;
                    $gameState['winnerName'] = $playerName;
                    $updatedState = json_encode($gameState);
                    
                    $updateQuery = $conn->prepare("UPDATE games SET state = ? WHERE game_id = ?");
                    $updateQuery->bind_param('si', $updatedState, $gameId);
                    $updateQuery->execute();
                }
            }
            
            // Create game_state entry
            $insertStateQuery = $conn->prepare("INSERT INTO game_state (game_id, state) VALUES (?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state)");
            $insertStateQuery->bind_param('is', $gameId, $gameState);
            $insertStateQuery->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Sample data seeded successfully', 'gamesCreated' => count($sampleGames)]);
    } catch (Exception $e) {
        logError($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// Close the database connection
$conn->close();