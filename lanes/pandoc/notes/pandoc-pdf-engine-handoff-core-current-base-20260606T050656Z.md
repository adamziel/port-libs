# PDF Engine Handoff Current-Base RichMedia Annotations

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T050656Z`
Base: `dffb68d11b769f872d4da32f21b819394fad38ff`
Date: 2026-06-06 UTC

## Scope

This slice adds bounded native PHP extraction for produced-PDF
`/Subtype /RichMedia` annotation metadata in `PdfEngineHandoff`. The fake
runner now surfaces RichMedia asset name-tree entries, configuration instance
asset references, activation/deactivation conditions, and presentation
settings, and carries the same data through multipass summaries as
`finalPdfRichMediaAnnotations` and `finalPdfRichMediaActivationModes`.

The implementation does not render or play media, execute JavaScript, validate
SWF/3D/Rendition payloads, run TeX/Typst/browser engines, or invoke external
PDF validators.

## Source Truth And Non-Overlap

Source truth is the accepted Pandoc PDF-engine handoff lane contract: port the
bounded produced-PDF metadata handoff needed by conversion review packets, not
external engines or PDF renderers. This is distinct from already accepted PDF
catalog permissions, digital signatures, active actions, optional content,
attachments, forms, tagging, URI base, output intents, XMP/PDF-A, and page
geometry slices.

This slice specifically covers RichMedia annotation metadata so WordPress
review queues can flag active embedded media and its referenced assets without
opening or executing the PDF.

## Evidence

Baseline focused check before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 577 assertions, 0 failures
```

Red-first check after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 579 assertions, 1 failures
```

Failure reason: `/Subtype /RichMedia` was neither counted in
`pdfAnnotationTypes` nor summarized as `pdfRichMediaAnnotations`.

Final focused check after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
1 test files, 589 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
pdf engine handoff self-test ok
```

Counters:

- `lane-status.json` `phpPass`: `1201 -> 1202`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1647 -> 1648`
- `pdfEngineHandoffCoreCases`: `10 -> 11`
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`
- `pdfEngineHandoffCoreAssertions`: `95 -> 107`

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`PdfEngineHandoff` PDF object/dictionary/name-tree parsing and the existing
WordPress PDF handoff example.

The upstream runner dependency blocker is unchanged: full Pandoc runner parity
still needs a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

Root harness: not run - isolated micro-slice.
