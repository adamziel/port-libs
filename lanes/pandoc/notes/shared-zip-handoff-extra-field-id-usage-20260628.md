# ZIP Handoff Extra Field ID Usage

Slice: `plib-v92hb` shared ZIP/OPC selected-entry handoff extra-field ID usage.

## Change

- `ZipPackage::entryHandoffPreflight()` now reports selected and readable handoff extra-field ID usage summaries:
  - `selectedExtraFieldIdUsage`
  - `handoffExtraFieldIdUsage`
  - selected and handoff central/local/shared/central-only/local-only ID counts
  - readable `handoffExtraFieldProvenanceEntries`
- Blocked oversized selections remain in selected extra-field provenance but stay out of readable handoff extra-field buckets.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,421 assertions, 0 failures

## Boundary

This is a native PHP ZIP/OPC metadata slice. It does not invoke Pandoc, office suites, TeX/browser engines, ZipArchive, zip/unzip, external validators, or network services.
