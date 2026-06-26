# PlainMath Text-Mode Recursion - 2026-06-26

Slice: `plib-wj70q.16`

## Source Truth

Targeted upstream source inspected:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs`

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
- Nonbraced one-token text-mode behavior remains unchanged.

## Verification

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
```

The new `converts bounded tex recursive text mode groups to mathml` case passes
in the focused file run. The file still has 6 unrelated pre-existing failures
in Markdown declaration capture and LaTeX writer source-preservation cases; the
same unrelated failures were present before this parser change.

## Scope

This is a bounded native PHP MathML handoff slice. It does not invoke upstream
Pandoc, texmath executables, TeX engines, MathJax, KaTeX, browser tooling,
package parsers, PDF engines, network fetches, or broad PlainMath parity.
