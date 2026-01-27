#!/bin/bash
#
# Deploy script for Brighter Websites MU Plugins - Multi-Site
# Downloads and extracts from GitHub branch to ALL sites
#
# Usage: ./deploy.sh [branch-name]
# Example: ./deploy.sh claude/fix-quiccloud-settings-0184qJeg1DsVPei1H7pyKhAE
#

GITHUB_REPO="brighterwebsites/mu-brighter-support"
BRANCH="${1:-main}"  # Use first argument or default to main
TEMP_DIR="/tmp/mu-plugin-deploy-$$"

# Sites to deploy to
declare -A SITES=(
    ["brigh1978"]="/home/brighterwebsites.com.au/public_html"
    ["onete1610"]="/home/oneteamqld.com.au/public_html"
    ["gueri4801"]="/home/guerillasteelstables.com.au/public_html"
    ["lucid3796"]="/home/lucidlearners.com.au/public_html"
    ["aband3945"]="/home/abandonstress.com.au/stage.abandonstress.com.au"
   
) 
 #   ["nmxstudio"]="/home/nmxstudio.com.au/public_html"
 #   ["south6867"]="/home/southbrislawn.com.au/public_html"
 #   ["daylesfordgardener"]="/home/daylesfordgardener.com.au/public_html"
 #   ["ballaratlawncare"]="/home/ballaratlawncare.com.au/public_html"
 #   ["baseb5993"]="/home/base.bweb1.com.au/public_html"
 #   ["cubew3861"]="/home/cubewebservices.com.au/public_html"
 #   ["mensf8194 "]="/home/mensfinanceadvice.com.au/public_html"
 # ["sunse5067"]="/home/sunsetbookkeeping.com.au/public_html"


#   ["cubew3861"]="/home/cubewebservices.com.au/public_html"
#   ["mensf8194 "]="/home/mensfinanceadvice.com.au/public_html"

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Multi-Site Deployment from GitHub                         ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "Repository: $GITHUB_REPO"
echo "Branch: $BRANCH"
echo "Sites: ${#SITES[@]}"
echo ""

# Step 1: Download from GitHub
echo "──────────────────────────────────────────────────────────────────"
echo "📥 Downloading from GitHub..."
echo "──────────────────────────────────────────────────────────────────"

mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR" || exit 1

# Download ZIP from GitHub
wget -q "https://github.com/$GITHUB_REPO/archive/refs/heads/$BRANCH.zip" -O plugin.zip

if [ $? -ne 0 ]; then
    echo "❌ Failed to download from GitHub"
    echo "   Check if branch name is correct: $BRANCH"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "✓ Download complete"
echo ""

# Step 2: Extract ZIP
echo "📦 Extracting files..."
unzip -q plugin.zip

# The extracted folder will be named: mu-brighter-support-{branch-name}
EXTRACTED_FOLDER=$(ls -d mu-brighter-support-* 2>/dev/null | head -1)

if [ -z "$EXTRACTED_FOLDER" ]; then
    echo "❌ Failed to find extracted folder"
    ls -la
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "✓ Extracted to: $EXTRACTED_FOLDER"
echo ""

# Step 3: Deploy to each site
SUCCESS_COUNT=0
FAIL_COUNT=0

for SITE_NAME in "${!SITES[@]}"; do
    SITE_PATH="${SITES[$SITE_NAME]}"
    MU_PLUGINS_PATH="$SITE_PATH/wp-content/mu-plugins"

    echo "──────────────────────────────────────────────────────────────────"
    echo "🚀 Deploying to: $SITE_NAME"
    echo "──────────────────────────────────────────────────────────────────"

    # Check if site path exists
    if [ ! -d "$SITE_PATH" ]; then
        echo "❌ ERROR: Site path not found: $SITE_PATH"
        echo "   Skipping $SITE_NAME..."
        echo ""
        FAIL_COUNT=$((FAIL_COUNT + 1))
        continue
    fi

    # Check if mu-plugins directory exists
    if [ ! -d "$MU_PLUGINS_PATH" ]; then
        echo "❌ ERROR: mu-plugins directory not found: $MU_PLUGINS_PATH"
        echo "   Skipping $SITE_NAME..."
        echo ""
        FAIL_COUNT=$((FAIL_COUNT + 1))
        continue
    fi

    # Copy files to mu-plugins (overwrite)
    echo "   📋 Copying files..."
    cp -rf "$TEMP_DIR/$EXTRACTED_FOLDER"/* "$MU_PLUGINS_PATH/"

    if [ $? -ne 0 ]; then
        echo "   ❌ Failed to copy files"
        FAIL_COUNT=$((FAIL_COUNT + 1))
        continue
    fi

    # Set correct permissions
    echo "   🔒 Setting permissions..."
    find "$MU_PLUGINS_PATH" -type f -exec chmod 644 {} \; 2>/dev/null
    find "$MU_PLUGINS_PATH" -type d -exec chmod 755 {} \; 2>/dev/null

    # Set owner (adjust username if needed)
    if [ -d "$MU_PLUGINS_PATH/mu-brighter-support" ]; then
        chown -R "$SITE_NAME:$SITE_NAME" "$MU_PLUGINS_PATH/mu-brighter-support" 2>/dev/null || \
        chown -R www-data:www-data "$MU_PLUGINS_PATH/mu-brighter-support" 2>/dev/null
    fi

    # Clear LiteSpeed Cache
    echo "   🧹 Clearing LiteSpeed cache..."
    rm -rf "$SITE_PATH/lscache/"* 2>/dev/null

    # Try to clear server-level cache too
    DOMAIN=$(basename "$(dirname "$SITE_PATH")")
    rm -rf "/usr/local/lsws/cachedata/$DOMAIN/"* 2>/dev/null

    # Flush WordPress rewrite rules
    echo "   🔄 Flushing rewrite rules..."
    cd "$SITE_PATH" || continue
    wp rewrite flush 2>/dev/null || echo "   ⚠ wp-cli not available, skip rewrite flush"

    echo ""
    echo "   ✅ $SITE_NAME deployment complete!"
    echo ""
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
done

# Step 4: Cleanup
echo "──────────────────────────────────────────────────────────────────"
echo "🗑️  Cleaning up temp files..."
echo "──────────────────────────────────────────────────────────────────"
rm -rf "$TEMP_DIR"
echo "✓ Cleanup complete"
echo ""

# Step 5: Summary
echo "──────────────────────────────────────────────────────────────────"
echo "📊 Deployment Summary"
echo "──────────────────────────────────────────────────────────────────"
echo "Branch deployed: $BRANCH"
echo "Successful: $SUCCESS_COUNT"
echo "Failed: $FAIL_COUNT"
echo ""

if [ $SUCCESS_COUNT -eq ${#SITES[@]} ]; then
    echo "✅ All deployments complete!"
else
    echo "⚠️  Some deployments failed. Check output above for details."
fi

echo ""
echo "Next steps:"
echo "1. Test Site Essentials in each site's admin"
echo "   - https://base.bweb1.com.au/wp-admin"
echo "   - https://lucidlearners.com.au/wp-admin"
echo "   - https://southbrislawn.com.au/wp-admin"
echo "   - https://daylesfordgardener.com.au/wp-admin"
echo "   - https://ballaratlawncare.com.au/wp-admin"
echo "   - https://abandonstress.com.au/wp-admin"
echo "   - https://nmxstudio.com.au/wp-admin"
echo ""
