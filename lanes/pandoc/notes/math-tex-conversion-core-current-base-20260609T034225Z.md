# Math TeX Conversion Core Current Base

Date: 2026-06-09 UTC
Base accepted HEAD: `91cca3175da49493fc1f64ed296d9fb56109fdfc`
Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T034225Z`

## Behavior

`MathTexConverter` now maps a bounded texmath unicode symbol-map cluster to native MathML operators instead of leaking literal TeX command identifiers:

- `\AC` -> `⏦`
- `\twoheadleftarrow` -> `↞`
- `\hookleftarrow` -> `↩`
- `\nleftarrow` -> `↚`
- `\nrightarrow` -> `↛`
- `\nleftrightarrow` -> `↮`
- `\nsubset` -> `⊄`
- `\nsupset` -> `⊅`

The accessibility handoff now names `⏦` as `AC current`, so accessible MathML produces stable alttext and intent strings for the new symbol-map case.

## Source Truth

The local Pandoc upstream checkout for texmath was not hydrated, so I used the upstream texmath source as bounded source truth only:

- `Text.TeXMath.Readers.TeX.Commands`: `\AC` maps to U+23E6.
- `Text.TeXMath.Unicode.ToTeX`: `\twoheadleftarrow`, `\hookleftarrow`, `\nleftarrow`, `\nrightarrow`, `\nleftrightarrow`, `\nsubset`, and `\nsupset` map to the Unicode operators above.

No Pandoc, Cabal, Haskell runner, TeX/PDF engine, office tool, zip/unzip command, browser renderer, external converter, online conversion service, live provider test, or live-service provider test was executed.

## Evidence

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 1175 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 1186 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
- Result: `math tex handoff self-test ok`

Delta:

- `phpPass`: `2243 -> 2244`
- `benchmarkDenominator.mapped`: `2651 -> 2652`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 96`

## Non-Overlap

This does not touch accepted roots/fractions/binomial/overset/phantom/cancel, color token args, layout wrappers, quadruple primes, arrow labels, nested macro declarations, tortoise shell delimiters, or text-token command argument behavior. It is only a bounded texmath symbol-map alias completion for aliases that were previously emitted as literal identifiers.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `MathTexConverter` command tables, the accessible MathML text/intent path, `MathTexConverterTest.php`, and the existing WordPress math handoff example.
