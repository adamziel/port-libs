# ODF ZIP Package Manifest Case-Fold Entry Provenance

Slice: `plib-k01ec`

ODF/ODT package ingestion now carries per-entry ZIP package-manifest case-fold
collision provenance through compact `OpenDocumentPackage` inventory/identity and
rich `OdfReader` package provenance/identity.

The added metadata is package-manifest only and remains byte-free:

- `zipPackageManifestCaseFoldKey`
- `zipPackageManifestCaseInsensitiveEquivalentEntryNames`
- `zipPackageManifestHasCaseInsensitiveNameCollision`
- `zipPackageManifestCaseInsensitiveNameCollisionIssues`

This complements the existing aggregate ZIP name-policy and package-manifest
case-insensitive collision summaries by making each collided package part
directly reviewable in ODF handoff rows.

Direct-format parity remains active. This slice does not invoke Pandoc, office
suites, zip/unzip, browser engines, or external validators.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php`
  with 1 file, 130 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  with 4 files, 8121 assertions, 0 failures
