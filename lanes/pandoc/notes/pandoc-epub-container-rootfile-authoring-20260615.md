# Pandoc EPUB Container Rootfile Authoring

Slice: `pandoc-epub-container-rootfile-authoring`
Base: current main `eee4df59d`

Implemented a bounded native PHP EPUB3 package-ingestion slice. `EpubReader`
now preserves OCF `container.xml` rootfile authoring provenance:

- raw `full-path` values alongside canonical package paths;
- structural rootfile attributes;
- custom namespaced/data review attributes;
- attribute and custom-attribute counts;
- propagation into selected and alternate rendition summaries, import reports, and
  document attributes.

This keeps multi-rendition EPUB review packets from dropping rootfile-level
authoring metadata before WordPress handoff.

Accounting:

- `phpPass`: `3658 -> 3659`
- `phpFail`: `0`
- mapped upstream cases: `3695 -> 3696`
- `mappedEpubContainerRootfileAuthoringCases`: `1`
- `epubContainerRootfileAuthoringAssertions`: `20`

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 4502 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86249 assertions, 0 failures`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
