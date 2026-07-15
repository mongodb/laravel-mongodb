#!/bin/bash
# Validates skill directories locally with human-readable output.
#
# NOTE: The validator flags used here (e.g. --strict) should stay aligned with
# .github/scripts/validate-skills.sh (the CI version). If you change which checks
# are run or how strictly they're enforced, update both scripts. The scripts
# otherwise differ by design:
#   - CI script:    diffs changed skills, uses --emit-annotations and -o markdown
#   - Local script: accepts an optional path or validates all skills, uses default
#                   terminal output
#
# Usage: validate-skills.sh [path/to/skill/]
#   path  Optional path to a single skill directory to validate.
#         When omitted, all directories under skills/ are validated.
#
# Exit codes:
#   0  All validated skills passed.
#   1  One or more skills failed validation.

set -euo pipefail

if ! command -v skill-validator &>/dev/null; then
  echo "Error: skill-validator is not installed."
  echo ""
  echo "Install via Homebrew (recommended):"
  echo "  brew tap agent-ecosystem/homebrew-tap"
  echo "  brew install skill-validator"
  echo ""
  echo "Or from source (requires Go 1.25.5+):"
  echo "  go install github.com/agent-ecosystem/skill-validator/cmd/skill-validator@latest"
  exit 1
fi

# Resolve and cd to the repo root so relative paths always work,
# regardless of where the script is invoked from.
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || {
  echo "Error: not inside a git repository."
  exit 1
}
cd "$REPO_ROOT"

SKILL_PATH="${1:-skills/}"

# Normalize absolute paths to be relative to the repo root.
if [[ "$SKILL_PATH" == /* ]]; then
  SKILL_PATH="${SKILL_PATH#"$REPO_ROOT"/}"
fi

if [ ! -d "$SKILL_PATH" ]; then
  echo "Error: '$SKILL_PATH' is not a directory."
  exit 1
fi

# Ensure the path ends with a trailing slash for consistency with the validator.
[[ "$SKILL_PATH" != */ ]] && SKILL_PATH="$SKILL_PATH/"

skill-validator check --strict "$SKILL_PATH"
