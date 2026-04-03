# TokenizeCommand

Tokenize input files using a specified grammar and display the results in various formats.

## Command Name

```bash
parser:tokenize
```

## Description

This command tokenizes an input file using a grammar definition and displays the tokenization results. It breaks down the input into tokens according to the grammar rules and can display:
- Token statistics and distribution
- Detailed token list with positions and regions
- Simple token name list
- Nested region structure

This is essential for testing grammars, debugging tokenization issues, and understanding how input is broken down into tokens.

## Usage

```bash
php bin/console parser:tokenize <grammar-class> <input-file> [options]
```

## Arguments

### `grammar-class` (required)

The fully qualified class name of the grammar definition.

**Example:**
```bash
"PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259"
```

The class must implement `GrammarDefinitionInterface`.

### `input-file` (required)

Path to the input file to tokenize.

**Example:**
```bash
packages/parser/tests/Data/Json/rfc8259/testfile_1.json
```

## Options

### `--output=FILE` (`-o`)

Save the tokenization output to a file instead of displaying it in the terminal.

**Example:**
```bash
php bin/console parser:tokenize "..." input.json -o output.txt
```

### `--format=FORMAT` (`-f`)

Specify the output format. Available formats:
- `detailed` (default): Full token list with statistics, positions, and nested regions
- `simple`: List of token names only (one per line)
- `stats`: Statistics only (token distribution and region counts)

**Example:**
```bash
php bin/console parser:tokenize "..." input.json --format=stats
```

### `--no-row-col`

Disable row and column tracking during tokenization. This can improve performance for large files when position information is not needed.

**Example:**
```bash
php bin/console parser:tokenize "..." large-file.json --no-row-col
```

## Output Formats

### `detailed` Format (Default)

Shows comprehensive tokenization information:

```
========================================================
TOKENIZATION STATISTICS
========================================================

Total Tokens: 736
Total Regions: 217

--------------------------------------------------------
TOKEN DISTRIBUTION
--------------------------------------------------------
  space                         :   379 tokens (51.49%)
  double-quote                  :    98 tokens (13.32%)
  newline                       :    57 tokens ( 7.74%)
  ...

--------------------------------------------------------
REGION DISTRIBUTION
--------------------------------------------------------
  trailing_ws                   :    57 occurrences
  leading_ws                    :    56 occurrences
  string                        :    49 occurrences
  ...

========================================================
FULL TOKEN LIST WITH NESTED REGIONS
========================================================

🏁     1. bof                       | ""                                                 | pos:     0-0     | region: global

╔═══ REGION START: object               ═══╗
  {     2. begin-object              | "{"                                                | pos:     0-1     | region: object
  
  ╔═══ REGION START: string               ═══╗
    "     8. double-quote              | "\""                                               | pos:    12-13    | region: string
          9. unescaped                 | "doubleQuoted"                                     | pos:    14-26    | region: string
    "    10. double-quote              | "\""                                               | pos:    38-39    | region: string
  ╚═══ REGION END: string               ═══╝
  ...
```

### `simple` Format

Lists only token names (useful for quick inspection or piping):

```
bof
begin-object
newline
space
space
double-quote
unescaped
double-quote
name-separator
...
```

### `stats` Format

Shows only statistics (useful for grammar optimization):

```
========================================================
TOKENIZATION STATISTICS
========================================================

Total Tokens: 736
Total Regions: 217

--------------------------------------------------------
TOKEN DISTRIBUTION
--------------------------------------------------------
  space                         :   379 tokens (51.49%)
  double-quote                  :    98 tokens (13.32%)
  newline                       :    57 tokens ( 7.74%)
  ...

--------------------------------------------------------
REGION DISTRIBUTION
--------------------------------------------------------
  trailing_ws                   :    57 occurrences
  leading_ws                    :    56 occurrences
  string                        :    49 occurrences
  ...
```

## Examples

### Basic tokenization with detailed output

```bash
php bin/console parser:tokenize \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json
```

### Save tokenization to file

```bash
php bin/console parser:tokenize \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  -o tokenization-output.txt
```

### View only statistics

```bash
php bin/console parser:tokenize \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  --format=stats
```

### Simple token list (for scripting)

```bash
php bin/console parser:tokenize \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  --format=simple > tokens.txt
```

### Fast tokenization without position tracking

```bash
php bin/console parser:tokenize \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  large-file.json \
  --no-row-col \
  --format=stats
```

## Use Cases

### Grammar Development

Test your grammar rules to ensure they correctly tokenize input:

```bash
php bin/console parser:tokenize "MyGrammar" test-input.txt --format=detailed
```

### Debugging

Identify which tokens are being matched and in which regions:

```bash
php bin/console parser:tokenize "..." problematic-input.txt -o debug.txt
```

### Performance Analysis

Check token distribution to optimize grammar rules:

```bash
php bin/console parser:tokenize "..." input.txt --format=stats
```

### Documentation

Generate tokenization examples for documentation:

```bash
php bin/console parser:tokenize "..." example.txt -o docs/tokenization-example.txt
```

### Testing

Verify tokenization output matches expectations:

```bash
php bin/console parser:tokenize "..." test.txt --format=simple | diff - expected-tokens.txt
```

## Understanding the Output

### Token Information

Each token in detailed format shows:
- **Index**: Sequential token number
- **Name**: Token type name (from grammar definition)
- **Value**: The actual text matched (truncated if too long)
- **Position**: Byte position in the input (start-end)
- **Region**: The region where the token was matched

### Region Nesting

Regions are displayed with visual indicators:
- `╔═══ REGION START: name ═══╗` - Region begins
- `╚═══ REGION END: name ═══╝` - Region ends
- Indentation shows nesting level

### Special Tokens

- `🏁 bof` - Beginning of file (if grammar requires BOF/EOF)
- `eof` - End of file (if grammar requires BOF/EOF)

### Statistics

- **Token Distribution**: Shows which token types appear most frequently
- **Region Distribution**: Shows how many times each region was entered
- **Percentages**: Help identify dominant token types

## Performance Considerations

### Large Files

For large files, consider:
- Using `--no-row-col` to disable position tracking
- Using `--format=stats` to reduce output size
- Saving to file with `-o` instead of terminal output

### Memory Usage

Tokenization loads the entire file into memory. For very large files:
- Monitor memory usage
- Consider processing in chunks (requires custom implementation)

## Error Handling

The command will display an error and exit with code 1 if:
- The grammar class does not exist
- The class does not implement `GrammarDefinitionInterface`
- The input file does not exist or cannot be read
- Tokenization fails due to invalid input
- Grammar compilation fails

## Related Commands

- [`parser:grammar:view`](./ViewGrammarCommand.md) - View the grammar definition
- [`parser:grammar:compiled`](./ViewCompiledGrammarCommand.md) - View compiled grammar patterns
- [`parser:parse`](./ParseCommand.md) - Parse the tokenized input
