# 🃏 110 Card Game

A web-based multiplayer Irish trick-taking card game. First to **110 points** wins!

## 🎮 Play Now

**[110.eoinkeegan.com](https://110.eoinkeegan.com)**

## Features

- **2-8 players** - Perfect for family game nights
- **Real-time gameplay** - See cards played instantly
- **Mobile-friendly** - Play on phone, tablet, or desktop
- **Emoji reactions** - React to plays in real-time
- **Game statistics** - Track your wins and history
- **On-demand hosting** - Server sleeps when not in use (cost-effective!)

## Game Rules

See [RULES.md](RULES.md) for complete game rules, or click **📖 Rules** in the game footer.

**Quick summary:**
- 53 cards (standard deck + Joker)
- Bid for the right to choose trump (15, 20, 25, or 30)
- Win tricks to score points (5 points per trick)
- Ace of Hearts is always trump
- First to 110 points wins!

## Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP 8 with SQLite |
| Hosting | AWS (S3 + CloudFront + EC2 + Lambda) |
| Real-time | Polling (WebSocket ready) |

## Local Development

```bash
# Start local server
cd /path/to/110game
php -S localhost:8080

# Open in browser
open http://localhost:8080
```

The app auto-detects local vs production environment.

## Deployment

See [DEPLOYMENT-ONDEMAND.md](DEPLOYMENT-ONDEMAND.md) for AWS deployment instructions.

## Project Structure

```
110game/
├── index.html          # Main game UI
├── style.css           # Styles
├── game-sqlite.php     # Backend API (SQLite)
├── statistics.html     # Game statistics page
├── RULES.md            # Game rules
├── assets/             # Logo and images
├── scripts/            # Deployment & auto-shutdown scripts
├── lambda/             # AWS Lambda for server control
└── tests/              # PHPUnit & Jest tests
```

## License

MIT License
