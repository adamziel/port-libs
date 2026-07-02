# DOCX/OpenXML package manifest name-length buckets

Work item: `plib-s0iak`

## Summary

`DocxOpenXmlReader` now projects shared
`ZipPackage::packageManifestPreflight()` entry-name byte-length bucket
provenance into DOCX/OpenXML package metadata. The DOCX handoff exposes ordered
`zipPackageManifestNameLengthBuckets` and name-length bucket summaries through
`packageProvenance.summary`, and mirrors the same package-manifest fields under
`packageProvenance.zipPackage`.

Per-entry `zipPackage.entries` and `byPackagePath` rows now also carry
`entryNameBytes` and `entryNameLengthBucket`, keeping the DOCX package review
surface aligned with the shared ZIP package manifest without exposing package
payload bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php`
  - Result: `1 test files, 35 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `3 test files, 12569 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 6061 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser/Typst engine,
Jupyter, Node tooling, `zip`/`unzip`, external validator, online service, or
live provider test was invoked.
