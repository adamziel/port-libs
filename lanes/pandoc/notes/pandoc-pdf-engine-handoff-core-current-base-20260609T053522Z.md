# PDF Engine Handoff Current-Base Slice

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T053522Z`

Accepted base: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`

## Behavior

This slice adds a bounded native PHP review policy for fake-produced PDF catalog `/ViewerPreferences` dictionaries. `PdfEngineHandoff::fakeRun()` now exposes `pdfViewerPreferencePolicy`, and `fakeRunSequence()` carries it forward as `finalPdfViewerPreferencePolicy`.

The policy groups already-parsed viewer preferences into UI, print, enforced UI, and enforced print buckets; converts `PrintPageRange` into bounded start/end pairs; and emits deterministic review issues for hidden viewer UI, non-default print scaling, duplex printing, multiple copies, print-tray-by-PDF-size, right-to-left direction, enforced preferences, and bounded print-page ranges.

## Evidence

Baseline before this patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1283 assertions, 0 failures`.

Red-first probe after adding the focused assertion:

```sh
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1285 assertions, 1 failures`, failing on missing `pdfViewerPreferencePolicy`.

Final focused verification:

```sh
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1295 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
```

Result: `pdf engine handoff self-test ok`.

Status movement: `phpPass +1` (`2382 -> 2383`), mapped denominator `+1` (`2776 -> 2777`), `pdfEngineHandoffCoreCases 12 -> 13`, `mappedPdfEngineHandoffCoreCases 12 -> 13`, and `pdfEngineHandoffCoreAssertions 108 -> 120`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `PdfEngineHandoff` fake runner, PDF catalog dictionary parser, and viewer preference parser. It does not shell out to Pandoc, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not rework accepted PDF output-intent, conformance, associated-file, page-label, URI base, name-tree, catalog requirement, structure, annotation, form, signature, DSS, active-action, optional-content, encryption, stream-filter, or produced-byte artifact diagnostics. The new behavior is limited to catalog `/ViewerPreferences` UI/print/enforcement policy summarization from fake-produced PDF bytes.
