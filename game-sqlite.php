<?php
/**
 * 110 Card Game - Backend API (SQLite Version)
 * 
 * This version uses SQLite instead of MySQL for serverless/on-demand hosting.
 * The database is stored as a file (game.db) in the data/ directory.
 */

header('Content-Type: application/json');

// CORS headers - allow requests from S3 static site
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration
$config = require __DIR__ . '/config.php';

// HTTPS check - disabled for initial deployment
// TODO: Enable HTTPS with a domain and Let's Encrypt certificate
// if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1']);
//     $isDevelopment = ($config['app']['env'] ?? 'development') === 'development';
//     if (!$isLocalhost && !$isDevelopment) {
//         echo json_encode(['error' => 'HTTPS is required for secure communication.']);
//         exit();
//     }
// }

// SQLite database path
$dbPath = __DIR__ . '/data/game.db';
$dbDir = dirname($dbPath);

// Create data directory if it doesn't exist
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Establish SQLite connection
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable foreign keys
    $db->exec('PRAGMA foreign_keys = ON');
    
    // Initialize database tables
    initializeDatabase($db);
} catch (PDOException $e) {
    logError($e->getMessage());
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Function to initialize database tables
function initializeDatabase($db) {
    // Create games table
    $db->exec("
        CREATE TABLE IF NOT EXISTS games (
            game_id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(6) UNIQUE,
            state TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create players table
    $db->exec("
        CREATE TABLE IF NOT EXISTS players (
            player_id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER,
            name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
        )
    ");
    
    // Create cards table
    $db->exec("
        CREATE TABLE IF NOT EXISTS cards (
            card_id INTEGER PRIMARY KEY AUTOINCREMENT,
            game_id INTEGER,
            player_id INTEGER,
            card VARCHAR(10),
            FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
            FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE
        )
    ");
    
    // Create game_state table
    $db->exec("
        CREATE TABLE IF NOT EXISTS game_state (
            game_id INTEGER PRIMARY KEY,
            state TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
        )
    ");
}

// Function to log errors to a file
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents(__DIR__ . '/error_log.txt', $logMessage, FILE_APPEND);
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

    $deck[] = 'Joker';
    shuffle($deck);
    return $deck;
}

// Function to check if a card is a reneging card
function isRenegingCard($card, $trumpSuit) {
    if ($card === 'Joker') return true;
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    if ($cardSuit === $trumpSuit && $cardRank === '5') return true;
    if ($cardSuit === $trumpSuit && $cardRank === 'J') return true;
    if ($cardSuit === 'H' && $cardRank === 'A') return true;
    
    return false;
}

// Function to get the rank of a reneging card
function getRenegingCardRank($card, $trumpSuit) {
    $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
    $cardRank = ($card === 'Joker') ? null : substr($card, 1);
    
    if ($cardSuit === $trumpSuit && $cardRank === '5') return 4;
    if ($cardSuit === $trumpSuit && $cardRank === 'J') return 3;
    if ($card === 'Joker') return 2;
    if ($cardSuit === 'H' && $cardRank === 'A') return 1;
    
    return 0;
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

// Function to check if player must follow suit
// Returns TRUE if player has non-reneging cards of lead suit, or reneging cards that are forced out
// Returns FALSE if player can play any card (no lead suit cards, or only reneging cards that can be held back)
function mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick) {
    $highestRenegingPlayed = getHighestRenegingCardPlayed($currentTrick, $trumpSuit);
    
    foreach ($playerCards as $card) {
        // Joker has no suit - skip
        if ($card === 'Joker') continue;
        
        $cardSuit = substr($card, 0, 1);
        
        // Only check cards that match the lead suit
        if ($cardSuit === $leadSuit) {
            if (isRenegingCard($card, $trumpSuit)) {
                // This is a reneging card (5, J, Joker, A♥)
                $cardRenegingRank = getRenegingCardRank($card, $trumpSuit);
                // Reneging card is FORCED OUT only if a HIGHER reneging card was played
                // Rank 4 = 5 of trump (highest, can never be forced)
                // Rank 3 = J of trump (forced by 5)
                // Rank 2 = Joker (forced by 5 or J)
                // Rank 1 = A♥ (forced by 5, J, or Joker)
                if ($highestRenegingPlayed > $cardRenegingRank) {
                    return true; // Must play this card - it's been forced out
                }
                // Otherwise, this reneging card can be held back - continue checking
            } else {
                // Non-reneging card of lead suit - MUST follow suit
                return true;
            }
        }
    }
    
    // No cards of lead suit that must be played
    // Player can play ANY card (trump or non-trump)
    return false;
}

// Function to get max swap cards based on player count
function getMaxSwapCards($numPlayers) {
    if ($numPlayers >= 8) return 1;
    if ($numPlayers >= 7) return 2;
    return 3; // 2-6 players
}

// Function to deal cards to players and the Kitty
function dealCards($gameId) {
    global $db;

    $stmt = $db->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll();

    if (empty($players)) {
        throw new Exception("No players found for game ID: $gameId");
    }

    $deck = generateDeck();
    $cardsPerPlayer = 5;
    $kitty = array_splice($deck, 0, $cardsPerPlayer);
    
    // Fetch existing state to preserve finalScores, dealer, roundNumber, etc.
    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $existingStateRow = $stmt->fetch();
    
    $gameState = $existingStateRow ? json_decode($existingStateRow['state'], true) : [];
    
    $gameState['kitty'] = $kitty;
    $gameState['players'] = [];
    $gameState['currentTurn'] = null;

    foreach ($players as $index => $player) {
        $playerId = $player['player_id'];
        $playerCards = array_splice($deck, 0, $cardsPerPlayer);
        $gameState['players'][$playerId] = $playerCards;

        if ($index === 0) {
            $gameState['currentTurn'] = $playerId;
        }

        foreach ($playerCards as $card) {
            $stmt = $db->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, ?, ?)");
            $stmt->execute([$gameId, $playerId, $card]);
        }
    }

    foreach ($kitty as $card) {
        $stmt = $db->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, NULL, ?)");
        $stmt->execute([$gameId, $card]);
    }
    
    // Store remaining deck for card swapping during kitty phase
    $gameState['remainingDeck'] = $deck;
    $gameState['swapComplete'] = []; // Track which players have completed swapping
    $gameState['maxSwapCards'] = getMaxSwapCards(count($players));

    $gameStateJson = json_encode($gameState);
    $stmt = $db->prepare("INSERT OR REPLACE INTO game_state (game_id, state) VALUES (?, ?)");
    $stmt->execute([$gameId, $gameStateJson]);
}

// Function to start the bidding phase
function startBidding($gameId) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $existingState = $stmt->fetch();
    
    $state = $existingState ? json_decode($existingState['state'], true) : [];

    $stmt = $db->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll();

    if (empty($players)) {
        throw new Exception("No players found for game ID: $gameId");
    }

    $playerIds = array_column($players, 'player_id');
    $dealerIndex = $state['dealer'] ?? 0;
    $firstBidderIndex = ($dealerIndex + 1) % count($playerIds);
    $firstBidder = $playerIds[$firstBidderIndex];

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
    
    if (!isset($state['roundNumber'])) {
        $state['roundNumber'] = 1;
    }
    if (!isset($state['dealer'])) {
        $state['dealer'] = 0;
    }
    if (!isset($state['finalScores'])) {
        $state['finalScores'] = [];
        foreach ($playerIds as $pid) {
            $state['finalScores'][$pid] = 0;
        }
    }

    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);
}

// Function to process a bid
function processBid($gameId, $playerId, $bidAmount) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    if ($state['currentBidder'] != $playerId) {
        throw new Exception("It's not your turn to bid.");
    }

    $playerIds = array_keys($state['players']);
    $dealerIndex = $state['dealer'] ?? 0;
    $dealerPlayerId = $playerIds[$dealerIndex];
    $isDealer = ($playerId == $dealerPlayerId);
    
    if ($bidAmount == 0) {
        $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
        $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
        $noBidsYet = ($state['currentBid'] ?? 0) == 0;
        
        if ($isDealer && $allOthersPassed && $noBidsYet) {
            throw new Exception("Everyone passed - dealer must bid at least 15.");
        }
        
        $state['forcedDealerBid'] = false;
        
        if (!in_array($playerId, $state['passedPlayers'])) {
            $state['passedPlayers'][] = $playerId;
        }
    } else {
        $dealerMatched = false;
        if ($isDealer && $bidAmount == $state['currentBid'] && $state['currentBid'] > 0) {
            $dealerMatched = true;
        } elseif ($bidAmount <= $state['currentBid']) {
            throw new Exception("Bid must be higher than the current bid: {$state['currentBid']}");
        }
        
        if ($bidAmount < 15 || $bidAmount > 30 || $bidAmount % 5 !== 0) {
            throw new Exception("Invalid bid amount. Bid must be 15, 20, 25, or 30.");
        }
        
        $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
        $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
        $noBidsYet = ($state['currentBid'] ?? 0) == 0;
        $state['forcedDealerBid'] = ($isDealer && $allOthersPassed && $noBidsYet);

        $state['currentBid'] = $bidAmount;
        $state['highestBidder'] = $playerId;
        
        if ($dealerMatched) {
            $state['biddingOver'] = true;
            $state['phase'] = 'kitty';
            $state['currentBidder'] = $playerId;
            $state['dealerCanMatch'] = false;
            
            $updatedStateJson = json_encode($state);
            $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $stmt->execute([$updatedStateJson, $gameId]);
            
            sendGameStateToWebSocket($state);
            return;
        }
        
        $state['validBids'] = array_values(array_filter([15, 20, 25, 30], function($b) use ($bidAmount) {
            return $b > $bidAmount;
        }));
    }

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

    $activeBidders = array_diff($playerIds, $state['passedPlayers']);
    
    if (count($activeBidders) === 1 && $state['highestBidder'] !== null) {
        $state['biddingOver'] = true;
        $state['phase'] = 'kitty';
        $state['currentBidder'] = $state['highestBidder'];
    } elseif (count($activeBidders) === 0) {
        throw new Exception("All players passed. Game needs to be redealt.");
    } elseif ($nextBidder === $state['highestBidder']) {
        $state['biddingOver'] = true;
        $state['phase'] = 'kitty';
        $state['currentBidder'] = $state['highestBidder'];
    } else {
        $state['currentBidder'] = $nextBidder;
        
        $nextBidderIsDealer = ($nextBidder == $dealerPlayerId);
        if ($nextBidderIsDealer && $state['currentBid'] > 0) {
            $state['dealerCanMatch'] = true;
            if (!in_array($state['currentBid'], $state['validBids'])) {
                array_unshift($state['validBids'], $state['currentBid']);
            }
        } else {
            $state['dealerCanMatch'] = false;
        }
        
        if ($nextBidderIsDealer) {
            $otherPlayers = array_filter($playerIds, fn($p) => $p != $dealerPlayerId);
            $allOthersPassed = count(array_intersect($otherPlayers, $state['passedPlayers'])) == count($otherPlayers);
            $noBidsYet = ($state['currentBid'] ?? 0) == 0;
            $state['dealerMustBid'] = ($allOthersPassed && $noBidsYet);
        } else {
            $state['dealerMustBid'] = false;
        }
    }

    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);

    sendGameStateToWebSocket($state);
}

// Helper function to check if all players are ready to start trick phase
function checkAndStartTrickPhase($gameId, &$state) {
    global $db;
    
    // Get all player IDs
    $stmt = $db->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll();
    $playerIds = array_column($players, 'player_id');
    
    // Check if bid winner is ready
    if (!($state['bidWinnerReady'] ?? false)) {
        return false;
    }
    
    // Check if all non-bid-winners have completed their swaps
    $bidWinnerId = $state['highestBidder'];
    $swapComplete = $state['swapComplete'] ?? [];
    
    foreach ($playerIds as $pid) {
        if ($pid != $bidWinnerId && !in_array($pid, $swapComplete)) {
            return false; // Someone hasn't swapped yet
        }
    }
    
    // Everyone is ready - transition to trick phase!
    $state['phase'] = 'trick';
    
    $bidWinnerIndex = array_search($bidWinnerId, $playerIds);
    $firstLeaderIndex = ($bidWinnerIndex + 1) % count($playerIds);
    $state['currentTurn'] = $playerIds[$firstLeaderIndex];
    
    $state['currentTrick'] = [];
    $state['tricksPlayed'] = 0;
    $state['scores'] = [];
    
    // Refresh players' hands from database
    foreach ($playerIds as $pid) {
        $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
        $stmt->execute([$gameId, $pid]);
        $cards = array_column($stmt->fetchAll(), 'card');
        $state['players'][$pid] = $cards;
    }
    
    // Clean up kitty phase state
    unset($state['remainingDeck']);
    unset($state['bidWinnerReady']);
    
    return true;
}

// Function to select the Kitty and Trump suit
function selectKittyAndTrump($gameId, $playerId, $selectedCards, $trumpSuit) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    if ($state['highestBidder'] != $playerId) {
        throw new Exception("Player is not the highest bidder.");
    }
    
    if (count($selectedCards) !== 5) {
        throw new Exception("You must select exactly 5 cards to keep.");
    }

    // Remove the Kitty cards
    $stmt = $db->prepare("DELETE FROM cards WHERE game_id = ? AND player_id IS NULL");
    $stmt->execute([$gameId]);

    // Remove the player's current hand
    $stmt = $db->prepare("DELETE FROM cards WHERE game_id = ? AND player_id = ?");
    $stmt->execute([$gameId, $playerId]);

    // Insert the selected cards
    foreach ($selectedCards as $card) {
        $stmt = $db->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, ?, ?)");
        $stmt->execute([$gameId, $playerId, $card]);
    }

    // Convert trump suit
    $trumpSuitLetter = $trumpSuit;
    if (strlen($trumpSuit) > 1) {
        $suitMap = ['Hearts' => 'H', 'Diamonds' => 'D', 'Clubs' => 'C', 'Spades' => 'S'];
        $trumpSuitLetter = $suitMap[$trumpSuit] ?? $trumpSuit[0];
    }

    $state['trumpSuit'] = $trumpSuitLetter;
    $state['bidWinnerReady'] = true; // Mark bid winner as ready
    
    // Check if we can start trick phase (all swaps must be complete)
    checkAndStartTrickPhase($gameId, $state);
    
    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);

    sendGameStateToWebSocket($state);
}

// Function to swap cards for non-bid-winners during kitty phase
function swapCards($gameId, $playerId, $cardsToDiscard) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    // Verify we're in kitty phase
    if (($state['phase'] ?? '') !== 'kitty') {
        throw new Exception("Card swapping is only allowed during the kitty phase.");
    }

    // Verify player is not the bid winner
    if ($state['highestBidder'] == $playerId) {
        throw new Exception("Bid winner cannot use card swap - use kitty selection instead.");
    }

    // Check if player already swapped
    if (in_array($playerId, $state['swapComplete'] ?? [])) {
        throw new Exception("You have already completed your card swap.");
    }

    $maxSwap = $state['maxSwapCards'] ?? 3;
    $numToSwap = count($cardsToDiscard);

    if ($numToSwap > $maxSwap) {
        throw new Exception("You can only swap up to $maxSwap cards.");
    }

    // Get player's current hand from database
    $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
    $stmt->execute([$gameId, $playerId]);
    $playerCards = array_column($stmt->fetchAll(), 'card');

    // Verify player has all the cards they want to discard
    foreach ($cardsToDiscard as $card) {
        if (!in_array($card, $playerCards)) {
            throw new Exception("You don't have card: $card");
        }
    }

    // Check we have enough cards in the remaining deck
    $remainingDeck = $state['remainingDeck'] ?? [];
    if (count($remainingDeck) < $numToSwap) {
        throw new Exception("Not enough cards in deck to complete swap.");
    }

    // Perform the swap
    if ($numToSwap > 0) {
        // Remove discarded cards from database
        foreach ($cardsToDiscard as $card) {
            $stmt = $db->prepare("DELETE FROM cards WHERE rowid = (SELECT rowid FROM cards WHERE game_id = ? AND player_id = ? AND card = ? LIMIT 1)");
            $stmt->execute([$gameId, $playerId, $card]);
        }

        // Draw new cards from remaining deck
        $newCards = array_splice($state['remainingDeck'], 0, $numToSwap);
        
        // Add new cards to database
        foreach ($newCards as $card) {
            $stmt = $db->prepare("INSERT INTO cards (game_id, player_id, card) VALUES (?, ?, ?)");
            $stmt->execute([$gameId, $playerId, $card]);
        }

        // Update player's hand in state
        $playerCards = array_diff($playerCards, $cardsToDiscard);
        $playerCards = array_merge(array_values($playerCards), $newCards);
        $state['players'][$playerId] = array_values($playerCards);
    }

    // Mark player as having completed swap (store count of cards swapped)
    if (!isset($state['swapComplete'])) {
        $state['swapComplete'] = [];
    }
    $state['swapComplete'][] = $playerId;
    
    // Track how many cards each player swapped
    if (!isset($state['swapCounts'])) {
        $state['swapCounts'] = [];
    }
    $state['swapCounts'][$playerId] = $numToSwap;
    
    // Check if we can start trick phase (bid winner must also be ready)
    $transitioned = checkAndStartTrickPhase($gameId, $state);

    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);

    sendGameStateToWebSocket($state);
    
    // Get updated hand from database if we transitioned
    $finalHand = $state['players'][$playerId] ?? [];
    if ($transitioned) {
        $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
        $stmt->execute([$gameId, $playerId]);
        $finalHand = array_column($stmt->fetchAll(), 'card');
    }
    
    return [
        'success' => true,
        'cardsSwapped' => $numToSwap,
        'newHand' => $finalHand,
        'phaseChanged' => $transitioned
    ];
}

// Function to check if a card is effectively trump
function isCardTrump($card, $trumpSuit) {
    if ($card === 'Joker') return true;
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    if ($cardSuit === 'H' && $cardRank === 'A') return true;
    
    return $cardSuit === $trumpSuit;
}

// Function to get the rank value of a card
function getCardRankValue($card, $trumpSuit, $leadSuit) {
    if ($card === 'Joker') {
        return 1013; // Joker is 3rd highest trump (after 5 and J of trump)
    }
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    $isRedSuit = ($cardSuit === 'H' || $cardSuit === 'D');
    
    if (isCardTrump($card, $trumpSuit)) {
        // Trump ranking: 5 > J > Joker > Ace of Hearts > A > K > Q > then number cards
        if ($cardSuit === $trumpSuit && $cardRank === '5') return 1015;
        if ($cardSuit === $trumpSuit && $cardRank === 'J') return 1014;
        // Joker handled above (1013)
        if ($cardSuit === 'H' && $cardRank === 'A') return 1012;
        if ($cardSuit === $trumpSuit && $cardRank === 'A') return 1011;
        if ($cardSuit === $trumpSuit && $cardRank === 'K') return 1010;
        if ($cardSuit === $trumpSuit && $cardRank === 'Q') return 1009;
        
        // Number cards differ by suit color (5 is always top trump, not in this list)
        if ($isRedSuit) {
            // Red trump: 10 > 9 > 8 > 7 > 6 > 4 > 3 > 2
            $trumpRanks = ['10' => 1008, '9' => 1007, '8' => 1006, '7' => 1005, 
                           '6' => 1004, '4' => 1003, '3' => 1002, '2' => 1001];
        } else {
            // Black trump: 2 > 3 > 4 > 6 > 7 > 8 > 9 > 10
            $trumpRanks = ['2' => 1008, '3' => 1007, '4' => 1006, '6' => 1005, 
                           '7' => 1004, '8' => 1003, '9' => 1002, '10' => 1001];
        }
        return $trumpRanks[$cardRank] ?? 1000;
    }
    
    if ($cardSuit === $leadSuit) {
        // Non-trump ranking differs by suit color
        if ($isRedSuit) {
            // Red non-trump: K > Q > J > 10 > 9 > 8 > 7 > 6 > 5 > 4 > 3 > 2 > A (Ace = 1, lowest)
            $leadRanks = ['K' => 513, 'Q' => 512, 'J' => 511, '10' => 510, 
                          '9' => 509, '8' => 508, '7' => 507, '6' => 506, 
                          '5' => 505, '4' => 504, '3' => 503, '2' => 502, 'A' => 501];
        } else {
            // Black non-trump: K > Q > J > A > 2 > 3 > 4 > 5 > 6 > 7 > 8 > 9 > 10
            $leadRanks = ['K' => 513, 'Q' => 512, 'J' => 511, 'A' => 510, 
                          '2' => 509, '3' => 508, '4' => 507, '5' => 506, 
                          '6' => 505, '7' => 504, '8' => 503, '9' => 502, '10' => 501];
        }
        return $leadRanks[$cardRank] ?? 500;
    }
    
    return 0;
}

// Function to play a card
function playCard($gameId, $playerId, $card) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    $state['trickComplete'] = false;
    $state['lastCompletedTrick'] = null;

    if ($state['currentTurn'] != $playerId) {
        throw new Exception("It's not your turn to play.");
    }

    $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
    $stmt->execute([$gameId, $playerId]);
    $playerCards = array_column($stmt->fetchAll(), 'card');

    if (!in_array($card, $playerCards)) {
        throw new Exception("You don't have this card in your hand.");
    }

    // Validate following suit
    // RENEGING RULES: The top 4 trumps (5, J, Joker, A♥) can choose not to follow suit
    // UNLESS a higher-ranked reneging card has been played in the same trick.
    // If your only cards of the lead suit are reneging cards that can be held back,
    // you may play ANY card (trump OR non-trump).
    if (!empty($state['currentTrick'])) {
        $leadCard = $state['currentTrick'][0]['card'];
        $trumpSuit = $state['trumpSuit'] ?? null;
        
        // Handle Joker lead - it's trump, so treat as trump led
        $leadSuit = ($leadCard === 'Joker') ? $trumpSuit : substr($leadCard, 0, 1);
        
        // Handle Ace of Hearts lead when not hearts trump - it's still trump
        if ($leadCard === 'HA' && $trumpSuit !== 'H') {
            $leadSuit = $trumpSuit; // Treat as trump led
        }
        
        $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
        
        // Check if the card being played is a trump or reneging card
        $isPlayingTrump = ($cardSuit === $trumpSuit) || ($card === 'Joker') || isRenegingCard($card, $trumpSuit);
        
        // If not following suit and not playing trump, check if we MUST follow suit
        if ($cardSuit !== $leadSuit && !$isPlayingTrump) {
            if (mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $state['currentTrick'])) {
                // Build helpful error message
                $nonRenegingCards = [];
                foreach ($playerCards as $c) {
                    if ($c !== 'Joker' && substr($c, 0, 1) === $leadSuit && !isRenegingCard($c, $trumpSuit)) {
                        $nonRenegingCards[] = $c;
                    }
                }
                $suitNames = ['H' => 'Hearts', 'D' => 'Diamonds', 'C' => 'Clubs', 'S' => 'Spades'];
                $suitName = $suitNames[$leadSuit] ?? $leadSuit;
                throw new Exception("You must follow suit with {$suitName}. You have: " . implode(', ', $nonRenegingCards));
            }
        }
    }

    // Remove the card from database (SQLite doesn't support LIMIT in DELETE, use subquery)
    $stmt = $db->prepare("DELETE FROM cards WHERE rowid = (SELECT rowid FROM cards WHERE game_id = ? AND player_id = ? AND card = ? LIMIT 1)");
    $stmt->execute([$gameId, $playerId, $card]);
    
    // Remove from state
    $state['players'][$playerId] = array_values(array_filter($state['players'][$playerId], function($c) use ($card) {
        return $c !== $card;
    }));

    $state['currentTrick'][] = ['playerId' => $playerId, 'card' => $card];

    // Track highest card
    $trumpSuit = $state['trumpSuit'] ?? 'H';
    $cardRank = getCardRankValue($card, $trumpSuit, $trumpSuit);
    
    if (!isset($state['highestCardInRound']) || $cardRank > ($state['highestCardRank'] ?? 0)) {
        $state['highestCardInRound'] = $card;
        $state['highestCardPlayer'] = $playerId;
        $state['highestCardRank'] = $cardRank;
        $state['highestCardTrick'] = ($state['tricksPlayed'] ?? 0) + 1;
    }

    $playerIds = array_keys($state['players']);
    $numPlayers = count($playerIds);
    
    if (count($state['currentTrick']) >= $numPlayers) {
        // Save current state
        $updatedStateJson = json_encode($state);
        $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
        $stmt->execute([$updatedStateJson, $gameId]);
        
        // Determine trick winner
        determineTrickWinner($gameId);
        
        // Fetch updated state
        $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $updatedGameState = $stmt->fetch();
        $state = json_decode($updatedGameState['state'], true);
        
        // Check if round is complete
        if (($state['tricksPlayed'] ?? 0) >= 5) {
            scoreRound($gameId);
            
            // Fetch updated state after scoring
            $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $stmt->execute([$gameId]);
            $updatedGameState = $stmt->fetch();
            $state = json_decode($updatedGameState['state'], true);
        }
    } else {
        $currentIndex = array_search($playerId, $playerIds);
        $nextIndex = ($currentIndex + 1) % $numPlayers;
        $state['currentTurn'] = $playerIds[$nextIndex];

        $updatedStateJson = json_encode($state);
        $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
        $stmt->execute([$updatedStateJson, $gameId]);
    }

    sendGameStateToWebSocket($state);
}

// Function to determine the winner of the trick
function determineTrickWinner($gameId) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    if (empty($state['currentTrick'])) {
        throw new Exception("No cards in current trick");
    }

    $trumpSuit = $state['trumpSuit'];
    $leadCard = $state['currentTrick'][0]['card'];
    
    if (isCardTrump($leadCard, $trumpSuit)) {
        $leadSuit = $trumpSuit;
    } else {
        $leadSuit = substr($leadCard, 0, 1);
    }
    
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
    $state['lastCompletedTrick'] = $state['currentTrick'];
    $state['trickComplete'] = true;
    
    if (!isset($state['trickWinners'])) {
        $state['trickWinners'] = [];
    }
    $trickNumber = ($state['tricksPlayed'] ?? 0) + 1;
    $state['trickWinners'][] = [
        'trick' => $trickNumber,
        'winner' => $winningCard['playerId'],
        'winningCard' => $winningCard['card']
    ];

    $trickWinnerId = $winningCard['playerId'];
    if (!isset($state['scores'][$trickWinnerId])) {
        $state['scores'][$trickWinnerId] = 0;
    }
    $state['scores'][$trickWinnerId] += 1;

    $state['currentTrick'] = [];
    $state['currentTurn'] = $winningCard['playerId'];
    $state['tricksPlayed'] = ($state['tricksPlayed'] ?? 0) + 1;

    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);
    
    return $winningCard['playerId'];
}

// Function to send game state updates to the WebSocket server
function sendGameStateToWebSocket($gameState) {
    global $config;
    $wsHost = $config['websocket']['host'] ?? 'localhost';
    $wsPort = $config['websocket']['port'] ?? '8081';
    
    $socket = @stream_socket_client("tcp://{$wsHost}:{$wsPort}", $errno, $errorMessage, 1);

    if (!$socket) {
        return;
    }

    $message = json_encode([
        'type' => 'gameStateUpdate',
        'payload' => $gameState
    ]);

    @fwrite($socket, $message);
    @fclose($socket);
}

// Function to score a round
function scoreRound($gameId) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    if (!isset($state['finalScores'])) {
        $state['finalScores'] = [];
    }

    $bidWinner = $state['highestBidder'] ?? null;
    $winningBid = $state['currentBid'] ?? 0;
    $playerIds = array_keys($state['players']);
    
    $highestCardPlayer = $state['highestCardPlayer'] ?? null;
    $highestCard = $state['highestCardInRound'] ?? null;
    $bidWinnerStartScore = $state['finalScores'][$bidWinner] ?? 0;
    $bidWinnerForfeitsBonus = ($bidWinnerStartScore >= 85) && ($highestCardPlayer == $bidWinner);
    
    $bidWinnerTricks = 0;
    foreach ($state['scores'] as $pid => $tricks) {
        if ($pid == $bidWinner) {
            $bidWinnerTricks = $tricks;
            break;
        }
    }
    $bidWinnerTrickPoints = $bidWinnerTricks * 5;
    
    $bidWinnerHasBonus = false;
    if ($highestCardPlayer !== null && !$bidWinnerForfeitsBonus) {
        $bidWinnerHasBonus = ($highestCardPlayer == $bidWinner);
    }
    $bidWinnerBonus = $bidWinnerHasBonus ? 5 : 0;
    
    $bidWinnerTotalPoints = $bidWinnerTrickPoints + $bidWinnerBonus;
    $bidWinnerMadeBid = ($bidWinnerTotalPoints >= $winningBid);
    
    foreach ($playerIds as $playerId) {
        $tricksWon = 0;
        foreach ($state['scores'] as $pid => $tricks) {
            if ($pid == $playerId) {
                $tricksWon = $tricks;
                break;
            }
        }
        $trickPoints = $tricksWon * 5;
        
        $hasBonus = ($highestCardPlayer !== null && $playerId == $highestCardPlayer);
        if ($playerId == $bidWinner && $bidWinnerForfeitsBonus) {
            $hasBonus = false;
        }
        
        if (!isset($state['finalScores'][$playerId])) {
            $state['finalScores'][$playerId] = 0;
        }
        
        if ($playerId == $bidWinner) {
            $totalPoints = $trickPoints + ($hasBonus ? 5 : 0);
            if ($bidWinnerMadeBid) {
                $state['finalScores'][$playerId] += $totalPoints;
            } else {
                $state['finalScores'][$playerId] -= $winningBid;
            }
        } else {
            $state['finalScores'][$playerId] += $trickPoints;
            if ($hasBonus) {
                $state['finalScores'][$playerId] += 5;
            }
        }
    }
    
    // Build tricksWon with all players
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
    
    // Build round summary
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
    
    foreach ($playerIds as $pid) {
        $tricksWon = 0;
        foreach ($state['scores'] as $scorePid => $tricks) {
            if ($scorePid == $pid) {
                $tricksWon = $tricks;
                break;
            }
        }
        $trickPoints = $tricksWon * 5;
        
        $hasBonus = ($highestCardPlayer !== null && $pid == $highestCardPlayer);
        if ($pid == $bidWinner && $bidWinnerForfeitsBonus) {
            $hasBonus = false;
        }
        
        if ($pid == $bidWinner) {
            $totalPoints = $trickPoints + ($hasBonus ? 5 : 0);
            if ($bidWinnerMadeBid) {
                $roundSummary['roundPoints'][$pid] = $totalPoints;
                $roundSummary['bidMade'] = true;
            } else {
                $roundSummary['roundPoints'][$pid] = -$winningBid;
                $roundSummary['bidMade'] = false;
            }
        } else {
            $bonusPoints = $hasBonus ? 5 : 0;
            $roundSummary['roundPoints'][$pid] = $trickPoints + $bonusPoints;
        }
        
        if ($hasBonus) {
            $roundSummary['bonusPlayer'] = $pid;
        }
    }
    
    $state['highestCardInRound'] = null;
    $state['highestCardPlayer'] = null;
    $state['highestCardRank'] = null;
    $state['highestCardTrick'] = null;
    $state['trickComplete'] = false;
    $state['lastCompletedTrick'] = null;
    
    $state['roundSummary'] = $roundSummary;
    $state['phase'] = 'round_summary';
    
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
    
    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);
}

// Function to determine the game winner
function determineGameWinner($gameId) {
    global $db;

    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();

    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }

    $state = json_decode($gameState['state'], true);

    $playerIds = array_keys($state['players']);
    $trickWinners = $state['trickWinners'] ?? [];
    $highestCardPlayer = $state['highestCardPlayer'] ?? null;
    $highestCardTrick = $state['highestCardTrick'] ?? 5;
    $bidWinner = $state['highestBidder'] ?? null;
    $roundSummary = $state['roundSummary'] ?? null;
    $bidMade = $roundSummary['bidMade'] ?? true;
    $previousScores = [];
    
    foreach ($playerIds as $pid) {
        $roundPoints = $roundSummary['roundPoints'][$pid] ?? 0;
        $previousScores[$pid] = ($state['finalScores'][$pid] ?? 0) - $roundPoints;
    }
    
    $bidWinnerStartScore = $previousScores[$bidWinner] ?? 0;
    $bidWinnerForfeitsBonus = ($bidWinnerStartScore >= 85) && ($highestCardPlayer == $bidWinner);
    
    $firstTo110 = [];
    $runningScores = $previousScores;
    $tricksWonByPlayer = [];
    foreach ($playerIds as $pid) {
        $tricksWonByPlayer[$pid] = 0;
    }
    
    foreach ($trickWinners as $trickInfo) {
        $trickNum = $trickInfo['trick'];
        $winner = $trickInfo['winner'];
        
        $tricksWonByPlayer[$winner] = ($tricksWonByPlayer[$winner] ?? 0) + 1;
        $runningScores[$winner] = ($runningScores[$winner] ?? 0) + 5;
        
        foreach ($playerIds as $pid) {
            $scoreWithBonus = $runningScores[$pid];
            
            $canGetBonus = ($pid == $highestCardPlayer && $trickNum >= $highestCardTrick);
            if ($pid == $bidWinner && $bidWinnerForfeitsBonus) {
                $canGetBonus = false;
            }
            
            if ($canGetBonus) {
                $scoreWithBonus += 5;
            }
            
            if ($scoreWithBonus >= 110 && !isset($firstTo110[$pid])) {
                $firstTo110[$pid] = $trickNum;
            }
        }
    }
    
    $winner = null;
    
    if (!empty($firstTo110)) {
        asort($firstTo110);
        
        foreach ($firstTo110 as $playerId => $trickNum) {
            if ($playerId == $bidWinner) {
                if ($bidMade) {
                    $winner = $playerId;
                    break;
                }
                continue;
            }
            
            $winner = $playerId;
            break;
        }
    }
    
    if ($winner !== null) {
        $state['gameWinner'] = $winner;
        $state['phase'] = 'game_over';
    }

    $updatedState = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedState, $gameId]);
}

// Function to continue to next round
function continueToNextRound($gameId) {
    global $db;
    
    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();
    
    if (!$gameState) {
        throw new Exception("Game state not found for game ID: $gameId");
    }
    
    $state = json_decode($gameState['state'], true);
    
    // Get player IDs from database (more reliable than state)
    $stmt = $db->prepare("SELECT player_id FROM players WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $players = $stmt->fetchAll();
    $playerIds = array_column($players, 'player_id');
    
    $state['scores'] = [];
    $state['tricksPlayed'] = 0;
    $state['currentTrick'] = [];
    $state['roundSummary'] = null;
    $state['trickWinners'] = [];
    
    $currentDealer = $state['dealer'] ?? 0;
    $numPlayers = count($playerIds);
    $nextDealerIndex = ($currentDealer + 1) % $numPlayers;
    $state['dealer'] = $nextDealerIndex;
    $state['roundNumber'] = ($state['roundNumber'] ?? 1) + 1;

    $updatedState = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedState, $gameId]);

    determineGameWinner($gameId);
    
    // Fetch the updated state after determineGameWinner
    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $updatedGameState = $stmt->fetch();
    $updatedState = json_decode($updatedGameState['state'], true);
    
    if (!isset($updatedState['gameWinner'])) {
        startNewRound($gameId);
    }
}

// Function to start a new round
function startNewRound($gameId) {
    global $db;
    
    // Clear old cards
    $stmt = $db->prepare("DELETE FROM cards WHERE game_id = ?");
    $stmt->execute([$gameId]);
    
    dealCards($gameId);
    
    $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $gameState = $stmt->fetch();
    $state = json_decode($gameState['state'], true);
    
    $playerIds = array_keys($state['players']);
    $dealerIndex = $state['dealer'] ?? 0;
    
    $firstBidderIndex = ($dealerIndex + 1) % count($playerIds);
    $firstBidder = $playerIds[$firstBidderIndex];
    
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
    
    $state['finalScores'] = $preservedFinalScores;
    $state['roundNumber'] = $preservedRoundNumber;
    $state['dealer'] = $preservedDealer;
    
    $updatedStateJson = json_encode($state);
    $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
    $stmt->execute([$updatedStateJson, $gameId]);
}

// ============== API ENDPOINTS ==============

$endpoint = $_GET['endpoint'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

// Health check endpoint (for server status detection)
if ($method === 'GET' && $endpoint === 'health') {
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
    exit;
}

// Create game endpoint
if ($method === 'POST' && $endpoint === 'createGame') {
    try {
        $playerName = isset($_POST['playerName']) ? sanitizeInput($_POST['playerName'], 'string') : null;
        
        if (!$playerName) {
            throw new Exception('Player name is required to create a game.');
        }
        
        $gameCode = generateGameCode();
        $stmt = $db->prepare("INSERT INTO games (code, state) VALUES (?, '{}')");
        $stmt->execute([$gameCode]);
        $gameId = $db->lastInsertId();
        
        $stmt = $db->prepare("INSERT INTO players (game_id, name) VALUES (?, ?)");
        $stmt->execute([$gameId, $playerName]);
        $playerId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'gameCode' => $gameCode,
            'gameId' => $gameId,
            'playerId' => $playerId
        ]);
    } catch (Exception $e) {
        logError($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Join game endpoint
if ($method === 'POST' && $endpoint === 'joinGame') {
    try {
        $gameCode = sanitizeInput($_POST['gameCode'] ?? '', 'gameCode');
        $playerName = sanitizeInput($_POST['playerName'] ?? '', 'string');

        if (!$gameCode || !$playerName) {
            throw new Exception('Invalid input: game code or player name is invalid.');
        }

        $stmt = $db->prepare("SELECT game_id FROM games WHERE code = ?");
        $stmt->execute([$gameCode]);
        $game = $stmt->fetch();

        if ($game) {
            $gameId = $game['game_id'];
            
            // Check if game is already in progress
            $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $stmt->execute([$gameId]);
            $gameState = $stmt->fetch();
            
            if ($gameState) {
                $state = json_decode($gameState['state'], true);
                $phase = $state['phase'] ?? null;
                
                // If game has a phase, it's already started
                if ($phase && $phase !== 'waiting') {
                    throw new Exception("This game is already in progress. You can't join after the game has started.");
                }
            }
            
            // Check if player name is already taken in this game
            $stmt = $db->prepare("SELECT player_id FROM players WHERE game_id = ? AND name = ?");
            $stmt->execute([$gameId, $playerName]);
            $existingPlayer = $stmt->fetch();
            
            if ($existingPlayer) {
                throw new Exception("A player with this name is already in the game. Please choose a different name.");
            }
            
            $stmt = $db->prepare("INSERT INTO players (game_id, name) VALUES (?, ?)");
            $stmt->execute([$gameId, $playerName]);
            $playerId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Player joined the game successfully',
                'playerId' => $playerId,
                'gameId' => $gameId
            ]);
        } else {
            throw new Exception('Invalid game code');
        }
    } catch (Exception $e) {
        logError($e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Deal cards endpoint
if ($method === 'POST' && $endpoint === 'dealCards') {
    $gameId = $_POST['gameId'] ?? $_GET['gameId'] ?? null;

    if ($gameId) {
        try {
            dealCards($gameId);
            echo json_encode(['success' => 'Cards dealt successfully']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter']);
    }
    exit;
}

// Start bidding endpoint
if ($method === 'POST' && $endpoint === 'startBidding') {
    if (isset($_POST['gameId'])) {
        try {
            startBidding($_POST['gameId']);
            echo json_encode(['success' => 'Bidding started successfully']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter']);
    }
    exit;
}

// Process bid endpoint
if ($method === 'POST' && $endpoint === 'processBid') {
    if (isset($_POST['gameId'], $_POST['playerId']) && isset($_POST['bidAmount'])) {
        $gameId = sanitizeInput($_POST['gameId'], 'int');
        $playerId = sanitizeInput($_POST['playerId'], 'int');
        $bidAmount = intval($_POST['bidAmount']);

        if (!$gameId || !$playerId) {
            echo json_encode(['error' => 'Invalid input: gameId or playerId is invalid.']);
            exit;
        }

        try {
            processBid($gameId, $playerId, $bidAmount);
            echo json_encode(['success' => 'Bid processed successfully']);
        } catch (Exception $e) {
            logError($e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing parameters (gameId, playerId, bidAmount)']);
    }
    exit;
}

// Select kitty and trump endpoint
if ($method === 'POST' && $endpoint === 'selectKittyAndTrump') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['selectedCards'], $_POST['trumpSuit'])) {
        try {
            selectKittyAndTrump(
                $_POST['gameId'],
                $_POST['playerId'],
                json_decode($_POST['selectedCards'], true),
                $_POST['trumpSuit']
            );
            echo json_encode(['success' => 'Kitty and trump suit selected successfully']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing parameters']);
    }
    exit;
}

// Swap cards endpoint (for non-bid-winners during kitty phase)
if ($method === 'POST' && $endpoint === 'swapCards') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['cardsToDiscard'])) {
        try {
            $result = swapCards(
                intval($_POST['gameId']),
                intval($_POST['playerId']),
                json_decode($_POST['cardsToDiscard'], true) ?? []
            );
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing parameters (gameId, playerId, cardsToDiscard)']);
    }
    exit;
}

// Get players endpoint
if ($method === 'GET' && $endpoint === 'getPlayers') {
    if (isset($_GET['gameId'])) {
        $stmt = $db->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
        $stmt->execute([$_GET['gameId']]);
        $players = $stmt->fetchAll();
        echo json_encode(['players' => $players]);
    } else {
        echo json_encode(['error' => 'Missing gameId parameter']);
    }
    exit;
}

// Verify session endpoint
if ($method === 'GET' && $endpoint === 'verifySession') {
    $gameId = sanitizeInput($_GET['gameId'] ?? '', 'int');
    $playerId = sanitizeInput($_GET['playerId'] ?? '', 'int');
    
    if (!$gameId || !$playerId) {
        echo json_encode(['valid' => false, 'error' => 'Missing gameId or playerId']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT game_id, code FROM games WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        echo json_encode(['valid' => false, 'error' => 'Game not found']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT player_id, name FROM players WHERE player_id = ? AND game_id = ?");
    $stmt->execute([$playerId, $gameId]);
    $player = $stmt->fetch();
    
    if (!$player) {
        echo json_encode(['valid' => false, 'error' => 'Player not found in game']);
        exit;
    }
    
    echo json_encode([
        'valid' => true,
        'gameCode' => $game['code'],
        'playerName' => $player['name']
    ]);
    exit;
}

// Get game state endpoint
if ($method === 'GET' && $endpoint === 'getGameState') {
    if (isset($_GET['gameId'])) {
        $gameId = $_GET['gameId'];
        $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $gameState = $stmt->fetch();

        if ($gameState) {
            $state = json_decode($gameState['state'], true);
            
            // Ensure state is an array
            if (!is_array($state)) {
                $state = [];
            }
            
            // Fetch player names
            $stmt = $db->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
            $stmt->execute([$gameId]);
            $players = $stmt->fetchAll();
            $playerNames = [];
            foreach ($players as $player) {
                $playerNames[$player['player_id']] = $player['name'];
            }
            $state['playerNames'] = $playerNames;
            
            // Refresh player hands during trick phase
            if (isset($state['phase']) && $state['phase'] === 'trick') {
                foreach (array_keys($playerNames) as $pid) {
                    $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id = ?");
                    $stmt->execute([$gameId, $pid]);
                    $cards = array_column($stmt->fetchAll(), 'card');
                    $state['players'][$pid] = $cards;
                }
            }

            // Fetch kitty for highest bidder
            $requestPlayerId = $_GET['playerId'] ?? null;
            if (isset($state['highestBidder']) && $requestPlayerId && $state['highestBidder'] == $requestPlayerId) {
                $stmt = $db->prepare("SELECT card FROM cards WHERE game_id = ? AND player_id IS NULL");
                $stmt->execute([$gameId]);
                $kittyCards = $stmt->fetchAll();
                $state['kitty'] = array_column($kittyCards, 'card');
            }

            if (isset($state['gameWinner'])) {
                $state['winner'] = $state['gameWinner'];
            }

            echo json_encode($state);
        } else {
            echo json_encode(['error' => 'Game state not found for the given game ID.']);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter.']);
    }
    exit;
}

// Play card endpoint
if ($method === 'POST' && $endpoint === 'playCard') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['card'])) {
        try {
            playCard($_POST['gameId'], $_POST['playerId'], $_POST['card']);

            $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $stmt->execute([$_POST['gameId']]);
            $gameState = $stmt->fetch();

            if (!$gameState) {
                throw new Exception("Game state not found");
            }

            $state = json_decode($gameState['state'], true);

            if (($state['phase'] ?? '') === 'round_summary') {
                $stmt = $db->prepare("SELECT player_id, name FROM players WHERE game_id = ?");
                $stmt->execute([$_POST['gameId']]);
                $players = $stmt->fetchAll();
                $playerNames = [];
                foreach ($players as $player) {
                    $playerNames[$player['player_id']] = $player['name'];
                }
                $state['playerNames'] = $playerNames;
                
                echo json_encode(['success' => 'Card played successfully', 'phase' => 'round_summary', 'gameState' => $state]);
            } else {
                echo json_encode(['success' => 'Card played successfully', 'phase' => $state['phase'] ?? 'trick']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing parameters']);
    }
    exit;
}

// Continue round endpoint
if ($method === 'POST' && $endpoint === 'continueRound') {
    if (isset($_POST['gameId'])) {
        try {
            continueToNextRound(intval($_POST['gameId']));
            echo json_encode(['success' => 'Next round started']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter']);
    }
    exit;
}

// Clear trick complete endpoint
if ($method === 'POST' && $endpoint === 'clearTrickComplete') {
    if (isset($_POST['gameId'])) {
        $gameId = intval($_POST['gameId']);
        
        try {
            $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
            $stmt->execute([$gameId]);
            $gameState = $stmt->fetch();
            
            if (!$gameState) {
                throw new Exception("Game state not found");
            }
            
            $state = json_decode($gameState['state'], true);
            $state['trickComplete'] = false;
            $state['lastCompletedTrick'] = null;
            
            $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $stmt->execute([json_encode($state), $gameId]);
            
            echo json_encode(['success' => 'Trick complete state cleared']);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Missing gameId parameter']);
    }
    exit;
}

// Send reaction endpoint
if ($method === 'POST' && $endpoint === 'sendReaction') {
    if (isset($_POST['gameId'], $_POST['playerId'], $_POST['emoji'])) {
        $gameId = intval($_POST['gameId']);
        $playerId = intval($_POST['playerId']);
        $emoji = htmlspecialchars($_POST['emoji']);
        
        $stmt = $db->prepare("SELECT name FROM players WHERE player_id = ?");
        $stmt->execute([$playerId]);
        $player = $stmt->fetch();
        $playerName = $player['name'] ?? 'Unknown';
        
        $stmt = $db->prepare("SELECT state FROM game_state WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $gameState = $stmt->fetch();
        
        if ($gameState) {
            $state = json_decode($gameState['state'], true);
            $state['lastReaction'] = [
                'playerId' => $playerId,
                'playerName' => $playerName,
                'emoji' => $emoji,
                'timestamp' => time()
            ];
            
            $stmt = $db->prepare("UPDATE game_state SET state = ? WHERE game_id = ?");
            $stmt->execute([json_encode($state), $gameId]);
            
            sendGameStateToWebSocket(['type' => 'reaction', 'playerId' => $playerId, 'playerName' => $playerName, 'emoji' => $emoji]);
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Missing parameters']);
    }
    exit;
}

// Get overall statistics endpoint
if ($method === 'GET' && $endpoint === 'getOverallStatistics') {
    try {
        $stmt = $db->query("SELECT g.game_id, gs.state FROM games g LEFT JOIN game_state gs ON g.game_id = gs.game_id");
        $games = $stmt->fetchAll();
        
        $winCounts = [];
        $totalCompleted = 0;
        
        foreach ($games as $game) {
            if (!$game['state']) continue;
            
            $state = json_decode($game['state'], true);
            if (isset($state['gameWinner']) || isset($state['winnerName'])) {
                $totalCompleted++;
                
                $winnerName = $state['winnerName'] ?? null;
                if (!$winnerName && isset($state['gameWinner'])) {
                    $stmt = $db->prepare("SELECT name FROM players WHERE player_id = ?");
                    $stmt->execute([$state['gameWinner']]);
                    $player = $stmt->fetch();
                    $winnerName = $player['name'] ?? 'Unknown';
                }
                
                if ($winnerName) {
                    $winCounts[$winnerName] = ($winCounts[$winnerName] ?? 0) + 1;
                }
            }
        }
        
        $topPlayer = 'N/A';
        $topWins = 0;
        foreach ($winCounts as $name => $wins) {
            if ($wins > $topWins) {
                $topWins = $wins;
                $topPlayer = $name . " ({$wins} wins)";
            }
        }
        
        $stmt = $db->query("SELECT COUNT(*) AS totalGames FROM games");
        $totalGames = $stmt->fetch();

        echo json_encode([
            'topPlayer' => $topPlayer,
            'totalGames' => $totalGames['totalGames'] ?? 0,
            'completedGames' => $totalCompleted
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Get game history endpoint
if ($method === 'GET' && $endpoint === 'getGameHistory') {
    try {
        $stmt = $db->query("SELECT g.game_id, g.code, gs.state FROM games g LEFT JOIN game_state gs ON g.game_id = gs.game_id ORDER BY g.game_id DESC");
        $games = $stmt->fetchAll();
        
        $result = [];
        foreach ($games as $game) {
            $state = $game['state'] ? json_decode($game['state'], true) : [];
            
            $winnerName = $state['winnerName'] ?? null;
            if (!$winnerName && isset($state['gameWinner'])) {
                $stmt = $db->prepare("SELECT name FROM players WHERE player_id = ?");
                $stmt->execute([$state['gameWinner']]);
                $player = $stmt->fetch();
                $winnerName = $player['name'] ?? null;
            }
            
            $result[] = [
                'game_id' => $game['game_id'],
                'code' => $game['code'],
                'winner' => $winnerName,
                'status' => isset($state['gameWinner']) ? 'Completed' : ($state['phase'] ?? 'In Progress'),
                'rounds' => count($state['roundHistory'] ?? [])
            ];
        }

        echo json_encode(['games' => $result]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Default response for unknown endpoints
echo json_encode(['error' => 'Unknown endpoint']);

