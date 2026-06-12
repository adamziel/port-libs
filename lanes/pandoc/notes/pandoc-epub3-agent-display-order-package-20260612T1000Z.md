# Pandoc EPUB3 Compact Agent Display Order

Bead: `plib-7api5`
Date: 2026-06-12 UTC
Scope: `lanes/pandoc` EPUB3 package ingestion.

## Change

`EpubPackage` compact package ingestion now preserves an ordered OPF
creator/contributor display report for package review. The report combines
`dc:creator` and `dc:contributor` detail rows, sorts valid positive-integer
`display-seq` metadata ahead of invalid and unsequenced rows, and keeps
`file-as`, roles, alternate scripts, source indexes, and invalid display-seq
diagnostics visible in `metadata()['agentDisplayOrder']`.

The same report is propagated through
`summary()['wordpressImport']['metadataDetails']['agentDisplayOrder']`, with
contributor detail and role groupings mirrored in the compact WordPress metadata
review packet.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1949 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 70504 assertions, 0 failures

No Pandoc executable, EPUBCheck, zip/unzip, browser renderer, external
validator, online service, live provider test, or live-service provider test was
run.

## Accounting

- `phpPass`: `3189 -> 3190`
- New mapped row: `mappedEpubAgentDisplayOrderPackageCases = 1`
- New assertion row: `epubAgentDisplayOrderPackageAssertions = 25`

## Non-Overlap

This does not repeat accepted full `EpubReader` agent-display ordering,
contributor-role parsing, OPF unique identifier, title/date/source/language
metadata, accessibility metadata, metadata-link vocabulary, manifest/spine,
guide/collection, OCF sidecar, media-overlay, fallback, binding, or navigation
work. This slice is limited to the compact `EpubPackage` review handoff surface.
