# Env

## Official Documentation & Specifications

Variants sorted from most basic to most extended: **environment** → **dotenv**

**Variant Conflicts:** None. Dotenv is a strict superset of environment format. Linear extension pipeline:
```
environment → dotenv (no conflicts)
```

---

### environment - Linux/systemd Standard

The environment.d format is the standard Linux/systemd configuration format for defining user service environment variables. It uses simple `KEY=VALUE` assignments with support for basic variable expansion (`$VAR` and `${VAR}`) and default values (`${VAR:-default}`). Files are placed in `/etc/environment.d/` or `~/.config/environment.d/` with `.conf` extension. This format is parsed by systemd-environment-d-generator and is used across all Linux distributions using systemd.

| Status | Document | Description |
|--------|----------|-------------|
| ✅ 200 | [environment.d(5)](https://www.man7.org/linux/man-pages/man5/environment.d.5.html) | Linux man page for environment.d configuration format (systemd, actively maintained) |
| ✅ 200 | [POSIX Shell](https://pubs.opengroup.org/onlinepubs/9699919799/utilities/V3_chap02.html) | POSIX shell specification (authoritative reference for expansion) |
| ✅ 200 | [environ(7)](https://man7.org/linux/man-pages/man7/environ.7.html) | Linux man page for environment variables (general reference) |

**Character Encoding Support:**

| Element | Encoding | Allowed Characters | Reference | Evidence | Confirmed |
|---------|----------|-------------------|-----------|----------|-----------|
| Keys | ASCII | `[a-zA-Z_][a-zA-Z0-9_]*` | environment.d(5) | [man#CONFIGURATION_FORMAT](https://www.man7.org/linux/man-pages/man5/environment.d.5.html) "valid variable name" | ✅ verified |
| Values | UTF-8 | any printable, no quoting | environment.d(5) | [man#CONFIGURATION_FORMAT](https://www.man7.org/linux/man-pages/man5/environment.d.5.html) "KEY=VALUE" | ✅ verified |

**Format Features:**

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ✅ | [vlucas/phpdotenv](https://packagist.org/packages/vlucas/phpdotenv) (570M+ downloads) |
| PHP Emitting | ✅ | Manual `KEY=value` or custom serializer |
| AST Library | ❌ | No dedicated AST library |
| Line Sensitive | ✅ | Each line is one assignment; cannot minify |
| Nestable | ❌ | Flat key-value pairs only |
| Indentation Sensitive | ❌ | Free-form whitespace around `=` |
| Comments Support | ✅ | Hash comments: `# comment` |
| Docblock Support | ❌ | No structured documentation |
| Multi-document | ❌ | Single file = single environment |
| Schema Support | ❌ | No schema validation |

**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Document → lines | `\n` | optional | ❌ | `KEY1=val1\nKEY2=val2` |

**Variant-specific example** (basic KEY=VALUE, simple expansion, default expansion):
```bash
# Common usage - simple assignment
MY_VAR=value
DEBUG=true
PORT=8080

# /etc/environment.d/60-myapp.conf - systemd example
PATH=/opt/myapp/bin:$PATH
LD_LIBRARY_PATH=/opt/myapp/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}
XDG_DATA_DIRS=/opt/myapp/share:${XDG_DATA_DIRS:-/usr/local/share/:/usr/share/}
```

**Variant Summary:**
The environment.d format is the standard Linux/systemd mechanism for configuring user service environment variables. It is used across all Linux distributions running systemd, parsed by systemd-environment-d-generator at user login. Essential for system-level configuration.
**Recommendation: ✅ MUST KEEP** - Foundational Linux/systemd standard, used on every systemd-based distribution.

---

### dotenv - Application Configuration

Dotenv is a widely adopted convention for storing application configuration in `.env` files. Originally created for Ruby (bkeepers/dotenv) and popularized by Node.js (motdotla/dotenv), it extends the basic environment format with additional features: double-quoted strings with escape sequences, single-quoted literal strings, advanced expansions (`:=`, `:+`, `:?`), nested expansions, and line continuations. Dotenv files are typically placed in project root directories and loaded at application startup. Used by millions of applications across all major programming languages.

| Status | Document | Description |
|--------|----------|-------------|
| ✅ 200 | [php-xdg/dotenv-spec](https://github.com/php-xdg/dotenv-spec) | POSIX-compliant dotenv file format specification (**formal spec**) |
| ✅ 200 | [motdotla/dotenv](https://github.com/motdotla/dotenv) | Original Node.js dotenv implementation (de facto standard, 18M+ weekly downloads) |
| ✅ 200 | [bkeepers/dotenv](https://github.com/bkeepers/dotenv) | Ruby dotenv implementation (original implementation) |

**Character Encoding Support:**

| Element | Encoding | Allowed Characters | Reference | Evidence | Confirmed |
|---------|----------|-------------------|-----------|----------|-----------|
| Keys | ASCII | `[a-zA-Z_][a-zA-Z0-9_]*` | php-xdg/dotenv-spec | [syntax.md#identifiers](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#identifiers) | ✅ verified |
| Values (unquoted) | UTF-8 | printable, no whitespace/special | php-xdg/dotenv-spec | [syntax.md#unquoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#unquoted-strings) | ✅ verified |
| Values (single-quoted) | UTF-8 | any Unicode, literal | php-xdg/dotenv-spec | [syntax.md#single-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#single-quoted-strings) | ✅ verified |
| Values (double-quoted) | UTF-8 | any Unicode + escapes | php-xdg/dotenv-spec | [syntax.md#double-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#double-quoted-strings) | ✅ verified |
| File encoding | UTF-8 | - | php-xdg/dotenv-spec | [syntax.md#syntax-overview](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#syntax-overview) "MUST be UTF-8" | ✅ verified |

**Format Features:**

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ✅ | [vlucas/phpdotenv](https://packagist.org/packages/vlucas/phpdotenv) (570M+ downloads) |
| PHP Emitting | ✅ | Manual string generation or custom serializer |
| AST Library | ❌ | No dedicated AST library; [php-xdg/dotenv](https://packagist.org/packages/php-xdg/dotenv) parses to array |
| Line Sensitive | ✅ | Each line is one assignment; line continuation with `\` |
| Nestable | ❌ | Flat key-value pairs only |
| Indentation Sensitive | ❌ | Free-form whitespace |
| Comments Support | ✅ | Hash comments: `# comment` |
| Docblock Support | ❌ | No structured documentation |
| Multi-document | ❌ | Single file = single environment |
| Schema Support | ❌ | No formal schema; [dotenv-linter](https://github.com/dotenv-linter/dotenv-linter) for linting |

**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Document → lines | `\n` | optional | ❌ | `KEY1=val1\nKEY2=val2` |
| Multiline value | `\\\n` | forbidden | ❌ | `"line1\\\nline2"` |

**Variant-specific example** (quoted strings, escapes, advanced expansions, unicode):
```bash
# .env - dotenv extensions beyond environment.d

# Quoted strings with escapes
SINGLE_QUOTED='literal $VAR not expanded'
DOUBLE_QUOTED="string with \"escapes\" and $VAR expansion"
MULTILINE="first line\
second line"

# Advanced expansions (dotenv-only)
DEFAULT=${VAR:-fallback}
ALTERNATE=${DEBUG:+--verbose}
ASSIGN=${CACHE:=/tmp/cache}
ERROR=${API_KEY:?API key is required}
NESTED=${PRIMARY:-${FALLBACK:-ultimate}}

# Unicode in values (fully supported in quoted strings)
POLISH="Zażółć gęślą jaźń"
CYRILLIC="Привет мир"
CHINESE="你好世界"
JAPANESE="こんにちは世界"
ARABIC="مرحبا بالعالم"
EMOJI="🚀 Deploy 💻 Code 🔧 Fix"
MIXED="Hello Świat Мир 世界 🌍"
```

**Variant Summary:**
Dotenv is the de facto standard for application configuration across all major programming languages. The Node.js implementation (motdotla/dotenv) has 18M+ weekly downloads. Used in virtually every modern web application, Docker containers, CI/CD pipelines, and cloud deployments. The `.env` file pattern is universally recognized.
**Recommendation: ✅ MUST KEEP** - Universal adoption across all ecosystems, essential for application configuration.

---

## Example

Based on the most extended form from env family: **dotenv**

```bash
# Comment explaining the configuration
# This is a dotenv file with all features

# === BASIC ASSIGNMENTS ===
SIMPLE_KEY=value
ANOTHER_KEY=123
EMPTY_VALUE=
PATH=/usr/local/bin:/usr/bin:/bin

# === QUOTED VALUES ===
SINGLE_QUOTED='literal string with $VAR not expanded'
DOUBLE_QUOTED="string with \"escaped quotes\""
DOUBLE_WITH_ESCAPES="line1\nline2\ttab"
MULTILINE="first line\
second line\
third line"

# === VARIABLE EXPANSION ===
# Simple expansion
SIMPLE_EXPANSION=$HOME
ANOTHER=$PATH

# Braced expansion
BRACED_EXPANSION=${HOME}
BRACED_PATH=${PATH}

# === DOTENV ADVANCED EXPANSIONS ===
# Default value expansion (use default if VAR is unset or empty)
DEFAULT_EXPANSION=${VAR:-default_value}
PORT=${PORT:-8080}

# Alternate value expansion (use alternate if VAR is set)
ALTERNATE_EXPANSION=${DEBUG:+--verbose}
LOG_LEVEL=${VERBOSE:+debug}

# Assign default expansion (assign default if VAR is unset)
ASSIGN_EXPANSION=${CACHE_DIR:=/tmp/cache}

# Error expansion (error if VAR is unset)
ERROR_EXPANSION=${API_KEY:?API key is required}

# Nested expansion
NESTED=${PRIMARY:-${FALLBACK:-ultimate_default}}

# === SPECIAL CASES ===
SPECIAL_CHARS=foo:bar:baz
URL=https://example.com/path?query=value
WITH_SPACES="value with spaces"
UNICODE="utf8: äöü ñ 中文"

# === LINE CONTINUATION ===
LONG_VALUE="this is a very long value \
that continues on the next line \
and even further"

# Inline comment (if supported)
KEY=value # this may or may not be a comment depending on implementation
```

### Example Coverage Validation

Based on the most extended variant: **dotenv**

| Feature Category | Feature | Covered | Location in Example |
|-----------------|---------|---------|---------------------|
| Basic Assignment | Simple `KEY=value` | ✅ | line 5: `SIMPLE_KEY=value` |
| Basic Assignment | Numeric value | ✅ | line 6: `ANOTHER_KEY=123` |
| Basic Assignment | Empty value `KEY=` | ✅ | line 7: `EMPTY_VALUE=` |
| Basic Assignment | Path with colons | ✅ | line 8: `PATH=/usr/local/bin:...` |
| Quoted Values | Single quotes (literal) | ✅ | line 11: `SINGLE_QUOTED='...'` |
| Quoted Values | Double quotes | ✅ | line 12: `DOUBLE_QUOTED="..."` |
| Quoted Values | Escaped quotes `\"` | ✅ | line 12: `\"escaped quotes\"` |
| Quoted Values | Escape `\n` | ✅ | line 13: `\n` |
| Quoted Values | Escape `\t` | ✅ | line 13: `\t` |
| Quoted Values | Line continuation `\` | ✅ | lines 14-16: multiline |
| Expansion | Simple `$VAR` | ✅ | line 20: `$HOME` |
| Expansion | Braced `${VAR}` | ✅ | line 24: `${HOME}` |
| Expansion | Default `${VAR:-default}` | ✅ | line 29: `${VAR:-default_value}` |
| Expansion | Alternate `${VAR:+alt}` | ✅ | line 33: `${DEBUG:+--verbose}` |
| Expansion | Assign `${VAR:=default}` | ✅ | line 37: `${CACHE_DIR:=/tmp/cache}` |
| Expansion | Error `${VAR:?msg}` | ✅ | line 40: `${API_KEY:?...}` |
| Expansion | Nested expansions | ✅ | line 43: `${PRIMARY:-${FALLBACK:-...}}` |
| Special | URL with special chars | ✅ | line 47: `URL=https://...` |
| Special | Unicode characters | ✅ | line 49: `UNICODE="utf8: äöü..."` |
| Comments | Line comment `#` | ✅ | line 1: `# Comment...` |
| Comments | Inline comment | ✅ | line 57: `KEY=value # comment` |

**Coverage: 21/21 features (100%)** ✅

### Separated Lists Coverage

| List Type | Demonstrated | Location in Example |
|-----------|--------------|---------------------|
| Document → lines | ✅ | entire file (57 lines) |
| Multiline value (continuation) | ✅ | lines 14-16, lines 52-54 |

**Edge cases:**
- Empty list (single line file): Not shown, but valid
- Single element: ✅ each line is single assignment

**Separated Lists Coverage: 2/2 (100%)** ✅

---

## All Possible Document Root Values

Env files are line-based. The document root is a sequence of lines, where each line can be one of:

### Empty Line
```bash

```

### Comment Line
```bash
# This is a comment
```

### Assignment Line
```bash
KEY=value
```

### Assignment with Quoted Value
```bash
KEY="quoted value"
KEY='single quoted'
```

### Assignment with Expansion
```bash
KEY=$OTHER_VAR
KEY=${VAR:-default}
```

### Summary of Root Line Types

| Type | Examples |
|------|----------|
| Empty | `` (blank line) |
| Comment | `# comment` |
| Assignment | `KEY=value` |
| Quoted Assignment | `KEY="value"`, `KEY='value'` |
| Expansion Assignment | `KEY=$VAR`, `KEY=${VAR:-default}` |

### Root Values Validation

Based on the most extended variant: **dotenv**

| Root Type | Minimal Valid Example | Spec Reference | Validated |
|-----------|----------------------|----------------|-----------|
| Empty file | `` (0 bytes) | php-xdg/dotenv-spec | ✅ |
| Empty line | `\n` | php-xdg/dotenv-spec | ✅ |
| Comment only | `# comment` | [syntax.md#comments](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#comments) | ✅ |
| Simple assignment | `K=v` | [syntax.md#assignments](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#assignments) | ✅ |
| Empty value | `K=` | [syntax.md#assignments](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#assignments) | ✅ |
| Single-quoted | `K=''` | [syntax.md#single-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#single-quoted-strings) | ✅ |
| Double-quoted | `K=""` | [syntax.md#double-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#double-quoted-strings) | ✅ |
| Simple expansion | `K=$V` | [syntax.md#expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#expansions) | ✅ |
| Braced expansion | `K=${V}` | [syntax.md#expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#expansions) | ✅ |

**Notes:**
- All line types can be mixed in any order within a file
- File MUST be UTF-8 encoded

**Root Values Validation: 9/9 (100%)** ✅

---

## Format Structure Groups

Logical groupings of structural elements in dotenv format.
Each element shows its structure in isolation and specifies valid parent context.

---

### 1. Document Root

The top-level container. Parent: **none** (entry point)

#### Root
```bash
<Lines>
```
Where `<Lines>` is a sequence of: Empty, Comment, or Assignment lines.

---

### 2. Line Structures

#### Empty Line
Parent: **Root**
```bash

```

#### Comment Line
Parent: **Root**
```bash
#<CommentText>
```

#### Assignment Line
Parent: **Root**
```bash
<Key><Equals><Value>
```

---

### 3. Key Structure

Parent: **Assignment**

#### Identifier
```bash
KEY
_PRIVATE
MY_VAR_123
```
Pattern: starts with letter or underscore, followed by letters, digits, or underscores.

---

### 4. Separator

Parent: **Assignment**

#### Equals Sign
```bash
=
```

---

### 5. Value Structures

Parent: **Assignment**

#### Unquoted Value
```bash
simple_value
123
/path/to/file
foo:bar:baz
```

#### Single Quoted Value
```bash
'literal string'
'$VAR not expanded'
'no escapes'
```

#### Double Quoted Value
```bash
"string with expansion"
"escaped \"quotes\""
"line1\nline2"
```

#### Empty Value
```bash

```
(nothing after `=`)

---

### 6. Expansion Structures

Parent: **Value** (inside double quotes or unquoted)

#### Simple Expansion
```bash
$VAR
$HOME
```

#### Braced Expansion
```bash
${VAR}
${HOME}
```

#### Default Expansion (dotenv)
```bash
${VAR:-default}
${PORT:-8080}
```

#### Alternate Expansion (dotenv)
```bash
${VAR:+alternate}
${DEBUG:+--verbose}
```

#### Assign Expansion (dotenv)
```bash
${VAR:=default}
```

#### Error Expansion (dotenv)
```bash
${VAR:?error message}
```

#### Nested Expansion (dotenv)
```bash
${A:-${B:-fallback}}
```

---

### 7. Comment Structures

Parent: **Root** (as line) or **Assignment** (inline, implementation-dependent)

#### Line Comment
```bash
# Full line comment
```

---

### 8. Whitespace Structures

Parent: **any position** (between tokens, around `=`)

#### Standard Whitespace
```
<space>
<tab>
```

#### Line Continuation (dotenv)
```bash
\<newline>
```

---

### Structure Hierarchy Summary

```
Root
├── EmptyLine
├── CommentLine
│   └── CommentText
└── AssignmentLine
    ├── Key (Identifier)
    ├── Equals
    └── Value
        ├── UnquotedValue
        ├── SingleQuotedValue
        ├── DoubleQuotedValue
        │   └── Expansion* (Simple, Braced, Default, Alternate, etc.)
        └── EmptyValue
```

### Structure Groups Summary

| Group | Elements | Valid Parents |
|-------|----------|---------------|
| Root | Document entry | none |
| Line | Empty, Comment, Assignment | Root |
| Key | Identifier | Assignment |
| Separator | Equals | Assignment |
| Value | Unquoted, SingleQuoted, DoubleQuoted, Empty | Assignment |
| Expansion | Simple, Braced, Default, Alternate, Assign, Error, Nested | Value (double-quoted or unquoted) |
| Comment | Line comment | Root |
| Whitespace | Space, Tab, Line continuation | any |

---

## Token Descriptions

Detailed token definitions for each structure group.

### Key Tokens

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_identifier` | `^[a-zA-Z_][a-zA-Z0-9_]*$` | Variable name | `PATH`, `MY_VAR`, `_PRIVATE` |

### Separator Tokens

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_equals` | `^=$` | Assignment operator | `=` |

### Value Tokens

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_unquoted-value` | ``^[^\s#='"$\\{}|&;<>()`]+$`` | Unquoted literal value | `value`, `/path`, `123` |
| `t_single-quoted-value` | `^'[^']*'$` | Single-quoted string (no expansion) | `'literal'` |
| `t_double-quoted-value` | `^"([^"]|\\.)*"$` | Double-quoted string (with escapes) | `"value"`, `"line\n"` |

### Expansion Tokens (environment)

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_simple-expansion` | `^\$[a-zA-Z_][a-zA-Z0-9_]*$` | Simple variable reference | `$HOME`, `$PATH` |
| `t_braced-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}$` | Braced variable reference | `${HOME}`, `${VAR}` |

### Expansion Tokens (dotenv extensions)

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_default-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?-[^}]*\}$` | Default if unset/empty | `${VAR:-default}`, `${VAR-default}` |
| `t_alternate-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?\+[^}]*\}$` | Alternate if set | `${VAR:+alt}`, `${VAR+alt}` |
| `t_assign-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?=[^}]*\}$` | Assign if unset | `${VAR:=default}`, `${VAR=default}` |
| `t_error-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?\?[^}]*\}$` | Error if unset | `${VAR:?msg}`, `${VAR?msg}` |
| `t_nested-expansion` | (complex) | Nested expansions | `${A:-${B}}` |

**Expansion operator variants:**
- With colon (`:`) - checks if unset OR empty
- Without colon - checks only if unset

### Comment Tokens

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_hash` | `^#$` | Comment start | `#` |
| `t_comment-text` | `^[^\n]*$` | Comment content (until newline) | `this is a comment` |

### Whitespace Tokens

| Token | Pattern | Description | Example |
|-------|---------|-------------|---------|
| `t_space` | `^ $` | Space character (U+0020) | ` ` |
| `t_tab` | `^\t$` | Tab character (U+0009) | `\t` |
| `t_newline` | `^\n$` | Line feed (U+000A) | `\n` |
| `t_line-continuation` | `^\\\n$` | Escaped newline (dotenv) | `\\\n` |

### Token Summary

| Category | Tokens | Variant |
|----------|--------|---------|
| Key | `t_identifier` | all |
| Separator | `t_equals` | all |
| Value | `t_unquoted-value`, `t_single-quoted-value`, `t_double-quoted-value` | all |
| Basic Expansion | `t_simple-expansion`, `t_braced-expansion` | environment, dotenv |
| Advanced Expansion | `t_default-expansion`, `t_alternate-expansion`, `t_assign-expansion`, `t_error-expansion`, `t_nested-expansion` | dotenv only |
| Comment | `t_hash`, `t_comment-text` | all |
| Whitespace | `t_space`, `t_tab`, `t_newline`, `t_line-continuation` | all (continuation: dotenv) |

---

## Step 24: Format Structure Groups & Tokens Validation

### Key Tokens Validation

| Token | Current Pattern | Spec Reference | Spec Grammar | Status |
|-------|-----------------|----------------|--------------|--------|
| `t_identifier` | `^[a-zA-Z_][a-zA-Z0-9_]*$` | [syntax.md#identifiers](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#identifiers) | `[a-zA-Z_][a-zA-Z0-9_]*` | ✅ Correct |

### Separator Tokens Validation

| Token | Current Pattern | Spec Reference | Spec Grammar | Status |
|-------|-----------------|----------------|--------------|--------|
| `t_equals` | `^=$` | [syntax.md#assignment-expressions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#assignment-expressions) | `=` (U+003D EQUALS SIGN) | ✅ Correct |

### Value Tokens Validation

| Token | Pattern | Spec Reference | Status |
|-------|---------|----------------|--------|
| `t_unquoted-value` | ``^[^\s#='"$\\{}|&;<>()`]+$`` | [syntax.md#unquoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#unquoted-strings) | ✅ Correct |
| `t_single-quoted-value` | `^'[^']*'$` | [syntax.md#single-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#single-quoted-strings) | ✅ Correct |
| `t_double-quoted-value` | `^"([^"]|\\.)*"$` | [syntax.md#double-quoted-strings](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#double-quoted-strings) | ✅ Correct |

### Expansion Tokens Validation

| Token | Pattern | Spec Reference | Status |
|-------|---------|----------------|--------|
| `t_simple-expansion` | `^\$[a-zA-Z_][a-zA-Z0-9_]*$` | [syntax.md#simple-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#simple-expansions) | ✅ Correct |
| `t_braced-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}$` | [syntax.md#simple-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#simple-expansions) | ✅ Correct |
| `t_default-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?-[^}]*\}$` | [syntax.md#complex-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#complex-expansions) | ✅ Correct |
| `t_alternate-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?\+[^}]*\}$` | [syntax.md#complex-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#complex-expansions) | ✅ Correct |
| `t_assign-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?=[^}]*\}$` | [syntax.md#complex-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#complex-expansions) | ✅ Correct |
| `t_error-expansion` | `^\$\{[a-zA-Z_][a-zA-Z0-9_]*:?\?[^}]*\}$` | [syntax.md#complex-expansions](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#complex-expansions) | ✅ Correct |

### Comment Tokens Validation

| Token | Pattern | Spec Reference | Status |
|-------|---------|----------------|--------|
| `t_hash` | `^#$` | [syntax.md#comments](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#comments) | ✅ Correct |
| `t_comment-text` | `^[^\n]*$` | [syntax.md#comments](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#comments) | ✅ Correct |

### Whitespace Tokens Validation

| Token | Pattern | Spec Reference | Status |
|-------|---------|----------------|--------|
| `t_space` | `^ $` | [syntax.md#syntax-overview](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#syntax-overview) | ✅ Correct (U+0020) |
| `t_tab` | `^\t$` | [syntax.md#syntax-overview](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#syntax-overview) | ✅ Correct (U+0009) |
| `t_newline` | `^\n$` | [syntax.md#syntax-overview](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#syntax-overview) | ✅ Correct (U+000A) |
| `t_line-continuation` | `^\\\n$` | [syntax.md#line-continuation](https://github.com/php-xdg/dotenv-spec/blob/main/syntax.md#line-continuation) | ✅ Correct |

### Validation Summary

| Category | Tokens | Correct | Issues |
|----------|--------|---------|--------|
| Key | 1 | 1 | 0 |
| Separator | 1 | 1 | 0 |
| Value | 3 | 3 | 0 |
| Expansion | 6 | 6 | 0 |
| Comment | 2 | 2 | 0 |
| Whitespace | 4 | 4 | 0 |

**Total: 17 tokens, 17 correct (100%)** ✅
