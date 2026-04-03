# ViewCompiledGrammarCommand

Display the compiled structure of a grammar, including pattern libraries, sequence libraries, and event subscribers.

## Command Name

```bash
parser:grammar:compiled
```

## Description

This command displays the compiled version of a grammar definition. After compilation, the grammar is transformed into optimized structures ready for tokenization and parsing:
- **Pattern Library**: Regex patterns for token matching
- **Sequence Library**: Sequence definitions for parsing
- **Event Subscribers**: Event listeners attached to specific events and rules

This is useful for understanding how the grammar compiler transforms your grammar definition into executable structures.

## Usage

```bash
php bin/console parser:grammar:compiled <grammar-class> [options]
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

Display only a specific compiled region instead of all regions.

**Example:**
```bash
php bin/console parser:grammar:compiled "..." --region=string
```

### `--show-patterns`

Show the pattern library with regex patterns for each token type, including:
- Token name
- Regex pattern
- Priority value
- Tags

**Example:**
```bash
php bin/console parser:grammar:compiled "..." --show-patterns
```

### `--show-sequences`

Display the sequence library with sequence definitions, including:
- Sequence name
- Node alternatives
- Cardinality (min/max occurrences)

**Example:**
```bash
php bin/console parser:grammar:compiled "..." --show-sequences
```

### `--show-event-subscribers`

Show event subscribers attached to the compiled grammar, including:
- Event class name
- Listener class or closure
- Rule-specific bindings

**Example:**
```bash
php bin/console parser:grammar:compiled "..." --show-event-subscribers
```

## Output Format

### Basic Output

```
Compiled Grammar: json (rfc8259)
================================

Compiled Grammar Information
----------------------------

 ----------------- --------- 
  Property          Value    
 ----------------- --------- 
  Name              json     
  Variant           rfc8259  
  Root Region       global   
  Require BOF/EOF   Yes      
  Total Regions     6        
 ----------------- --------- 

Compiled Regions
----------------

📦 global
  Patterns: 16
  Sequences: 0
  Event Subscribers: 15

📦 string
  Patterns: 4
  Sequences: 0
  Event Subscribers: 1
```

### With `--show-patterns`

```
📦 string
  Patterns: 4

  Pattern Library:
    - escape-char: ~\G\\[bfnrt\\"]~ui (priority: 1)
    - double-quote: ~\G"~u (priority: 0)
    - unescaped: ~\G[^\x00-\x1F\x22\x5C]+~ui (priority: 0)
    - escape-unicode: ~\G\\u[0-9a-fA-F]{4}~ui (priority: 0)
```

### With `--show-sequences`

```
📦 array
  Sequences: 1

  Sequence Library:
    - items:
      [0] (value) - min:0, max:2147483647
      [1] (value-separator) - min:1, max:1
```

### With `--show-event-subscribers`

```
📦 global
  Event Subscribers: 15

  Event Subscribers:
    - TokenMatchedEvent → IdentifyRowsAndColumns (rule: all)
    - TokenRegionEndedEvent → IdentifyRowsAndColumns (rule: all)
```

## Examples

### View compiled grammar overview

```bash
php bin/console parser:grammar:compiled \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259"
```

### View pattern library for a specific region

```bash
php bin/console parser:grammar:compiled \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  --region=string \
  --show-patterns
```

### View all compilation details

```bash
php bin/console parser:grammar:compiled \
  "PhpArchitecture\Parser\Grammar\Registry\Definition\Json\JsonRfc8259" \
  --show-patterns \
  --show-sequences \
  --show-event-subscribers
```

### Compare regions

```bash
# View number region
php bin/console parser:grammar:compiled "..." --region=number --show-sequences

# View array region
php bin/console parser:grammar:compiled "..." --region=array --show-sequences
```

## Use Cases

- **Grammar Optimization**: Verify that patterns are compiled correctly and efficiently
- **Debugging**: Understand why certain tokens are matched or not matched
- **Performance Analysis**: Check pattern priorities and complexity
- **Learning**: Study how the grammar compiler transforms definitions
- **Validation**: Ensure sequences are correctly defined for parsing

## Understanding the Output

### Pattern Library

Patterns are regex expressions used for tokenization. Key aspects:
- **Priority**: Higher priority patterns are matched first
- **Regex Flags**: `u` (UTF-8), `i` (case-insensitive), `G` (anchored at current position)
- **Tags**: Metadata attached to patterns

### Sequence Library

Sequences define the order and cardinality of tokens/sequences in parsing:
- **Alternatives**: Multiple token types that can match at a position
- **Cardinality**: `min` and `max` occurrences allowed
- **Nested Sequences**: Sequences can contain other sequences

### Event Subscribers

Event subscribers are listeners that react to tokenization/parsing events:
- **Event Class**: The event type being listened to
- **Listener**: The class or closure handling the event
- **Rule Binding**: Optional binding to specific rule names

## Error Handling

The command will display an error and exit with code 1 if:
- The grammar class does not exist
- The class does not implement `GrammarDefinitionInterface`
- The specified region does not exist in the compiled grammar
- Grammar compilation fails

## Related Commands

- [`parser:grammar:view`](./ViewGrammarCommand.md) - View the original grammar definition
- [`parser:tokenize`](./TokenizeCommand.md) - Test tokenization with the compiled grammar
- [`parser:parse`](./ParseCommand.md) - Test parsing with the compiled grammar
