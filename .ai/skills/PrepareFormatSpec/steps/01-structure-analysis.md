## Step 1: Create Example Block

Create a file `packages/parser/src/Infrastructure/Grammar/Definition/{PascalCaseFamily}/{family}.md` with an example block that demonstrates the most comprehensive form of the format.

### Purpose

The example block serves as:
- A reference for all possible syntax elements in the format
- A visual guide when designing tokens and nodes
- A test case for validating the schema

### Structure

```markdown
# {Format Name}

## Example

Based on the most extended form from {family} family: **{variant}**

```{language}
{comprehensive example covering all syntax elements}
```
```

### Guidelines

1. **Include all syntax variations** - Cover every possible syntax element the format supports
2. **Use the most feature-rich variant** - If the family has multiple variants (e.g., json, json5, jsonc), base the example on the most extended one
3. **Add comments** - If the format supports comments, include all comment styles
4. **Show nesting** - Demonstrate nested structures at multiple levels
5. **Include edge cases** - Special values, escapes, multiline content

### Example: JSON Family

```markdown
# Json

## Example

Based on the most extended form from json family: **json5**

```js
// comment
{
    /**
        Block comment
     */
    "key": "value",
    "key2": {
        test: value
    },
    key3: true,
    "key4": [
        1,
        "test",
        true,
        null,
        {
            test: value // trailing comment
        },
    ],
    key5: infinity,
    key6: NaN,
    key7: undefined,
}
```
```

---

## Step 2: Document All Possible Root Values

After the comprehensive example, document all possible values that can serve as the document root.

### Purpose

- Defines all valid top-level structures for the format
- Helps identify the `RootNode` children in schema design
- Serves as test cases for root-level parsing

### Structure

```markdown
## All Possible Document Root Values

{Format} allows any {value types} as the document root.

### {Type} Root
```{language}
{example}
```

### Summary of Root Value Types

| Type | Examples |
|------|----------|
| ... | ... |
```

### Guidelines

1. **List every possible root type** - Object, Array, primitives, special values
2. **Show each variant separately** - Different quote styles, number formats, etc.
3. **Include a summary table** - Quick reference of all root types

### Example: JSON5

For JSON5, valid root values include:
- **Object**: `{}`, `{key: "value"}`
- **Array**: `[]`, `[1, 2, 3]`
- **String**: `"text"`, `'text'`
- **Number**: `42`, `-1`, `+1`, `.5`, `5.`, `1e10`, `0xFF`
- **Infinity**: `Infinity`, `-Infinity`
- **NaN**: `NaN`
- **Boolean**: `true`, `false`
- **Null**: `null`

---

## Step 3: Document Format Structure Groups

Identify and document logical groupings of structural elements in the format.

### Purpose

- Provides a conceptual model of the format's structure
- Helps identify Node types during schema design
- Groups related elements for easier understanding

### Structure

```markdown
## Format Structure Groups

Logical groupings of structural elements in {Format}.

### 1. {Group Name}

{Description}

#### {Element}
```{language}
{examples}
```
```

### Standard Groups

Most formats have these common groups:

1. **Container Structures** - Elements that can contain other values (Object, Array, Block)
2. **Primitive Values** - Atomic values (String, Number, Boolean, Null)
3. **Member/Item Structures** - Components of containers (Keys, Separators)
4. **Comment Structures** - Documentation elements
5. **Whitespace Structures** - Formatting elements

### Guidelines

1. **Group by semantic role** - Not by syntax
2. **Show all variants** - Different syntaxes for the same concept
3. **Include separators** - Commas, colons, etc.
4. **Add a summary table** - Quick reference

### Example: JSON5 Groups

| Group | Elements |
|-------|----------|
| Container | Object, Array |
| Primitive | String, Number, Boolean, Null, Infinity, NaN |
| Object Member | Quoted Key, Unquoted Key, Colon, Comma |
| Array Element | Element, Comma |
| Comment | Single-Line, Multi-Line Block |
| Whitespace | Space, Tab, Newline, Extended |

---

## Step 4: Add Token Descriptions to Structure Groups

Extend the Format Structure Groups section with detailed token definitions for each group.

### Purpose

- Defines the lexical building blocks of the format
- Maps structure elements to their token representations
- Prepares for token schema definition in YAML

### Structure

For each structure group, add a "Tokens" subsection:

```markdown
### {Group Name}

#### {Element}
```{language}
{structure example}
```

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| t_{name} | `{regex or literal}` | {description} |
```

### Guidelines

1. **Name tokens with `t_` prefix** - Convention: `t_open_brace`, `t_comma`
2. **Use regex patterns** - Show the exact pattern the lexer will match
3. **Group related tokens** - All string-related tokens together, etc.
4. **Note token variants** - Different patterns for same semantic concept
5. **Include escape sequences** - For strings and special characters

### Example: JSON5 Object Tokens

```markdown
### Object

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| t_open_brace | `{` | Object opening delimiter |
| t_close_brace | `}` | Object closing delimiter |
| t_colon | `:` | Key-value separator |
| t_comma | `,` | Member separator |
```

### Example: JSON5 String Tokens

```markdown
### String

**Tokens:**
| Token | Pattern | Description |
|-------|---------|-------------|
| t_double_quote | `"` | Double quote delimiter |
| t_single_quote | `'` | Single quote delimiter |
| t_string_content | `[^"\\]+` | String content (double quoted) |
| t_escape_sequence | `\\[bfnrtv\\'"/]` | Escape sequences |
| t_unicode_escape | `\\u[0-9a-fA-F]{4}` | Unicode escape |
| t_line_continuation | `\\\n` | Line continuation |
```

---

