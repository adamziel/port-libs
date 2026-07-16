# Pandoc Math/TeX Current-Base: DeclarePairedDelimiterX

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T235820Z`
Base: `98e36d1bfbcd2aff359b39b4120999431e5e0fde`

## Behavior

- Added bounded native PHP support for mathtools-style `\DeclarePairedDelimiterX` raw-TeX macro definitions.
- `MarkdownReader` now captures one-line `\DeclarePairedDelimiterX` declarations as `raw_tex` blocks and stores a macro template for Markdown math expansion.
- `MathTexConverter` validates and normalizes one- through nine-argument body templates into `\left...\right...` templates, rejects unsafe delimiters and invalid placeholders, and expands ordinary, starred, and sized invocations before MathML rendering.
- Updated the WordPress math TeX handoff example with a two-argument paired-delimiter template smoke.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` failed with `1 test files, 999 assertions, 1 failures` because `\DeclarePairedDelimiterX` was not captured.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 1021 assertions, 0 failures`.
- Expanded focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `2 test files, 5239 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP Markdown raw-TeX macro capture and MathML conversion. It did not run Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal, Haskell runners, external converters, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat accepted Math/TeX support for declared math operators, plain `\DeclarePairedDelimiter`, paired-delimiter star/size invocations, AMS environments, array width metadata, bangle fractions, modulo commands, TeX comments, or binary/relation operator aliases. A follow-up could cover bounded `\DeclarePairedDelimiterXPP` prefix/suffix templates or nested balanced body groups.
