# ZIP Package Manifest Local Header Variable Fields

Slice: `plib-qnva6`

## Summary

`ZipPackage::packageManifestPreflight()` now carries local-header source
accounting in the deterministic package manifest used by shared ZIP/OPC handoff.
The manifest reports aggregate local header bytes, fixed-header bytes, variable
field bytes, raw name bytes, extra-field/review bytes, and local extra-field
entry counts.

Each package manifest entry now also includes local-header variable-field byte
counts and SHA-256 hashes, plus raw-name, extra-field, and review-field byte
totals. This keeps local header review metadata visible to DOCX, EPUB3, ODF/ODT,
and raw OPC package paths without exposing package payload bytes.

## Post-Rebase Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 2 files, 9,900 assertions, 0 failures

No Pandoc binary, office suite, `zip`/`unzip`, `ZipArchive`, browser renderer,
external validator, online service, live provider test, or live-service provider
test was executed.
