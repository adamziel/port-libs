# Pandoc JSON/native legacy Null block slice 2026-07-02

Issue: `plib-1bw5u`

This slice closes a constructor-completeness gap in the older `JsonReader`/`JsonWriter` pair. The newer `PandocJsonReader`, `PandocJsonWriter`, `NativeReader`, and `NativeWriter` already handled Pandoc's `Null` block constructor, but the legacy JSON reader rejected `Null` and the legacy JSON writer could not emit `null_block`.

Changes:

- `JsonReader` maps Pandoc JSON `{"t":"Null"}` to the shared `null_block` AST node.
- `JsonWriter` emits `{"t":"Null"}` for `null_block`.
- `JsonReaderWriterTest.php` now covers read, write, round-trip, and manual AST emission for the `Null` constructor.

Manifest accounting:

- `benchmarkDenominator.mapped`: `2318` to `2319`
- `mappedJsonNativeLegacyNullBlockCases`: `1`

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/src/JsonWriter.php`
- `php -l lanes/pandoc/tests/JsonReaderWriterTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeTextInlineConstructorCompletenessTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php`
