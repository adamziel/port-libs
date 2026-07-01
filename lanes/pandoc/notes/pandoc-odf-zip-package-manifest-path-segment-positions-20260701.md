# ODF ZIP package manifest path segment positions

Implemented for `plib-yytr6`.

- Compact `OpenDocumentPackage` and rich `OdfReader` ODF/ODT package
  provenance now carry ZIP package-manifest path segment position summaries
  through metadata-only package identities.
- Per-package-entry identity rows now include
  `zipPackageManifestPathSegmentPositionReviews`, preserving first/last/only
  segment provenance from the shared ZIP package manifest.
- The focused aggregate provenance test now verifies compact inventory,
  compact identity, rich package provenance, and rich package identity parity
  for these aggregate and per-entry fields.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderTest.php`

No package payload bytes are exposed by the new identity fields, and no
external Pandoc, office, TeX, browser, ZIP, Jupyter, or live validation tools
were invoked.
