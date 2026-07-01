# Pandoc ODF ZIP Package Manifest Case Collisions - 2026-07-01

## Scope

- ODF/ODT compact `OpenDocumentPackage` inventory and package identity now
  carry ZIP package-manifest case-insensitive name collision buckets.
- Rich `OdfReader` package provenance, document manifest provenance, and package
  identity expose the same metadata-only fields.
- The fields are derived from `ZipPackage::packageManifestPreflight()`; no
  package bytes are exposed and no external ZIP, office, or Pandoc tools are
  invoked.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipNamePolicyProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
- `git diff --check -- lanes/pandoc`
