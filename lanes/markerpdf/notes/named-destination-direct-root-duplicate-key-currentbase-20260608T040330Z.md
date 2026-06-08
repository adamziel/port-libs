# markerPDF Named Destination Direct Root Duplicate Key Boundary

Date: 2026-06-08 04:03 UTC

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T040330Z`

## Behavior

Direct inline catalog `/Names /Dests` root dictionaries with duplicate decoded traversal keys now fail closed before standalone named-destination extraction.

- An inline destination root such as `<< /Names [...] /#4eames [...] >>` is treated as ambiguous, matching the existing fail-closed boundary for indirect name-tree nodes with duplicate `/Names`, `/Kids`, or `/Limits` keys.
- The ambiguous names-tree root no longer promotes stale overwritten destinations into standalone `PdfNamedDestinationExtractor` rows.
- Independent legacy catalog `/Dests` fallback rows remain available.
- Outline, annotation, link, and metadata paths stay aligned: stale direct-root destination labels remain out of WordPress review metadata and visible text, while safe URI actions still promote normally.

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through parsing before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary: `/Names`, `/Kids`, and `/Limits` are traversal keys for a name-tree node, and duplicate decoded keys in one node are ambiguous because a parser can otherwise overwrite earlier values.

This reuses the native PHP PDF parser and does not run Python, pdftext, pypdfium2, OCR, Surya, Texify, Torch, raster rendering, or external PDF tools.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDirectRootDuplicateKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 1 failures`. The stale overwritten `Stale Root` destination was emitted before `LegacyOk`.

Post-fix focused gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDirectRootDuplicateKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 50 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-direct-root-duplicate-key-currentbase.php`

Result: exits `0` and emits `destination_names=["LegacyOk"]`, `document_destination_names=["LegacyOk"]`, `promoted_link_objects=[9,10]`, `duplicate_direct_root_hidden=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests move `2163 -> 2165`.
- Lane `phpPass` moves `2927 -> 2929`.
- WordPress scenarios move `2438 -> 2439`.
- The focused file adds 2 passing cases and 50 assertions after the red-first failure.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, direct dictionary raw-value scanner, duplicate-key detector, named-destination extractor, metadata extractor, outline review, annotation/link review, and text extractor.

GPU/model/OCR execution, Surya/Texify/Torch, pdftext runtime parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted duplicate `/Dests` keys inside catalog `/Names`, direct inline catalog `/Names` duplicate `/Dests`, indirect node duplicate-key rejection, duplicate leaf-row precedence, legacy `/Dests` duplicate-key rejection, `/Limits` pruning/fallback/order, indirect `/Kids`/`/Names` arrays, PDFDocEncoding byte comparisons, PDF-name-key rejection, action dictionary filtering, view-mode/coordinate validation, generation-exact destinations, object-stream/xref repair, outline destination action context, PageLabels, annotation rectangle promotion, URI review, table/equation handoffs, or OCR/model surfaces.

The bounded behavior is only duplicate decoded traversal keys inside a direct inline catalog `/Names /Dests` root node before standalone named-destination extraction.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs without Python/model/external PDF tools.
