#!/bin/bash
# Auto-inject entrypoint context when a Class::method is mentioned in the prompt

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

INPUT=$(cat)

# Extract first Class::method pattern using PHP (project dependency)
NODE=$(printf '%s' "$INPUT" | php -r '
$data = json_decode(file_get_contents("php://stdin"), true);
$prompt = $data["prompt"] ?? "";
if (preg_match("/[A-Za-z\\\\][A-Za-z0-9_\\\\]*::[a-zA-Z_][a-zA-Z0-9_]+/", $prompt, $m)) {
    echo $m[0];
}
')

if [ -z "$NODE" ]; then
    exit 0
fi

cd "$PROJECT_ROOT" || exit 0

CONTEXT=$(php bin/engine.php context . \
    --entrypoint="$NODE" \
    --minimal \
    --strict-entrypoint \
    2>/dev/null)

# Only output additionalContext if node was found (non-empty output)
if [ -n "$CONTEXT" ]; then
    printf '%s' "$CONTEXT" | php -r '
$context = file_get_contents("php://stdin");
echo json_encode([
    "hookSpecificOutput" => [
        "hookEventName" => "UserPromptSubmit",
        "additionalContext" => $context
    ]
]);
'
fi

exit 0
