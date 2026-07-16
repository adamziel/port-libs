# ODF XML package part version provenance

## Slice

This slice keeps OpenDocument core XML part version provenance visible to native
PHP package review flows. `OdfReader` now reports `documentPartVersions` through
the document manifest attributes, the top-level read result, and the import
report manifest section.

The report covers `content.xml`, `styles.xml`, `meta.xml`, and `settings.xml`
when present or declared. It records each part's expected root, observed root,
`office:version`, package manifest version, declaration state, byte length,
compressed length, compression method, CRC32, missing-version diagnostics, and
manifest-version mismatch rows.

## Direct parity accounting

- `phpPass`: 3276 -> 3277
- `phpFail`: 0
- `mappedOdfXmlPartVersionProvenanceCases`: 1
- `odfXmlPartVersionProvenanceAssertions`: 23

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 4460 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 73477 assertions, 0 failures

No Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
