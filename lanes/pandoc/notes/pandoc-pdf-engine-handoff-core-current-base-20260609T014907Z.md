# PDF Engine Handoff Core Current Base - XMP Media Management Provenance

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T014907Z`
Base accepted HEAD: `08f16fc4bbcf45b83d9ea2497b2ad817ee73416e`
Date: 2026-06-09 UTC

## Behavior

`PdfEngineHandoff` now extracts bounded XMP Media Management provenance from fake-produced PDF metadata packets:

- Top-level `xmpMM:OriginalDocumentID`, `xmpMM:RenditionClass`, `xmpMM:RenditionParams`, and `xmpMM:VersionID`.
- Structured `xmpMM:DerivedFrom` resource-reference fields including source document, instance, original document, rendition, and manager handoff metadata.
- Bounded `xmpMM:History` event records from `rdf:li` entries using either child elements or compact `stEvt:*` attributes.
- Fake-runner diagnostics for original-document IDs, rendition class, derived-from resources, and history event counts.
- Multipass fake-runner final summary propagation through `finalPdfXmpMetadata`.

This is a metadata handoff only. It does not execute Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, JavaScript, external PDF validators, signing engines, online services, live provider tests, or live-service provider tests.

## Evidence

Baseline focused test before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1109 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- Result: `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1118 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: `pdf engine handoff self-test ok`
- `php -r 'foreach (["lanes/pandoc/UPSTREAM_TEST_MANIFEST.json","lanes/pandoc/lane-status.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "json ok\n";'`
- Result: `json ok`
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2077 -> 2078`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2489 -> 2490`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 117`.
- Added `mappedPdfEngineXmpMediaManagementCases: 1`.
- Added `pdfEngineXmpMediaManagementAssertions: 9`.

## Non-Overlap

This slice avoids recent PDF handoff work for PDF/A/PDF/UA/PDF/X identification, PDF/A extension schemas, output intents, page output intents, catalog URI base, catalog requirements, viewer print policy, tagged structure, annotation appearances, optional content, signatures, encryption, and active actions. It only adds XMP Media Management provenance fields inside already detected produced-PDF XMP metadata packets.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP bounded PDF byte scanner, PDF object resolver, XML metadata extraction, fake-runner diagnostics, focused `PdfEngineHandoffTest.php`, and the lane-local WordPress PDF handoff example.

Full renderer parity remains outside this slice and would require explicit authorization for Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, signing engines, or online services.
