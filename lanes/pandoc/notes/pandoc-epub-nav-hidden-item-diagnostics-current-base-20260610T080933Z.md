# pandoc-epub-nav-hidden-item-diagnostics-current-base-20260610T080933Z

Slice: `pandoc-epub-nav-hidden-item-diagnostics-current-base-20260610T080933Z`

This slice extends the existing native PHP EPUB nav item diagnostics coverage.
`EpubReader` now emits a normalized `hidden-nav-item` document diagnostic for
hidden navigation list items and rolls that diagnostic into section and document
`hiddenItemCount` fields. The broader EPUB nav item diagnostics from current
base remain intact: empty labels, missing hrefs, missing direct item labels,
missing leaf links, duplicate targets, duplicate ids, and duplicate label ids.

The regression stays inside the existing `reports EPUB nav item diagnostics for
package review` fixture. It adds one hidden nav item with a unique package
target so the test isolates hidden-item provenance without triggering duplicate
target diagnostics.

This is an assertion-only extension of an existing mapped case. It does not
increase `lane-status.json` `phpPass` or the upstream manifest mapped-case
denominator. It does not invoke Pandoc, EPUBCheck, browser renderers, zip/unzip,
external validators, online services, live provider tests, or live-service
provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 file, 3862 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 files, 59169 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: unchanged at `2948`
- `lane-status.json` suite progress: unchanged at `851`, with hidden-item
  assertions recorded under the existing EPUB nav item diagnostics case
