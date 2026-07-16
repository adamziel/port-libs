# Pandoc PDF Engine Handoff Current Base - Marked Content Properties

Date: 2026-06-06 UTC
Base: 218f7be316686ea5b2005dbccc9e20ca989dc733
Slice: pandoc-pdf-engine-handoff-core-current-base-20260606T030044Z

## Scope

Implemented one bounded native PDF-output fake-runner handoff cluster: page resource `/Properties` marked-content property dictionaries. The fake runner now extracts property metadata (`MCID`, `Lang`, `Alt`, `ActualText`, `E`) and property-level `/AF` associated file references, then attributes embedded file summaries to stable `marked-content:<page-ref>.Properties.<name>.AF` sources.

This is intentionally not a TeX, Typst, browser, roff, or Pandoc renderer implementation. No external renderer or upstream Haskell runner was executed.

## Non-Overlap

Avoided recent accepted PDF engine clusters for catalog URI base, page/structure associated files, AcroForm dictionary metadata, article threads, page metadata streams, PieceInfo, version metadata, and annotation details. This slice only owns page-resource marked-content property dictionaries and their associated file handoff.

## Evidence

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 559 assertions, 0 failures
```

Red-first after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 561 assertions, 1 failures
```

Passing focused check after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 568 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
pdf engine handoff self-test ok
```

## Status Delta

- `lane-status.json` `phpPass`: 1167 -> 1168.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 1617 -> 1618.
- `pdfEngineHandoffCoreCases`: 10 -> 11.
- `mappedPdfEngineHandoffCoreCases`: 10 -> 11.
- `pdfEngineHandoffCoreAssertions`: 95 -> 104.

## Dependency Closure

No new support component is required. This reuses native PHP `PdfEngineHandoff` object, dictionary, page-tree, and embedded-file inspection helpers. Upstream runner parity remains gated on a hydrated Pandoc checkout plus Cabal project/package files and Haskell Tasty runner build closure.

## Follow-Up

Keep marked-content content-stream operator correlation, inherited resource-property collision handling, PDF/A-3 associated-file conformance checks, richer file-attachment relationship policies, and real renderer parity as separate bounded slices.
