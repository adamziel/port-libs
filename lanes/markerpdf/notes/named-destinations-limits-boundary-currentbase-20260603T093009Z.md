# markerPDF Named Destinations Limits Boundary Current Base

Date: 2026-06-03 09:30 UTC

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260603T093009Z`

## Behavior

`PdfNamedDestinationExtractor` now enforces catalog `/Names /Dests` name-tree `/Limits` before promoting named-destination review rows into WordPress import metadata.

- Inherited name-tree limits are intersected as child nodes are traversed.
- Out-of-limits stale rows in otherwise valid leaves are skipped.
- In-limits names-tree rows still take precedence over duplicate legacy catalog `/Dests` rows.
- Valid non-duplicate legacy `/Dests` rows remain available as review metadata.
- Name-tree traversal keeps the existing object cycle guard and now also has a bounded recursion depth.

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF TOC/navigation metadata from PDF parsing into conversion metadata before OCR/model stages. The native PHP boundary for this slice is PDF name-tree semantics: `/Limits` constrain the key range contained in a destination name-tree node, so stale appended destination keys must not become WordPress navigation metadata.

This reuses accepted adjacent markerPDF behavior from outline and metadata name-tree handling without re-running Python, pdftext, pypdfium2, OCR, Surya, Texify, Torch, or external PDF tools.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`

Result: `1 test files, 18 assertions, 1 failures`. The new direct extractor case returned stale out-of-limits names `Z Stale Deck` and `A Stale Deck`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`

Result: `1 test files, 28 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php`

Result: emitted `destination_count=4`, `named_destinations=["migration-start","media-cleanup","review-summary","legacy-review"]`, `out_of_limits_destination_filtered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
- `php -l lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-named-destinations-import.php`

Result: no syntax errors.

## Status Delta

- Behavior tests move `1017 -> 1018`.
- Direct `PdfNamedDestinationExtractorTest` assertions move `18 red-first -> 28 passing` for this focused file after adding one current-base boundary case.
- WordPress named-destination smoke now proves `/Limits` filtering in the import metadata path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, value parser, page-tree traversal, and direct named-destination extractor. GPU/model/OCR execution, Surya/Texify/Torch, pdftext runtime parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted outline name-tree `/Limits` handling, metadata catalog `/Names /Dests` limits, named-destination Fit operand normalization, outline action-chain review, page-label transition propagation, xref current-base catalog selection, stream-filter boundaries, or embedded-file name-tree review. The bounded behavior is specifically the direct `PdfNamedDestinationExtractor` API used by WordPress named-destination import metadata.

## Next Task

Continue with non-overlapping native PDF parser/review boundaries such as annotations, forms, xref repair, image/filter metadata, page geometry, or supplied table/equation handoffs without Python/model/external PDF tools.
