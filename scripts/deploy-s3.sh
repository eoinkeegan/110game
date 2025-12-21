#!/bin/bash
# S3 Deployment Script for 110 Card Game
# Optimized cache settings to reduce S3 requests (stay within free tier)

set -e

echo "🚀 Deploying to S3 (110game-static)"
echo "==================================="

S3_BUCKET="s3://110game-static"
CLOUDFRONT_ID="E16Q4D8XTNFJL3"

# Navigate to project directory
cd "$(dirname "$0")/.."

# Upload HTML with short cache (5 min) - allows quick updates
echo "📄 Uploading index.html (5 min cache)..."
aws s3 cp index.html $S3_BUCKET/ --cache-control "max-age=300"

echo "📄 Uploading rules.html (5 min cache)..."
aws s3 cp rules.html $S3_BUCKET/ --cache-control "max-age=300"

echo "📄 Uploading statistics.html (5 min cache)..."
aws s3 cp statistics.html $S3_BUCKET/ --cache-control "max-age=300"

echo "📄 Uploading scores.html (5 min cache)..."
aws s3 cp scores.html $S3_BUCKET/ --cache-control "max-age=300"

# Upload CSS/JS with longer cache (1 day) - rarely changes
echo "🎨 Uploading style.css (1 day cache)..."
aws s3 cp style.css $S3_BUCKET/ --cache-control "max-age=86400"

echo "⚙️  Uploading app-config.js (1 hour cache)..."
aws s3 cp app-config.js $S3_BUCKET/ --cache-control "max-age=3600"

echo "📊 Uploading statistics.js (1 day cache)..."
aws s3 cp statistics.js $S3_BUCKET/ --cache-control "max-age=86400"

# Upload assets with long cache (1 week) - static images
echo "🖼️  Uploading assets (1 week cache)..."
aws s3 sync assets/ $S3_BUCKET/assets/ --cache-control "max-age=604800"

# Upload docs
echo "📖 Uploading RULES.md..."
aws s3 cp RULES.md $S3_BUCKET/ --cache-control "max-age=86400"

# Invalidate CloudFront cache for HTML files only (they have short cache anyway)
echo "🔄 Invalidating CloudFront cache..."
aws cloudfront create-invalidation \
    --distribution-id $CLOUDFRONT_ID \
    --paths "/index.html" "/rules.html" "/statistics.html" "/scores.html" "/app-config.js" "/style.css"

echo ""
echo "✅ S3 deployment complete!"
echo ""
echo "Cache settings:"
echo "  - HTML files: 5 minutes"
echo "  - app-config.js: 1 hour"
echo "  - CSS/JS: 1 day"
echo "  - Assets: 1 week"
echo ""
echo "This reduces S3 requests by having CloudFront cache files longer."

