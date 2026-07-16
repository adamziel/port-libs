# Pandoc ODT encoded package reference provenance 20260612T004431Z

This slice keeps the native PHP ODT reader aligned with the package-level
OpenDocument ingestion path for encoded package references.

- `OdtReader` now normalizes manifest `full-path` values into raw path,
  decoded package path, reference path, query, fragment, and combined suffix.
- ODT image `xlink:href` values resolve through the same package-reference
  logic, so encoded references such as `Pictures/source%20hero.png?cache=1#draw`
  can locate the decoded ZIP member `Pictures/source hero.png`.
- The media import report preserves both the document href and the resolved
  manifest/package provenance used for byte lookup.

Verification:

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
