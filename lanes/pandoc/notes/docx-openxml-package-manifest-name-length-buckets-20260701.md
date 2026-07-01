# DOCX package manifest name-length buckets

Hook: `plib-8tfjo`, Pandoc DOCX OpenXML package ingestion core blocker slice.

`DocxOpenXmlReader` now carries ZIP package-manifest entry-name byte-length
bucket provenance into DOCX package metadata. ZIP-backed DOCX reads expose
the shared `ZipPackage::packageManifestPreflight()` name-length bucket count,
ordered bucket labels, and bucket summaries through:

- `docx.packageProvenance.summary.zipPackageManifestNameLength*`
- `docx.packageProvenance.zipPackage.packageManifestNameLength*`
- per ZIP package entries as `entryNameBytes` and `entryNameLengthBucket`

This keeps directory entries visible for manifest review, including entries
that are intentionally not loaded as DOCX parts, while preserving the existing
metadata-only byte exposure policy.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php`
  - Result: `1 test files, 31 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlPackageManifestNameLengthBucketsTest.php`
  - Result: `2 test files, 12539 assertions, 0 failures`

No Pandoc binary, office suite, TeX/browser engine, `zip`/`unzip`, Node tooling,
Jupyter, external validator, online service, or live provider test was invoked.
