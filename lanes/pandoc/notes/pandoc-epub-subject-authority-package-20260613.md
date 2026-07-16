# pandoc-epub-subject-authority-package-20260613

Slice: EPUB3 compact package subject metadata provenance.

This slice extends native PHP `EpubPackage` OPF metadata ingestion so
`dc:subject` entries are no longer limited to plain strings in review packets.
The compact package metadata now preserves:

- per-subject `scheme`, language, direction, and id provenance;
- `authority`, `term`, `display-seq`, `file-as`, and `alternate-script`
  refinements;
- invalid subject `display-seq` diagnostics;
- subject grouping by scheme, authority, and term;
- package `link refines="#subject-id"` records, including local and missing
  package-resource ZIP provenance;
- WordPress metadata review summaries under `metadataDetails`.

Verification performed:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused result: 1 file, 2266 assertions, 0 failures.
Full Pandoc PHP result: 46 files, 75867 assertions, 0 failures.

Lane-status accounting:

- `phpPass`: 3363 -> 3364 after rebase onto `2b56dcf6`
- `phpFail`: 0
- `mappedEpubSubjectAuthorityPackageCases`: 0 -> 1
- `epubSubjectAuthorityPackageAssertions`: 0 -> 51

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked. This does not repeat accepted OPF source/date/language/identifier,
bibliographic, accessibility, metadata-link, collection, manifest, spine,
binding, media-overlay, OCF sidecar, or XHTML resource-scan slices.
