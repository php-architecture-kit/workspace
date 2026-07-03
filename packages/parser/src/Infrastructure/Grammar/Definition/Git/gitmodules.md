# Gitmodules

## Official Documentation & Specifications

### Gitmodules

Gitmodules is the configuration file Git creates in the top-level directory of a working tree to record the submodules used by a repository, written using the same syntax as `git-config`. Each submodule occupies its own `[submodule "<name>"]` subsection, declaring the mandatory `path` and `url` it should be checked out from, plus optional `update`, `branch`, `ignore`, `shallow`, and `fetchRecurseSubmodules` settings. `.gitmodules` is committed alongside the superproject so every clone shares the same submodule layout, while `submodule.<name>.*` settings in the local `.git/config` can override individual values (e.g. `active`, a different `url`) without modifying the shared file. The format is universal wherever Git submodules are used, across every hosting platform and Git client.

**Variant-specific example** (standard gitmodules syntax):
```ini
[submodule "vendor/symfony-flex"]
	path = vendor/symfony-flex
	url = https://github.com/symfony/flex.git
	branch = main

[submodule "docs"]
	path = docs
	url = ../shared-docs.git
	branch = .
	update = rebase
```

| Status | Document | Description |
|--------|----------|--------------|
| ✅ 200 | [gitmodules(5) — Git Documentation](https://git-scm.com/docs/gitmodules) | Primary specification: defining submodule properties, the seven recognized keys |
| ✅ 200 | [git-config(1) — Git Documentation](https://git-scm.com/docs/git-config) | Defines the underlying file syntax `.gitmodules` follows verbatim: comments, sections, subsections, quoting, escaping, line continuation |
| ✅ 200 | [gitsubmodules(7) — Git Documentation](https://git-scm.com/docs/gitsubmodules) | Conceptual overview of submodule behavior; clarifies which settings live in `.gitmodules` vs. only in `.git/config` (e.g. `active`) |
| ✅ 200 | [git-submodule(1) — Git Documentation](https://git-scm.com/docs/git-submodule) | Command reference; documents how `update`, `branch`, etc. are consumed by `git submodule` subcommands |
| ✅ 200 | [gitmodules(5) — kernel.org mirror](https://www.kernel.org/pub/software/scm/git/docs/gitmodules.html) | Mirror of the official man page |
| ✅ 200 | [Pro Git Book — Git Tools: Submodules](https://git-scm.com/book/en/v2/Git-Tools-Submodules) | Official community book chapter; narrative walkthrough of submodule workflows |
| ✅ 200 | [The GitHub Blog — Working with submodules](https://github.blog/open-source/git/working-with-submodules/) | Vendor tutorial/overview of submodule usage |
| ✅ 200 | [Atlassian Git Tutorial — git submodule](https://www.atlassian.com/git/tutorials/git-submodule) | Comprehensive community tutorial on submodule workflows |
| ✅ 200 | [W3Schools — Git Submodules](https://www.w3schools.com/git/git_submodules.asp) | Beginner-friendly submodule guide |

**Variants:** Only one variant exists — **gitmodules** (no conflicts). It is not an independent grammar: it is a semantically-restricted dialect of the generic `git-config` file syntax (same lexer/parser shape as `.git/config`), conventionally limited to `[submodule "<name>"]` subsections and the seven keys listed above.

### Variant Summary

**Gitmodules**

**Adoption:** Universal wherever Git submodules are used — every repository that uses `git submodule add` gets a committed `.gitmodules` file. Supported identically by all Git clients and hosting services (GitHub, GitLab, Bitbucket, etc.).

**Key Features:**
- `git-config` syntax: sections, quoted/dotted/bare subsections, comments (`#`, `;`), quoted and unquoted values, backslash escapes, line continuation
- Seven recognized keys: `path`, `url`, `branch`, `update`, `ignore`, `shallow`, `fetchRecurseSubmodules`
- One subsection per submodule, keyed by submodule name
- Shared/committed file — local overrides live in `.git/config`, not here

**Recommendation:** ✅ **STRONGLY RECOMMENDED** — Required file whenever a repository uses Git submodules. Cross-platform and universally supported.

### Variant Conflicts

**Analysis:** No conflicts exist — gitmodules has only one variant. The only nuance is its relationship to `git-config`: `.gitmodules` reuses 100% of the generic config syntax, but only a subset of constructs (e.g. `include`/`includeIf`, arbitrary section names) is *meaningful* here, even though the lexer/parser must still accept them as valid generic syntax.

**Compatibility:** 100% — All Git clients and platforms parse `.gitmodules` with the same `git-config` syntax rules, with no known platform-specific variations.

---

## Character Encoding Support

### Gitmodules

**Character Encoding Support:**

| Element | Encoding | Allowed Characters | Reference | Evidence | Confirmed |
|---------|----------|---------------------|-----------|----------|-----------|
| Keys (variable names) | ASCII | `[A-Za-z][A-Za-z0-9-]*` | [git/git config.c](https://github.com/git/git/blob/master/config.c) | `iskeychar()`: `isalnum(c) \|\| c == '-'`, used by the variable-name scan loop in `get_value()` | ✅ verified |
| Section names (bare/dotted) | ASCII | `[A-Za-z0-9.-]+` | [git/git config.c](https://github.com/git/git/blob/master/config.c) | `get_base_var()`: accepts `iskeychar(c) \|\| c == '.'`, lower-cased | ✅ verified |
| Subsection names (quoted) | UTF-8 (byte-transparent) | Any byte except unescaped `"`, `\`, newline, NUL | [git/git config.c](https://github.com/git/git/blob/master/config.c) | `get_extended_base_var()` copies every byte verbatim via `strbuf_addch(name, c)` except `"`/`\`/`\n` | ✅ verified |
| Values (quoted) | UTF-8 (byte-transparent) | Any byte except unescaped `"`, `\`, raw newline | [git/git config.c](https://github.com/git/git/blob/master/config.c) | `parse_value()`: inside an open quote every byte falls through to `strbuf_addch(&cs->value, c)` unconditionally | ✅ verified |
| Values (unquoted) | UTF-8 (byte-transparent) | Any byte except unescaped `"`, `\`, raw newline, and `#`/`;` (starts a comment) | [git/git config.c](https://github.com/git/git/blob/master/config.c) | `parse_value()`: `#`/`;` only special-cased `if (!quote)` | ✅ verified |

**Character Categories to Check:**

| Category | Characters | Keys / Section names | Subsection names | Values | Confirmed |
|----------|------------|------------------------|-------------------|--------|-----------|
| ASCII letters | `a-zA-Z` | ✅ | ✅ | ✅ | ✅ verified |
| Digits | `0-9` | ✅ | ✅ | ✅ | ✅ verified |
| Polish | `ąćęłńóśźżĄĆĘŁŃÓŚŹŻ` | ❌ (not `iskeychar`) | ✅ (passes through as raw UTF-8 bytes) | ✅ | ✅ verified |
| German | `äöüßÄÖÜ` | ❌ | ✅ | ✅ | ✅ verified |
| Cyrillic | `абвгдеёжзийАБВГДЕЁЖЗИЙ` | ❌ | ✅ | ✅ | ✅ verified |
| Chinese | `中文字符汉字` | ❌ | ✅ | ✅ | ✅ verified |
| Japanese | `日本語ひらがなカタカナ` | ❌ | ✅ | ✅ | ✅ verified |
| Arabic | `العربية` | ❌ | ✅ | ✅ | ✅ verified |
| Emoji | `🚀💻🔧🌍` | ❌ | ✅ (4-byte UTF-8 sequences are still just bytes ≥ 0x80 to this parser) | ✅ | ✅ verified |
| Special WS | `  ` | ❌ | ✅ — not recognized as whitespace (`isspace()` is ASCII-only), so never trimmed; stored as literal byte content | ✅ — same: not eligible for the leading/trailing trim heuristic | ✅ verified |

**Numeric Format Support:** Not applicable — `.gitmodules` (via `git-config` syntax) has no typed numeric literal grammar. Every value, including ones a consumer later interprets as a boolean (`shallow = true`) or an integer, is lexically just a string (quoted, unquoted, or a concatenation of both); type interpretation happens entirely outside the file format's grammar, in the code that reads the parsed key/value pairs.

**Post-Step-16 verification:** All rows above are marked ✅ verified, each traced to a specific function in Git's own `config.c`. No entries are `⚠️ from memory`, so no STOP is required here — proceeding to Step 17.

---

## Format Features

### Gitmodules

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ❌ | No native function or extension parses `.gitmodules`/`git-config` syntax directly; [`parse_ini_string()`](https://www.php.net/parse_ini_string) handles plain `key=value`/`[section]` INI but not git-config's quoting/escaping/subsection/continuation rules; [`gitonomy/gitlib`](https://packagist.org/packages/gitonomy/gitlib) and [`symplify/git-wrapper`](https://packagist.org/packages/symplify/git-wrapper) shell out to the `git` binary rather than parsing the file themselves |
| PHP Emitting | ✅ | Trivial to emit via string concatenation; no dedicated library needed |
| AST Library | ❌ | No maintained PHP AST library specific to `git-config`/`.gitmodules` syntax |
| Line Sensitive | ✅ | Section headers cannot span multiple lines (hard parse error in Git's own parser); a value can span lines only via explicit backslash-newline continuation |
| Nestable | ❌ | Flat structure — sections contain variables only, no nested sections |
| Indentation Sensitive | ❌ | Free-form whitespace; leading tabs before variables are conventional (and what `git submodule add` writes) but not required |
| Comments Support | ✅ | Full-line or inline-trailing, `#` or `;`, extending to end of line; not recognized inside an open quote |
| Docblock Support | ❌ | No structured documentation block syntax |
| Multi-document | ❌ | One file = one document — a working tree has at most one `.gitmodules` |
| Schema Support | ❌ | No formal schema validation; `git config -f .gitmodules --list` enumerates keys but does not validate them |

**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Sections (submodules) | `\n` (newline) | optional | ❌ | `[submodule "a"]\n\t...\n[submodule "b"]\n\t...` |
| Variable lines within a section | `\n` (newline) | optional | ❌ | `path = a\nurl = b` |
| Repeated values for one variable (multi-valued key) | `\n` (newline, same key repeated) | optional | ❌ | `fetchRecurseSubmodules = false\nfetchRecurseSubmodules = on-demand` |

---

## Example

Based on the most extended form from git family: **gitmodules**

```ini
; leading comment trivia (semicolon style)
# leading comment trivia (hash style)

[submodule "vendor/symfony-flex"]
	path = vendor/symfony-flex
	url = https://github.com/symfony/flex.git
	branch = main
	update = checkout

[submodule "themes/corporate"]
	path = themes/corporate
	url = git@github.com:example-org/corporate-theme.git
	branch = release
	ignore = dirty                  # inline trailing comment after a value
	fetchRecurseSubmodules = true

[submodule "docs"]
	path = docs
	url = ../shared-docs.git
	branch = .
	# a value can be continued onto the next physical line with a
	# trailing backslash — the backslash and line break are discarded
	update = re\
base
	ignore = untracked

[submodule "tools/linters \"strict\""]
	; quoted values may contain whitespace and escaped characters
	path = "tools/linters"
	url = "https://example.com/tools/linters.git"
	shallow = true
	ignore = all                    ; inline trailing comment (semicolon style)
	fetchRecurseSubmodules = on-demand

[submodule "libs/legacy-widget"]
	Path=libs/legacy-widget
	URL    =    ./legacy-widget.git
	update=none
	shallow

[submodule "ui/legacy widgets"] path = "ui/legacy widgets"
	url = git@github.com:example-org/legacy-widgets.git
	fetchRecurseSubmodules = false
	fetchRecurseSubmodules = on-demand

; deprecated dotted subsection form (equivalent to [submodule "deprecated"])
[submodule.deprecated]
	path = libs/deprecated
	url = ../deprecated.git

; subsection name escaping differs from value escaping: inside a subsection
; name, a backslash before any character other than " or \ is simply
; dropped, so \n here is read as a literal "n", not a newline
[submodule "weird\name"]
	path = libs/weird-name

; a section header with no variables at all is syntactically valid
[submodule "placeholder"]

[submodule "scripts"]
	path = scripts
	; quoted values recognize exactly five escapes: \" \\ \n \t \b —
	; any other backslash sequence inside a value is invalid (unlike
	; subsection names, where unknown escapes are merely dropped)
	url = "C:\\repos\\scripts.git \"mirror\" line1\nline2\tindented\bbackspace"
	; these same five escapes are also recognized OUTSIDE quotes — and a
	; single value may freely toggle between unquoted and quoted segments,
	; which are concatenated into one value: "prefix" + "-mid-" + "suffix"
	ignore = prefix"-mid-"suffix\tend

; a bare section (no subsection) is valid git-config syntax, though it
; carries no keys recognized by the .gitmodules format itself
[submodule]
	unrecognizedKey = some-value
```

### Example Coverage Validation

Based on the only variant: **gitmodules**

| Feature Category | Feature | Covered | Location in Example |
|-------------------|---------|---------|----------------------|
| **Comments** | Hash-style full-line comment (`#`) | ✅ | line 127 |
| | Semicolon-style full-line comment (`;`) | ✅ | line 126 |
| | Inline trailing comment (`#`) after a value | ✅ | line 139 |
| | Inline trailing comment (`;`) after a value | ✅ | line 157 |
| **Blank Lines** | Blank line between constructs | ✅ | lines 128, 134, 141, etc. |
| **Section Headers** | Quoted subsection | ✅ | line 129 |
| | Quoted subsection containing escaped `"` | ✅ | line 152 |
| | Quoted subsection containing a space | ✅ | line 166 |
| | Quoted subsection with escape-drop rule (`\n` → literal `n`) | ✅ | line 179 |
| | Deprecated dotted subsection | ✅ | line 172 |
| | Bare section (no subsection) | ✅ | line 198 |
| | Section header with no variables | ✅ | line 183 |
| | Variable assignment on the header's own line (remainder-of-line) | ✅ | line 166 |
| **Variable Assignment** | `name = value` with surrounding whitespace | ✅ | lines 130, 162 |
| | `name=value` with no surrounding whitespace | ✅ | line 163 |
| | Case-insensitive variable name (`Path`, `URL`) | ✅ | lines 161–162 |
| | Boolean shorthand (bare name, no `=`) | ✅ | line 164 |
| | Multi-valued variable (same key repeated) | ✅ | lines 168–169 |
| **Values** | Unquoted value | ✅ | line 130 |
| | Quoted value | ✅ | line 154 |
| | Value mixing quoted + unquoted segments (concatenation) | ✅ | line 194 |
| | Escape sequences inside quotes (`\" \\ \n \t \b`) | ✅ | line 190 |
| | Escape sequences outside quotes | ✅ | line 194 |
| | Line continuation (trailing `\` + physical line break) | ✅ | lines 148–149 |
| | Relative URL (`../`) | ✅ | line 144 |
| | Relative URL (`./`) | ✅ | line 162 |
| | SSH-style URL (`git@host:path`) | ✅ | line 137 |
| | HTTPS URL | ✅ | line 131 |
| | `branch = .` special value | ✅ | line 145 |
| **Whitespace** | Leading indentation (tab) before a variable | ✅ | line 130 |
| | Extra whitespace surrounding `=` (discarded) | ✅ | line 162 |

**Note on enumerated key values:** `update` (`checkout`/`rebase`/`merge`/`none`), `ignore` (`all`/`dirty`/`untracked`/`none`), and `shallow`/`fetchRecurseSubmodules` booleans are not separately tokenized — every one of them is lexically just a generic **Value** (Section 4 of Format Structure Groups). The example demonstrates `update = checkout` (line 133), `update = re\`+`base` (lines 148–149), and `update = none` (line 163) to exercise the value-token paths (plain word, continuation, plain word); it does not enumerate `update = merge` or `ignore = none` since those would exercise the exact same token rule a second time, not a new grammar path.

### Separated Lists Coverage

| List Type | Demonstrated | Location in Example |
|-----------|---------------|----------------------|
| Sections (submodules), newline-separated | ✅ | entire example — each `[submodule ...]` block |
| Variable lines within a section, newline-separated | ✅ | e.g. lines 130–133 |
| Repeated values for one variable (multi-valued key) | ✅ | lines 168–169 |

**Coverage Summary:**
- ✅ All section-header forms covered (quoted, dotted, bare, header-trailing-variable, no-variables)
- ✅ All variable-assignment forms covered (key=value, boolean shorthand, multi-valued)
- ✅ All value forms covered (quoted, unquoted, mixed/concatenated, escaped, continued)
- ✅ Both comment markers covered, both full-line and inline-trailing
- ✅ All three separated-list types covered

---

## All Possible Document Root Values

A `.gitmodules` file shares its syntax with `git-config`: the document is not a single typed value like in JSON/YAML — it is a sequence of zero or more top-level constructs (comments, blank lines, section headers, and the variable assignments that belong to a section). There is no single "root value" type; instead, the root is the sequence of these constructs.

### Empty Document
```ini
```
(Empty file — valid, defines no submodules)

### Single Comment (hash style)
```ini
# This is a comment
```

### Single Comment (semicolon style)
```ini
; This is a comment
```

### Blank Line Only
```ini

```

### Section Header With No Variables
```ini
[submodule "empty"]
```
(Syntactically valid; semantically incomplete — `path` and `url` are required by the format but their absence is not a syntax error)

### Section With a Single Variable
```ini
[submodule "libfoo"]
	path = include/foo
```

### Section With Quoted Value
```ini
[submodule "libfoo"]
	path = "include/foo"
```

### Section Header With Variable On The Same Line
```ini
[submodule "libfoo"] path = include/foo
```
(The remainder of the section-header line is itself a variable assignment belonging to that section)

### Deprecated Dotted Section Form
```ini
[submodule.libfoo]
	path = include/foo
```
(Equivalent to `[submodule "libfoo"]`, with the subsection name lower-cased; deprecated but syntactically valid)

### Bare Section Header (no subsection)
```ini
[submodule]
	unrecognizedKey = some-value
```
(Syntactically valid generic git-config form; carries no keys recognized by `.gitmodules` itself)

### Boolean Shorthand Variable (no `=`)
```ini
[submodule "libfoo"]
	shallow
```
(Bare variable name is shorthand for `shallow = true`)

### Summary of Root Construct Types

| Type | Examples |
|------|----------|
| Empty/Blank | `` (empty file), `   ` (whitespace-only line) |
| Comment | `# comment`, `; comment` |
| Section header (quoted subsection) | `[submodule "name"]` |
| Section header (deprecated dotted) | `[submodule.name]` |
| Section header (bare, no subsection) | `[submodule]` |
| Variable assignment | `path = value`, `path=value`, `path` (boolean shorthand) |
| Quoted value | `path = "value with spaces"` |

**Note:** A `.gitmodules` document is always a sequence of comments, blank lines, and sections. Every variable assignment must belong to a section — there must be a section header before the first variable in the file.

### Root Values Validation

Based on the only variant: **gitmodules**

| Root Type | Minimal Valid Example | Spec Reference | Validated |
|-----------|------------------------|-----------------|-----------|
| Empty document | ` ` (empty) | [config.adoc](https://raw.githubusercontent.com/git/git/master/Documentation/config.adoc) — "Blank lines are ignored"; a zero-byte file is the degenerate case | ✅ |
| Comment (hash) | `#` | config.adoc: "The `#` and `;` characters begin comments to the end of line" | ✅ |
| Comment (semicolon) | `;` | config.adoc, same sentence | ✅ |
| Blank line | `\n` | config.adoc: "Blank lines are ignored" | ✅ |
| Section header, quoted subsection | `[a "b"]` | config.adoc: `[section "subsection"]`; [git-config.c `get_extended_base_var()`](https://github.com/git/git/blob/master/config.c) | ✅ |
| Section header, deprecated dotted | `[a.b]` | config.adoc: "deprecated `[section.subsection]` syntax"; [config.c `get_base_var()`](https://github.com/git/git/blob/master/config.c) | ✅ |
| Section header, bare (no subsection) | `[a]` | config.adoc: "You can have `[section]` if you have `[section "subsection"]`, but you don't need to" | ✅ |
| Variable assignment, `name = value` | `k = v` | config.adoc: "All the other lines ... are recognized as setting variables, in the form 'name = value'" | ✅ |
| Variable assignment, boolean shorthand | `k` | config.adoc: "(or just 'name', which is a short-hand to say that the variable is the boolean \"true\")" | ✅ |
| Quoted value | `k = ""` | config.adoc: "If `value` needs to contain leading or trailing whitespace ... it must be enclosed in double quotation marks" — an empty quoted string is the minimal case | ✅ |

**Validation Notes:**
- Every root-type example above traces to a direct quote from `config.adoc` (the file `git-config.adoc` includes verbatim) or to the exact C function in `config.c` that implements it — not to the secondary `gitmodules.adoc` page, since these are properties of the underlying `git-config` syntax that `.gitmodules` inherits wholesale.
- Unlike `gitignore` (a flat, line-only format), a `.gitmodules` document cannot have a bare variable assignment as its very first construct — `get_value()` is only ever reached after a section header has been parsed, so "section header before first variable" is a hard structural constraint, not just a convention.

---

## Format Structure Groups

Logical groupings of structural elements in Gitmodules (which shares its lexical syntax with `git-config`).

### 1. Top-Level Constructs

#### Comment Line
```ini
# hash-style comment
; semicolon-style comment
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_hash` | `#` | Hash comment marker |
| `t_semicolon` | `;` | Semicolon comment marker |
| `t_comment_text` | `[^\n]*` | Comment content (rest of line) |
| `t_newline` | `\n` | Line ending |

#### Blank Line
```ini

```
(Empty or whitespace-only)

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_inline_ws` | `[ \t]+` | Optional spaces/tabs |
| `t_newline` | `\n` | Line ending |

---

### 2. Section Header

#### Quoted Subsection Header
```ini
[submodule "vendor/symfony-flex"]
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_bracket_open` | `\[` | Section header opening delimiter |
| `t_section_name` | `[A-Za-z0-9.-]+` | Section name (case-insensitive; alphanumeric, `-`, `.` — **no underscore**) |
| `t_inline_ws` | `[ \t]+` | Space separating section name from quoted subsection |
| `t_dquote` | `"` | Subsection opening/closing delimiter |
| `t_subsection_char` | `[^"\\\n]` | Literal subsection character (case-sensitive; any char except `"`, `\`, newline) |
| `t_subsection_escape` | `\\[^\n]` | Backslash followed by any character but newline — the backslash is always dropped and the following character is kept literally, uniformly for `"`, `\`, and everything else (e.g. `\"` → `"`, `\\` → `\`, `\n` → literal `n`, not newline) |
| `t_bracket_close` | `\]` | Section header closing delimiter |

**Invalid:** a backslash immediately followed by a literal newline inside a subsection name is a hard parse error — section headers cannot span multiple lines, and (unlike values) there is no continuation mechanism here.

#### Deprecated Dotted Section Header
```ini
[submodule.deprecated]
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_bracket_open` | `\[` | Section header opening delimiter |
| `t_section_name` | `[A-Za-z0-9.-]+` | Combined `section.subsection` name; matched as one run (dot is just another allowed character at this position) and lower-cased as a whole |
| `t_bracket_close` | `\]` | Section header closing delimiter |

#### Bare Section Header (no subsection)
```ini
[submodule]
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_bracket_open` | `\[` | Section header opening delimiter |
| `t_section_name` | `[A-Za-z0-9.-]+` | Section name only, no subsection |
| `t_bracket_close` | `\]` | Section header closing delimiter |

#### Header-Trailing Variable
```ini
[submodule "ui/legacy widgets"] path = "ui/legacy widgets"
```

**Tokens:** same as **Variable Assignment** below — the remainder of a section-header line is parsed as an ordinary variable assignment belonging to that section.

---

### 3. Variable Assignment

#### Key/Value Pair
```ini
path = vendor/symfony-flex
URL=https://github.com/symfony/flex.git
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_variable_name` | `[A-Za-z][A-Za-z0-9-]*` | Variable name (case-insensitive; alphanumeric and `-`; must start with a letter) |
| `t_inline_ws` | `[ \t]+` | Optional whitespace around `=` (discarded) |
| `t_equals` | `=` | Key/value separator |
| *(value)* | see **Values** group | The assigned value |
| `t_newline` | `\n` | Line ending |

#### Boolean Shorthand
```ini
shallow
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_variable_name` | `[A-Za-z][A-Za-z0-9-]*` | Variable name with no `=value` — shorthand for `name = true` |
| `t_newline` | `\n` | Line ending |

#### Inline Trailing Comment
```ini
ignore = dirty                  # inline trailing comment
ignore = all                    ; inline trailing comment
```

**Tokens:** identical to **Comment Line** tokens; the lexer treats `#`/`;` outside of an open quote as the unconditional start of a comment that extends to the end of the line, even mid-value.

#### Multi-Valued Variable (repeated key)
```ini
fetchRecurseSubmodules = false
fetchRecurseSubmodules = on-demand
```
Repeating the same variable name within a section does not overwrite — both lines are independent **Variable Assignment** nodes; consuming code decides whether to use the first, the last, or all values. See **Separated Lists** (Step 21) for the list semantics.

---

### 4. Values

#### Unquoted Value
```ini
path = vendor/symfony-flex
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_value_char` | `[^"\\\n;#]` | Literal value character (anything but quote, backslash, newline, or a comment marker) |
| `t_value_ws` | `[ \t]+` | Whitespace run inside an unquoted segment — retained only if non-whitespace content follows later in the same value; trimmed if it runs to end-of-value |

#### Quoted Value
```ini
path = "tools/linters"
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_dquote` | `"` | Opens/closes a quoted segment — toggled, so a single value may contain multiple quoted/unquoted segments concatenated together (e.g. `prefix"-mid-"suffix`) |
| `t_quoted_char` | `[^"\\]` | Literal character inside a quoted segment — all whitespace here is preserved verbatim, unconditionally |

#### Value Escape Sequences
```ini
url = "C:\\repos\\scripts.git \"mirror\" line1\nline2\tindented\bbackspace"
```
Recognized **both inside and outside quotes** — escaping is not a quoting-only feature in `git-config` values.

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_escaped_quote` | `\\"` | Literal `"` |
| `t_escaped_backslash` | `\\\\` | Literal `\` |
| `t_escaped_newline_char` | `\\n` | Newline character (NL) |
| `t_escaped_tab_char` | `\\t` | Horizontal tab (HT) |
| `t_escaped_backspace_char` | `\\b` | Backspace (BS) |
| `t_line_continuation` | `\\\n` | Literal backslash immediately followed by end-of-line — backslash and line break are both discarded, next physical line continues the value |

**Invalid:** any other `\<char>` sequence inside a value is a parse error (unlike the drop-rule used for subsection names).

---

### 5. Whitespace Handling

#### Surrounding Whitespace (discarded)
```ini
update    =    checkout
```
(Whitespace around `name`, `=`, and the value's outer edges is discarded)

**Tokens:**
| Token | Pattern | Description |
|-------|---------|--------------|
| `t_inline_ws` | `[ \t]+` | Spaces/tabs surrounding `name`/`=`/value boundaries |

#### Internal Whitespace (retained)
```ini
ignore = prefix"-mid-"suffix\tend
```
(Whitespace between two non-whitespace parts of the same value is kept verbatim; quoting is one way to guarantee whitespace survives even at what looks like a leading/trailing position)

#### Leading Indentation
```ini
	path = vendor/symfony-flex
```
(Leading tabs/spaces before a variable name are conventional but not semantically required — config syntax is indentation-insensitive)

---

### Structure Groups Summary

| Group | Elements |
|-------|----------|
| Top-Level Constructs | Comment (`#`, `;`), Blank line |
| Section Header | Quoted subsection, Deprecated dotted, Bare (no subsection), Header-trailing variable |
| Variable Assignment | Key/value pair, Boolean shorthand, Inline trailing comment, Multi-valued (repeated key) |
| Values | Unquoted, Quoted, Escape sequences, Line continuation |
| Whitespace | Surrounding (discarded), Internal (retained), Leading indentation (insignificant) |

---

## Format Structure Groups & Tokens Validation (Step 24)

Every element below was checked directly against Git's own source — primarily [`config.c`](https://github.com/git/git/blob/master/config.c) (the actual lexer: `get_next_char`, `parse_value`, `get_value`, `get_base_var`, `get_extended_base_var`, and the dispatcher loop around line 1095) and [`config.adoc`](https://raw.githubusercontent.com/git/git/master/Documentation/config.adoc) (the prose spec it backs) — not from memory. Two real discrepancies were found and corrected in place; both are called out below rather than silently fixed.

### 1. Top-Level Constructs Validation

| Structure | Element | Current Value | Spec Reference | Spec Quote / Function | Status |
|-----------|---------|----------------|-----------------|------------------------|--------|
| Comment Line | `t_hash` | `#` | config.adoc, Syntax | "The `#` and `;` characters begin comments to the end of line" | ✅ |
| | `t_semicolon` | `;` | config.adoc, Syntax | same quote | ✅ |
| | `t_comment_text` | `[^\n]*` | `config.c parse_value()` | `if (comment) continue;` loops until `\n`, no other char excluded | ✅ |
| Blank Line | `t_inline_ws` | `[ \t]+` | config.adoc, Syntax | "Whitespace characters, which **in this context** are the space character (SP) and the horizontal tabulation (HT)" | ✅ |
| | `t_newline` | `\n` | `config.c get_next_char()` | normalizes a `\r\n` pair to a single `\n`; a lone `\r` not followed by `\n` is returned as literal `\r` (ordinary content, not whitespace) | ⚠️ see note below |

**Note on `t_newline` and `\r`:** Git's own parser silently normalizes `\r\n` → `\n` while reading (it does not need to round-trip the file). This grammar's job is byte-perfect round-tripping (see Step 28's invariant), so a `\r` immediately before `\n` must not be silently discarded — it has to be captured as literal content so re-serialization reproduces the original bytes. This is a deliberate divergence from Git's own internal semantics, not a mistake; the exact token shape (`\r?\n` vs. treating a trailing `\r` as part of the preceding value) is left to Step 27, where it can follow whatever convention this project's other grammars already use for line endings.

### 2. Section Header Validation

| Structure | Element | Current Value | Spec Reference | Spec Quote / Function | Status |
|-----------|---------|----------------|-----------------|------------------------|--------|
| Quoted Subsection | `t_bracket_open` / `t_bracket_close` | `\[` / `\]` | config.adoc, Syntax | "A section begins with the name of the section in square brackets" | ✅ |
| | `t_section_name` | `[A-Za-z0-9.-]+` | `config.c iskeychar()` | `isalnum(c) \|\| c == '-'`, plus `.` allowed by `get_base_var()`'s loop condition | ❌→✅ **corrected** — originally documented as `[A-Za-z0-9_.-]+`; `iskeychar()` does **not** include `_`. Fixed in Section 2 above. |
| | `t_subsection_char` | `[^"\\\n]` | `config.c get_extended_base_var()` | loop appends every byte via `strbuf_addch` except `"` (ends name), `\\` (escape), `\n` (error) | ✅ |
| | `t_subsection_escape` | `\\[^\n]` | `config.c get_extended_base_var()` | `if (c=='\\') { c=get_next_char(...); if (c=='\n') error; } strbuf_addch(name, c);` — backslash is **always** dropped and the next byte kept literally, uniformly for `"`, `\`, or anything else | ❌→✅ **corrected** — originally modeled as three separate tokens (`escaped_quote`, `escaped_backslash`, `escaped_other`) implying `"`/`\` were specially "recognized" escapes distinct from a fallback drop-rule. The code treats all three identically; collapsed into one token. Also removed an unverified `\x00`-exclusion claim from `t_subsection_char` — no code path was found that special-cases NUL during this scan. |
| Deprecated Dotted | `t_section_name` (combined) | `[A-Za-z0-9.-]+` | `config.c get_base_var()` | `if (!iskeychar(c) && c != '.') return -1;` then `tolower(c)` | ✅ (same underscore correction as above) |
| Bare Section | `t_section_name` only, no subsection | `[A-Za-z0-9.-]+` | config.adoc, Syntax | "You can have `[section]` if you have `[section \"subsection\"]`, but you don't need to" | ✅ |
| Case folding | (semantic note, not a token) | n/a | `config.c get_base_var()` / `get_value()` | both lower-case characters as they scan (`tolower(c)`) for git's own case-insensitive comparison; this grammar must preserve the **original** casing as written, since its purpose is lossless round-trip, not key lookup | ✅ noted |

### 3. Variable Assignment Validation

| Structure | Element | Current Value | Spec Reference | Spec Quote / Function | Status |
|-----------|---------|----------------|-----------------|------------------------|--------|
| Key/Value Pair | `t_variable_name` | `[A-Za-z][A-Za-z0-9-]*` | config.adoc + `config.c` dispatcher loop | doc: "must start with an alphabetic character"; code: `if (!isalpha(c)) break;` (main dispatch loop, `config.c` ~line 1121) gates entry into variable-parsing **before** `get_value()`'s own scan loop (which by itself only checks `iskeychar`, no first-char rule) | ✅ verified at the right call site after initially looking in the wrong function |
| | `t_equals` | `=` | config.adoc, Syntax | "in the form 'name = value'" | ✅ |
| Boolean Shorthand | bare `t_variable_name`, no `=` | — | config.adoc, Syntax | "(or just 'name', which is a short-hand to say that the variable is the boolean \"true\")" | ✅ |
| Inline Trailing Comment | `#`/`;` mid-line | — | `config.c parse_value()` | `if (!quote) { if (c==';' \|\| c=='#') { comment=1; continue; } }` — comment markers are only special when not inside an open quote; once set, `comment` is never unset within that value | ✅ |
| Multi-Valued Variable | repeated key, last line not special | — | config.adoc, Syntax | "Some variables may appear multiple times; we say then that the variable is multivalued" | ✅ |

### 4. Values Validation

| Structure | Element | Current Value | Spec Reference | Spec Quote / Function | Status |
|-----------|---------|----------------|-----------------|------------------------|--------|
| Unquoted Value | `t_value_char` | `[^"\\\n;#]` | `config.c parse_value()` | default `strbuf_addch` fallthrough; `"`, `\`, `\n` divert to dedicated branches, `;`/`#` divert to comment branch only `if (!quote)` | ✅ |
| | `t_value_ws` (trim behavior) | `[ \t]+`, kept only if non-ws follows | `config.c parse_value()` | `trim_len` bookkeeping: a whitespace run is provisionally appended, then truncated back out only if nothing but `\n` follows it; any subsequent non-whitespace byte (including a quote or escape) cancels the pending trim | ✅ |
| Quoted Value | `t_dquote` (toggle, not open/close pair) | `"` | `config.c parse_value()` | `if (c=='"') { quote = 1 - quote; continue; }` — a single value may toggle quoting an arbitrary number of times, segments concatenate | ✅ |
| | `t_quoted_char` | `[^"\\]` | `config.c parse_value()` | inside `quote`, every byte (including whitespace, `;`, `#`) falls through to the same unconditional `strbuf_addch` | ✅ |
| Value Escapes | `t_escaped_quote` `\\"` | emits `"` | `config.c parse_value()` | `case '\\': case '"': break;` (kept as-is) | ✅ |
| | `t_escaped_backslash` `\\\\` | emits `\` | same `case` line | ✅ |
| | `t_escaped_newline_char` `\\n` | emits NL | `case 'n': c = '\n'; break;` | ✅ |
| | `t_escaped_tab_char` `\\t` | emits HT | `case 't': c = '\t'; break;` | ✅ |
| | `t_escaped_backspace_char` `\\b` | emits BS | `case 'b': c = '\b'; break;` | ✅ |
| | `t_line_continuation` `\\\n` | emits nothing | `case '\n': continue;` | ✅ |
| | Invalid escape → error | any other `\<char>` | `default: return NULL;` | ✅ — confirms values use a strict allow-list, unlike the permissive drop-rule for subsection names (Section 2) |
| | Escapes apply outside quotes too | — | `config.c parse_value()` | the entire `if (c == '\\')` branch is unconditional on `quote` — it runs identically whether `quote` is 0 or 1 | ✅ (non-obvious; doc prose frames escaping as a quoting topic, code applies it unconditionally) |

### 5. Whitespace Validation

| Structure | Element | Current Value | Spec Reference | Spec Quote / Function | Status |
|-----------|---------|----------------|-----------------|------------------------|--------|
| Surrounding Whitespace | `t_inline_ws` around name/`=`/value edges | `[ \t]+`, discarded | config.adoc, Syntax | "Whitespace characters surrounding `name`, `=` and `value` are discarded" | ✅ |
| Internal Whitespace | retained verbatim | — | config.adoc, Syntax | "Internal whitespace characters within 'value' are retained verbatim" | ✅ |
| Leading Indentation | insignificant | — | config.adoc, Syntax | whitespace is only ever discarded around `name`/`=`/`value`; nothing in the grammar treats column position as meaningful | ✅ |

### Validation Summary

**Total structures validated:** 5 groups, 28 distinct token/behavior rows.

**Discrepancies found and corrected:**
1. `t_section_name` wrongly included `_` in all three Section Header forms — `iskeychar()` is `isalnum(c) || c == '-'` only. Fixed.
2. Subsection-name escaping was modeled as three distinct "recognized" escapes (`\"`, `\\`, other) — the source treats all backslash-escapes in subsection names identically (unconditional drop-and-keep-next-byte). Collapsed into one `t_subsection_escape` token; also dropped an unverified NUL-exclusion claim.

**Verified without changes:** variable-name first-character rule (traced to the correct call site after initially checking the wrong function), all five value-escape sequences, the quote-toggling/concatenation behavior, comment-marker scoping, and the leading/trailing whitespace-trim behavior.

**Open implementation note carried to Step 27:** how `t_newline` should represent a stray `\r` before `\n` for byte-perfect round-tripping — Git's own parser discards this distinction since it doesn't need to round-trip; this grammar does.
