# pandoc-epub-direct-metadata-link-authoring-20260615

Slice: `plib-0ns6r` / EPUB3 package ingestion.

## Behavior

This slice extends the direct native PHP `EpubPackageReader` path for OPF
metadata `link` records. Direct metadata links now preserve reviewer-visible
authoring fields:

- `title`
- `hreflang`
- `xml:lang` / `lang` with `languageSource`
- `dir` / `direction`
- raw attributes and non-structural custom attributes

The same records are carried through `metadataReport` local/external buckets
plus aggregate title, language, direction, and custom-attribute counts.

## Evidence

- Red-first focused check:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - Failed on missing `linkTitleCount`.
- Final syntax checks:
  - `php -l lanes/pandoc/src/EpubPackageReader.php`
  - `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- Final focused check after rebase onto current main `872264077e`:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - `1 test files, 1427 assertions, 0 failures`
- Full Pandoc lane gate after rebase onto current main `872264077e`:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 88373 assertions, 0 failures`

No Pandoc, EPUBCheck, zip/unzip command, ZipArchive, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Dashboard Accounting

- `phpPass`: `3725 -> 3726`
- `phpFail`: `0`
- `mappedEpubDirectMetadataLinkAuthoringCases = 1`
- `epubDirectMetadataLinkAuthoringAssertions = 28`

## Non-Overlap

This does not repeat accepted compact `EpubPackage` metadata-link, collection
link, OCF sidecar, manifest/spine authoring, navigation, media-overlay,
encryption, or XHTML resource slices. It owns only direct `EpubPackageReader`
OPF metadata-link authoring provenance and report buckets.
