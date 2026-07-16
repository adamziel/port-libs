# ODF Compact Manifest Custom Attributes

Slice: `pandoc-odf-compact-manifest-custom-attributes`, rebased on current main
`6ae1fcc83`.

## Closure Evidence Recovered

`progress.md`, `PANDOC_STATUS.md`, and `lanes/pandoc/lane-status.json` already
record ODF/ODT as ship-ready before this follow-up:

- upstream ODF/ODT denominator: 20 format cases / 575 assertions
- local ODF/ODT numerator before this slice: 50 mapped cases / 1546 focused assertions
- pre-slice coverage: 250.0% case coverage / 268.9% assertion coverage
- remaining critical ODF/ODT gaps: 0
- recorded closure gate: focused ODF/ODT tests passed 4 files / 5827 assertions / 0 failures, and full Pandoc tests passed 44 files / 73816 assertions / 0 failures before the later closure-wave slices

This follow-up keeps the same ship-ready verdict and adds one bounded native PHP
package-reader parity case:

- local ODF/ODT numerator after this slice: 51 mapped cases / 1573 focused assertions
- post-slice coverage: 255.0% case coverage / 273.6% assertion coverage
- remaining critical ODF/ODT gaps: 0

## Implementation

`OpenDocumentPackage` now preserves compact ODT `manifest:file-entry` attribute
provenance in parity with the richer `OdfReader` path. Manifest entries,
`manifestReview` items, manifest order rows, aggregate custom-attribute buckets,
and package inventory parts now expose:

- structural vs custom attribute classification
- namespaced vendor attributes such as `loext:*` and `wp:*`
- XML attributes such as `xml:lang`
- empty custom attribute values
- deterministic custom attribute name maps

This is metadata-only package review coverage. It does not expose blocked package
bytes and does not invoke Pandoc, office suites, zip/unzip, ZipArchive, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1181 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 4 files, 5942 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 45 files, 75148 assertions, 0 failures
