#!/bin/bash
# Fun Dacha - Deploy to server
# Usage: ./deploy.sh [--branch BRANCH]
#   --branch BRANCH  Branch to deploy (default: develop)
#
# Configure via env: SSH_KEY, REMOTE_DIR
# If key has passphrase, run: ssh-add /path/to/id_rsa  (first)

set -e

# Configuration (override with env vars)
SSH_KEY="${SSH_KEY:-/Users/vgogunsky/Downloads/id_rsa}"
SSH_USER="xjgqivxn"
SSH_HOST="fun-dacha.com.ua"
SSH_PORT="22"
# Remote project path. Default: $HOME/public_html (expanded on server). Override: REMOTE_DIR=/home/xjgqivxn/www ./deploy.sh
REMOTE_DIR="${REMOTE_DIR:-\$HOME/public_html}"
BRANCH="develop"

# Parse branch argument
while [[ $# -gt 0 ]]; do
  case $1 in
    --branch)
      BRANCH="$2"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      echo "Usage: ./deploy.sh [--branch BRANCH]"
      exit 1
      ;;
  esac
done

SSH_OPTS="-i $SSH_KEY -o StrictHostKeyChecking=accept-new -p $SSH_PORT"

echo "==> Deploying branch: $BRANCH"
echo "==> Connecting to $SSH_USER@$SSH_HOST..."
echo ""

ssh $SSH_OPTS "$SSH_USER@$SSH_HOST" "
  set -e
  cd \"$REMOTE_DIR\" || { echo 'Error: Remote project dir not found'; exit 1; }
  echo '==> git stash'
  git stash
  echo '==> git pull origin $BRANCH'
  git pull origin $BRANCH
  echo '==> sh build.sh'
  sh build.sh
  echo ''
  echo '==> Deploy complete.'
"

echo ""
echo "Deploy finished successfully."
