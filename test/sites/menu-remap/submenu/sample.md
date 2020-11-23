# Markdown syntax guide

## Headers

# This is a Heading h1
## This is a Heading h2 
###### This is a Heading h6

## Emphasis

*This text will be italic*  
_This will also be italic_

**This text will be bold**  
__This will also be bold__

_You **can** combine them_

## Lists

### Unordered

* Item 1
* Item 2
* Item 2a
* Item 2b

### Ordered

1. Item 1
1. Item 2
1. Item 3
  1. Item 3a
  1. Item 3b

## Images

![This is a alt text.](https://www.php.net/images/logos/new-php-logo.png "This is a sample image.")

## Links

You may be using [Markdown Live Preview](https://parsedown.org/extra/).

## Blockquotes

> Markdown is a lightweight markup language with plain-text-formatting syntax, created in 2004 by John Gruber with Aaron Swartz.
>
>> Markdown is often used to format readme files, for writing messages in online discussion forums, and to create rich text using a plain text editor.

## Tables

| Left columns  | Right columns |
| ------------- |:-------------:|
| left foo      | right foo     |
| left bar      | right bar     |
| left baz      | right baz     |

## Blocks of code

```
let message = 'Hello world';
alert(message);
```

## Inline code

This web site is using `erusev/parsedown`.

## Parsedown Extra Demo

<div markdown="1">
This is *true* markdown text.
</div>

<table>
<tr>
<td markdown="1">This is *true* markdown text.</td>
</tr>
</table>

Header 1            {#header1}
========

## Header 2 ##      {#header2}

## The Site ##    {.main}

[link](https://github.com/erusev/parsedown-extra){#id .class}  

This is a paragraph introducing:

~~~~~~~~~~~~~~~~~~~~~
a one-line code block
~~~~~~~~~~~~~~~~~~~~~

``````````````````
another code block
``````````````````

~~~

blank line before
blank line after

~~~

1.  List item

    Not an indented code block, but a second paragraph
    in the list item

~~~~
This is a code block, fenced-style
~~~~

~~~~~~~~~~~~~~~~~~~~~~~~~~~~ .html
<p>paragraph <b>emphasis</b>
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

First Header  | Second Header
------------- | -------------
Content Cell  | Content Cell
Content Cell  | Content Cell

| First Header  | Second Header |
| ------------- | ------------- |
| Content Cell  | Content Cell  |
| Content Cell  | Content Cell  |

| Item      | Value |
| --------- | -----:|
| Computer  | $1600 |
| Phone     |   $12 |
| Pipe      |    $1 |

| Function name | Description                    |
| ------------- | ------------------------------ |
| `help()`      | Display the help window.       |
| `destroy()`   | **Destroy your computer!**     |

Apple
:   Pomaceous fruit of plants of the genus Malus in 
    the family Rosaceae.

Orange
:   The fruit of an evergreen tree of the genus Citrus.

Apple
:   Pomaceous fruit of plants of the genus Malus in 
    the family Rosaceae.
:   An American computer company.

Orange
:   The fruit of an evergreen tree of the genus Citrus.

Term 1
Term 2
:   Definition a

Term 3
:   Definition b

Term 1

:   This is a definition with two paragraphs. Lorem ipsum 
    dolor sit amet, consectetuer adipiscing elit. Aliquam 
    hendrerit mi posuere lectus.

    Vestibulum enim wisi, viverra nec, fringilla in, laoreet
    vitae, risus.

:   Second definition for term 1, also wrapped in a paragraph
    because of the blank line preceding it.

Term 2

:   This definition has a code block, a blockquote and a list.

        code block.

    > block quote
    > on two lines.

    1.  first list item
    2.  second list item

That's some text with a footnote.[^1]

[^1]: And that's the footnote.

*[HTML]: Hyper Text Markup Language
*[W3C]:  World Wide Web Consortium

Please open the folder "secret_magic_box".

I like it when you say _you love me_.

