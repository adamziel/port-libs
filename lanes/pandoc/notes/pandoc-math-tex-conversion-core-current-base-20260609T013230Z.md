# Pandoc Math/TeX Current-Base: DeclarePairedDelimiterXPP

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T013230Z`
Base: `800b696344a9bf658321def4bebfd04d22ba2df2`

## Behavior

- Added bounded native PHP support for mathtools-style `\DeclarePairedDelimiterXPP` raw-TeX macro definitions.
- `MarkdownReader` now captures one-line XPP declarations with macro name, optional arity, prefix, opening delimiter, closing delimiter, suffix, and body template metadata.
- `MathTexConverter` validates prefix/suffix affixes, rejects placeholders in affixes, preserves the source TeX annotation, and expands ordinary, starred, and sized invocations before MathML rendering.
- Updated the WordPress math TeX handoff example with an XPP prefix/suffix paired-delimiter smoke.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` failed with `1 test files, 1036 assertions, 1 failures` before implementation.
- Final focused math test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 1057 assertions, 0 failures`.
- Focused reader regression check: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4278 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP Markdown raw-TeX macro capture, AST handoff, MathML conversion, and WordPress block output. It did not run Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal, Haskell runners, external converters, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This follows the prior X template note and does not repeat accepted Math/TeX support for declared math operators, plain `\DeclarePairedDelimiter`, paired-delimiter star/size invocation parsing, `\DeclarePairedDelimiterX`, AMS environments, array width metadata, bangle fractions, modulo commands, TeX comments, or binary/relation operator aliases. A follow-up could cover nested balanced body/affix groups in Markdown raw-TeX declaration capture.
