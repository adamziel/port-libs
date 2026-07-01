# Pandoc ODF Package Path-Depth Role Buckets - 2026-07-01

## Scope

- Compact `OpenDocumentPackage` package inventory now groups ZIP package parts
  by package path depth and role.
- Compact and rich package identities carry the same path-depth role and
  byte-exposure maps, so the metadata contributes to the package identity hash.
- Rich `OdfReader` package provenance and document manifest provenance expose
  the same metadata-only buckets.

## Boundary

This is ODF/ODT package-ingestion metadata. It does not expose blocked package
bytes and does not invoke Pandoc, office suites, TeX/browser engines,
`zip`/`unzip`, `ZipArchive`, Jupyter, Node tooling, online services, or external
validators.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackagePathDepthRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePathDepthRoleBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackagePathSegmentPositionRoleBucketsTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
