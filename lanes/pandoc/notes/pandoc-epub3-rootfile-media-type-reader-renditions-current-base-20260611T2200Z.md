# EPUB3 Rootfile Media Type Reader Renditions

Slice: `pandoc-epub3-rootfile-media-type-reader-renditions-current-base-20260611T2200Z`

## Scope

EpubReader now preserves OCF `META-INF/container.xml` rootfile media-type
parameter provenance for native PHP EPUB package review. This closes the reader
side of the rootfile parameter gap after compact `EpubPackage` already preserved
the same rootfile metadata.

## Implementation

- `EpubReader::readContainer()` records raw rootfile media types plus normalized
  base media types, parameter maps, parameter names/counts, syntax validity, and
  diagnostics.
- Rendition discovery now admits selected and alternate OPF rootfiles by base
  media type, so parameterized `application/oebps-package+xml` rootfiles are not
  dropped from `renditions`.
- Selected and alternate rendition summaries carry the same rootfile media-type
  provenance through `result['renditions']`, `importReport['renditions']`, and
  document attributes.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4028 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66585 assertions, 0 failures

## Accounting

- Adds one focused `EpubReaderTest.php` PASS case for OCF rootfile media-type
  parameter handoff in reader rendition summaries.
- Adds 33 focused assertions.
- `lane-status.json` moves `phpPass` from 3131 to 3132 after rebasing onto
  current main `895143aff`.
- `mappedEpubContainerRootfileMediaTypeParameterCases` moves from 1 to 2.
- `epubContainerRootfileMediaTypeParameterAssertions` moves from 25 to 58.

## Boundaries

This slice does not invoke Pandoc, EPUBCheck, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests. It does not repeat OPF manifest media-type parameter handling,
compact `EpubPackage` rootfile validation, OCF sidecar handling, manifest/spine
parsing, nav/NCX parsing, media overlay parsing, or XHTML resource scanning.
