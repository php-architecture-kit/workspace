# Parser CLI Commands

The parser package exposes four development/debug commands via `bin/console`.

**Example grammar class used throughout this document:**
```
PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259
```

---

## `parser:grammar:view`

Display the raw (pre-compilation) grammar definition structure.

```
bin/console parser:grammar:view <grammar-class> [options]
```

### Arguments

| Argument | Required | Description |
|---|---|---|
| `grammar-class` | yes | Fully qualified class name implementing `GrammarDefinitionInterface` |

### Options

| Option | Short | Description |
|---|---|---|
| `--region=NAME` | `-r` | Show only one specific region |
| `--show-rules` | | Show detailed rule definitions (regex, type, priority, tags) |
| `--hide-middlewares` | | Hide middleware information |
| `--hide-event-subscribers` | | Hide event subscriber information |
| `--show-tags` | | Show all tags and what rules/regions carry them |

### Examples

```bash
# Basic overview
bin/console parser:grammar:view "PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259"

# Show rules for a single region
bin/console parser:grammar:view "..." --region=object --show-rules

# Full view with tags
bin/console parser:grammar:view "..." --show-rules --show-tags
```

---

## `parser:grammar:compiled`

Display the compiled grammar — patterns compiled to regex, sequences sorted by priority, event subscribers resolved.

```
bin/console parser:grammar:compiled <grammar-class> [options]
```

### Arguments

| Argument | Required | Description |
|---|---|---|
| `grammar-class` | yes | Fully qualified class name implementing `GrammarDefinitionInterface` |

### Options

| Option | Short | Description |
|---|---|---|
| `--region=NAME` | `-r` | Show only one specific compiled region |
| `--show-patterns` | | Show pattern library (token name → regex, priority, tags) |
| `--show-sequences` | | Show sequence library (matching rules with cardinalities) |
| `--show-event-subscribers` | | Show registered event subscribers |
| `--show-tags` | | Show tag index (which patterns and sequences carry a tag) |

### Examples

```bash
# Overview of compiled regions
bin/console parser:grammar:compiled "..."

# Inspect patterns and sequences for one region
bin/console parser:grammar:compiled "..." --region=json --show-patterns --show-sequences

# Full compiled dump
bin/console parser:grammar:compiled "..." --show-patterns --show-sequences --show-event-subscribers --show-tags
```

---

## `parser:tokenize`

Run the lexer (tokenizer) on an input file and display the token stream.

```
bin/console parser:tokenize <grammar-class> <input-file> [options]
```

### Arguments

| Argument | Required | Description |
|---|---|---|
| `grammar-class` | yes | Fully qualified class name implementing `GrammarDefinitionInterface` |
| `input-file` | yes | Path to the file to tokenize |

### Options

| Option | Short | Description |
|---|---|---|
| `--output=FILE` | `-o` | Write output to a file instead of stdout |
| `--format=FORMAT` | `-f` | Output format: `detailed` (default), `simple`, `stats` |
| `--no-row-col` | | Disable row/column position tracking |

### Output formats

- **`detailed`** — Full token list with positions (absolute + row:col), nested regions displayed as box-drawing frames, token statistics header.
- **`simple`** — Token names only, one per line.
- **`stats`** — Aggregate statistics: token counts by type and region occurrence counts.

The command exits with code `1` if any `unknown` tokens are present in the output (indicates the grammar does not fully cover the input).

### Examples

```bash
# Default detailed output
bin/console parser:tokenize "..." input.json

# Statistics only
bin/console parser:tokenize "..." input.json --format=stats

# Save detailed output to file, without row/col tracking
bin/console parser:tokenize "..." input.json --output=tokens.txt --no-row-col
```

---

## `parser:parse`

Run the full pipeline (tokenize → match → parse) and display the resulting Node tree.

```
bin/console parser:parse <grammar-class> <input-file> [options]
```

### Arguments

| Argument | Required | Description |
|---|---|---|
| `grammar-class` | yes | Fully qualified class name implementing `GrammarDefinitionInterface` |
| `input-file` | yes | Path to the file to parse |

### Options

| Option | Short | Description |
|---|---|---|
| `--output=FILE` | `-o` | Write output to a file instead of stdout |
| `--format=FORMAT` | `-f` | Output format: `tree` (default), `json`, `simple` |
| `--max-depth=N` | `-d` | Limit tree display depth (tree format only) |

### Output formats

- **`tree`** — ASCII tree with node types, attribute names, content previews (50 chars), meta and tag annotations.
- **`json`** — Recursive JSON representation of the node tree. Meta values that contain PHP objects are simplified to their `name` property or string representation.
- **`simple`** — Reconstructed source string (concatenation of all leaf values via `__toString()`).

### Examples

```bash
# Tree view (default)
bin/console parser:parse "..." input.json

# Limit depth to 3 levels
bin/console parser:parse "..." input.json --max-depth=3

# JSON output to file
bin/console parser:parse "..." input.json --format=json --output=tree.json

# Verify round-trip (output should equal input)
bin/console parser:parse "..." input.json --format=simple
```

---

## Notes

- All commands require the grammar class to be autoloadable (i.e. present in `vendor/autoload.php`).
- Grammar classes must implement `PhpArchitecture\Parser\Foundation\Grammar\Contract\GrammarDefinitionInterface`.
- The built-in example grammar is `PhpArchitecture\Parser\Infrastructure\Grammar\Definition\Json\JsonRfc8259` (JSON RFC 8259).
