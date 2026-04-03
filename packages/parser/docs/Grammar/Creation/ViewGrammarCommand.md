# ViewGrammarCommand

Display the structure of a grammar definition, including regions, rules, and event subscribers.

## Command Name

```bash
parser:grammar:view
```

## Description

This command displays the complete structure of a grammar definition class. It shows:
- Grammar metadata (name, variant, root region, BOF/EOF requirements)
- Region hierarchy with nested regions
- Rules within each region (with types, tags, and priorities)
- Event subscribers attached to regions and rules

## Usage

```bash
php bin/console parser:grammar:view <grammar-class> [options]
```

## Arguments

### `grammar-class` (required)

The fully qualified class name of the grammar definition.

**Example:**
```bash
"PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259"
```

The class must implement `GrammarDefinitionInterface`.

## Options

### `--region=REGION` (`-r`)

Display only a specific region instead of the entire grammar structure.

**Example:**
```bash
php bin/console parser:grammar:view "..." --region=string
```

### `--show-rules`

Show detailed information about rules, including:
- Rule type (pattern, sequence, reference, etc.)
- Tags assigned to the rule
- Priority values
- Event subscribers count

**Example:**
```bash
php bin/console parser:grammar:view "..." --show-rules
```

### `--show-event-subscribers`

Display event subscribers attached to regions and rules, including:
- Event class name
- Listener class or closure
- Rule-specific bindings

**Example:**
```bash
php bin/console parser:grammar:view "..." --show-event-subscribers
```

## Output Format

### Basic Output

```
Grammar Definition: json (rfc8259)
==================================

Grammar Information
-------------------

 ----------------- --------- 
  Property          Value    
 ----------------- --------- 
  Name              json     
  Variant           rfc8259  
  Root Region       global   
  Require BOF/EOF   Yes      
  Total Regions     6        
 ----------------- --------- 

Regions Structure
-----------------

📦 global
  Rules: 13
  Nested Regions (5):
    📦 whitespace_region
      Rules: 5
    📦 array
      Rules: 4
    ...
```

### With `--show-rules`

```
📦 global
  Rules: 13
    - space (pattern) (priority: 0)
    - tab (pattern) (priority: 0)
    - newline (pattern) (priority: 0)
    ...
```

### With `--show-event-subscribers`

```
📦 global
  Event Subscribers: 15
    - TokenMatchedEvent → IdentifyRowsAndColumns
    - TokenRegionEndedEvent → IdentifyRowsAndColumns
    ...
```

## Examples

### View complete grammar structure

```bash
php bin/console parser:grammar:view \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259"
```

### View specific region with rules

```bash
php bin/console parser:grammar:view \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  --region=object \
  --show-rules
```

### View all details

```bash
php bin/console parser:grammar:view \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  --show-rules \
  --show-event-subscribers
```

## Use Cases

- **Grammar Development**: Verify the structure of a grammar during development
- **Documentation**: Generate documentation about grammar structure
- **Debugging**: Understand how regions and rules are organized
- **Learning**: Study existing grammar definitions to understand patterns

## Error Handling

The command will display an error and exit with code 1 if:
- The grammar class does not exist
- The class does not implement `GrammarDefinitionInterface`
- The specified region does not exist in the grammar

## Related Commands

- [`parser:grammar:compiled`](./ViewCompiledGrammarCommand.md) - View the compiled version of the grammar
- [`parser:tokenize`](./TokenizeCommand.md) - Tokenize input using the grammar
- [`parser:parse`](./ParseCommand.md) - Parse input using the grammar
