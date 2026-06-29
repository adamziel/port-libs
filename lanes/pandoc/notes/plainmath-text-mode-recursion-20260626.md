# PlainMath Text-Mode Recursion - 2026-06-26

Slice: `plib-wj70q.16`

## Source Truth

Targeted upstream source inspected:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs`
- `.upstream-cache/texmath/test/reader/tex/text.test` at TexMath
  `170899673ee31de9096e178605e8da31a36e4185`

The relevant upstream `textContents` parser handles braced text content as
bounded text chunks, text spacing commands, nested brace groups, and delimited
inner math. `textOps` maps `\text`, `\mbox`, and selected styled text commands
to text expressions.

## Behavior

- `MathTexConverter` now parses braced text-mode content recursively instead of
  flattening it into one literal `<mtext>` node.
- Nested grouping braces are structural and no longer appear as literal text.
- Delimited inner math in text-mode groups, such as `$x_i \in S$`, is parsed
  through the existing bounded TeX-to-MathML path.
- Nested local text-mode commands such as `\mbox`, `\textbf`, `\texttt`, and
  `\emph` produce styled `<mtext>` chunks without executing arbitrary TeX.
- The TexMath `text.test` glyph and punctuation fixture is mapped through a
  bounded text-mode path: selected text glyph commands, `\"` umlaut targets, and
  TeX quote/dash ligatures normalize inside `<mtext>`.
- Unsupported text accent targets fall back to literal text instead of widening
  the text-mode parser into broad Unicode or TeX accent parity.
- Nonbraced one-token text-mode behavior remains unchanged.

## Verification

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php lanes/pandoc/tests/EpubWriterTest.php lanes/pandoc/tests/Html5DomTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 2038 assertions, 0 failures
```

The focused run covers the recursive text-mode case, the TexMath `text.test`
glyph/ligature fixture assertion, static PlainMath TexMath fixtures, EPUB MathML
XHTML packaging/fallback behavior, and HTML5 MathML foreign-content handling.

## Scope

This is a bounded native PHP MathML handoff slice. It does not invoke upstream
Pandoc, texmath executables, TeX engines, MathJax, KaTeX, browser tooling,
package parsers, PDF engines, network fetches, broad PlainMath parity, or broad
text-mode accent/Unicode normalization parity.
