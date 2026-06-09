# Math/TeX Text Token Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T031738Z`

Base accepted HEAD: `fcee36bd5dbe5864d3125594c593630bcda502b2`

## Source Truth

Upstream texmath's TeX reader accepts braced text-mode command content or one
nonbraced text token for `textOps` commands. Source inspected:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs`

This slice ports the bounded format contract into native PHP without running
Pandoc, texmath, MathJax, KaTeX, TeX engines, Cabal, Haskell runners, browser
renderers, external converters, online services, live provider tests, or
live-service provider tests.

## Behavior

- `MathTexConverter` now accepts one nonbraced text-mode token after `\text`,
  `\mbox`, `\textbf`, `\textit`, `\texttt`, `\textsf`, `\textnormal`,
  `\textrm`, `\textup`, `\textmd`, and `\emph`.
- Escaped text tokens such as `\%`, `\&`, `\#`, `\TeX`, `\LaTeX`, `\ldots`,
  and `\textbackslash` are normalized through the same bounded text handoff
  path.
- Scripts after the text token stay attached to the rendered text atom, so
  `\textbf x_i` becomes a subscripted bold MathML text node.
- Unknown nonbraced text token commands remain rejected before handoff.
- The WordPress math handoff example now covers this editable source form.

## Red-First Evidence

Before the implementation:

```text
ERR \textbf x_i :: InvalidArgumentException: Expected TeX text group at offset 8
ERR \textit\% + \mbox~ :: InvalidArgumentException: Expected TeX text group at offset 7
ERR \texttt\& :: InvalidArgumentException: Expected TeX text group at offset 7
```

Focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1151 assertions, 0 failures
```

## Verification

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1163 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

`git diff --check -- lanes/pandoc` passed after the final edits.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `2215 -> 2216`
- `benchmarkDenominator.mapped`: `2625 -> 2626`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- Focused MathTexConverter assertions: `1151 -> 1163`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter`, existing source annotations, accessibility metadata,
`MarkdownReader` math spans, and `WordPressBlockWriter` handoff path. Full
upstream Pandoc/texmath runner parity remains a separate upstream-runner
dependency task requiring a hydrated pinned checkout and Haskell test
executables.

## Non-Overlap

This does not repeat accepted Math/TeX slices for TeX-token roots/fractions,
color operands, layout wrappers, arrow labels, prime canonicalization, macro
declaration capture, equation metadata, array metadata, or braced text-mode
aliases. The new behavior is specifically texmath-style one-token text-mode
command content.
