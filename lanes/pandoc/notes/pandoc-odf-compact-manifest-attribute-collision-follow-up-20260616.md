# Pandoc ODF Compact Manifest Attribute Collision Follow-Up

Slice: `pandoc-odf-compact-manifest-attribute-collision-follow-up`

## Summary

Added bounded `OpenDocumentPackageTest.php` coverage for compact ODT manifest
custom attribute collisions:

- duplicate custom QName rejection before package exposure
- custom `media-type` and `full-path` local-name collisions that must not
  override structural `manifest:media-type` or `manifest:full-path`
- namespace prefix remapping across file entries
- namespace URI alias provenance across different prefixes
- stable manifest file-entry ordering
- parity with `OdfReader` package provenance metadata

No Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, Node
tooling, online services, live providers, or external validators were invoked.

## Accounting

- `phpPass`: `16351 -> 16352`
- `mappedOdfCompactManifestAttributeCollisionCases`: `1 -> 2`
- `odfCompactManifestAttributeCollisionAssertions`: `45 -> 83`

## Verification

- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 1746 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfOdtShipReadinessStatusTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `5 test files, 7060 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `195 test files, 170118 assertions, 0 failures`
