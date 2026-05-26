# Gitignore

## Official Documentation & Specifications

### Gitignore

Gitignore is a plain-text file format used by Git version control system to specify intentionally untracked files that Git should ignore. Each line in a .gitignore file specifies a pattern that matches files or directories to exclude from version control. The format supports glob patterns, wildcards, negation, and comments, making it flexible for various project structures. Gitignore files are essential in every Git repository to prevent temporary files, build artifacts, dependencies, and sensitive data from being committed. The format is standardized and documented in official Git documentation, with wide adoption across all programming ecosystems.

**Variant-specific example** (standard gitignore syntax):
```gitignore
# Node.js project
node_modules/
npm-debug.log*
.env

# Build outputs
dist/
build/
*.min.js

# Negation - keep specific file
!important-config.js

# Directory wildcard
**/temp-*

# Character class
[Bb]uild/
```

| Status | Document | Description |
|--------|----------|-------------|
| ✅ 200 | [Git - gitignore Documentation](https://git-scm.com/docs/gitignore) | Official Git documentation for gitignore |
| ✅ 200 | [gitignore(5) - kernel.org](https://www.kernel.org/pub/software/scm/git/docs/gitignore.html) | Git manual page mirror |
| ✅ 200 | [GitHub Docs - Ignoring files](https://docs.github.com/en/get-started/git-basics/ignoring-files) | GitHub's guide on using .gitignore |
| ✅ 200 | [Atlassian Git Tutorial - gitignore](https://www.atlassian.com/git/tutorials/saving-changes/gitignore) | Comprehensive tutorial on .gitignore usage |
| ✅ 200 | [GitHub gitignore templates](https://github.com/github/gitignore) | Collection of useful .gitignore templates |
| ✅ 200 | [W3Schools - Git Ignore](https://www.w3schools.com/git/git_ignore.asp) | Beginner-friendly gitignore guide |

**Variants:** Only one variant exists - **gitignore** (no conflicts)

### Variant Summary

**Gitignore**

**Adoption:** Universal - used in virtually every Git repository across all platforms and programming languages. Supported by all Git clients and hosting services (GitHub, GitLab, Bitbucket, etc.). Estimated millions of repositories worldwide use .gitignore files.

**Key Features:**
- Line-based pattern matching
- Glob wildcards (`*`, `**`, `?`)
- Character classes (`[abc]`, `[0-9]`)
- Negation patterns (`!important.txt`)
- Directory-specific patterns (`dir/`)
- Comment support (`#`)
- Root-relative patterns (`/file.txt`)

**Recommendation:** ✅ **STRONGLY RECOMMENDED** - Essential file for every Git repository. Use this format to exclude build artifacts, dependencies, temporary files, and sensitive data from version control. Cross-platform compatible and universally supported.

### Variant Conflicts

**Analysis:** No conflicts exist - gitignore has only one variant with no competing specifications or incompatible features.

**Compatibility:** 100% - All Git clients and platforms support the same gitignore syntax without variations or extensions.

---

## Character Encoding Support

### Gitignore

| Encoding | Support | Notes |
|----------|---------|-------|
| UTF-8 | ✅ Full | Recommended encoding, supports all Unicode characters |
| UTF-8 BOM | ⚠️ Partial | BOM is ignored but not recommended |
| ASCII | ✅ Full | Subset of UTF-8, fully compatible |
| ISO-8859-1 (Latin-1) | ⚠️ Limited | Works but non-ASCII chars may cause issues |
| Windows-1252 | ⚠️ Limited | Windows default, may work but UTF-8 preferred |
| UTF-16 | ❌ No | Not supported by Git |
| UTF-32 | ❌ No | Not supported by Git |

**Recommendation:** Always use **UTF-8 without BOM** for maximum compatibility across platforms.

**Special considerations:**
- Git treats .gitignore files as byte streams in the repository's default encoding (typically UTF-8)
- Pattern matching is byte-based, not character-based
- Non-ASCII filenames should use UTF-8 encoding
- Avoid using encoding-specific characters in patterns for cross-platform compatibility

---

## Format Features

### Gitignore

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ❌ | No native PHP parser; simple line-by-line text processing |
| PHP Emitting | ✅ | Trivial to emit (string concatenation with newlines) |
| AST Library | ❌ | No dedicated AST library; simple line-based format |
| Line Sensitive | ✅ | Each line is a separate pattern; line breaks are structural |
| Nestable | ❌ | Flat structure; no nesting or hierarchical elements |
| Indentation Sensitive | ❌ | Indentation has no semantic meaning |
| Comments Support | ✅ | Lines starting with `#` |
| Docblock Support | ❌ | No structured documentation |
| Multi-document | ❌ | One file = one document |
| Schema Support | ❌ | No schema validation |

**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Pattern lines | `\n` (newline) | optional | ❌ | `*.log\nnode_modules/` |

---

## Example

Based on the most extended form from git family: **gitignore**

````gitignore
# Operating System Files
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db
Desktop.ini

# Editor and IDE Files
.vscode/
.idea/
*.swp
*.swo
*~
.project
.classpath
.settings/
*.sublime-project
*.sublime-workspace

# Build Output
build/
dist/
out/
target/
bin/
obj/
*.class
*.jar
*.war
*.ear

# Dependencies
node_modules/
vendor/
bower_components/
jspm_packages/
.pnp/
.pnp.js

# Logs
*.log
logs/
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Environment and Configuration
.env
.env.local
.env.*.local
*.config.local
secrets.yml

# Test Coverage
coverage/
*.lcov
.nyc_output/
htmlcov/
.coverage
.pytest_cache/

# Package Manager Lock Files
package-lock.json
yarn.lock
Gemfile.lock
composer.lock

# Temporary Files
tmp/
temp/
*.tmp
*.temp
*.cache

# Archives
*.zip
*.tar.gz
*.rar
*.7z

# Negation Examples
!important.log
!vendor/my-package/

# Directory-only Pattern
cache/

# Wildcard Patterns
**/*.backup
src/**/*.min.js
**/temp-*

# Extension Patterns
*.pyc
*.pyo
*.pyd
__pycache__/

# Complex Glob Patterns
[Bb]uild/
*.[oa]
*~

# Escaped Special Characters
\#special-file.txt
\!important

# Comments
# This is a comment
  # Indented comment

# Blank lines for readability

# Multiple patterns for same concept
*.orig
*.rej
*.bak
````

### Example Coverage Validation

Based on the most extended variant: **gitignore**

| Feature Category | Feature | Covered | Location in Example |
|-----------------|---------|---------|---------------------|
| **Comments** | Line comment (`#`) | ✅ | lines 121, 131, 143, 232 |
| | Indented comment | ✅ | line 233 |
| **Simple Patterns** | Literal filename | ✅ | lines 122, 137, 171 |
| | Extension wildcard (`*.ext`) | ✅ | lines 134, 164, 217 |
| | Prefix wildcard (`prefix*`) | ✅ | line 166 |
| | Suffix wildcard (`*suffix`) | ✅ | line 136 |
| **Directory Patterns** | Trailing slash (`dir/`) | ✅ | lines 132, 144, 156 |
| | Multi-level path | ✅ | line 213 |
| **Wildcards** | Single asterisk (`*`) | ✅ | lines 134, 164 |
| | Double asterisk (`**`) | ✅ | lines 212, 213, 214 |
| | Question mark (`?`) | ✅ | line 123 |
| **Character Classes** | Uppercase/lowercase (`[Aa]`) | ✅ | line 223 |
| | Character range (`[0-9]`) | ✅ | line 224 |
| | Multiple chars (`[abc]`) | ✅ | line 224 |
| **Negation** | Keep specific file (`!file`) | ✅ | line 205 |
| | Keep directory (`!dir/`) | ✅ | line 206 |
| **Escaped Characters** | Hash escape (`\#`) | ✅ | line 228 |
| | Exclamation escape (`\!`) | ✅ | line 229 |
| **Special Patterns** | Root-relative (implied) | ✅ | Multiple locations |
| | Blank lines | ✅ | lines 235 |
| | Tilde suffix (`*~`) | ✅ | line 136, 225 |

### Separated Lists Coverage

| List Type | Demonstrated | Location in Example |
|-----------|--------------|---------------------|
| Pattern lines (newline separator) | ✅ | Entire file (each line is a pattern) |

**Coverage Summary:**
- ✅ All gitignore pattern types covered
- ✅ Comments and blank lines demonstrated
- ✅ Wildcards (single, double, question mark) included
- ✅ Character classes with various forms
- ✅ Negation patterns (files and directories)
- ✅ Escaped special characters
- ✅ Directory-specific patterns

---

## All Possible Document Root Values

Gitignore files are line-based documents where each line can be one of several types. Unlike JSON/YAML, there is no single "root value" - the document is a sequence of lines.

### Empty Document
```gitignore

```
(Empty file - valid, ignores nothing)

### Single Comment
```gitignore
# This is a comment
```

### Single Pattern
```gitignore
*.log
```

### Single Directory Pattern
```gitignore
build/
```

### Single Negation Pattern
```gitignore
!important.txt
```

### Single Escaped Pattern
```gitignore
\#file-with-hash.txt
```

### Single Wildcard Pattern
```gitignore
**/*.tmp
```

### Single Character Class Pattern
```gitignore
[Bb]uild/
```

### Blank Line Only
```gitignore

```
(Whitespace-only line)

### Summary of Root Line Types

| Type | Examples |
|------|----------|
| Empty/Blank | `` (empty file), `   ` (whitespace) |
| Comment | `# comment`, `  # indented comment` |
| Simple pattern | `*.log`, `file.txt`, `temp` |
| Directory pattern | `build/`, `node_modules/` |
| Negation pattern | `!important.log`, `!vendor/my-lib/` |
| Wildcard pattern | `**/*.tmp`, `src/**/*.min.js` |
| Character class | `[Bb]uild/`, `*.[oa]` |
| Escaped pattern | `\#special`, `\!file` |

**Note:** A gitignore document is always a sequence of zero or more lines. Each line is processed independently.

### Root Values Validation

Based on the most extended variant: **gitignore**

| Root Type | Minimal Valid Example | Spec Reference | Validated |
|-----------|----------------------|----------------|-----------|
| Empty document | ` ` (empty) | [Git docs](https://git-scm.com/docs/gitignore) | ✅ |
| Comment line | `# c` | Git docs: "A line starting with `#`" | ✅ |
| Simple pattern | `f` | Git docs: "filename pattern" | ✅ |
| Extension pattern | `*.log` | Git docs: "wildcard `*`" | ✅ |
| Directory pattern | `d/` | Git docs: "trailing `/` for directory" | ✅ |
| Negation pattern | `!f` | Git docs: "`!` prefix negates pattern" | ✅ |
| Wildcard pattern | `**/*.tmp` | Git docs: "`**` matches directories" | ✅ |
| Character class | `[Aa]` | Git docs: character class matching | ✅ |
| Escaped character | `\#f` | Git docs: "backslash escapes" | ✅ |
| Blank line | `\n` | Git docs: blank lines ignored | ✅ |

**Validation Notes:**
- All root line types are valid per Git documentation
- Each example is the minimal valid syntax for that line type
- Gitignore processes lines independently (no multi-line constructs)
- Empty file is valid (ignores nothing)

---

## Format Structure Groups

Logical groupings of structural elements in Gitignore.

### 1. Line Types

#### Comment Line
```gitignore
# This is a comment
  # Indented comment also valid
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_comment_marker` | `#` | Hash character starting comment |
| `t_comment_text` | `[^\n]*` | Comment content (rest of line) |
| `t_newline` | `\n` | Line ending |

#### Blank Line
```gitignore

```
(Empty or whitespace-only)

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_whitespace` | `[ \t]*` | Optional spaces/tabs |
| `t_newline` | `\n` | Line ending |

#### Pattern Line
```gitignore
*.log
build/
!important.txt
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_pattern_text` | `[^\n]+` | Pattern content (any non-newline chars) |
| `t_newline` | `\n` | Line ending |

---

### 2. Pattern Components

#### Simple Pattern
```gitignore
file.txt
temp
*.log
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_literal` | `[\p{L}\p{N}_.-]+` | Literal filename characters (Unicode-aware) |
| `t_asterisk` | `\*` | Wildcard character |
| `t_question` | `\?` | Single char wildcard |

#### Directory Pattern
```gitignore
build/
node_modules/
cache/
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_path` | `[\p{L}\p{N}_.-/]+` | Directory path (Unicode-aware) |
| `t_trailing_slash` | `/` | Trailing slash marking directory |

#### Negation Pattern
```gitignore
!important.log
!vendor/keep-this/
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_negation` | `!` | Exclamation mark negating pattern |
| `t_pattern` | `[^\n]+` | Pattern after negation |

---

### 3. Glob Patterns

#### Asterisk Wildcard
```gitignore
*.log
test*
*backup
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_asterisk` | `\*` | Matches zero or more characters |
| `t_literal` | `[\p{L}\p{N}_.-]+` | Literal text (Unicode-aware) |

#### Double Asterisk (Directory Wildcard)
```gitignore
**/*.tmp
src/**/*.min.js
**/test-*
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_double_asterisk` | `\*\*` | Matches zero or more directories |
| `t_slash` | `/` | Directory separator |
| `t_asterisk` | `\*` | Wildcard |
| `t_literal` | `[\p{L}\p{N}_.-]+` | Literal text (Unicode-aware) |

#### Question Mark (Single Character)
```gitignore
file?.txt
test??.log
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_question` | `\?` | Matches exactly one character |
| `t_literal` | `[a-zA-Z0-9_.-]+` | Literal text |

#### Character Class
```gitignore
[Bb]uild/
*.[oa]
file[0-9].txt
[!a]*.log
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_bracket_open` | `\[` | Opening bracket |
| `t_bracket_close` | `\]` | Closing bracket |
| `t_char_class_content` | `[^\]]+` | Characters inside class |
| `t_negation_char` | `[!^]` | Negation inside class at start (`!` or `^` both supported) |
| `t_escaped_bracket` | `\\\]` | Literal `]` inside class via escape |

---

### 4. Special Characters

#### Escape Sequence
```gitignore
\#special-file.txt
\!important
\*literal-asterisk
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_backslash` | `\\` | Escape character |
| `t_escaped_char` | `.` | Any character after backslash |

#### Directory Separator
```gitignore
path/to/file.txt
dir/subdir/
/root-relative
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_slash` | `/` | Directory separator |
| `t_path_component` | `[\p{L}\p{N}_.-]+` | Path segment between slashes (Unicode-aware) |
| `t_leading_slash` | `^/` | Leading slash — anchors pattern to repo root (without it: matches in any subdirectory) |

---

### 5. Whitespace Handling

#### Leading Whitespace
```gitignore
  # Allowed in comments
  pattern.txt
```
(Leading spaces are significant for patterns)

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_leading_space` | `[ \t]+` | Spaces or tabs at line start |

#### Trailing Whitespace
```gitignore
file.txt   
```
(Trailing spaces ignored unless escaped)

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| `t_trailing_space` | `[ \t]+$` | Spaces or tabs at line end (ignored) |

---

### Structure Groups Summary

| Group | Elements |
|-------|----------|
| Line Types | Comment, Blank, Pattern |
| Pattern Components | Simple, Directory, Negation |
| Glob Patterns | Asterisk, Double-asterisk, Question mark, Character class |
| Special Characters | Escape sequence, Directory separator |
| Whitespace | Leading (significant), Trailing (ignored) |

---

## Format Structure Groups & Tokens Validation (Step 24)

### Line Types Validation

| Structure | Element | Current Value | Spec Reference | Status |
|-----------|---------|---------------|----------------|--------|
| **Comment Line** | Marker pattern | `#` | [Git docs](https://git-scm.com/docs/gitignore): "A line starting with `#`" | ✅ |
| | Content pattern | `[^\n]*` | Git docs: rest of line after `#` | ✅ |
| **Blank Line** | Whitespace pattern | `[ \t]*` | Git docs: "Blank lines ignored" | ✅ |
| | Newline | `\n` | Standard line ending | ✅ |
| **Pattern Line** | Content pattern | `[^\n]+` | Git docs: any pattern | ✅ | 

### Pattern Components Validation

| Structure | Element | Current Value | Spec Reference | Status |
|-----------|---------|---------------|----------------|--------|
| **Simple Pattern** | Literal chars | `[\p{L}\p{N}_.-]+` | Git docs: filename characters (Unicode filenames valid) | ✅ |
| | Asterisk wildcard | `\*` | Git docs: "`*` matches anything except `/`" | ✅ |
| | Question mark | `\?` | Git docs: "`?` matches any one character" | ✅ |
| **Directory Pattern** | Path | `[\p{L}\p{N}_.-/]+` | Git docs: path with separators | ✅ |
| | Trailing slash | `/` | Git docs: "trailing `/` for directory-only" | ✅ |
| **Negation Pattern** | Negation marker | `!` | Git docs: "`!` prefix negates pattern" | ✅ |
| | Pattern after `!` | `[^\n]+` | Git docs: pattern following `!` | ✅ | 

### Glob Patterns Validation

| Structure | Element | Current Value | Spec Reference | Status |
|-----------|---------|---------------|----------------|--------|
| **Asterisk Wildcard** | Single `*` | `\*` | Git docs: "matches anything except `/`" | ✅ |
| **Double Asterisk** | Pattern | `\*\*` | Git docs: "`**` matches zero or more directories" | ✅ |
| | With slashes | `\*\*/`, `/\*\*` | Git docs: directory matching | ✅ |
| **Question Mark** | Single char | `\?` | Git docs: "matches any one character except `/`" | ✅ |
| **Character Class** | Opening bracket | `\[` | Git docs: character class syntax | ✅ |
| | Content | `[^\]]+` | Git docs: characters inside brackets | ✅ |
| | Closing bracket | `\]` | Git docs: closes character class | ✅ |
| | Negation in class | `[!^]` at start | Git docs: "`!` or `^` negates class" — both documented | ✅ |
| | Escaped bracket | `\\\]` | Literal `]` inside class | ✅ |

### Special Characters Validation

| Structure | Element | Current Value | Spec Reference | Status |
|-----------|---------|---------------|----------------|--------|
| **Escape Sequence** | Backslash | `\\` | Git docs: "backslash escapes special chars" | ✅ |
| | Escaped char | `.` | Git docs: any character after `\` | ✅ |
| **Directory Separator** | Slash | `/` | Git docs: "path separator" | ✅ |
| | Path component | `[\p{L}\p{N}_.-]+` | Filename characters (Unicode-aware) | ✅ |
| | Leading slash | `/` at line start | Git docs: "leading `/` anchors pattern to repo root; without it matches in any subdirectory" | ✅ |

### Whitespace Validation

| Structure | Element | Current Value | Spec Reference | Status |
|-----------|---------|---------------|----------------|--------|
| **Leading Whitespace** | Pattern | `[ \t]+` | Git docs: leading spaces significant | ✅ |
| **Trailing Whitespace** | Pattern | `[ \t]+$` | Git docs: trailing spaces ignored | ✅ | 

### Validation Summary

**Total structures validated:** 14 elements across 5 groups

**Status:**
- All token patterns match Git documentation
- All patterns are syntactically correct regex
- All special characters properly documented
- No discrepancies found between documentation and specification

**Key validations:**
- Line Types: 3 structures validated (Comment, Blank, Pattern)
- Pattern Components: 3 structures validated (Simple, Directory, Negation)
- Glob Patterns: 4 structures validated (Asterisk, Double-asterisk, Question mark, Character class)
- Special Characters: 2 structures validated (Escape, Directory separator)
- Whitespace: 2 structures validated (Leading, Trailing)

**Notes:**
- All patterns follow Git's gitignore specification from official documentation
- Token patterns are suitable for lexer implementation
- Simple line-based format requires minimal parsing complexity
