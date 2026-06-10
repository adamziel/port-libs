# Pandoc ODF Mimetype Entry Provenance Current Base 20260610T145104Z

## Scope

- Lane: `pandoc`
- Bead: `plib-8byq`
- Micro-slice: ODF/ODT OpenDocument package ingestion
- Base accepted HEAD: `2cf16ba0802e9873e143be9b0dcfaa47b6679740`
- Behavior cluster: ODT package `mimetype` entry provenance before content handoff.

## Implementation

`OdfReader::readPackage()` now preserves the existing `ZipPackage`
stored-first-entry preflight for the required ODT `mimetype` part instead of
discarding it after validation. The summary is exposed in:

- `document->attr('manifest')['mimetypeEntry']`
- `importReport['manifest']['mimetypeEntry']`

The handoff keeps first-local-entry status, compression method, data descriptor
use, central/local extra-field ids, expected/content byte counts, content-match
status, and diagnostics. Invalid packages are still rejected through the same
stored-first preflight path as before.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `jq empty lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 3505 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60376 assertions, 0 failures`

## Accounting

- `phpPass`: `2980 -> 2981`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3141 -> 3142`
- ODF/OpenDocument core mapped cases: `15 -> 16`
- ODF/OpenDocument core assertions: `310 -> 324`
- New focused slice assertions: `14`

## Non-Overlap

This does not change ZIP parsing, ODT manifest validation, XML parsing, styles,
content block conversion, media extraction, RDF, signatures, encryption, or
writer behavior. It only surfaces already-enforced ODT `mimetype` package
boundary provenance for reviewer handoff without invoking Pandoc, office
suites, zip/unzip, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.
