# Pandoc JSON/native textual Space constructor

Slices: `plib-hup52`, `plib-2wcn6`
Area: Pandoc JSON/native AST constructor completeness.

`NativeReader` now keeps textual native `Space` as a `space` AST node instead
of flattening it into a literal text node. Paragraph and inline plain-text
summaries still include the separator, while `PandocJsonWriter` now emits the
same `Space` constructor boundary that the textual native input carried.
`plib-2wcn6` extends the handoff through `NativeWriter` and `PandocJsonReader`
round trips so the constructor boundary survives both native-text and JSON AST
serialization paths.

This is a bounded native PHP JSON/native reader slice. It does not invoke
Pandoc, Haskell/Cabal runners, TeX engines, browser tooling, office suites,
zip/unzip, Node tooling, or external validators.

Accounting:

- `lane-status.json` `phpPass`: `469 -> 470`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2317 -> 2318`.
- `UPSTREAM_TEST_MANIFEST.json` `nativeReaderFocusedConstructorReadbackCases`: `10 -> 11`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedNativeReaderFocusedConstructorReadbackCases`: `10 -> 11`.
- Added `mappedJsonNativeTextualSpaceConstructorCases: 1`.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
  passed with 6 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/NativeDefinitionTermConstructorTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php`
  passed with 33 assertions and 0 failures.
- `php -l lanes/pandoc/tests/NativeReaderEscapeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderEscapeTest.php`
  passed with 1 file, 25 assertions, and 0 failures for the `plib-2wcn6`
  integration merge.

`NativeReaderTest.php` remains broader baseline-red with 6 unrelated existing
failures outside this textual Space constructor slice.
