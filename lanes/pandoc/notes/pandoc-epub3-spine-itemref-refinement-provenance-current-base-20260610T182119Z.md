# Pandoc EPUB3 Spine Itemref Refinement Provenance Slice

Date: 2026-06-10
Bead: plib-zabw

## Scope

Compact EPUB package ingestion now preserves OPF spine itemref provenance without shelling out to Pandoc, EPUBCheck, zip/unzip, browser renderers, or external validators.

The bounded case covers:

- itemref `id` retention in compact `EpubPackage::spine()` / `readingOrder()`.
- raw `linear` token retention alongside the existing boolean reading-order value.
- metadata refinements targeted at spine itemrefs through `meta refines="#itemref-id"`.
- `rendition:viewport` itemref refinement handoff for fixed-layout review provenance.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file / 827 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files / 60977 assertions / 0 failures

## Accounting

- `phpPass`: 2996 -> 2997
- `phpFail`: 0
- `benchmarkDenominator.mapped`: 3152 -> 3153
- New mapped row: `mappedEpubSpineItemrefRefinementProvenanceCases = 1`
- New assertion row: `epubSpineItemrefRefinementProvenanceAssertions = 22`
