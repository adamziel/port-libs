# ODF ZIP manifest archive and package-comment aggregate provenance

Date: 2026-07-01
Slice: `plib-l8xvy`

ODF/ODT package ingestion now projects additional package-level ZIP manifest
aggregate fields through both compact `OpenDocumentPackage` summaries and rich
`OdfReader` package provenance:

- ZIP package manifest archive byte counts are exposed as
  `zipPackageManifestArchiveBytes` and `zipPackageManifestArchiveLength`;
- ZIP package manifest package-comment presence is exposed as
  `zipPackageManifestHasPackageComment`;
- compact package inventory, compact package identity, rich package provenance,
  and rich package identity all carry the same aggregate values.

This remains metadata-only package review data. No Pandoc binary, office suite,
TeX/browser engine, `zip`/`unzip`, Jupyter, Node tooling, live service, or
external validator was invoked.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - 3 files, 7,555 assertions, 0 failures

Direct-format parity remains active in lane status.
