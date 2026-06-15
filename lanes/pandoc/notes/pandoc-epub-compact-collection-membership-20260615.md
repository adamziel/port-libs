# EPUB Compact Collection Membership

Bead: `plib-tjrxa`
Base: `5068dd8e84`
Date: 2026-06-15 UTC

This slice keeps EPUB3 package ingestion native-PHP-only while adding compact
OPF `belongs-to-collection` metadata membership review support in
`EpubPackage`.

The compact package parser now exposes `collectionMembership` through:

- `metadata.collectionMembership`
- top-level `summary().collectionMembership`
- `wordpressImport.metadataDetails.collectionMembership`
- `wordpressImport.metadataCollectionMembership`
- the `metadata-collection-membership` compact package report case

The report preserves collection titles from text or `content`, `collection-type`
refinements, numeric `group-position` validation diagnostics, display/file-as
refinements, language/direction provenance, local linked-record ZIP provenance,
and remote package-link policy diagnostics without fetching remote resources.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 2767 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 86322 assertions, 0 failures
- PHP JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan across touched files

Accounting:

- `phpPass`: `3660 -> 3661`
- `phpFail`: `0`
- mapped upstream cases: `3697 -> 3698`
- New mapped row: `mappedEpubCompactCollectionMembershipCases = 1`
- New assertion row: `epubCompactCollectionMembershipAssertions = 45`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were run.
