# Parser Source Files — Creation Guide

How to create **pretty**, **minified**, and **messy** parser source files that test a parser's
robustness across all whitespace and trivia positions.

---

## How to Invoke This Skill

The user provides a path to a format definition file, e.g.:

```
Na podstawie pliku packages/parser/src/Infrastructure/Grammar/Definition/Env/env.md
przygotuj pliki reprezentacyjne format i jego warianty.
```

**Your job given a format md file:**

1. Read the md file and identify: format name, variants (sorted base→extended), file extensions,
   whitespace rules, comment support, value types, token list.
2. Determine the directory slug and file slug for each variant from the variant names.
3. For **each variant** create exactly 5 files: `pretty`, `minified`, `messy`, `messy-2`, `messy-3`.
4. Write all files under `assets/parser-source-files/{format}/{variant-slug}/`.
5. Verify messy-3 files end without trailing newline: `xxd file | tail -1` — last byte must NOT be `0a`.

---

## File Naming Convention

```
{format-slug}.{variant-slug}.{extension}
```

| Segment | Rules |
|---------|-------|
| `format-slug` | `json-rfc8259`, `json-c`, `json-5`, `env-environment`, `env-dotenv`, etc. |
| `variant-slug` | `pretty`, `minified`, `messy`, `messy-2`, `messy-3`, ... |
| Numbering | Append `-2`, `-3`… never use descriptive suffixes |
| Extension | From the format spec (`.json`, `.jsonc`, `.json5`, `.env`, `.conf`, …) |

❌ Don't: `env-dotenv.messy-with-tabs.env`
✅ Do: `env-dotenv.messy-2.env`

---

## Directory Structure Pattern

```
assets/parser-source-files/{format-family}/{variant-slug}/
    {format-slug}.pretty.{ext}
    {format-slug}.minified.{ext}
    {format-slug}.messy.{ext}
    {format-slug}.messy-2.{ext}
    {format-slug}.messy-3.{ext}
```

**JSON example** (three variants → three subdirs):
```
assets/parser-source-files/json/
├── rfc8259/
│   ├── json-rfc8259.pretty.json
│   ├── json-rfc8259.minified.json
│   ├── json-rfc8259.messy.json
│   ├── json-rfc8259.messy-2.json
│   └── json-rfc8259.messy-3.json
├── c/
│   └── …
└── 5/
    └── …
```

**Env example** (two variants → two subdirs):
```
assets/parser-source-files/env/
├── environment/
│   ├── env-environment.pretty.conf
│   ├── env-environment.minified.conf
│   ├── env-environment.messy.conf
│   ├── env-environment.messy-2.conf
│   └── env-environment.messy-3.conf
└── dotenv/
    ├── env-dotenv.pretty.env
    ├── env-dotenv.minified.env
    ├── env-dotenv.messy.env
    ├── env-dotenv.messy-2.env
    └── env-dotenv.messy-3.env
```

**Subdir slug derivation:** use the variant name after the dash in the format-slug if short
(`rfc8259` → `rfc8259`, `json-c` → `c`, `json-5` → `5`, `env-dotenv` → `dotenv`).
When ambiguous, pick the shortest meaningful word from the variant name.

---

## The Five File Variants

### pretty
- Well-formatted, readable, canonical whitespace
- Grouped into sections with comment headers (if comments supported)
- Every value type and feature from the format spec represented at least once
- Demonstrates the intended human-readable style

### minified
- Minimal whitespace — as compact as the format allows
- No blank lines, no comments (exception: if comments are a distinguishing format feature — see below)
- All value types still present
- For line-based formats (env, ini, …): no blank lines, no comment lines, assignments on
  consecutive lines with no spaces around separators

### messy
- Valid format, chaotic whitespace and trivia placement
- **MUST have leading trivia** (before first meaningful token)
- **MUST have trailing trivia** (after last meaningful token)
- Mixes excessive and missing whitespace throughout

### messy-2
- Same rules as messy, different chaos pattern
- Different indentation style, different trivia placement
- **MUST have leading trivia** and **MUST have trailing trivia**

### messy-3 — No Trailing Newline
- **MUST have leading trivia** (mandatory like all messy variants)
- Ends **immediately after the last byte of the last meaningful token** — no `\n`, no spaces, nothing
- Verify: `xxd file | tail -1` — last byte must NOT be `0a` (LF)
- Use `printf '%s' "..."` (not `echo`) to write this file, to avoid automatic newline

---

## Key Concept: Trivia

**Trivia** = whitespace characters and comments that appear between meaningful tokens.
The format grammar allows trivia between any two tokens. A robust parser must handle
trivia regardless of quantity, variety, or position.

### Leading Trivia (Critical)
Trivia that appears **before the root value** (before the first `{`, `[`, `"`, `KEY=`, etc.).

> ⚠️ **Every messy file MUST have leading trivia.**
> Pretty and minified start with the root value directly. A messy file that also starts with
> the root value duplicates their coverage and tests nothing new.
> Leading trivia is the unique contribution of the messy variant.

### Trailing Trivia (Critical)
Trivia that appears **after the root value** (after the closing `}`, `]`, last assignment, etc.).

> ⚠️ **Every messy file (except messy-3) MUST have trailing trivia.**
> Pretty and minified end immediately after the root value. Messy files must have trailing
> whitespace or comments to cover that position.

### No Trailing Newline (Critical)
A file that ends **immediately after the last byte of the root value**, with no `\n` and no
trailing whitespace or comments whatsoever.

> ⚠️ **Every format must have exactly one "no trailing newline" file.**
> This is the only messy variant that is exempt from the trailing trivia rule.
> It must still have **leading trivia** (mandatory for all messy variants).
> Verify with: `xxd file | tail -1` — the last byte must NOT be `0a` (LF).

---

## Whitespace and Comment Rules by Format Family

### JSON family

| Format | Allowed Whitespace (between tokens) | Spec Reference |
|--------|--------------------------------------|----------------|
| RFC 8259 | `%x20` SP, `%x09` TAB, `%x0A` LF, `%x0D` CR | RFC 8259 §2 `ws` rule |
| JSONC | same as RFC 8259 | inherits JSON |
| JSON5 | RFC 8259 + `\v` VT `\f` FF ` ` NBSP `﻿` BOM + Zs Unicode | ECMA-262 WhiteSpace |

| Format | Supported Comment Types |
|--------|------------------------|
| RFC 8259 | none |
| JSONC | `//` single-line (ends at line terminator), `/* */` block |
| JSON5 | `//` single-line, `/* */` block (same as JSONC) |

### Env family

| Format | Allowed Whitespace (trivia positions) | Comment Types |
|--------|--------------------------------------|---------------|
| environment | blank lines, spaces/tabs around `=`, leading spaces before KEY | `#` line comments |
| dotenv | blank lines, spaces/tabs around `=`, leading spaces before KEY | `#` line comments |

**Leading trivia for line-based formats** = blank lines + comment lines before the first assignment.
**Trailing trivia for line-based formats** = blank lines + comment lines after the last assignment.

---

## Minified Files with Comments

"Minified" means minimal whitespace. For formats that support comments **as a distinguishing
feature** (JSONC, JSON5), the minified file **must still include at least one of each comment
type** — stripping all comments would make it indistinguishable from the base format.

- Prefer `/* */` block comments over `//` single-line in minified form, because `//` forces a
  line break after it, making the file multiline even when everything else is compact.
- Keep comments short and inline: `[/* before */"first","second"/* after */]`

For env formats, comments are **not** a distinguishing feature between variants (both
`environment` and `dotenv` support `#`), so the minified file omits all comments.

---

## Messy Techniques by Format Type

### Nested/Structured Formats (JSON, XML, …)
- No indentation where expected: keys flush left at root level
- Excessive indentation: 15+ spaces for a key with no structural reason
- Space(s) **before** separator: `"key" : "value"` or `"key"   : "value"`
- Excessive spaces **after** separator: `"key":              "value"`
- Value on **next line** after separator
- Multiple pairs on **one line**: `"a":1,"b":2,"c":true`
- Compact arrays where pretty would expand
- Inline objects where pretty would expand

### Comment Chaos (JSONC / JSON5)
- Comment **before** the opening `{` or `[` (leading comment trivia)
- Block comment **between key and colon**: `"key"/* comment */: "value"`
- Block comment **between colon and value**: `"key":/* comment */"value"`
- `//` comment inside an array element list (forces a newline after it)
- Comment **after the closing** `}` (trailing comment trivia)

### Line-Based Formats (env, ini, …)
- Spaces before `KEY`: `   KEY=value`, `\t\tKEY=value`
- Spaces around `=`: `KEY = value`, `KEY  =  value`, `KEY= value`
- Excessive spaces after `=`: `KEY=          value`
- Random blank lines between assignments (0–3 blank lines, irregular)
- Comment lines interspersed mid-section
- Mix of no-space and multi-space assignments in the same file
- Tab characters as leading whitespace before KEY

---

## Leading / Trailing Trivia Patterns

### Structured formats (JSON, …)

| Format | Leading trivia example |
|--------|----------------------|
| RFC 8259 | `\n\n\t   \n{...}` — blank lines and mixed whitespace before `{` |
| JSONC | `// comment\n/* block */\n{...}` — comments before `{` |
| JSON5 | same as JSONC; optionally `﻿` BOM as very first byte |

| Format | Trailing trivia example |
|--------|------------------------|
| RFC 8259 | `{...}\n\n   \t` — blank lines and spaces after `}` |
| JSONC | `{...}// trailing comment` — comment after `}` |
| JSON5 | `{...}/* trailing */` — block comment after `}` |

### Line-based formats (env, …)

| Format | Leading trivia example |
|--------|----------------------|
| environment | `\n\n# comment\n\nKEY=value` — blank lines + comment before first assignment |
| dotenv | same as environment |

| Format | Trailing trivia example |
|--------|------------------------|
| environment | `LAST=val\n\n# comment\n\n` — blank lines + comment after last assignment |
| dotenv | same as environment |

---

## Deep Nesting Requirement

Applies to **structured (nestable) formats only** (JSON, XML, YAML, …). Skip for flat formats
(env, ini, CSV, …) — the format spec's `Nestable: ❌` flag indicates this requirement does not apply.

For nestable formats, all pretty/minified/messy files must include deep nesting:
objects inside arrays inside objects, at minimum **5 levels** deep.

```json
"deepNesting": {
    "level1": {
        "value": "L1",
        "level2": {
            "value": "L2",
            "level3": {
                "value": "L3",
                "level4": {
                    "value": "L4",
                    "level5": {
                        "value": "L5",
                        "arrayOfObjects": [
                            {"item": 1, "nested": {"deep": true}},
                            {"item": 2, "matrix": [[1,2,3],[4,5,[6,7,[8]]]]}
                        ]
                    }
                }
            }
        }
    }
}
```

---

## Coverage Requirements

Each format variant's 5 files must collectively cover:

1. **All value types** valid for that variant (read them from the md's token table and examples)
2. **Deep nesting** — only if format is nestable (≥ 5 levels)
3. **Leading trivia** — EVERY messy file must start with whitespace/comments before the first token
4. **Trailing trivia** — every messy file (except messy-3) must end with whitespace/comments
5. **No trailing newline** — exactly one file (messy-3) ends at the last byte of the last token
6. **All whitespace character types** allowed by the format
7. **Comments in all valid positions** — if the format supports comments

For **superset variants** (e.g. dotenv extends environment): the superset file must additionally
cover every feature that distinguishes it from the base (quoted strings, advanced expansions, etc.).

---

## Extracting Rules from the Format md File

When given a format md file, extract these fields before writing any files:

| What to extract | Where to find it in the md |
|-----------------|---------------------------|
| Variant names and order | `Variants sorted from most basic to most extended:` line |
| File extension per variant | Look for extension in the variant description or examples |
| Whitespace rules | `Format Features` table — `Indentation Sensitive`, `Line Sensitive` rows |
| Comment support | `Comments Support` and `Docblock Support` rows |
| Nestable | `Nestable` row — if ❌, skip deep nesting requirement |
| Value types | `Token Descriptions` section — value token patterns |
| Expansion types | Expansion token tables, split by base vs. extended variant |
| Distinguishing features per variant | `Variant-specific example` subsections |

**Variant slug for directory name:** use the part after the last `-` in the format-slug
(e.g. `env-environment` → dir `environment`, `env-dotenv` → dir `dotenv`).

---

## Writing messy-3 Without Trailing Newline

Always use `printf '%s'` (not `echo`, not the Write tool alone) to guarantee no trailing newline:

```bash
printf '%s' "content without trailing newline" > path/to/file

# Verify:
xxd path/to/file | tail -1
# Last byte must NOT be 0a
```

Dollar signs in shell strings must be escaped: `\$VAR`, `\${VAR}`.

---

## Validation Commands

| Format | Validation command |
|--------|--------------------|
| RFC 8259 | `python3 -c "import json; json.load(open('file.json'))"` |
| JSONC | Strip `//…` and `/*…*/` comments, then validate as JSON |
| JSON5 | Use a JSON5 parser (e.g. `json5` npm, or PHP `colinodell/json5`) |
| env / dotenv | Manual review; `dotenv-linter` for lint checks |
