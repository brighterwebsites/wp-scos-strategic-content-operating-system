#!/bin/bash
#
# Site Essentials - Post-Deploy Cleanup
#
# This script removes old lowercase directories that may exist due to
# case-sensitivity issues during git deployments.
#
# Usage: Run this after every git pull/deploy
#

PLUGIN_DIR="/path/to/wp-content/mu-plugins"
cd "$PLUGIN_DIR" || exit 1

echo "=== Site Essentials Post-Deploy Cleanup ==="
echo ""

# Check and remove old lowercase directories
REMOVED=0

if [ -d "site-essentials/core" ]; then
    echo "❌ REMOVING: site-essentials/core/ (old lowercase)"
    rm -rf site-essentials/core
    REMOVED=$((REMOVED + 1))
fi

if [ -d "site-essentials/modules" ]; then
    echo "❌ REMOVING: site-essentials/modules/ (old lowercase)"
    rm -rf site-essentials/modules
    REMOVED=$((REMOVED + 1))
fi

if [ -d "site-essentials/views" ]; then
    echo "❌ REMOVING: site-essentials/views/ (old lowercase)"
    rm -rf site-essentials/views
    REMOVED=$((REMOVED + 1))
fi

if [ $REMOVED -eq 0 ]; then
    echo "✓ No old directories found"
else
    echo ""
    echo "✓ Removed $REMOVED old directory/directories"
fi

echo ""
echo "Current structure:"
ls -la site-essentials/ | grep "^d" | grep -v "^\.$" | grep -v "^\.\.$"

echo ""
echo "✓ Cleanup complete!"
