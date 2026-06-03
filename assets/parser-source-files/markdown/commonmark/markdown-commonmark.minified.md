# Document Title
## Introduction
This is a paragraph with **bold text**, *italic text*, ***bold and italic***, and `inline code`. Also __bold__ and _italic_ with underscores.
This paragraph has a hard break (two spaces):  
Next line. Backslash break:\
Next line.
---
## Block Elements
### Thematic Breaks
---
***
___
### Blockquotes
> Single-line blockquote.
> Multi-line.
> Continues here.
> > Second level.
> > > Third level.
> > > > Fourth level.
> > > > > Fifth level.
### Code Blocks
```javascript
function greet(name){console.log("Hello, "+name);}
```
```python
def greet(name): print(f"Hello, {name}!")
```
~~~
tilde fence
~~~
    indented code block
    second line
### Lists
- Item 1
- Item 2
  - Nested 2.1
  - Nested 2.2
    - Deep 2.2.1
      - Level 4
        - Level 5
- Item 3
* Asterisk
+ Plus
1. First
2. Second
   1. Nested 2.1
3. Third
1) Paren 1
2) Paren 2
## Inline
Escaped: \* \_ \` \[ \] \( \) \# \+ \- \. \! \\ \|
### Links
[Inline](https://example.com)
[With title](https://example.com "Title")
[Reference][ref]
<https://auto.example.com>
<user@example.com>
[ref]: https://example.com "Ref"
### Images
![Alt](https://example.com/image.png)
![With title](https://example.com/photo.jpg "Title")
![Ref][img]
[img]: https://example.com/ref.png
## HTML
<!-- comment -->
<strong>inline</strong> and <em>italic</em>
<div><p>Block HTML.</p></div>
