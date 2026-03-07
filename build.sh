#!/bin/bash
# Fun Dacha - Update and recompile script
# Pulls from origin develop, then recompiles Bootstrap and stylesheet CSS (PHP only, no Node.js)

set -e
cd "$(dirname "$0")"

echo "==> Pulling from origin develop..."
git pull origin develop

echo ""
echo "==> Recompiling SCSS with PHP (ScssPhp)..."
php build-compile.php

echo ""
echo "==> Build complete."
