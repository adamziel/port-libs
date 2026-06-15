# Pandoc EPUB collection authoring report 2026-06-15

Slice: `plib-uvn10` / EPUB3 package ingestion.

This slice preserves OPF `collection` root authoring attributes in the compact
native PHP EPUB package handoff. `EpubPackage` now records collection
`xml:base`, `xml:lang`, `dir`, id/role structural attributes, and custom review
attributes on parent and nested collections, then exposes a recursive
`collectionAuthoring` report in the package summary and WordPress import packet.

Collection `xml:base` is reported as metadata-only and is not applied to package
path resolution. Existing link/package target resolution remains based on the
OPF package document location.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Result: 1 file, 3283 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 46 files, 88408 assertions, 0 failures

Lane accounting:

- `phpPass`: 3726 -> 3727
- `phpFail`: 0
- Upstream mapped cases: 3744 -> 3745
- `mappedEpubCollectionAuthoringCases`: 1
- `epubCollectionAuthoringAssertions`: 35

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
