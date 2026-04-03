# ParseCommand

Parse input files using a specified grammar and display the parse tree in various formats.

## Command Name

```bash
parser:parse
```

## Description

This command parses an input file using a grammar definition and displays the resulting parse tree. It performs tokenization and then organizes tokens into a hierarchical structure based on the grammar rules.

The parse tree shows:
- Token hierarchy and nesting
- Region structure
- Token values and positions
- Complete document structure

This is useful for testing grammars, debugging parsing issues, and understanding document structure.

## Usage

```bash
php bin/console parser:parse <grammar-class> <input-file> [options]
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

Path to the input file to parse.

**Example:**
```bash
packages/parser/tests/Data/Json/rfc8259/testfile_1.json
```

## Options

### `--output=FILE` (`-o`)

Save the parse tree output to a file instead of displaying it in the terminal.

**Example:**
```bash
php bin/console parser:parse "..." input.json -o parse-tree.txt
```

### `--format=FORMAT` (`-f`)

Specify the output format. Available formats:
- `tree` (default): Visual tree representation with indentation
- `json`: JSON structure with full metadata
- `simple`: Plain text reconstruction (token values only)

**Example:**
```bash
php bin/console parser:parse "..." input.json --format=json
```

### `--max-depth=DEPTH` (`-d`)

Limit the depth of the tree display. Useful for large parse trees.

**Example:**
```bash
php bin/console parser:parse "..." input.json --max-depth=5
```

## Output Formats

### `tree` Format (Default)

Displays a visual tree structure:

```
├─ Region: global
  ├─ Token: bof = ""
  ├─ Region: object
    ├─ Token: begin-object = "{"
    ├─ Region: trailing_ws
      ├─ Token: newline = "\n"
    ├─ Region: leading_ws
      ├─ Token: space = " "
      ├─ Token: space = " "
      ├─ Token: space = " "
      ├─ Token: space = " "
    ├─ Region: string
      ├─ Token: double-quote = "\""
      ├─ Token: unescaped = "doubleQuoted"
      ├─ Token: double-quote = "\""
    ├─ Token: name-separator = ":"
    ├─ Region: inline_ws
      ├─ Token: space = " "
    ├─ Region: string
      ├─ Token: double-quote = "\""
      ├─ Token: unescaped = "standard JSON string"
      ├─ Token: double-quote = "\""
    ...
```

### `json` Format

Outputs structured JSON with complete metadata:

```json
{
    "type": "Region",
    "name": "global",
    "children": [
        {
            "type": "Token",
            "name": "bof",
            "value": "",
            "position": {
                "start": 0,
                "end": 0
            }
        },
        {
            "type": "Region",
            "name": "object",
            "children": [
                {
                    "type": "Token",
                    "name": "begin-object",
                    "value": "{",
                    "position": {
                        "start": 0,
                        "end": 1
                    }
                },
                {
                    "type": "Region",
                    "name": "string",
                    "children": [
                        {
                            "type": "Token",
                            "name": "double-quote",
                            "value": "\"",
                            "position": {
                                "start": 12,
                                "end": 13
                            }
                        }
                    ]
                }
            ]
        }
    ]
}
```

### `simple` Format

Reconstructs the original text from token values:

```
{
    "doubleQuoted": "standard JSON string",
    "withEscapes": "tab:\there\nnewline\r\ncarriage return",
    ...
}
```

This format is useful for:
- Verifying that tokenization is lossless
- Extracting text content
- Comparing with original input

## Examples

### Basic parsing with tree output

```bash
php bin/console parser:parse \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json
```

### Save parse tree to file

```bash
php bin/console parser:parse \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  -o parse-tree.txt
```

### Export as JSON for processing

```bash
php bin/console parser:parse \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  --format=json \
  -o parse-tree.json
```

### Limit tree depth for large files

```bash
php bin/console parser:parse \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  large-file.json \
  --max-depth=3
```

### Reconstruct original text

```bash
php bin/console parser:parse \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  testfile.json \
  --format=simple
```

### Compare with original

```bash
php bin/console parser:parse "..." input.txt --format=simple | diff - input.txt
```

## Use Cases

### Grammar Validation

Verify that your grammar correctly parses input:

```bash
php bin/console parser:parse "MyGrammar" test-input.txt
```

### Structure Analysis

Understand the hierarchical structure of documents:

```bash
php bin/console parser:parse "..." document.txt --format=tree -o structure.txt
```

### Debugging

Identify parsing issues by examining the parse tree:

```bash
php bin/console parser:parse "..." problematic-input.txt --max-depth=5
```

### Data Extraction

Export parse tree as JSON for further processing:

```bash
php bin/console parser:parse "..." data.txt --format=json -o data.json
```

### Testing

Verify parsing output in automated tests:

```bash
php bin/console parser:parse "..." test.txt --format=json | jq '.children | length'
```

### Documentation

Generate parse tree examples for documentation:

```bash
php bin/console parser:parse "..." example.txt --max-depth=3 -o docs/parse-example.txt
```

## Understanding the Output

### Tree Format

- `├─` - Tree branch indicator
- `Region: name` - A region containing nested tokens/regions
- `Token: name = "value"` - A token with its value
- Indentation shows nesting level

### JSON Format

Each node in the JSON output contains:
- `type`: Either "Token" or "Region"
- `name`: The token/region name
- `value`: The token value (for tokens only)
- `position`: Start and end byte positions (for tokens only)
- `children`: Array of child nodes (for regions only)

### Simple Format

Concatenates all token values in order, reconstructing the original input. This should match the input file exactly if tokenization is lossless.

## Performance Considerations

### Large Files

For large files:
- Use `--max-depth` to limit output size
- Save to file with `-o` instead of terminal output
- Consider using `--format=simple` for quick validation

### Memory Usage

Parsing loads the entire file and builds the complete parse tree in memory. For very large files, monitor memory usage.

## Differences from Tokenization

While [`parser:tokenize`](./TokenizeCommand.md) shows the flat list of tokens, `parser:parse` shows the hierarchical structure:

**Tokenization** (flat):
```
1. bof
2. begin-object
3. newline
4. space
5. space
...
```

**Parsing** (hierarchical):
```
├─ Region: global
  ├─ Token: bof
  ├─ Region: object
    ├─ Token: begin-object
    ├─ Region: trailing_ws
      ├─ Token: newline
    ├─ Region: leading_ws
      ├─ Token: space
      ├─ Token: space
```

## Error Handling

The command will display an error and exit with code 1 if:
- The grammar class does not exist
- The class does not implement `GrammarDefinitionInterface`
- The input file does not exist or cannot be read
- Parsing fails due to invalid input
- Grammar compilation fails

## Related Commands

- [`parser:grammar:view`](./ViewGrammarCommand.md) - View the grammar definition
- [`parser:grammar:compiled`](./ViewCompiledGrammarCommand.md) - View compiled grammar
- [`parser:tokenize`](./TokenizeCommand.md) - View tokenization output (flat structure)
