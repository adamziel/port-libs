# Pandoc JSON Reader Single-Wrapped Table Collections

`plib-ccn6d` extends the strict `JsonReader` compatibility path to accept
single-wrapped Pandoc table collection payloads for column specs, table bodies,
table rows, and row cells.

This keeps constructor-complete current Pandoc JSON table packets readable by
the lightweight JSON reader/writer pair. `JsonWriter` continues to emit
canonical unwrapped table JSON after reading these compatibility shapes.

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderSingleWrappedConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`

No external Pandoc, office suite, TeX/browser engine, Node tooling, zip/unzip,
online service, or validator was invoked.
