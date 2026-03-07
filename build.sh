#!/bin/bash
# Fun Dacha - Update and recompile script
# Pulls from origin develop, then recompiles Bootstrap and stylesheet CSS

set -e
cd "$(dirname "$0")"

echo "==> Pulling from origin develop..."
git pull origin develop

echo ""
echo "==> Recompiling SCSS..."

# Use npx sass (dart-sass) - works without global install, downloads on first use
if command -v npx &> /dev/null; then
  npx --yes sass catalog/view/stylesheet/bootstrap.scss catalog/view/stylesheet/bootstrap.css --no-source-map --style=expanded
  npx --yes sass catalog/view/stylesheet/stylesheet.scss catalog/view/stylesheet/stylesheet.css --no-source-map --style=expanded
  echo "Done. Bootstrap and stylesheet compiled with npx sass."
elif command -v sass &> /dev/null; then
  sass catalog/view/stylesheet/bootstrap.scss catalog/view/stylesheet/bootstrap.css --no-source-map --style=expanded
  sass catalog/view/stylesheet/stylesheet.scss catalog/view/stylesheet/stylesheet.css --no-source-map --style=expanded
  echo "Done. Bootstrap and stylesheet compiled with sass."
else
  echo "ERROR: Neither 'npx' nor 'sass' found. Install Node.js (for npx) or dart-sass, then run again."
  echo "  npm install -g sass   # or: npm install -g dart-sass"
  exit 1
fi

echo ""
echo "==> Build complete."
