# Pandoc JSON/native mixed caption Native writer slice

Slice: `plib-5jd0i` JSON/native AST constructor completeness, 2026-07-01.

Implemented a bounded Native writer constructor fix for mixed table caption
blocks: explicit `space` AST nodes now render as native `Space` inlines, and
`captionBlocks` are normalized into valid `Plain`/block constructor lists before
native text rendering. This matches the existing JSON writer behavior for mixed
inline/block caption and table-cell content without invoking upstream Pandoc or
external converters.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeWriterMixedCaptionBlocksTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeWriterMixedCaptionBlocksTest.php`
- Result: `1 test files, 23 assertions, 0 failures`

Broader baseline:

- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Result: `1 test files, 5889 assertions, 12 failures`
- The large aggregate remains baseline-red in unrelated WordPress sanitizer,
  citation handoff, metadata/native JSON syntax, and definition-list areas; the
  new focused mixed-caption Native writer regression is isolated and green.

Accounting:

- `mappedJsonNativeMixedCaptionNativeWriterCases`: `+1`
- `jsonNativeMixedCaptionNativeWriterAssertions`: `23`
- Direct-format parity remains active; this slice only covers native PHP
  JSON/native constructor behavior and does not shell out to Pandoc, office
  suites, TeX/browser engines, Typst, Node tooling, zip/unzip, validators, or
  live services.
