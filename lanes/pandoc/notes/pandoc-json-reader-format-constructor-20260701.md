# Pandoc JSON Format Constructor

The strict `JsonReader` now accepts raw format helper constructors in current
Pandoc JSON packets. `RawBlock` and `RawInline` may provide their format as a
plain string or as a tagged `Format` payload, including single-wrapped scalar
content.

The strict `JsonWriter` still emits canonical current JSON strings for those
formats, so constructor-bearing input is normalized without shelling out to
Pandoc or external validators.

Validation:

- `php -l lanes/pandoc/src/JsonReader.php`
- `php -l lanes/pandoc/tests/JsonReaderFormatConstructorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php lanes/pandoc/tests/JsonReaderWriterTest.php`
  (59 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
  (74 assertions, 0 failures after rebase)

No external Pandoc or validator tooling was used.
