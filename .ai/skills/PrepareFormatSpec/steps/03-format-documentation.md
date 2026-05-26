## Step 16: Document Character Encoding Support

After adding documentation links, create a table documenting character encoding support for each variant.

### Purpose

- Document which character sets are supported in keys vs values
- Identify Unicode/emoji support based on official specs
- Provide reference for parser implementation

### Structure

Add after the link table for each variant:

```markdown
**Character Encoding Support:**

| Element | Encoding | Allowed Characters | Reference | Evidence | Confirmed |
|---------|----------|-------------------|-----------|----------|-----------|
| Keys | ASCII | `[a-zA-Z_][a-zA-Z0-9_]*` | {spec link} | {anchor or search term} | ✅ verified / ⚠️ from memory |
| Values (unquoted) | UTF-8 | printable, no whitespace | {spec link} | {anchor or search term} | ✅ verified / ⚠️ from memory |
| Values (quoted) | UTF-8 | any Unicode | {spec link} | {anchor or search term} | ✅ verified / ⚠️ from memory |
```

**Evidence column (MANDATORY):** Provide one of:
- Direct anchor link: `[spec#section](url#anchor)`
- Full URL with anchor: `https://example.com/spec#section-name`
- No evidence available: `❌ no evidence found`

**Confirmed column:**
- `✅ verified` - AI visited the page and confirmed the evidence
- `⚠️ from memory` - AI wrote from training data without verification
- `❌ unverified` - No evidence link provided

### Character Categories to Check

Based on official documentation, verify support for:

| Category | Characters | Test |
|----------|------------|------|
| ASCII letters | `a-zA-Z` | basic |
| Digits | `0-9` | basic |
| Polish | `ąćęłńóśźżĄĆĘŁŃÓŚŹŻ` | Latin Extended |
| German | `äöüßÄÖÜ` | Latin Extended |
| Cyrillic | `абвгдеёжзийАБВГДЕЁЖЗИЙ` | Cyrillic block |
| Chinese | `中文字符汉字` | CJK Unified |
| Japanese | `日本語ひらがなカタカナ` | Hiragana/Katakana/Kanji |
| Arabic | `العربية` | Arabic block (RTL) |
| Emoji | `🚀💻🔧🌍` | Emoji (surrogate pairs) |
| Special WS | `\u00A0\u2003` | Non-breaking, Em space |

### Numeric Format Support

For formats that distinguish numeric types, also check:

| Category | Examples | Notes |
|----------|----------|-------|
| Integers | `123`, `0`, `42` | basic |
| Negative | `-123`, `-1` | minus sign handling |
| Float | `3.14`, `0.5`, `.5`, `5.` | decimal point, leading/trailing |
| Exponent | `1e10`, `1E-5`, `1.5e+3` | scientific notation |
| Infinity | `Infinity`, `-Infinity`, `+Infinity` | special values |
| NaN | `NaN` | not-a-number |
| Hexadecimal | `0xFF`, `0xDECAF` | hex literals |
| Octal | `0o777`, `0777` | octal literals |
| Binary | `0b1010` | binary literals |
| Thousand sep | `1_000_000`, `1,000,000` | numeric separators |
| Explicit plus | `+123`, `+3.14` | plus sign prefix |

### ⚠️ Post-Step 16 Verification

After completing Step 16, AI MUST check if any entry in the Character Encoding or Numeric Format tables has `⚠️ from memory` in the Confirmed column.

**If ANY entry is marked `⚠️ from memory`:**

# ⛔ STOP

AI must present a list of unverified entries and wait for user decision:

```markdown
**Unverified entries (from memory):**

| Variant | Element | Current Value | Action Needed |
|---------|---------|---------------|---------------|
| {variant} | {element} | {value} | User to verify or AI to search |

**User options:**
1. "verify" - User will check the links manually
2. "search {variant}" - AI will search for evidence
3. "accept as-is" - Continue without verification
```

**If ALL entries are `✅ verified`:** Proceed to Step 17.

---

## Step 17: Create Variant-Specific Examples

After reading the official specifications and documenting encoding support, create a single representative example for each variant.

### Purpose

- Highlight differences between variants
- Serve as quick reference for variant features
- Provide test cases for parsing

### Guidelines

1. **Read the whitepapers first** - Understand the exact syntax from official specs
2. **Focus on differences** - Show what makes this variant unique
3. **One codeblock per variant** - Keep it concise
4. **Add descriptive label** - e.g., "Variant-specific example (strict syntax, no comments)"
5. **Base variant MUST show common usage** - The most basic variant example should be the simplest, most common case
6. **Use encoding table** - Include characters from supported categories in examples

### Example Structure

```markdown
**Variant-specific example** ({what makes it different}):
```{language}
{code showing variant-specific features}
```
```

### JSON Family Examples

**JSON (RFC 8259):**
- Strict syntax
- Double-quoted strings only
- No comments, no trailing commas

**JSONC:**
- Comments only (`//` and `/* */`)
- NO trailing commas (per jsonc.org spec)
- Everything else same as JSON

**JSON5:**
- Unquoted keys (IdentifierName)
- Single-quoted strings
- Multi-line strings (escaped newlines)
- Trailing commas
- Hexadecimal, leading/trailing decimal
- Infinity, NaN, explicit plus
- Comments

---

## Step 18: Add Variant Summary with Recommendation

After the variant-specific example, add a summary focusing on adoption and importance.

**⚠️ CRITICAL RULE: AI MUST NOT remove variants on its own. Only the user can decide to remove a variant.**

### Structure

```markdown
**Variant Summary:**
{Description of adoption and common usage}.
**Recommendation: {MUST KEEP / SHOULD KEEP / CONSIDER REMOVING}** - {reason}.
```

### Recommendation Levels

| Level | Meaning |
|-------|---------|
| ✅ MUST KEEP | Foundational format, universal adoption |
| ✅ SHOULD KEEP | High adoption, specific ecosystem value |
| ⚠️ CONSIDER REMOVING | Low adoption, user should evaluate need |

### Example Summaries

**JSON:** "Universal baseline, every API supports it. **Recommendation: ✅ MUST KEEP**"

**JSONC:** "Millions use via VS Code daily. **Recommendation: ✅ SHOULD KEEP**"

**JSON5:** "Used in Babel, Parcel, JS tooling. **Recommendation: ✅ SHOULD KEEP**"

---

## Step 19: ⛔ STOP - Final User Verification

# ⛔ STOP

**AI MUST STOP HERE AND WAIT FOR USER VERIFICATION.**

This is a mandatory checkpoint. AI cannot proceed without explicit user confirmation.

### What User Must Verify

1. **Documentation links** - Click each link and verify it works
2. **Character encoding tables** - Verify evidence links are correct
3. **Variant examples** - Check if examples are accurate
4. **Variant summaries** - Confirm recommendations are correct
5. **Variant selection** - User may remove variants they don't need

### User Actions

- **Confirm** - Say "continue" or "proceed" to move to next step
- **Remove variant** - Say "remove {variant}" to delete a variant
- **Fix issue** - Point out any errors for AI to correct

### ⚠️ CRITICAL

**AI MUST NOT proceed without explicit user command.**

---

## Step 20: Check Variant Conflicts

After user verification, check if there are still conflicts between remaining variants.

### Actions

1. **Review remaining variants** - After user removed unwanted variants
2. **Check extends pipeline** - Verify linear extension is still valid
3. **Identify new conflicts** - If removal created new conflicts

### Decision

- **No conflicts** → Proceed to Step 21
- **Conflicts found** → Request user resolution (see below)

### If Conflicts Found

# ⚠️ VARIANT CONFLICTS DETECTED

AI must present conflicts to user and request resolution:

```markdown
**Conflict detected between variants:**

| Variant A | Variant B | Conflict |
|-----------|-----------|----------|
| {variant} | {variant} | {description of incompatibility} |

**Resolution options:**
1. Remove {variant A} - Keep only {variant B}
2. Remove {variant B} - Keep only {variant A}
3. Create separate pipelines (if architecturally supported)

**User action required:** Choose resolution option or provide alternative.
```

After user resolves conflict → Return to Step 19 for re-verification.

### Example

After removing a variant, check if the extends pipeline is still valid:

```
Before: JSON → JSONC → JSON5 (linear, no conflicts)
After removing JSONC: JSON → JSON5 (still linear, OK)
```

If conflicts arise (e.g., two variants now have incompatible features), user must resolve.

---

## Step 21: Format Features Table

For each variant, add a comprehensive features table describing technical characteristics of the format.

### Purpose

- Document technical capabilities of each format variant
- Provide quick reference for parser/tooling selection
- Enable comparison between variants

### Required Features

Add this table to each variant section:

```markdown
**Format Features:**

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ✅/❌ | If ✅: link to php.net or packagist library |
| PHP Emitting | ✅/❌ | If ✅: link to php.net or packagist library |
| AST Library | ✅/❌ | If ✅: link to packagist + downloads count |
| Line Sensitive | ✅/❌ | If ❌: can be minified to single line |
| Nestable | ✅/❌ | If ✅: what structures can nest |
| Indentation Sensitive | ✅/❌ | If ✅: describe rules; If ❌: state "free-form" |
| Comments Support | ✅/❌ | If ✅: list comment types |
| Docblock Support | ✅/❌ | If ✅: describe docblock format |
| Multi-document | ✅/❌ | If ✅: describe separator |
| Schema Support | ✅/❌ | If ✅: name + link + brief description |
```

### Feature Definitions

1. **PHP Native Parsing**
   - Can the format be parsed to PHP array/object/class?
   - **List ALL available options** (native functions AND libraries):
     - First: `php.net` function if native/extension support exists (note: some require PECL extensions)
     - Then: most popular non-deprecated packagist library
   - If multiple options exist, list all with semicolon separator
   - NOT interested in AST - only useful parsing to data structures
   - Example: `✅ | [yaml_parse()](php.net) (PECL); [symfony/yaml](packagist) (200M+)`

2. **PHP Emitting**
   - Can PHP array/object be serialized back to this format?
   - **List ALL available options** (native functions AND libraries):
     - First: `php.net` function if native/extension support exists
     - Then: packagist library
   - If multiple options exist, list all with semicolon separator
   - Example: `✅ | [yaml_emit()](php.net) (PECL); [symfony/yaml](packagist)`

3. **AST Library**
   - Is there a packagist library for AST parsing?
   - Include download count and whether it's actively maintained

4. **Line Sensitive**
   - `❌ No` = File CAN be minified to single working line
   - `✅ Yes` = File CANNOT be minified (line breaks are significant)

5. **Nestable**
   - Can structures (objects, arrays) contain other structures?
   - If ✅, specify which structures can nest

6. **Indentation Sensitive**
   - Do whitespace indents affect parsing result?
   - `✅ Yes` = Describe: allowed chars (spaces/tabs), default indent size (if any), suggested indent size
   - `❌ No` = State "free-form whitespace"
   - **If ✅, include:** "default: {n} spaces" or "no default, suggested: {n} spaces" or "configurable"

7. **Comments Support**
   - `❌ No` = Comments not supported
   - `✅ Yes` = List types: single-line (`//`), multi-line (`/* */`), hash (`#`)

8. **Docblock Support**
   - Does format support structured documentation blocks?
   - `❌ No` = Not supported
   - `✅ Yes` = Describe format (e.g., `/** @param */`, `# @type`)

9. **Multi-document Support**
   - Can file contain multiple documents?
   - `❌ No` = Single root value per file
   - `✅ Yes` = Describe separator (e.g., `---` in YAML)

10. **Schema Support**
    - Does format have schema validation support?
    - `❌ No` = No schema support
    - `✅ Yes` = Name + link + brief description of what schema validates

### Separated Lists Section

**REQUIRED** - Document each list type in the format:

```markdown
**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| {type} | `{char}` | forbidden/optional/required | ✅/❌ | `{minimal example}` |
```

**Column definitions:**

- **List Type**: Name of the list structure (e.g., "Array elements", "Document → rows")
- **Separator**: The character(s) that separate elements:
  - Single char: `,`, `;`, `\n`, `\t`
  - Multiple options: `,` or `\t` (CSV)
  - Contextual: whitespace (YAML flow), indentation (YAML block)
- **Trailing**: Policy for separator after last element:
  - `forbidden` = Parser error if present
  - `optional` = Allowed but not required  
  - `required` = Must be present
- **Configurable**: Can separator be changed by user/header/config?
  - ✅ = Yes (e.g., CSV delimiter)
  - ❌ = No (fixed by spec)

**Special cases to document:**

1. **Format IS a list** (e.g., `.env`, `.gitignore`, CSV rows)
   - Document: "Document → {element type}"
   
2. **Multiple list types in same format** (e.g., PHP)
   - Document each: expressions `;`, array elements `,`, match arms `,`
   
3. **Multiple syntaxes for same list** (e.g., YAML)
   - Document each variant:
   
```markdown
| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Block sequence | `- ` + newline | n/a | ❌ | `- item1\n- item2` |
| Flow sequence | `,` | optional | ❌ | `[a, b, c]` |
| Block mapping | `: ` + newline | n/a | ❌ | `key: value\n` |
| Flow mapping | `,` | optional | ❌ | `{a: 1, b: 2}` |
```

4. **Nested lists with different separators** (e.g., CSV)
```markdown
| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Document → rows | `\n` or `\r\n` | optional | ❌ | `row1\nrow2` |
| Row → fields | `,` | forbidden | ✅ | `a,b,c` |
```

### Structure Example

```markdown
**Format Features:**

| Feature | Value | Notes |
|---------|-------|-------|
| PHP Native Parsing | ✅ | [`json_decode()`](https://www.php.net/json_decode) → array/object |
| PHP Emitting | ✅ | [`json_encode()`](https://www.php.net/json_encode) |
| AST Library | ❌ | No maintained library |
| Line Sensitive | ❌ | Can be minified to single line |
| Nestable | ✅ | Objects and arrays can contain objects/arrays |
| Indentation Sensitive | ❌ | Free-form whitespace |
| Comments Support | ❌ | Not supported |
| Docblock Support | ❌ | Not supported |
| Multi-document | ❌ | Single root value per file |
| Schema Support | ✅ | [JSON Schema](https://json-schema.org/) - validates structure, types, constraints |

**Separated Lists:**

| List Type | Separator | Trailing | Configurable | Example |
|-----------|-----------|----------|--------------|---------|
| Array elements | `,` | forbidden | ❌ | `[1,2,3]` |
| Object properties | `,` | forbidden | ❌ | `{"a":1,"b":2}` |
```

### ⛔ STOP After Step 21

After completing Format Features tables for all variants:

> **AI MUST STOP and present results to user for review.**
>
> User may want to:
> - Add more features
> - Modify feature definitions
> - Remove unnecessary features
> - Adjust library recommendations

---

