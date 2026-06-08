# Pandoc Math/TeX Symbol Override Alias Slice

- Slice: `pandoc-math-tex-conversion-core-current-base-20260608T205806Z`
- Base accepted HEAD: `5d4304c18bb1f0b3ffb02f52a119f3462fac3ca7`
- Source truth: upstream texmath TeX command-table/source-map behavior, inspected in `Text/TeXMath/Readers/TeX/Commands.hs` and `Text/TeXMath/Readers/TeX.hs` at <https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs> and <https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs>.

## Behavior

This slice maps one bounded native Math/TeX command-table cluster that previously fell through as literal command identifiers:

- identifier/function aliases: `\arg`, `\hbar`, `\digamma`, `\varnothing`
- binary/relation aliases: `\dag`, `\ddag`, `\barwedge`, `\wr`, `\lhd`, `\rhd`, `\unlhd`, `\unrhd`, `\Join`, `\eqcolon`, `\longmapsto`
- shape aliases: `\Box`, `\Diamond`, `\lozenge`, `\blacklozenge`, `\blacksquare`, `\blacktriangleleft`, `\blacktriangleright`

The aliases now emit semantic MathML tokens, preserve source TeX annotations, and have stable accessibility labels/intent IDs.

## Evidence

- No rework notes existed for `port-pandoc-*.needs-lane-rework.md`.
- Baseline focused check before adding this case: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 864 assertions, 0 failures`.
- Red-first focused check after adding the test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` failed as expected with `1 test files, 866 assertions, 1 failures`; `\arg`, `\hbar`, `\digamma`, and `\varnothing` were emitted as literal `<mi>\command</mi>` identifiers.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 876 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/MathTexConverter.php`, `lanes/pandoc/tests/MathTexConverterTest.php`, and `lanes/pandoc/examples/wordpress-math-tex-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `phpPass`: `1845` -> `1846`
- `benchmarkDenominator.mapped`: `2269` -> `2270`
- `mathTexConversionCoreCases`: `14` -> `15`
- `mappedMathTexConversionCoreCases`: `14` -> `15`
- `mathTexConversionCoreAssertions`: `85` -> `97`

## Dependency Closure

No new support component is needed. The patch reuses `MathTexConverter` command maps/accessibility text, `MarkdownReader` math nodes, `LatexWriter` source preservation, and `WordPressBlockWriter` editable math spans. No Pandoc, texmath, MathJax, KaTeX, Cabal/Haskell runner, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is distinct from accepted Math/TeX alignedat, multline, array width-column, bangle infix fraction, modular command, TeX comment, large-operator, and prior binary/relation alias slices. Follow-up should target a different bounded texmath command-table cluster, equation-environment metadata, or MathML accessibility handoff gap.
