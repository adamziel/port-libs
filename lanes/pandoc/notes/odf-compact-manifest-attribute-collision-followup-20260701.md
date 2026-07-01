ODF compact manifest attribute collision follow-up

- Added focused `OpenDocumentPackageTest.php` coverage for compact ODT manifest custom attributes that shadow structural names across different namespaces.
- The test verifies stable ordering, prefix and namespace provenance, decoded package path collision rejection, and parity with `OdfReader` package provenance.
- This is a test-only closure slice for `plib-73jm7`; existing OpenDocument package parsing behavior is unchanged.

Verification:

- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php` passed: 4 files, 7,736 assertions.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check` passed.
- `php tools/run-tests.php lanes/pandoc/tests` remains red in unrelated Markdown/HTML reader fixtures: 534 files, 142,294 assertions, 8,912 failures.
