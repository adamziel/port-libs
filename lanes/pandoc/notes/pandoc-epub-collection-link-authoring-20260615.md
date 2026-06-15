# pandoc-epub-collection-link-authoring-20260615

Slice: `plib-6oj1z` / EPUB3 package ingestion.

## Behavior

This slice extends the compact native PHP `EpubPackage` preflight path for OPF
`collection` links. Collection link records now preserve authoring language
provenance that package metadata links already carried:

- `title`
- `hreflang`
- `xml:lang` / `language`
- `dir` / `direction`

The same values are also copied into remote-resource policy rows, so WordPress
review packets can inspect localized collection records while retaining the
existing package target, local ZIP byte length, compression, and CRC metadata.

## Evidence

- Red-first focused check:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Failed on missing `hreflang` in collection link handoff.
- Final syntax checks:
  - `php -l lanes/pandoc/src/EpubPackage.php`
  - `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Final focused check after rebase onto current main `1c04e5385d`:
  - `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 2803 assertions, 0 failures`
- Full Pandoc lane gate after rebase onto current main `1c04e5385d`:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86612 assertions, 0 failures`
- Status/tooling checks:
  - `jq empty lanes/pandoc/lane-status.json`
  - `git diff --check`
  - conflict-marker scan across touched Pandoc files

No Pandoc, EPUBCheck, zip/unzip command, ZipArchive, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Dashboard Accounting

- `phpPass`: `3670 -> 3671`
- `phpFail`: `0`
- mapped upstream cases: `3705 -> 3706`
- `mappedEpubCollectionLinkAuthoringCases = 1`
- `epubCollectionLinkAuthoringAssertions = 22`

## Non-Overlap

This does not repeat accepted OPF collection hierarchy, collection link
vocabulary, collection role vocabulary, remote-resource policy, package-link
metadata, manifest/spine authoring, navigation, media-overlay, encryption, OCF
sidecar, or XHTML resource slices. It owns only collection link authoring
language-field propagation in compact package preflight records and policy
handoff rows.
