# ODF ZIP Package Manifest Expansion Ratio Buckets

Date: 2026-07-01
Slice: `plib-btlep`

ODF/ODT package ingestion now carries ZIP package-manifest expansion ratio bucket
metadata through compact and rich package review surfaces:

- `OpenDocumentPackage` package inventory parts and package identity entries now
  include per-entry `zipPackageManifestExpansionRatioBucket` values.
- `OdfReader` package provenance parts, package identity entries, and document
  manifest metadata carry the same per-entry bucket values.
- All ODF handoff surfaces expose the package-level
  `zipPackageManifestExpansionRatioBucketSummaryCount`,
  `zipPackageManifestExpansionRatioBuckets`, and
  `zipPackageManifestExpansionRatioBucketSummaries` aggregate fields from native
  ZIP package manifest preflight data.

The buckets mirror the existing shared ZIP manifest thresholds: `zero-byte`,
`up-to-1x`, `1x-to-10x`, `10x-to-100x`, `over-100x`, and `unknown`. The
projection remains metadata-only and does not expose package payload bytes.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestExpansionRatioBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestExpansionRatioBucketsTest.php`
  - 1 file, 101 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/OdfPackagePathDepthRoleBucketsTest.php`
  - 3 files, 948 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
`zip`/`unzip`, external validators, or live services were invoked.
