# Document Title

## Introduction

This is a paragraph with **bold text**, *italic text*, ***bold and italic***,
and `inline code`. Also __bold__ and _italic_ with underscores.

This is a second paragraph separated by a blank line.

Text with a hard line break (two trailing spaces):  
Continued on this line.

Text with a backslash hard break:\
Continued on this line.

---

## Block Elements

### Setext Headings

Setext Heading Level One
========================

Setext Heading Level Two
------------------------

### Thematic Breaks

---

***

___

### Blockquotes

> Simple single-line blockquote.

> Multi-line blockquote.
> Continues on this line.
>
> Second paragraph inside blockquote.

> Outer level
>
> > Second level
> >
> > > Third level
> > >
> > > > Fourth level
> > > >
> > > > > Fifth level deep nesting

### Code Blocks

```javascript
function greet(name) {
    console.log("Hello, " + name + "!");
    return true;
}
```

```python
def greet(name):
    print(f"Hello, {name}!")
    return True
```

~~~
Alternative fence style
using tilde characters
~~~

    four spaces before each line
    second line of indented code
    third line here

### Lists

Unordered with nesting:

- Item 1 at root level
- Item 2 at root level
  - Nested item 2.1
  - Nested item 2.2
    - Deeply nested 2.2.1
      - Level 4 item
        - Level 5 item
- Item 3 at root level

Alternative bullet styles:

* Asterisk bullet
+ Plus bullet

Ordered with dot delimiter:

1. First item
2. Second item
   1. Nested ordered 2.1
   2. Nested ordered 2.2
3. Third item

Ordered with parenthesis delimiter:

1) First item
2) Second item
3) Third item

Auto-numbered (all start with 1.):

1. First
1. Second
1. Third

List item with multiple paragraphs:

- First item with a longer text.

  Second paragraph inside the first list item.

- Second item.

## Inline Elements

Escaped characters: \* \_ \` \[ \] \( \) \# \+ \- \. \! \\ \|

### Links

[Inline link](https://example.com)

[Link with title](https://example.com "Example Domain")

[Reference link][link-ref]

[Implicit reference][]

<https://autolink.example.com>

<user@example.com>

[link-ref]: https://example.com/page "Reference page"
[Implicit reference]: https://example.com/implicit

### Images

![Alt text for image](https://example.com/image.png)

![Image with title](https://example.com/photo.jpg "Photo title")

![Reference image][image-ref]

[image-ref]: https://example.com/ref.png "Reference image"

## HTML

<!-- This is an HTML comment -->

Inline HTML: <strong>bold</strong> and <em>italic</em> tags.

Self-closing: <br/>

<div>
  <p>Block HTML content.</p>
</div>
