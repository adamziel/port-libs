# Math TeX Conversion Core Current Base 20260609T053523Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T053523Z`
- Base accepted HEAD: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`
- Behavior cluster: bounded TeX escaped special symbols in MathML handoff.

## Source Truth And Non-Overlap

This slice ports a narrow TeX math token contract: escaped special symbols
`\\#`, `\\$`, `\\%`, `\\&`, `\\_`, and `\\textbackslash` should not leak as
literal command identifiers in MathML. They are now normalized to MathML
operator tokens while the original source TeX remains available in the
`application/x-tex` semantics annotation.

No hydrated Pandoc upstream checkout was available for a runner-backed
texmath comparison in this isolated worktree. No Pandoc, Cabal, Haskell
runner, Word, LibreOffice, zip/unzip, external template engine, external
converter, TeX/PDF engine, MathJax, KaTeX, browser renderer, online service,
live provider test, or live-service provider test was invoked.

This does not overlap prior accepted math/TeX slices for fractions, roots,
plain root syntax, matrices, arrays, row rules, plain alignment commands,
operator aliases, equation references, comments, spacing commands, color,
phantom/cancel, math variants, accents, or TeX prime notation.

## Red-First Evidence

Before implementation, a direct native probe showed escaped symbols leaking as
literal identifier tokens:

```text
\\{x\\} + a\\#b + c\\&d + e\\$f
=> <mi>\\#</mi>, <mi>\\&amp;</mi>, and <mi>\\$</mi> appeared in generated MathML
```

After adding the focused test but before implementation, the expected
`converts bounded tex escaped special symbols to mathml` case failed for the
same reason.

## Implemented

- Added a bounded escaped-symbol command map in `MathTexConverter`.
- Rendered escaped special symbols as MathML `<mo>` tokens instead of literal
  `<mi>\\...</mi>` identifiers.
- Added accessibility token names for backslash, number sign, dollar sign,
  percent sign, and ampersand while preserving the existing underscore
  accessibility wording as `underbar`.
- Updated the existing environment-comment assertion for escaped percent to
  expect normalized MathML.
- Extended the WordPress math TeX handoff example with escaped symbol review
  coverage.

## Verification

Focused checks:

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1315 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

Focused delta: `+1` PHP PASS line and `+9` focused assertions.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses the native PHP
`MathTexConverter` tokenizer, MathML semantics annotation handling,
accessibility metadata extraction, focused PHP test runner, and the lane-local
WordPress math TeX handoff example. Full upstream Pandoc runner parity remains
a separate upstream-runner dependency task requiring a hydrated Pandoc
checkout and explicitly authorized Haskell/Cabal runner work.

## Follow-Up

Potential non-overlapping follow-up: additional safe texmath symbol-command
aliases, equation-number alignment variants, or bounded parser edge cases with
red-first MathML assertions. Do not run Pandoc, Cabal/Haskell runners, Word,
LibreOffice, zip/unzip, TeX/PDF engines, MathJax, KaTeX, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests from this lane.
