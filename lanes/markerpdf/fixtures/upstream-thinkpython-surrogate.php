<?php

declare(strict_types=1);

return [
    'document' => 'thinkpython.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'markerPath' => 'data/examples/marker/thinkpython.md',
    'referencePath' => 'data/examples/nougat/thinkpython.md',
    'referenceKind' => 'committed-nougat-output-surrogate',
    'scoreThreshold' => 0.78,
    'chunkLength' => 500,
    'markerExcerpt' => <<<'MARKER'
## Think Python

How to Think Like a Computer Scientist Version 2.0.17

## Think Python

How to Think Like a Computer Scientist Version 2.0.17 Allen Downey Green Tea Press Needham, Massachusetts Copyright © 2012 Allen Downey. Green Tea Press 9 Washburn Ave Needham MA 02492 Permission is granted to copy, distribute, and/or modify this document under the terms of the Creative Commons Attribution-NonCommercial 3.0 Unported License, which is available at http:
//creativecommons.org/licenses/by-nc/3.0/.

The original form of this book is LATEX source code. Compiling this LATEX source has the effect of generating a device-independent representation of a textbook, which can be converted to other formats and printed.

The LATEX source for this book is available from http://www.thinkpython.com

## Preface The Strange History Of This Book

In January 1999 I was preparing to teach an introductory programming class in Java. I had taught it three times and I was getting frustrated. The failure rate in the class was too high and, even for students who succeeded, the overall level of achievement was too low. One of the problems I saw was the books. They were too big, with too much unnecessary detail about Java, and not enough high-level guidance about how to program. And they all suffered from the trap door effect: they would start out easy, proceed gradually, and then somewhere around Chapter 5 the bottom would fall out. The students would get too much new material, too fast, and I would spend the rest of the semester picking up the pieces. Two weeks before the first day of classes, I decided to write my own book. My goals were:
- Keep it short. It is better for students to read 10 pages than not read 50 pages.

- Be careful with vocabulary. I tried to minimize the jargon and define each term at
first use.
- Build gradually. To avoid trap doors, I took the most difficult topics and split them
into a series of small steps.
- Focus on programming, not the programming language. I included the minimum
useful subset of Java and left out the rest.
I needed a title, so on a whim I chose *How to Think Like a Computer Scientist*.
MARKER,
    'referenceExcerpt' => <<<'REFERENCE'
## Appendix A Proof of Theorem 1

###

[MISSING_PAGE_POST]

Proof of Theorem 1

[MISSING_PAGE_EMPTY:2]

## Chapter 1 Introduction

In this thesis we introduce a new class of _finite_Green Tea Press

9 Washburn Ave

Needham MA 02492

Permission is granted to copy, distribute, and/or modify this document under the terms of the Creative Commons Attribution-NonCommercial 3.0 Unported License, which is available at [http://creativecommons.org/licenses/by-nc/3.0/](http://creativecommons.org/licenses/by-nc/3.0/).

The original form of this book is LaTeX source code. Compiling this LaTeX source has the effect of generating a device-independent representation of a textbook, which can be converted to other formats and printed.

The LaTeX source for this book is available from [http://www.thinkpython.com](http://www.thinkpython.com)

## Preface

### The strange history of this book

In January 1999 I was preparing to teach an introductory programming class in Java. I had taught it three times and I was getting frustrated. The failure rate in the class was too high and, even for students who succeeded, the overall level of achievement was too low.

One of the problems I saw was the books. They were too big, with too much unnecessary detail about Java, and not enough high-level guidance about how to program. And they all suffered from the trap door effect: they would start out easy, proceed gradually, and then somewhere around Chapter 5 the bottom would fall out. The students would get too much new material, too fast, and I would spend the rest of the semester picking up the pieces.

Two weeks before the first day of classes, I decided to write my own book. My goals were:

* Keep it short. It is better for students to read 10 pages than not read 50 pages.
* Be careful with vocabulary. I tried to minimize the jargon and define each term at first use.
* Build gradually. To avoid trap doors, I took the most difficult topics and split them into a series of small steps.
* Focus on programming, not the programming language. I included the minimum useful subset of Java and left out the rest.

I needed a title, so on a whim I chose _How to Think Like a Computer Scientist_.
REFERENCE,
];
