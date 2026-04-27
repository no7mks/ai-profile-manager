# AI Profile Manager (`aipm`)

`aipm` is a PHP CLI tool for managing AI IDE/CLI abilities such as:

- `skills`
- `steerings`
- `rules`
- `custom-agents`

## Install

```bash
composer install
```

Run with:

```bash
php bin/aipm --help
```

## Usage

Install default abilities to default targets:

```bash
aipm install
```

Install selected abilities:

```bash
aipm install skills rules
```

Install to specific targets:

```bash
aipm install -t kiro -t cursor
```

Combine both:

```bash
aipm install skills -t cursor
```

Update knowledge base:

```bash
aipm update
```

## Current defaults

- Default abilities: `skills`, `rules`
- Default target: `cursor`
