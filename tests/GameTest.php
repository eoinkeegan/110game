<?php

use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        // Set up a mock database connection
        $this->db = new mysqli('localhost', 'test_user', 'test_password', 'test_database');
        $this->db->query("CREATE TABLE IF NOT EXISTS games (game_id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(6), state TEXT)");
    }

    protected function tearDown(): void
    {
        // Clean up the database after each test
        $this->db->query("DROP TABLE IF EXISTS games");
        $this->db->close();
    }

    public function testGenerateGameCode()
    {
        $gameCode = generateGameCode();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $gameCode, 'Game code should be a 6-character alphanumeric string.');
    }

    public function testCreateGame()
    {
        $gameCode = generateGameCode();
        $stmt = $this->db->prepare("INSERT INTO games (code, state) VALUES (?, '{}')");
        $stmt->bind_param('s', $gameCode);
        $result = $stmt->execute();

        $this->assertTrue($result, 'Game should be created successfully.');
        $stmt->close();
    }
}