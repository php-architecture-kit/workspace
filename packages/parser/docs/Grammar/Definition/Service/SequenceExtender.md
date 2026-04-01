# SequenceExtender

The `SequenceExtender` is a fluent builder that allows you to conditionally modify sequence rules by adding, modifying, or removing nodes. This feature was created to enable seamless injection of trivia (whitespace, comments) into sequences without polluting the sequence definitions with non-semantic nodes.

## Recommended Usage

The most common use case is to add trivia nodes between semantic nodes in a sequence. This is typically done through a middleware that processes all sequence rules during grammar definition:

```php
public function grammar(): Grammar
{
    // ...
    $grammar->global->addMiddleware($this->addTriviaMiddleware()); 
    // Note: This adds the middleware only to the global region.
    // Middlewares are not inherited during definition phase,
    // so you need to add them to each region where you want to apply them.
    // ...
}

private function addTriviaMiddleware(): AddRuleMiddleware
{
    return AddRuleMiddleware::fromCallable(
        static function (Rule $rule): Rule {
            if (!$rule->definition instanceof SequenceRule) {
                return $rule;
            }

            $extender = new SequenceExtender();
            $extender
                ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
                ->addNext('ws*');

            $rule->definition = $extender->extend($rule->definition);

            return $rule;
        },
        10
    );
}
```

## DSL Method Call Order

| Step | Method | Returns | Description |
|------|--------|---------|-------------|
| 1 | `when(callable $matcher)` | `SequenceExtenderRule` | Define the condition for matching nodes |
| 2 | `addPrev(...)` / `addNext(...)` / `modify(...)` / `remove()` | `SequenceExtenderRuleContext` | Define the action to perform |
| 3 (optional) | `which(callable $contextMatcher)` or `always()` | `SequenceExtender` | Define context matching or explicitly register the rule |

**Note:** If neither `which()` nor `always()` is called, the rule is automatically registered when the statement ends (via destructor).

## API Reference

### `when(callable $matcher): SequenceExtenderRule`

Defines the matcher function that determines which nodes the rule applies to.

**Matcher signature:** `fn(NestedSequence|SequenceNode $node, int $index, array $nodes): bool`

**Example:**
```php
$extender->when(fn($node) => in_array('separator', $node->alternatives))
```

---

### `addPrev(string|SequenceNode|NestedSequence|callable $node): SequenceExtenderRuleContext`

Adds a node before the matched node.

**Before:**
```
token separator value
```

**Code:**
```php
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->addPrev('ws*')
    ->always();
```

**After:**
```
token ws* separator value
```

---

### `addNext(string|SequenceNode|NestedSequence|callable $node): SequenceExtenderRuleContext`

Adds a node after the matched node.

**Before:**
```
token separator value
```

**Code:**
```php
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->addNext('ws*')
    ->always();
```

**After:**
```
token separator ws* value
```

---

### `modify(callable $callback): SequenceExtenderRuleContext`

Modifies the matched node by applying a transformation callback.

**Callback signature:** `fn(NestedSequence|SequenceNode $node, array $context): NestedSequence|SequenceNode`

The `$context` array contains:
- `'prev'` - Previous node (or `null` if first)
- `'current'` - Current matched node
- `'next'` - Next node (or `null` if last)

**Before:**
```
token separator value
```

**Code:**
```php
$extender
    ->when(fn($node) => in_array('token', $node->alternatives))
    ->modify(fn($node, $context) => new SequenceNode(
        ['modified-token'],
        $node->cardinality,
        $node->isLookahead,
        $node->isLookbehind,
        $node->anchorName,
        $node->tags
    ));
```

**After:**
```
modified-token separator value
```

---

### `remove(): SequenceExtenderRuleContext`

Removes the matched node from the sequence.

**Before:**
```
token separator value
```

**Code:**
```php
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->remove();
```

**After:**
```
token value
```

---

### `which(callable $contextMatcher): SequenceExtender`

Adds an additional context matcher that checks the surrounding nodes. The context node depends on the action:
- For `addPrev()`: checks the previous node
- For `addNext()`: checks the next node
- For `modify()` and `remove()`: checks the previous node

**Context matcher signature:** `fn(NestedSequence|SequenceNode|null $contextNode, int $index, array $nodes): bool`

**Before:**
```
token separator value
```

**Code:**
```php
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->addNext('ws*')
    ->which(fn($nextNode) => $nextNode !== null && in_array('value', $nextNode->alternatives));
```

**After:**
```
token separator ws* value
```

**Note:** If the context matcher returns `false`, the rule is not applied.

---

### `always(): SequenceExtender`

Registers the rule without any additional context matching. This is the default behavior if neither `which()` nor `always()` is called explicitly (auto-registration happens when the `SequenceExtenderRuleContext` is destroyed).

**Example:**
```php
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->addNext('ws*')
    ->always();
```

---

## Advanced Examples

### Adding trivia between all nodes

```php
$extender = new SequenceExtender();
$extender
    ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
    ->addNext('ws*');

$sequence = SequenceRule::fromString('a b c');
$result = $extender->extend($sequence);
// Result: a ws* b ws* c
```

### Multiple rules with different conditions

```php
$extender = new SequenceExtender();
$extender
    ->when(fn($node) => in_array('a', $node->alternatives))
    ->addNext('w+')
    ->always()  // Required for chaining
    ->when(fn($node) => in_array('b', $node->alternatives))
    ->addNext('ws*');

$sequence = SequenceRule::fromString('a b c');
$result = $extender->extend($sequence);
// Result: a w+ b ws* c
```

### Dynamic node creation with callable

```php
$counter = 0;
$extender = new SequenceExtender();
$extender
    ->when(fn($node, $index, $nodes) => $index < count($nodes) - 1)
    ->addNext(function($node, $context) use (&$counter) {
        $counter++;
        return SequenceNode::fromString("w{$counter}");
    });

$sequence = SequenceRule::fromString('a b c');
$result = $extender->extend($sequence);
// Result: a w1 b w2 c
```

### Context-aware modifications

```php
$extender = new SequenceExtender();
$extender
    ->when(fn($node) => in_array('separator', $node->alternatives))
    ->modify(fn($node) => new SequenceNode(['comma'], Cardinality::ExactlyOne))
    ->which(fn($prevNode) => $prevNode !== null && in_array('token', $prevNode->alternatives));

$sequence = SequenceRule::fromString('token separator value');
$result = $extender->extend($sequence);
// Result: token comma value
```

### Edge cases: First and last nodes

```php
// Add trivia before the first node
$extender = new SequenceExtender();
$extender
    ->when(fn($node, $index) => $index === 0)
    ->addPrev('ws*')
    ->which(fn($contextNode) => $contextNode === null);

// Add trivia after the last node
$extender = new SequenceExtender();
$extender
    ->when(fn($node, $index, $nodes) => $index === count($nodes) - 1)
    ->addNext('ws*')
    ->which(fn($contextNode) => $contextNode === null);
```

## Important Notes

- The `extend()` method returns a **news** `SequenceRule` instance and does not modify the original sequence.
- Rules are applied in the order they are registered.
- When multiple rules match the same node, all matching rules are applied sequentially.
- The matcher and context matcher functions receive the current state of the nodes array, which may have been modified by previous rules.
