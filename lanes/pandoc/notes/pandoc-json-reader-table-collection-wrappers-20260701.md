# Pandoc JSON reader table collection wrappers

Area: Pandoc JSON/native AST constructor completeness.

The compatibility `JsonReader` now accepts single-wrapped current table helper
collections for column specs, table bodies, table rows, and row cells. This
matches the constructor-preserving JSON/native reader behavior for wrapped table
collection payloads while keeping `JsonWriter` output normalized to current
Pandoc JSON packet shape.

Direct-format parity accounting remains scoped to native PHP JSON/native parsing
and writing; no external Pandoc, office, TeX/browser, Typst, Node, zip/unzip,
validators, or live services are required for this slice.

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReader*.php`

An additional broad `PandocJsonNativeAstTest.php` run was attempted and remains
red outside this slice in existing WordPress HTML sanitizer/citation rendering
expectations; the table collection wrapper and JSON/native constructor cases in
that file passed.
