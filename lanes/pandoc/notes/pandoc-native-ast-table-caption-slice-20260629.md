# Pandoc Native AST Table Caption Slice

Slice: `plib-zt62`

This bounded JSON/native AST slice closes the pure native text table-caption gap.
NativeReader now accepts current text-native short captions shaped as
`Just (ShortCaption [...])` while keeping the older `Just [...]` reader path.
NativeWriter now emits current `ShortCaption` constructors for text-native table
and figure captions instead of the older inline-list-only shape.

Mapped parity accounting:

- `nativeTextTableCaptionConstructorCases`: `0 -> 1`
- `mappedNativeTextTableCaptionConstructorCases`: `0 -> 1`
- `lane-status.phpPass`: `458 -> 459`

Scope notes:

- No Pandoc binary, Haskell runner, office suite, TeX engine, browser engine,
  Node tooling, zip/unzip, or external validator was invoked.
- The slice is limited to native PHP Pandoc lane code under `lanes/pandoc`.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- Selected `NativeReaderTest` closure
  `round trips text native table caption short caption constructors`: 24 assertions,
  0 failures.
- Full `NativeReaderTest.php` was also sampled and remains baseline-red on unrelated
  existing expectations outside this slice: 321 assertions, 8 failures, with the
  new table-caption constructor closure passing.
