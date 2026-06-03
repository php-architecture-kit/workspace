
<!-- leading comment as trivia -->


# Document Title
## Introduction

This paragraph has **bold**, *italic*, ***bold italic***, `code`, __also bold__, _also italic_.

Text with hard break (two spaces):  
Next. Backslash:\
Next.


---
***
___



Setext Heading One
==================

Setext Heading Two
------------------




> Blockquote line one.
> Line two.



> > Double nested.
> > > Triple nested.
> > > > Quad nested.
> > > > > Fifth level blockquote.




```javascript
function greet(name) {
    return "Hello, " + name;
}
```
~~~
tilde fenced block
second line
~~~

    indented block
    second line
    third line




- Item 1
  - Nested 2.1
    - Nested 3.1
      - Level 4
        - Level 5


- Item 2
- Item 3

* Asterisk
+ Plus



1. One
2. Two
   1. Sub one
   2. Sub two
3. Three


1) Paren one
2) Paren two



- Multi-para item

  Second paragraph here.

- Normal item.




[Inline](https://example.com)
[Title](https://example.com "T")
[Ref][r1]
[Implicit][]
<https://a.example.com>
<a@example.com>

[r1]: https://example.com
[Implicit]: https://example.com/i

![Alt](https://example.com/i.png)
![Title](https://example.com/j.png "T")
![Ref][i1]

[i1]: https://example.com/r.png



Escaped: \* \_ \` \[ \] \( \) \# \+ \- \. \! \\ \|

<strong>inline</strong> <em>italic</em> <br/>

<div>
  <p>Block HTML.</p>
</div>

<!-- trailing comment as trivia -->

