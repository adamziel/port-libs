# ODF ZIP package manifest CRC32 rollups

Hook: `plib-i4cq1`, Pandoc ODF/ODT OpenDocument package ingestion core
blocker slice.

ODF/ODT package ingestion now carries shared ZIP package-manifest CRC32
rollups through compact `OpenDocumentPackage` inventory/identity and rich
`OdfReader` package provenance/identity. The ODF aggregate surfaces expose
the manifest CRC32 summary count, summary rows, duplicate CRC32 hex counts,
duplicate entry counts, duplicate hex lists, and duplicate summary rows.

The focused fixture keeps the data metadata-only and adds a deterministic
duplicate CRC32 group by giving `Pictures/review.png` and
`Pictures/review-copy.png` the same package bytes. This verifies the duplicate
rollup without exposing payload bytes or invoking external ZIP tools.

Validation before post-rebase gate:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestCrc32RollupTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestCrc32RollupTest.php`
  - Result: `1 test files, 141 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestCrc32RollupTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `6 test files, 13,054 assertions, 0 failures`

No Pandoc, office suite, TeX/browser/Typst engine, Jupyter, Node tooling,
`zip`/`unzip`, external validator, online service, or live provider test was
invoked.
