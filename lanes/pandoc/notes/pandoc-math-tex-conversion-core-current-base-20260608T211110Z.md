# Pandoc Math/TeX Intertext Rows

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T211110Z`
Base accepted HEAD: `26bbd2b7e4199c593e970e19e2909436056056d0`

## Behavior

- Added bounded native Math/TeX handling for AMS `\intertext{...}` and `\shortintertext{...}` rows in `align`, `align*`, `alignat`, `alignat*`, `flalign`, and `flalign*`.
- The row splitter now recognizes those commands only at top-level row starts after an equation row separator.
- The MathML table renderer emits a spanning text row with `data-tex-intertext="normal"` or `data-tex-intertext="short"` and `columnspan` matching the environment column count.
- Placement validation rejects intertext at the start/end, consecutive intertext rows, inline intertext inside an equation row, empty text, structural commands, raw alignment markers, and row separators inside intertext text.
- Equation-reference collection skips intertext rows so labels/tags remain owned by actual equation rows.

## Evidence

- Baseline before source changes:
  - `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 864 assertions, 0 failures`
- Red-first after adding the focused intertext test:
  - `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 866 assertions, 1 failures`
  - Failure: `\intertext` rendered as a literal MathML identifier instead of a metadata text row.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 876 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/MathTexConverter.php`
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` moved `1854 -> 1855`; focused mapped checks moved `1,936 -> 1,937`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator moved `2282 -> 2283`; `mathTexConversionCoreCases` and `mappedMathTexConversionCoreCases` moved `14 -> 15`; `mathTexConversionCoreAssertions` moved `85 -> 97`.

## Dependency Closure

No new support component is needed. This reuses native `MathTexConverter` row splitting/table rendering plus the existing `MarkdownReader`, `WordPressBlockWriter`, focused math tests, and WordPress math handoff example.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids recent accepted Math/TeX work for binary/relation aliases, modular commands, TeX comments, bangle infix fractions, color declarations, optional row spacing, multline/multlined, array width columns, and alignedat. It covers only AMS intertext row handoff and placement validation.

Root harness not run - isolated micro-slice.
