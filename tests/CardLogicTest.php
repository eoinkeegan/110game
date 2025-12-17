<?php
/**
 * Unit tests for card logic functions
 * 
 * Run with: ./vendor/bin/phpunit tests/CardLogicTest.php
 * 
 * NOTE: Currently these tests require extracting pure functions to a separate file.
 * TODO: Create includes/card-logic.php with pure functions (no database dependencies)
 * 
 * For now, these tests document the expected behavior of the card logic functions.
 * They serve as a specification that can be run once the refactoring is complete.
 */

use PHPUnit\Framework\TestCase;

// TODO: Uncomment after extracting functions to card-logic.php
// require_once __DIR__ . '/../includes/card-logic.php';

// Temporary: Define the functions inline for testing
// These should match the implementations in game-sqlite.php

function isRenegingCard($card, $trumpSuit) {
    if ($card === 'Joker') return true;
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    
    if ($cardSuit === $trumpSuit && $cardRank === '5') return true;
    if ($cardSuit === $trumpSuit && $cardRank === 'J') return true;
    if ($cardSuit === 'H' && $cardRank === 'A') return true;
    
    return false;
}

function getRenegingCardRank($card, $trumpSuit) {
    $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
    $cardRank = ($card === 'Joker') ? null : substr($card, 1);
    
    if ($cardSuit === $trumpSuit && $cardRank === '5') return 4;
    if ($cardSuit === $trumpSuit && $cardRank === 'J') return 3;
    if ($card === 'Joker') return 2;
    if ($cardSuit === 'H' && $cardRank === 'A') return 1;
    
    return 0;
}

// Get the reneging rank of the LEAD card only (first card in trick)
function getLeadRenegingCardRank($currentTrick, $trumpSuit) {
    if (empty($currentTrick)) {
        return 0;
    }
    $leadCard = $currentTrick[0]['card'];
    return getRenegingCardRank($leadCard, $trumpSuit);
}

function mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick) {
    $leadRenegingRank = getLeadRenegingCardRank($currentTrick, $trumpSuit);
    
    foreach ($playerCards as $card) {
        $cardSuit = ($card === 'Joker') ? null : substr($card, 0, 1);
        $cardRank = ($card === 'Joker') ? null : substr($card, 1);
        
        // Check if this card counts as "following suit"
        // A card follows the lead suit if:
        // 1. Its actual suit matches the lead suit, OR
        // 2. Lead suit is trump AND card is a "permanent trump" (Joker or Ace of Hearts)
        $cardFollowsLead = false;
        
        if ($cardSuit === $leadSuit) {
            $cardFollowsLead = true;
        } elseif ($leadSuit === $trumpSuit) {
            // Trump was led - check for permanent trumps
            if ($card === 'Joker') {
                $cardFollowsLead = true;
            } elseif ($cardSuit === 'H' && $cardRank === 'A') {
                // Ace of Hearts is always trump
                $cardFollowsLead = true;
            }
        }
        
        if ($cardFollowsLead) {
            if (isRenegingCard($card, $trumpSuit)) {
                $cardRenegingRank = getRenegingCardRank($card, $trumpSuit);
                // Only forced out if higher reneging card LEADS
                if ($leadRenegingRank > $cardRenegingRank) {
                    return true;
                }
            } else {
                return true;
            }
        }
    }
    
    return false;
}

function isCardTrump($card, $trumpSuit) {
    if ($card === 'Joker') return true;
    if ($card === 'HA') return true; // Ace of Hearts is always trump
    
    $cardSuit = substr($card, 0, 1);
    return $cardSuit === $trumpSuit;
}

function getCardRankValue($card, $trumpSuit, $leadSuit) {
    if ($card === 'Joker') return 1013;
    
    $cardSuit = substr($card, 0, 1);
    $cardRank = substr($card, 1);
    $isRedSuit = ($cardSuit === 'H' || $cardSuit === 'D');
    
    if (isCardTrump($card, $trumpSuit)) {
        if ($cardSuit === $trumpSuit && $cardRank === '5') return 1015;
        if ($cardSuit === $trumpSuit && $cardRank === 'J') return 1014;
        if ($cardSuit === 'H' && $cardRank === 'A') return 1012;
        if ($cardSuit === $trumpSuit && $cardRank === 'A') return 1011;
        if ($cardSuit === $trumpSuit && $cardRank === 'K') return 1010;
        if ($cardSuit === $trumpSuit && $cardRank === 'Q') return 1009;
        
        if ($isRedSuit) {
            $trumpRanks = ['10' => 1008, '9' => 1007, '8' => 1006, '7' => 1005, 
                           '6' => 1004, '4' => 1003, '3' => 1002, '2' => 1001];
        } else {
            $trumpRanks = ['2' => 1008, '3' => 1007, '4' => 1006, '6' => 1005, 
                           '7' => 1004, '8' => 1003, '9' => 1002, '10' => 1001];
        }
        return $trumpRanks[$cardRank] ?? 1000;
    }
    
    if ($cardSuit === $leadSuit) {
        if ($isRedSuit) {
            $leadRanks = ['K' => 513, 'Q' => 512, 'J' => 511, '10' => 510, 
                          '9' => 509, '8' => 508, '7' => 507, '6' => 506, 
                          '5' => 505, '4' => 504, '3' => 503, '2' => 502, 'A' => 501];
        } else {
            $leadRanks = ['K' => 513, 'Q' => 512, 'J' => 511, 'A' => 510, 
                          '2' => 509, '3' => 508, '4' => 507, '5' => 506, 
                          '6' => 505, '7' => 504, '8' => 503, '9' => 502, '10' => 501];
        }
        return $leadRanks[$cardRank] ?? 500;
    }
    
    return 0;
}

class CardLogicTest extends TestCase
{
    // =====================================================
    // isRenegingCard Tests
    // =====================================================
    
    public function testFiveOfTrumpIsRenegingCard()
    {
        $this->assertTrue(isRenegingCard('S5', 'S'), '5 of Spades should be reneging when Spades is trump');
        $this->assertTrue(isRenegingCard('H5', 'H'), '5 of Hearts should be reneging when Hearts is trump');
    }
    
    public function testFiveOfNonTrumpIsNotRenegingCard()
    {
        $this->assertFalse(isRenegingCard('H5', 'S'), '5 of Hearts should NOT be reneging when Spades is trump');
        $this->assertFalse(isRenegingCard('D5', 'C'), '5 of Diamonds should NOT be reneging when Clubs is trump');
    }
    
    public function testJackOfTrumpIsRenegingCard()
    {
        $this->assertTrue(isRenegingCard('SJ', 'S'), 'Jack of Spades should be reneging when Spades is trump');
        $this->assertTrue(isRenegingCard('CJ', 'C'), 'Jack of Clubs should be reneging when Clubs is trump');
    }
    
    public function testJokerIsAlwaysRenegingCard()
    {
        $this->assertTrue(isRenegingCard('Joker', 'S'), 'Joker should always be reneging');
        $this->assertTrue(isRenegingCard('Joker', 'H'), 'Joker should always be reneging');
    }
    
    public function testAceOfHeartsIsAlwaysRenegingCard()
    {
        $this->assertTrue(isRenegingCard('HA', 'S'), 'Ace of Hearts should be reneging even when Spades is trump');
        $this->assertTrue(isRenegingCard('HA', 'H'), 'Ace of Hearts should be reneging when Hearts is trump');
        $this->assertTrue(isRenegingCard('HA', 'C'), 'Ace of Hearts should be reneging when Clubs is trump');
    }
    
    public function testRegularCardsAreNotRenegingCards()
    {
        $this->assertFalse(isRenegingCard('HK', 'S'), 'King of Hearts is not a reneging card');
        $this->assertFalse(isRenegingCard('S10', 'S'), '10 of Spades is not a reneging card even when Spades is trump');
        $this->assertFalse(isRenegingCard('DA', 'S'), 'Ace of Diamonds is not a reneging card');
    }
    
    // =====================================================
    // getRenegingCardRank Tests
    // =====================================================
    
    public function testRenegingCardRanking()
    {
        // 5 of trump is highest (rank 4)
        $this->assertEquals(4, getRenegingCardRank('S5', 'S'), '5 of trump should have rank 4');
        
        // Jack of trump is second (rank 3)
        $this->assertEquals(3, getRenegingCardRank('SJ', 'S'), 'Jack of trump should have rank 3');
        
        // Joker is third (rank 2)
        $this->assertEquals(2, getRenegingCardRank('Joker', 'S'), 'Joker should have rank 2');
        
        // Ace of Hearts is fourth (rank 1)
        $this->assertEquals(1, getRenegingCardRank('HA', 'S'), 'Ace of Hearts should have rank 1');
        
        // Non-reneging cards have rank 0
        $this->assertEquals(0, getRenegingCardRank('HK', 'S'), 'King of Hearts should have rank 0');
    }
    
    // =====================================================
    // mustFollowSuit Tests
    // =====================================================
    
    public function testMustFollowSuitWithRegularCards()
    {
        $playerCards = ['HK', 'H10', 'CQ'];
        $leadSuit = 'H';
        $trumpSuit = 'S';
        $currentTrick = [['playerId' => 1, 'card' => 'H7']];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertTrue($result, 'Should must follow suit when holding non-reneging cards of lead suit');
    }
    
    public function testCanRenegeWithOnlyRenegingCards()
    {
        $playerCards = ['HA', 'CQ', 'D10']; // Only Ace of Hearts matches lead suit
        $leadSuit = 'H';
        $trumpSuit = 'S'; // Spades trump, so Ace of Hearts can renege
        $currentTrick = [['playerId' => 1, 'card' => 'HK']];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertFalse($result, 'Should NOT must follow suit when only reneging cards of lead suit');
    }
    
    public function testRenegingCardForcedOutByHigher()
    {
        $playerCards = ['HA', 'CQ']; // Ace of Hearts (rank 1)
        $leadSuit = 'H';
        $trumpSuit = 'S';
        // Joker played (rank 2) - forces out Ace of Hearts
        $currentTrick = [['playerId' => 1, 'card' => 'Joker']];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertTrue($result, 'Ace of Hearts should be forced out when Joker is played');
    }
    
    public function testFiveOfTrumpCanNeverBeForcedOut()
    {
        $playerCards = ['S5', 'CQ']; // 5 of Spades (highest reneging card)
        $leadSuit = 'S';
        $trumpSuit = 'S';
        // Even with other high cards played, 5 can't be forced
        $currentTrick = [['playerId' => 1, 'card' => 'SJ']]; // Jack of trump played
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertFalse($result, '5 of trump can never be forced out');
    }
    
    public function testAceOfHeartsForcedOutWhenTrumpLedWithFive()
    {
        // BUG FIX TEST: When 5 of trump LEADS, Ace of Hearts must be played
        // because Ace of Hearts is always trump, and 5 (rank 4) > A♥ (rank 1)
        $playerCards = ['HA', 'D10', 'CQ']; // Ace of Hearts + non-trump cards
        $leadSuit = 'C'; // Clubs led (trump)
        $trumpSuit = 'C'; // Clubs is trump
        // 5 of Clubs LEADS (rank 4)
        $currentTrick = [['playerId' => 1, 'card' => 'C5']];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertTrue($result, 'Ace of Hearts must be played when 5 of trump LEADS (A♥ is forced out)');
    }
    
    public function testJokerForcedOutWhenTrumpLedWithFive()
    {
        // Joker is always trump, and should be forced out when 5 of trump LEADS
        $playerCards = ['Joker', 'D10', 'HQ']; // Joker + non-trump cards
        $leadSuit = 'S'; // Spades led (trump)
        $trumpSuit = 'S'; // Spades is trump
        // 5 of Spades LEADS (rank 4)
        $currentTrick = [['playerId' => 1, 'card' => 'S5']];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        $this->assertTrue($result, 'Joker must be played when 5 of trump LEADS (Joker is forced out)');
    }
    
    public function testRenegingNotForcedWhenHigherPlayedAfterLead()
    {
        // KEY RULE: Reneging cards are only forced by LEAD, not by subsequent plays
        // Player 1 leads with King of Spades (not reneging)
        // Player 2 plays 5 of Spades (reneging, but didn't lead)
        // Player 3 (us) has Jack of Spades - should NOT be forced out!
        $playerCards = ['SJ', 'D10', 'HQ']; // Jack of trump + non-trump cards
        $leadSuit = 'S'; // Spades led (trump)
        $trumpSuit = 'S'; // Spades is trump
        $currentTrick = [
            ['playerId' => 1, 'card' => 'SK'],  // King LEADS (not reneging)
            ['playerId' => 2, 'card' => 'S5']   // 5 played after (reneging, but didn't lead)
        ];
        
        $result = mustFollowSuit($playerCards, $leadSuit, $trumpSuit, $currentTrick);
        
        // Jack should NOT be forced - only the LEAD card matters, and King is not a reneging card
        $this->assertFalse($result, 'Jack should NOT be forced out when 5 plays AFTER a non-reneging lead');
    }
    
    // =====================================================
    // isCardTrump Tests
    // =====================================================
    
    public function testCardOfTrumpSuitIsTrump()
    {
        $this->assertTrue(isCardTrump('S10', 'S'), '10 of Spades is trump when Spades is trump');
        $this->assertTrue(isCardTrump('SK', 'S'), 'King of Spades is trump when Spades is trump');
    }
    
    public function testJokerIsAlwaysTrump()
    {
        $this->assertTrue(isCardTrump('Joker', 'S'), 'Joker is always trump');
        $this->assertTrue(isCardTrump('Joker', 'H'), 'Joker is always trump');
    }
    
    public function testAceOfHeartsIsAlwaysTrump()
    {
        $this->assertTrue(isCardTrump('HA', 'S'), 'Ace of Hearts is trump even when Spades is trump');
        $this->assertTrue(isCardTrump('HA', 'C'), 'Ace of Hearts is trump even when Clubs is trump');
    }
    
    // =====================================================
    // getCardRankValue Tests (Trump ranking)
    // =====================================================
    
    public function testTrumpRankingOrder()
    {
        $trumpSuit = 'S';
        $leadSuit = 'S';
        
        $fiveRank = getCardRankValue('S5', $trumpSuit, $leadSuit);
        $jackRank = getCardRankValue('SJ', $trumpSuit, $leadSuit);
        $jokerRank = getCardRankValue('Joker', $trumpSuit, $leadSuit);
        $aceHeartsRank = getCardRankValue('HA', $trumpSuit, $leadSuit);
        $aceTrumpRank = getCardRankValue('SA', $trumpSuit, $leadSuit);
        $kingRank = getCardRankValue('SK', $trumpSuit, $leadSuit);
        
        // 5 > J > Joker > A♥ > A of trump > K
        $this->assertGreaterThan($jackRank, $fiveRank, '5 of trump should beat Jack');
        $this->assertGreaterThan($jokerRank, $jackRank, 'Jack of trump should beat Joker');
        $this->assertGreaterThan($aceHeartsRank, $jokerRank, 'Joker should beat Ace of Hearts');
        $this->assertGreaterThan($aceTrumpRank, $aceHeartsRank, 'Ace of Hearts should beat Ace of trump');
        $this->assertGreaterThan($kingRank, $aceTrumpRank, 'Ace of trump should beat King');
    }
}

