# Installing skill-validator

## Installation methods

### Homebrew (recommended for macOS)

```bash
brew tap agent-ecosystem/homebrew-tap
brew install skill-validator
```

### From source (requires Go 1.25.5+)

```bash
go install github.com/agent-ecosystem/skill-validator/cmd/skill-validator@latest
```

Ensure `$GOPATH/bin` (usually `~/go/bin`) is on your PATH:

```bash
export PATH="$PATH:$(go env GOPATH)/bin"
```

### From a pre-built binary

```bash
cp /path/to/skill-validator /usr/local/bin/ && chmod +x /usr/local/bin/skill-validator
```

## Verify installation

```bash
skill-validator --version
```

## Prerequisites for LLM scoring

LLM scoring uses the `claude-cli` provider, which shells out to the locally
installed `claude` binary. No API keys are needed — it uses the CLI's existing
authentication.

1. **Claude Code CLI** (`claude --version`) — install with
   `curl -fsSL https://claude.ai/install.sh | bash` on macOS, or follow the
   [Claude Code quickstart guide](https://code.claude.com/docs/en/quickstart)
   for other platforms
2. **Authenticated session** — run `claude` interactively to complete login if
   not yet authenticated
