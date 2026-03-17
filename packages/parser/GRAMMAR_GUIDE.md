# Grammar Guide

## Sequence

### Sequence Properties

| Property | Example | Description |
|----------|---------|-------------|
| Basic node | `token` | Single node without modifiers |
| Alternatives (union) | `token\|member\|value` | Node can be one of multiple alternatives, separated by `\|` |
| Optional (zero or one) | `?token` | Node can occur 0 or 1 time |
| Zero or more | `token*` | Node can occur 0 or more times |
| One or more | `token+` | Node can occur 1 or more times |
| Lookahead | `>token` | Node checked without consumption (must be last in sequence) |
| Lookbehind | `<token` | Node checked backwards without consumption (must be first in sequence) |
| Nested sequence | `(ws token)` | Nested sequence in parentheses |
| Nested union | `(seq1)\|(seq2)` | Alternative nested sequences |
| Anchor name | `token+[anchorName]` | Optional anchor name for SequenceNode (not for NestedSequence) |
| Tags | `token/t` | Single-letter tags for categorization |
| Tags multiple | `token/abc` | Multiple tags at once |
| Anchor + tags | `token+[anchor]/st` | Combination of anchor name and tags |
| Nested + tags | `(ws token)/s` | Nested sequence with tags |

### Example: JSON Object

```
begin-object[lbrace]/s ws ?members ws end-object[rbrace]/s
```

Where `members` can be defined as:

```
member (?value-separator[comma]/s ws member)*
```

And `member` as:

```
ws string-literal[key] ws name-separator[colon]/s ws value
```

Full sequence with expansion:

```
begin-object[lbrace]/s ws ?(ws string-literal[key] ws name-separator[colon]/s ws value (?value-separator[comma]/s ws ws string-literal[key] ws name-separator[colon]/s ws value)*) ws end-object[rbrace]/s
```
