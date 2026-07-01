# Pandoc JSON/native inline note text constructors

Slice: `plib-wbijv`
Area: Pandoc JSON/native AST constructor completeness.

`NativeWriter` now treats direct inline children of `note` nodes as JSON-native
inline runs before handing them to `PandocJsonWriter`. This keeps a direct
inline note child like `text("Review source")` from becoming a single
space-containing `Str` constructor in generated `Note` blocks; it now emits
`Str`, `Space`, `Str` and round-trips back through `PandocJsonReader` as
`text`, `space`, `text`.

The same inline-container list in `NativeReader` includes `note` for symmetric
normalization. Existing block-shaped note bodies remain unchanged because
`Plain` and `Para` block children are not coalesced as direct inline text.

This is a bounded native PHP JSON/native writer slice. It does not invoke
Pandoc, Haskell/Cabal runners, TeX engines, browser tooling, office suites,
zip/unzip, Jupyter, Node tooling, or external validators.

Accounting:

- Direct-format parity accounting is unchanged.
- `lane-status.json` `phpPass`: `474 -> 475`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2318 -> 2319`.
- Added `mappedJsonNativeInlineNoteTextConstructorCases: 1`.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
  passed with 1 file, 12 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  passed with 1 file, 478 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed with 1 file, 6278 assertions, and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/PandocJsonNativeCaptionConstructorEditTest.php`
  passed with 2 files, 50 assertions, and 0 failures.
