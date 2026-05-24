#!/bin/bash
# Inject current Flow Engine analysis as Claude context
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/../.."
php bin/engine.php analyze . >/dev/null 2>&1
php bin/engine.php context . --minimal 2>/dev/null
