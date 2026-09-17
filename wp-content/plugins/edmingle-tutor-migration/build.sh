#!/bin/bash
# ==============================================================================
# Build & Package Script: Edmingle to Tutor LMS Migration
# Creates a clean production-ready ZIP archive for WordPress installation & release.
# ==============================================================================

set -e

PLUGIN_SLUG="edmingle-tutor-migration"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$SCRIPT_DIR/dist"
BUILD_DIR="$DIST_DIR/$PLUGIN_SLUG"
ZIP_FILE="$DIST_DIR/${PLUGIN_SLUG}.zip"

echo "==> Building package for: $PLUGIN_SLUG"

# 1. Clean previous build directory
rm -rf "$DIST_DIR"
mkdir -p "$BUILD_DIR"

# 2. Copy production files using git archive if available, or rsync fallback
if [ -d "$SCRIPT_DIR/.git" ]; then
    echo "==> Exporting repository files using git archive..."
    git archive HEAD --format=tar | tar -x -C "$BUILD_DIR"
else
    echo "==> Copying plugin files..."
    rsync -av \
        --exclude='.git' \
        --exclude='.github' \
        --exclude='.gitignore' \
        --exclude='.gitattributes' \
        --exclude='dist' \
        --exclude='build.sh' \
        --exclude='etm-diagnostics.php' \
        --exclude='test_ajax.php' \
        --exclude='debug_step.log' \
        --exclude='diagnostic.txt' \
        --exclude='*.log' \
        --exclude='.DS_Store' \
        --exclude='__MACOSX' \
        "$SCRIPT_DIR/" "$BUILD_DIR/"
fi

# Ensure dev/scratch scripts are not in build folder
rm -f "$BUILD_DIR/build.sh"
rm -f "$BUILD_DIR/etm-diagnostics.php"
rm -f "$BUILD_DIR/test_ajax.php"
rm -f "$BUILD_DIR/debug_step.log"
rm -f "$BUILD_DIR/diagnostic.txt"
rm -f "$BUILD_DIR/.gitattributes"
rm -f "$BUILD_DIR/.gitignore"

# 3. Create ZIP package
echo "==> Creating production ZIP archive: $ZIP_FILE"
cd "$DIST_DIR"
zip -r -q "${PLUGIN_SLUG}.zip" "$PLUGIN_SLUG" -x "*.DS_Store" "*__MACOSX*"

# Cleanup temporary build directory (keep only ZIP)
rm -rf "$BUILD_DIR"

echo "==> Build complete!"
echo "==> Package output: $ZIP_FILE"
echo "==> Size: $(du -h "$ZIP_FILE" | cut -f1)"
