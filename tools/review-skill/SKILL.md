---
name: review-skill
description: >-
  Review a proposed Agent Skill for structural validity and content
  quality before publishing. Runs the skill-validator CLI to check for
  structural issues, scores the skill with an LLM judge, and interprets results
  to advise SMEs on what to address. Use when a user wants to review, validate,
  or quality-check an Agent Skill.
compatibility: Requires skill-validator CLI and claude CLI for LLM scoring. LLM scoring can be skipped for structural-only review.
metadata:
  author: mongodb
  version: "1.0"
---

# Review Skill Workflow

You are helping an SME review an Agent Skill before publishing. This is a
multi-step process: determine environment, verify prerequisites, run structural
validation, review content, optionally run LLM scoring, and interpret results.
Follow every step in order.

## Step 0: Determine Environment

Check for saved configuration:

```bash
cat ~/.config/skill-validator/review-state.yaml 2>/dev/null
```

**If the state file exists** with `prereqs_passed: true`, offer:

> Found saved settings — configured for **[full/structural-only]** reviews.
>
> 1. **Continue with saved settings** — skip to Step 2
> 2. **Re-run prerequisite checks**
> 3. **Change environment** — switch between full and structural-only

Option 1: read `llm_scoring` from the file and skip to Step 2.
Options 2-3: continue below.

**If no state file exists**, or the user chose to re-check/change, ask:

> LLM scoring evaluates content quality across multiple dimensions.
>
> 1. **Yes, run LLM scoring** — full review with LLM scoring
> 2. **No, skip LLM scoring** — structural validation only

Option 1: set `LLM_SCORING=true`.
Option 2: set `LLM_SCORING=false`. Run Step 1a only, then jump to Step 2.

## Step 1: Verify Prerequisites

### 1a. Check for `skill-validator` binary

```bash
skill-validator --version
```

If not found, search common locations (`/usr/local/bin`, `/opt/homebrew/bin`,
`~/go/bin`). If found but not on PATH, tell the user. If not found anywhere,
follow [references/install-skill-validator.md](references/install-skill-validator.md).

If `--version` is not at least v1.5.1, help the user upgrade with
`brew upgrade skill-validator` or
`go install github.com/agent-ecosystem/skill-validator/cmd/skill-validator@latest`.

Do NOT proceed until this succeeds.

### 1b. Check for `claude` CLI (LLM scoring only)

If `LLM_SCORING=true`, verify the Claude CLI is available:

```bash
claude --version
```

If not found, tell the user to install Claude Code:

- **macOS**: `curl -fsSL https://claude.ai/install.sh | bash`
- **Other platforms**: follow the [Claude Code quickstart guide](https://code.claude.com/docs/en/quickstart)

The user must authenticate by running `claude` interactively before continuing.

Do NOT proceed with LLM scoring until this succeeds.

### Save state after prerequisites pass

Persist state so future runs skip this step. Replace `<true or false>` with
the actual `LLM_SCORING` value:

```bash
mkdir -p ~/.config/skill-validator
cat > ~/.config/skill-validator/review-state.yaml << 'EOF'
prereqs_passed: true
llm_scoring: <true or false>
EOF
```

## Step 2: Locate the Skill

Ask the user for the path to the skill they want to review, unless they have
already provided it. Verify the path contains a `SKILL.md` file:

```bash
ls <path>/SKILL.md
```

If `SKILL.md` does not exist at the given path, tell the user this is not a
valid skill directory and ask them to provide the correct path.

## Step 3: Run Structural Validation

Run the full check suite:

```bash
skill-validator check <path>
```

Capture the exit code:

| Exit code | Meaning |
|-----------|---------|
| 0 | Clean — no errors or warnings |
| 1 | Errors found — must fix before publishing |
| 2 | Warnings only — review but not blocking |
| 3 | CLI/usage error — check the command |

Exit 0: proceed. Exit 2: note warnings, proceed. Exit 1: list errors — these
are blocking. The user must fix them before the skill can be published. Do NOT
proceed to LLM scoring if exit code is 1.

## Step 4: Content Review

Read the SKILL.md and any reference files, then evaluate each check below.
Report which checks pass and which do not, with specific details on what is
missing.

| Check | Criteria |
|-------|----------|
| Examples | Does the skill provide examples of expected inputs and outputs? |
| Edge cases | Does the skill document common edge cases or failure modes? |
| Scope-gating | Does the skill define when to stop/continue, prerequisites, and conditions for branching paths? |
| MongoDB data access | If the skill needs MongoDB contextual data, does it instruct agents to use the MCP server for auth and tool calls? Skip if not applicable. |

Flag any failing checks as areas the SME should address. These are not blocking
but should be resolved before publishing for best results.

## Step 5: LLM Scoring and Interpretation

If `LLM_SCORING=false`, skip to Step 6.

If `LLM_SCORING=true`, follow the "Run LLM Scoring" and "Interpret LLM Scores"
sections of
[references/llm-scoring.md](references/llm-scoring.md).

## Step 6: Present the Review Summary

If `LLM_SCORING=true`, follow the "Full Review Summary" section of
[references/llm-scoring.md](references/llm-scoring.md).
Include any failing content review checks from Step 4 in the action items.

If `LLM_SCORING=false`, present structural result, content review result,
areas to address, and a self-assessment checklist using the scoring dimensions
from [assets/report.md](assets/report.md). Note that LLM scoring was skipped;
advise re-running with LLM scoring enabled or self-assessing against the report
dimensions.

## Example Review Summary Structure

Structure the final summary with these sections in order:

1. **Structural validation** — pass/fail with errors or warnings
2. **SKILL.md scores** — overall and per-dimension table
3. **Reference scores** — per-file table with overall and lowest dimension
4. **Novelty assessment** — mean novelty vs threshold of 3; list `novel_info`
   per file for SME verification
5. **Action items** — prioritized list of what to fix
6. **Recommendation** — ready to publish / minor revisions / significant rework
