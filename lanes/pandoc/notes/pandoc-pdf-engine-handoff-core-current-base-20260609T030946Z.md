# Pandoc PDF Engine Handoff Core Current Base 20260609T030946Z

Base accepted HEAD: `6ab30597dbaeef18dd989f9dad5bd875e13a7661`

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T030946Z`

## Behavior

- Added native PHP fake-runner review policy for PDF associated files found in produced PDF bytes.
- The policy reports PDF/A identification, embedded file counts, associated file counts, unassociated embedded files, missing `/AFRelationship` counts, relationship distribution, per-file review issues, and an overall `ok` or `review` status.
- PDF/A-claimed outputs now flag associated files in non-PDF/A-3 packets and embedded files that are discoverable only through non-`/AF` sources.
- Fake-run diagnostics now include associated-file policy status, counts, relationships, missing relationship counts, and issue counts.
- `fakeRunSequence()` now propagates `finalPdfAssociatedFilePolicy` from the final produced PDF.

## Evidence

- Focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1168 assertions, 0 failures`
- Focused assertion delta: `+13` assertions in one new PDF engine handoff PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: passed after asserting associated-file policy fields and diagnostics.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2208 -> 2209`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2618 -> 2619`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 121`.

## Non-Overlap

This slice does not repeat accepted PDF engine handoff work for TeX sidecars, fake-produced artifact hashes, log warnings/errors, rerun-needed signals, PDF page/structure associated-file source extraction, output-intent policy, or conformance-policy summaries. It adds the bounded policy layer that reviews associated-file relationships and PDF/A-3 suitability after embedded files are already extracted.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF byte parser, XMP metadata extraction, embedded-file discovery, and fake-runner diagnostics. It does not shell out to Pandoc, Cabal, Haskell runners, TeX/PDF engines, browser renderers, Word, LibreOffice, zip/unzip, online services, or live-service provider tests.

## Follow-Up

Next non-overlapping PDF engine handoff work can cover PDF/A extension-schema validation, marked-content artifact role correlation, richer DSS/signature review policy, or page/resource conformance summaries.
