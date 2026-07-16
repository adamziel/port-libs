# pandoc-epub-manifest-property-token-report

- Slice: `pandoc-epub-manifest-property-token-report`
- Area: EPUB3 package ingestion, compact OPF manifest review packets.
- Rebase base: `origin/main` at `847e0a8496`.

`EpubPackage` now reports OPF manifest `properties` token inventories in the
compact package validation and WordPress import handoff. The report preserves
token order, per-property manifest IDs and package part names, duplicate-token
review items, and `duplicate-manifest-property-token` diagnostics.

Accounting:
- `phpPass`: 15327 -> 15328
- `phpFail`: 0
- Mapped upstream cases: 14998 -> 14999
- `mappedEpubManifestPropertyTokenCases`: 1
- `epubManifestPropertyTokenAssertions`: 23

Verification:
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3834 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 181 files, 165391 assertions, 0 failures

No Pandoc executable, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
