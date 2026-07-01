# ODF ZIP package manifest name-length buckets

Hook: `plib-q2v0g`, Pandoc ODF/ODT OpenDocument package ingestion core
blocker slice.

ODF/ODT package ingestion now carries shared ZIP package-manifest
name-length bucket provenance through the compact `OpenDocumentPackage`
summary and rich `OdfReader` package provenance. The compact inventory,
compact package identity, rich package provenance, and rich package identity
now expose the ordered `nameLengthBucket` rollups already produced by
`ZipPackage::packageManifestPreflight()`.

Per package part, the ODF surfaces also preserve metadata-only entry-name byte
lengths and name-length bucket labels. This lets ODT package review compare
short top-level entries with nested package parts without exposing package
payload bytes or invoking external ZIP tools.

Validation before post-rebase gate:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
  - Result: `1 test files, 868 assertions, 0 failures`

No Pandoc, office suite, TeX/browser/Typst engine, Jupyter, Node tooling,
`zip`/`unzip`, external validator, online service, or live provider test was
invoked.
