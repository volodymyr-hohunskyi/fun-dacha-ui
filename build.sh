#!/bin/bash
# Fun Dacha - Recompile Bootstrap and stylesheet CSS (PHP only, no Node.js)
# Run from any branch - checkout manually before running.

set -e
cd "$(dirname "$0")"

echo "==> Recompiling SCSS with PHP (ScssPhp)..."
php build-compile.php

echo ""
echo "==> Build complete."
