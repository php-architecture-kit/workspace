# GFM Document

## Introduction

This is a paragraph with **bold text**, *italic text*, ***bold and italic***,
~~strikethrough~~, and `inline code`.

---

## GFM Extensions

### Strikethrough

~~Deleted text~~ replaced with new content.

This is ~~old~~ new text in a sentence.

Combined: ~~**bold strikethrough**~~ and **~~also bold~~**.

### Task Lists

- [ ] Unchecked task
- [x] Checked task (lowercase x)
- [X] Checked task (uppercase X)
- [ ] Another pending task

Nested task list:

- [ ] Parent unchecked
  - [ ] Child task 1
  - [x] Child task 2 done
- [x] Parent checked
  - [x] All children complete

Task list mixed with regular items:

- [ ] Task item
- Regular list item
- [x] Completed task
- Another regular item

### Tables

Basic table:

| Header 1 | Header 2 |
|----------|----------|
| Cell A   | Cell B   |
| Cell C   | Cell D   |

Table with column alignment:

| Left     | Center   |    Right |
|:---------|:--------:|---------:|
| L1       |    C1    |       R1 |
| L2       |    C2    |       R2 |
| L3       |    C3    |       R3 |

Table with inline formatting in cells:

| Name     | Status        | Code      |
|----------|---------------|-----------|
| **Bold** | *Italic*      | `snippet` |
| ~~Old~~  | Normal value  | `other`   |
| [Link](https://example.com) | text | `x` |

Compact table:

| A | B | C |
|-|-|-|
| 1 | 2 | 3 |

### Extended Autolinks

Bare URL autolinks (no angle brackets needed):

https://github.com

http://example.org

www.example.net

GFM autolinks in text:

Visit https://github.com for more information.

Check out www.example.com for details.

Standard CommonMark autolinks still work:

<https://spec.commonmark.org>

<user@example.com>

### Emoji Shortcodes

:smile: :rocket: :+1: :tada:

Emoji in text: :heart: for love and :thumbsup: for approval.

## CommonMark Base Features (inherited)

### Blockquotes

> Simple blockquote.
>
> > Second level
> >
> > > Third level
> > >
> > > > Fourth level
> > > >
> > > > > Fifth level deep nesting

### Lists

- Item 1
- Item 2
  - Nested 2.1
    - Deep 2.2.1
      - Level 4
        - Level 5
- Item 3

1. First
2. Second
   1. Nested 2.1
3. Third

### Code Blocks

```javascript
// Syntax-highlighted code block
const x = 42;
function hello() {
    return "Hello, World!";
}
```

```python
# Python block
def hello():
    print("Hello, World!")
```

~~~
Tilde fence block
~~~

    Indented code block

### Links and Images

[Inline link](https://example.com)

[Reference link][ref1]

[ref1]: https://example.com "Reference"

![Image alt](https://example.com/image.png)

### HTML

<!-- HTML comment -->

<strong>Inline HTML</strong>

<div>
  <p>Block HTML content.</p>
</div>

### Escaped Characters

\* \_ \` \[ \] \( \) \# \+ \- \. \! \\ \|
