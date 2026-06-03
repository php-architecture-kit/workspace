
<!-- leading trivia comment -->


# GFM Document
## Introduction

This has **bold**, *italic*, ~~strike~~, `code`, ***bold italic***.


~~Deleted~~ old. ~~**bold strikethrough**~~. *~~italic strike~~*.


- [ ] Task unchecked
- [x] Task checked x
- [X] Task checked X


- [ ] Parent unchecked
  - [x] Child done
  - [ ] Child pending
- [x] Parent done


| Left | Center | Right |
|:-----|:------:|------:|
| L1 | C1 | R1 |
| **Bold** | *Italic* | `code` |
| ~~Old~~ | Normal | value |


| A | B | C |
|-|-|-|
| 1 | 2 | 3 |



https://example.com


www.example.net


<https://spec.commonmark.org>

<mail@example.com>


:smile: :rocket: :+1: :tada: :heart:



> Outer level.
>
> > Second.
>
> > > Third.
> > >
> > > > Fourth.
> > > >
> > > > > Fifth level.


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
   1. Sub 2.1
3. Three



```javascript
const x = 42;
function hello() { return "hi"; }
```

~~~
tilde block
second line
~~~

    indented
    second


[Inline](https://example.com)
[Title](https://example.com "T")
[Ref][r]
<https://a.example.com>

[r]: https://example.com "R"

![Alt](https://example.com/i.png)
![Ref][i]
[i]: https://example.com/r.png



\* \_ \` \[ \] \( \) \# \+ \- \. \! \\ \|

<strong>inline</strong> <em>italic</em>

<div>
  <p>Block HTML.</p>
</div>

<!-- trailing trivia comment -->

