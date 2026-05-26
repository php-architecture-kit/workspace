## Step 25: Create Test Data Files

For each variant, create example files under `packages/parser/tests/Data/{PascalCaseFamily}/{variantDir}/`.

### Directory Convention

| Component | Convention | Example (JSON family) |
|-----------|------------|----------------------|
| `{PascalCaseFamily}` | PascalCase family name | `Json` |
| `{variantDir}` | matches `VARIANT` constant, lowercased | `rfc8259`, `c`, `5` |
| file extension | format's own extension | `.json`, `.jsonc`, `.json5` |

### Example Structure

For JSON family with variants JSON, JSONC, JSON5:

```
packages/parser/tests/Data/Json/
├── rfc8259/
│   └── testfile_1.json
├── c/
│   ├── line_comments.jsonc
│   ├── block_comments.jsonc
│   └── mixed.jsonc
└── 5/
    ├── numbers.json5
    ├── identifier_keys.json5
    ├── single_quoted.json5
    ├── trailing_commas.json5
    └── mixed.json5
```

### Naming Guidelines

**Use descriptive names that reflect the file's content.** No strict rules — name files based on what they demonstrate or test.

- One file per logical feature group (e.g., `numbers.json5`, `identifier_keys.json5`)
- A `mixed.{ext}` file covering the most common combinations
- Base content on the variant-specific examples from Step 17

### Adaptation Guidelines

When creating files for each variant:

1. **Most extended variant** — full example as-is (this is your baseline)
2. **Restricted variants** — remove unsupported features:
   - Remove comments if variant doesn't support them
   - Remove trailing commas if not allowed
   - Change string quotes (single → double, or unquoted → quoted)
   - Change number formats (hex → decimal, leading dot → full notation)
   - Remove special values (Infinity, NaN if not supported)
   - Adjust key syntax (unquoted → quoted if required)
3. **Keep structure identical** — same nesting, same keys/values (adapted syntax only)

---

## Step 26: Create Grammar PHP Class Skeletons

For each variant, create a PHP class in `packages/parser/src/Infrastructure/Grammar/Definition/{PascalCaseFamily}/`.

### File and Class Naming Convention

| Component | Convention | Example |
|-----------|------------|---------|
| Directory | `{PascalCaseFamily}` | `Json` |
| Class name | `{PascalCaseFamily}{PascalCaseVariant}` | `JsonRfc8259`, `JsonC`, `Json5` |
| `FORMAT` constant | family name, lowercase | `"json"` |
| `VARIANT` constant | variant identifier | `"rfc8259"`, `"c"`, `"5"` |
| Namespace | `PhpArchitecture\Parser\Infrastructure\Grammar\Definition\{PascalCaseFamily}` | |

### Inheritance Chain

Derived from the extends pipeline established in Step 9:

- **Base variant** extends `Whitespace` (`Technical\Whitespace`) — provides whitespace tokens shared by all grammars
- **Each subsequent variant** extends the previous one — inherits all rules, adds only new ones

**Example: JSON family**
```
Whitespace → JsonRfc8259 → JsonC → Json5
```

### Class Skeleton

```php
<?php

declare(strict_types=1);

namespace PhpArchitecture\Parser\Infrastructure\Grammar\Definition\{PascalCaseFamily};

use PhpArchitecture\Parser\Foundation\Grammar\Definition\Grammar;
use PhpArchitecture\Parser\Foundation\Grammar\Definition\Rule;
use PhpArchitecture\Parser\Foundation\Parsing\Model\NodeType;

class {ClassName} extends {ParentClass}
{
    public const FORMAT = "{family}";
    public const VARIANT = "{variant}";

    public function grammar(): Grammar
    {
        $grammar = parent::grammar();

        $grammar->global->add(
            // rules added in Step 27
        );

        return $grammar;
    }
}
```

**For the base variant**, set root region after adding rules:

```php
$grammar->setRootRegion($rootRegion);
```

---

## Step 27: Implement Grammar Rules

Translate the token table from Steps 4 and 24 into `Rule::*` calls inside each class.

### Rule API Reference

| Method | Use for | Notes |
|--------|---------|-------|
| `Rule::token($name, $literal)` | exact string match | auto-escaped, no regex needed |
| `Rule::expr($name, $pattern)` | PCRE pattern match | `u` flag always applied |
| `Rule::keyword($keyword)` | reserved word | like `token()` |
| `Rule::seq($name, $sequence)` | sequence of named rules | DSL string syntax |
| `Rule::choice($name, [$r1, $r2])` | one of named rules | |

### PHP String Escaping for `Rule::expr()`

The pattern is a plain PHP string — no delimiters. `RegexRule::fromString()` wraps it as `~\G{pattern}~u`.

| To match | PHP string | Resulting PCRE |
|----------|------------|----------------|
| literal `\` | `"\\\\"` | `\\` |
| `\n` in pattern | `"\\n"` | `\n` |
| `\p{L}` | `"\\p{L}"` | `\p{L}` |
| `[^\r\n]+` | `"[^\\r\\n]+"` | `[^\r\n]+` |
| `\\u[0-9a-fA-F]{4}` | `"\\\\u[0-9a-fA-F]{4}"` | `\\u[0-9a-fA-F]{4}` |

### Inheritance Rule

**Only implement rules NEW to this variant.** Rules from the parent class are inherited automatically via `parent::grammar()`.

To **extend** a region defined in a parent class, access it by name:

```php
$regions = $grammar->getAllRegions();
$regions['regionName']->add(...);
$regions['regionName']->withRootSequence('...');
```

### Example: JSON family structure

```
JsonRfc8259::grammar()   — defines: object, array, string, number, primitive, root region
JsonC::grammar()         — calls parent::grammar(), adds: lineComment, blockComment
Json5::grammar()         — calls parent::grammar(), adds: singleQuotedString, identifierKey,
                           signedInfinity, nan; modifies: number region, object region, array region
```

---

## Step 28: Verify Grammar Against Test Data Files

Parse each test data file (from Step 25) through its grammar and verify the **roundtrip invariant**: tokenized and parsed content must reconstruct the original input byte-for-byte.

### Verification Script

Create a temporary PHP script at `var/grammar-verify.php` (gitignored):

```php
<?php

require 'vendor/autoload.php';

use PhpArchitecture\Parser\Foundation\Grammar\Compiled\GrammarCompiler;
use PhpArchitecture\Parser\Foundation\Parsing\Context\DefaultParsingContext;
use PhpArchitecture\Parser\Foundation\Tokenization\Lexer;
use PhpArchitecture\Parser\Foundation\Tokenization\Model\StringStream;
use PhpArchitecture\Parser\Infrastructure\Grammar\Definition\{PascalCaseFamily}\{GrammarClass};

$grammar = (new {GrammarClass}())->grammar();
$grammar->requireBofEof = true;
$compiled = (new GrammarCompiler())->compile($grammar);
$context = new DefaultParsingContext($compiled);

$files = glob('packages/parser/tests/Data/{PascalCaseFamily}/{variantDir}/*');
foreach ($files as $file) {
    $input = file_get_contents($file);
    $stream = new StringStream($input);
    $tokenRegion = (new Lexer($context->tokenizationContext()))->process($stream);

    if ((string) $tokenRegion !== $input) {
        echo "FAIL (tokenization): $file\n";
        continue;
    }

    $result = $context->nodeFactory()->fromTokenRegion($tokenRegion, null);
    if ((string) $result !== $input) {
        echo "FAIL (parsing): $file\n";
        continue;
    }

    echo "OK: $file\n";
}
```

Run with:

```bash
php var/grammar-verify.php
```

### Success Criteria

All files print `OK`. If any prints `FAIL`, return to Step 27 and fix the grammar rule responsible.

### Repeat for All Variants

Run a separate verification for each variant class. Fix grammar rules until all variants pass.

---

## Step 29: Run Full Test Suite

After all variants pass verification:

```bash
bin/phpunit
```

from the workspace root. Verify all existing tests remain green — grammar changes must not regress the parser foundation.

**Expected output:**
```
OK (XXXX tests, XXXX assertions)
```

If any tests fail, investigate whether the failure is in the new grammar code or in the parser foundation, and fix accordingly before proceeding.
