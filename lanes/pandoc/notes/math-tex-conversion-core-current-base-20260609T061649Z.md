# Math TeX Conversion Core Current Base

Date: 2026-06-09 UTC
Base accepted HEAD: `54e4f08a09f2e83c9a94575366cb4582953b41b9`
Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T061649Z`

## Behavior

`MathTexConverter` now maps bounded Greek identifiers inside math alphabet
variants and texmath aliases to Unicode Mathematical Alphanumeric Symbols
instead of leaving plain Greek glyphs inside `<mstyle>` wrappers:

- `\boldsymbol{\alpha}`, `\bm{\alpha_i}`
- `\mathit{\Gamma\alpha}`
- `\mathbfit{\Gamma\alpha}`
- `\mathbfsfup{\Theta\beta}`
- `\mathbfsfit{\Omega\omega}`

The accessible MathML path now reverse-maps the generated styled Greek
codepoints back to base Greek names such as `alpha`, `gamma`, and `omega`.
Existing styled Latin/digit accessibility remains compact, so `\symbf{A1}`
still speaks as `A1` rather than `A 1`.

## Source Truth

The local Pandoc upstream checkout for texmath was not hydrated. Source truth
for this bounded support-library behavior is the Unicode Mathematical
Alphanumeric Symbols chart, which records Greek styled codepoints in the
bold, italic, bold-italic, bold-sans-serif, and sans-serif-bold-italic blocks
with font compatibility mappings back to base Greek letters:

- https://www.unicode.org/charts/PDF/U1D400.pdf

No Pandoc, Cabal, Haskell runner, TeX/PDF engine, office tool, zip/unzip
command, browser renderer, external converter, online conversion service, live
provider test, or live-service provider test was executed.

## Evidence

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 1327 assertions, 0 failures`

Red-first coverage before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 1315 assertions, 3 failures`

Final focused verification:

- `php -l lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 1339 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
- Result: `math tex handoff self-test ok`

Delta:

- `phpPass`: unchanged at `2435`; this adds focused assertions inside existing
  Math/TeX PASS cases instead of a new PHP PASS line.
- `benchmarkDenominator.mapped`: `2824 -> 2825`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 97`

## Non-Overlap

This does not repeat accepted roots/fractions/binomial/overset/phantom/cancel,
color token args, layout wrappers, quadruple primes, arrow labels, nested macro
declarations, tortoise shell delimiters, text-token command arguments,
texmath symbol-map aliases, or CSL/Citation work. It is only a bounded Greek
math alphabet variant completion for math/TeX conversion.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`MathTexConverter` command tables, math variant rewrite path, accessible MathML
text/intent annotations, `MathTexConverterTest.php`, and the WordPress math
handoff example. Full upstream Pandoc/texmath runner parity remains a separate
upstream-runner dependency task.
