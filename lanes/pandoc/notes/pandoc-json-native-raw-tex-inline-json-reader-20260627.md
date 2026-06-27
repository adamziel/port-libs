# Pandoc JSON/native raw TeX inline JSON reader

Area: Pandoc JSON/native AST constructor completeness.

This slice completes the reader side of raw TeX inline constructor handling for
Pandoc JSON/native packets. `PandocJsonReader` now maps TeX-family
`RawInline` constructors (`tex`, `latex`, and aliases) to `raw_tex_inline`
instead of the block-oriented `raw_tex` node, matching the text-native reader
and the generic JSON reader. Plain text handoff now recognizes
`raw_tex_inline` so paragraph summaries and `PlainWriter` output keep the raw
TeX payload.

No Pandoc binary, TeX engine, Haskell/Cabal runner, browser renderer, external
validator, Node tooling, office suite, or package validator was invoked.

Validation:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  remains at the recorded baseline: 6,023 assertions with 7 unrelated failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php` remains at
  the recorded baseline: 297 assertions with 8 unrelated failures.

Accounting:

- `phpPass`: `465 -> 466`
- `benchmarkDenominator.mapped`: `2309 -> 2310`
- `mappedJsonNativeRawTexInlineJsonReaderCases`: `1`
