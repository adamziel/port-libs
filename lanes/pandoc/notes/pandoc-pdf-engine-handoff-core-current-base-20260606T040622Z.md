# PDF Engine Handoff Current-Base Catalog Permissions

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T040622Z`
Base: `aacd91f0c62d29521f76ed00e1ea16c126d3b35d`
Date: 2026-06-06 UTC

## Scope

This slice adds bounded native PHP extraction for produced-PDF catalog
`/Perms` permission signatures in `PdfEngineHandoff`. The fake runner now
surfaces catalog-level `/DocMDP` and `/UR3` signature dictionaries as
`pdfCatalogPermissions`, carries them through multipass summaries as
`finalPdfCatalogPermissions`, and emits diagnostics for permission counts,
byte ranges, reference transforms, and subfilters.

The implementation reuses the existing PDF dictionary/reference helpers and
bounded signature summarizer. It does not validate cryptographic signatures,
run real TeX/Typst/browser engines, validate byte ranges against incremental
updates, or implement PDF/UA or PDF/A validator parity.

## Source Truth And Non-Overlap

Source truth is the accepted Pandoc PDF-engine handoff lane contract: port the
bounded produced-PDF metadata handoff needed by conversion review packets, not
the external PDF engine or verifier. This is distinct from already accepted
produced-PDF document info, XMP/PDF-A, output intents, tagging, URI base,
AcroForm signature field, active-action, optional-content, collection, and
thread metadata slices.

This slice is specifically catalog `/Perms` permission-signature metadata. It
does not overlap AcroForm field signatures or standalone signature-field
handoff except for reusing the same summary shape for byte range and
transform metadata.

## Evidence

Baseline focused check before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 568 assertions, 0 failures
```

Red-first check after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 570 assertions, 1 failures
```

Failure reason: the new expectation for `pdfCatalogPermissions` failed because
the fake runner did not expose catalog `/Perms` signatures.

Final focused check after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 577 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
pdf engine handoff self-test ok
```

Counters:

- `lane-status.json` `phpPass`: `1186 -> 1187`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1634 -> 1635`
- `pdfEngineHandoffCoreCases`: `10 -> 11`
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`
- `pdfEngineHandoffCoreAssertions`: `95 -> 104`

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`PdfEngineHandoff` PDF object/dictionary parsing and the existing bounded
signature summarizer.

The upstream runner dependency blocker is unchanged: full Pandoc runner parity
still needs a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

Root harness: not run - isolated micro-slice.
