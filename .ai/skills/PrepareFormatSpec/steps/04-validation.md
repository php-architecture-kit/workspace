## Step 22: Validate Example Coverage

After completing all variant documentation, validate that the comprehensive example (from Step 1) covers all features of the most extended variant.

### Purpose

- Ensure the example demonstrates ALL syntax features of the format
- Verify no features are missing from the example
- Provide a complete reference for parser testing

### Process

1. **List all features** of the most extended variant based on the specification
2. **Check each feature** against the comprehensive example
3. **Mark coverage status** for each feature

### Structure

Create a coverage checklist after the comprehensive example:

```markdown
### Example Coverage Validation

Based on the most extended variant: **{variant}**

| Feature Category | Feature | Covered | Location in Example |
|-----------------|---------|---------|---------------------|
| {category} | {feature} | ✅/❌ | line {n} or "missing" |
```

### Guidelines

1. **Be exhaustive** - Check every feature mentioned in the specification
2. **Note missing features** - Mark with ❌ and add them to the example
3. **Reference line numbers** - Point to exact location in the example
4. **Group by category** - Organize features logically (scalars, collections, etc.)

### Separated Lists Coverage

**REQUIRED** - Verify that the comprehensive example demonstrates ALL list types from "Separated Lists" table:

```markdown
### Separated Lists Coverage

| List Type | Demonstrated | Location in Example |
|-----------|--------------|---------------------|
| {type from Separated Lists table} | ✅/❌ | line {n} or "missing" |
```

For each list type, example MUST show:
- Basic usage with multiple elements
- Edge case: empty list (if allowed)
- Edge case: single element
- Trailing separator behavior (if optional/required)

### Actions

- **All features covered (✅)** → Example is complete, proceed to Step 23
- **Missing features (❌)** → Update the example to include missing features, then re-validate

---

## Step 23: Validate Root Values

Validate the "All Possible Document Root Values" section to ensure it contains minimal valid examples for each legal root type.

### Purpose

- Document the minimal content that parses successfully for each root type
- Provide test cases for parser root-level handling
- Ensure completeness based on official specification (not implementation)

### Process

1. **List all valid root types** from the most extended variant specification
2. **Create minimal example** for each type - smallest valid document
3. **Verify against specification** - confirm each is legal per spec

### Structure

Update the "All Possible Document Root Values" section with a validation table:

```markdown
### Root Values Validation

Based on the most extended variant: **{variant}**

| Root Type | Minimal Valid Example | Spec Reference | Validated |
|-----------|----------------------|----------------|-----------|
| {type} | `{minimal example}` | {section} | ✅/❌ |
```

### Guidelines

1. **Minimal means minimal** - The smallest possible valid document of that type
2. **Use specification, not implementation** - What the spec says is valid, not what a parser accepts
3. **Include edge cases** - Empty containers, single values, etc.
4. **Group related types** - All number variants together, all string variants together

### Examples of Minimal Values

| Type | Minimal Example | Notes |
|------|-----------------|-------|
| Empty object | `{}` | No properties |
| Empty array | `[]` | No elements |
| Empty string | `""` or `''` | Zero-length string |
| Integer | `0` | Smallest integer |
| Boolean | `true` | Single keyword |
| Null | `null` | Single keyword |

### Actions

- **All root types validated (✅)** → Proceed to next step
- **Missing root types (❌)** → Add missing examples to the section

---

## Step 24: Validate Format Structure Groups & Tokens

Perform thorough validation of all Format Structure Groups and their tokens against official specification.

### Purpose

- Ensure all structural elements are correctly documented
- Verify all token patterns match specification grammar
- Confirm parent-child relationships are accurate
- Validate examples are syntactically correct

### ⚠️ CRITICAL: Quality over Speed

**TRUTH > ACCURACY > PRECISION are MORE IMPORTANT than COSTS.**

**PROCESS MUST NOT BE OPTIMIZED.**

This step requires:
- Reading official specification for each element
- Cross-referencing token patterns with grammar rules
- Verifying every claim with evidence

### Process

For EACH structure group in "Format Structure Groups" section:

1. **Read specification section** for this structure
2. **Verify structure definition** matches spec
3. **Validate each token pattern** against spec grammar
4. **Confirm parent contexts** are complete and accurate
5. **Check examples** are syntactically valid

### Validation Table

Create validation table for each structure group:

```markdown
### {Structure Name} Validation

| Element | Current Value | Spec Reference | Spec Value | Status |
|---------|---------------|----------------|------------|--------|
| Pattern | `{current}` | {section} | `{from spec}` | ✅/❌ |
| Parent contexts | {list} | {section} | {from spec} | ✅/❌ |
| Token: {name} | `{pattern}` | {section} | `{from spec}` | ✅/❌ |
```

### Token Pattern Validation

For each token, verify:

1. **Pattern accuracy** - Does regex match spec grammar exactly?
2. **Character classes** - Are all allowed characters included?
3. **Quantifiers** - Are `*`, `+`, `?` correct per spec?
4. **Anchoring** - Is pattern properly bounded?

### Multi-line Comment Handling

**IMPORTANT:** Block/multi-line comments MUST be tokenized line-by-line, not as a single content blob.

```markdown
**Block Comment Tokens (line-by-line):**
| Token | Pattern | Description |
|-------|---------|-------------|
| t_block_comment_start | `/\*` | Block comment opening |
| t_block_comment_line | `[^\n]*` | Single line content within block comment |
| t_block_comment_newline | `\n` | Line break within block comment |
| t_block_comment_end | `\*/` | Block comment closing |
```

This approach:
- Preserves line structure for formatting/reconstruction
- Enables line-by-line processing
- Matches how editors handle multi-line comments

### Evidence Required

Every validation MUST include:
- **Spec section reference** (e.g., RFC8259#section-7)
- **Direct quote or grammar rule** from specification
- **Comparison** between documented and spec value

### Actions

- **All validated (✅)** → Proceed to next step
- **Discrepancies found (❌)** → Fix immediately, document correction

### ⛔ STOP After Step 24

After completing validation:

> **AI MUST STOP and present validation results to user.**
>
> Present:
> - Summary of validated elements
> - Any corrections made
> - Any remaining uncertainties

---

