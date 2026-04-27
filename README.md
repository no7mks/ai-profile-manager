# AI Profile Manager (`aipm`)

`aipm` is a PHP CLI tool for managing AI profile items across IDE/CLI tools.
It distinguishes item types explicitly: `skill`, `rule`, and `agent`.

## Install

```bash
composer install
```

Run with:

```bash
php bin/aipm --help
```

## Usage

Install skills:

```bash
aipm skill:install graphify
```

Install rules (in Kiro, rule is treated as steering):

```bash
aipm rule:install spec-core -t kiro
```

Install agents:

```bash
aipm agent:install gatekeeper flow-finisher
```

Install preset (top-level `install` is preset-only):

```bash
aipm install gitflow
```

```bash
aipm install kiro-spec -t kiro
```

Check typed items (placeholder semantics):

```bash
aipm skill:check graphify -t cursor
```

```bash
aipm rule:check spec-core -t kiro
```

```bash
aipm agent:check gatekeeper -t cursor
```

Capture typed item drifts (placeholder semantics):

```bash
aipm skill:capture graphify -t cursor
```

```bash
aipm rule:capture spec-core -t kiro
```

```bash
aipm agent:capture gatekeeper -t cursor
```

Check and capture preset:

```bash
aipm check gitflow -t cursor
```

```bash
aipm capture kiro-spec -t kiro
```

Update knowledge base:

```bash
aipm update
```

## Presets

- `gitflow`
  - skills: `gitflow`
  - agents: `flow-starter`, `flow-finisher`
- `kiro-spec`
  - skills: `kiro-spec-planning`, `kiro-spec-execution`
  - rules: `kiro-spec-steering`
  - agents: `gatekeeper`

## Current defaults

- Default skills: `graphify`
- Default rules: `(none)`
- Default agents: `(none)`
- Default target: `cursor`

## Check/Capture status model

Current `check`/`capture` behavior is placeholder-only. Status values are:

- `unchanged`
- `modified`
- `missing`
- `unknown`

Exit codes:

- `0`: all unchanged
- `2`: modified or missing exists
- `1`: command/validation error
