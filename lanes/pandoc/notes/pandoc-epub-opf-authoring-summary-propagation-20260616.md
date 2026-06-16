# Pandoc EPUB OPF authoring summary propagation

Bounded native PHP EPUB reader coverage adds one OPF package root authoring summary propagation case after rebase onto current main `564d424924`.

`EpubReader` now exposes a compact `package.summary` projection for OPF package root authoring metadata. The summary carries duplicate/conflicting `xml:base`, language, and direction source diagnostics, custom conflict counts, metadata-only `xml:base` policy, and stable root attribute counts. Focused coverage asserts the summary propagates consistently through `importReport.package.summary`, `metadata.packageAuthoring.summary`, `importReport.metadata.packageAuthoring.summary`, and AST document metadata.

No Pandoc binary, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderers, Node tooling, online services, live providers, or external validators were invoked.

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` - 1 file, 4787 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 195 files, 170141 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

Counters:

- `mappedEpubPackageAuthoringSummaryPropagationCases = 1`
- `epubPackageAuthoringSummaryPropagationAssertions = 23`
- `phpPass 16352 -> 16353`
- `phpFail = 0`
