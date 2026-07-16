# Pandoc PDF Engine Handoff Core Current Base - 2026-06-09

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T120134Z`
Base: `263539ba3d73b56b4272022ad65c63907f61136b`

## Behavior

- Added native fake-run PDF collection portfolio policy summarization for `/Collection` metadata and `/Names/EmbeddedFiles` members.
- Reports default-document match state, schema/sort field counts, undefined sort fields, per-file collection item counts, missing schema fields, extra collection item fields, and `ok`/`review` status.
- Added diagnostics such as `pdf-byte-collection-policy:review`, `pdf-byte-collection-policy-default-missing`, and per-issue counts.
- Added first-run and final-run handoff keys: `pdfCollectionPolicy` and `finalPdfCollectionPolicy`.
- Updated the WordPress PDF engine handoff smoke to expose the collection policy for a valid review-assets portfolio packet.

## Evidence

- Red-first command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result before implementation: `1 test files, 1422 assertions, 1 failures`.
  - Failure: new collection policy expectation returned `null`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1430 assertions, 0 failures`.
- Example smoke command: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: passed.

## Non-Overlap

This slice does not repeat existing PDF collection metadata extraction, embedded-file extraction, action, signature, optional-content, structure, or appearance policy coverage. It adds a bounded policy layer over the already-native collection and embedded-file data.

## Dependency Closure

No new support component is needed. The patch reuses `PdfEngineHandoff`'s lane-local PDF object parser, collection metadata extractor, embedded-file extractor, fake runner, and WordPress example path. It does not run Pandoc, TeX/PDF engines, browser renderers, zip/unzip, online services, live provider tests, or the upstream Haskell runner.

## Root Harness

Not run - isolated micro-slice.
