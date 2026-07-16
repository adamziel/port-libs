# markerPDF Named Destinations Decoded Collision Source Order Boundary

Base accepted HEAD: `5fc1508fa8cbb6f73d200148dae4d18548fb8029`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through the PDF parser/PDFium boundary before OCR or model handoff. Under the current no-GPU markerPDF scope, this slice maps the native catalog `/Names /Dests` boundary for decoded-collision destination labels: two raw PDF string keys can decode to the same visible label, and review metadata must preserve their raw byte identity so annotations and outlines resolve to the intended target pages.

## Behavior

`PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, and `PdfActionReviewExtractor` now distinguish duplicate raw keys from duplicate decoded labels in destination name-tree leaves:

- exact duplicate raw keys keep the accepted whole-leaf source-byte sort used by the existing duplicate-key boundary;
- decoded-collision keys with different raw bytes are sorted only within the duplicate decoded-name positions, preserving accepted physical order for ordinary aliases and unique leaf names;
- `name_bytes_hex` remains attached to decoded-collision rows so WordPress review UIs can distinguish the ASCII `(Collision)` key from the UTF-16 `<FEFF...>` key without leaking labels into page text.

## Evidence

Focused behavior test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionSourceOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders decoded-collision destination rows by source bytes before WordPress metadata
PASS applies decoded-collision aliases to WordPress spans without leaking destination labels

1 test files, 43 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 71 selected test files (root lock skipped)
148 PASS cases
71 test files, 2482 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-decoded-collision-source-order-currentbase.php
```

Passed and emitted `review_order_from_source_bytes=["Collision","Collision","Alias ASCII","Alias UTF16"]`, `ascii_collision_name_bytes_hex=436f6c6c6973696f6e`, `utf16_collision_name_bytes_hex=feff0043006f006c006c006900730069006f006e`, `metadata_order_matches_review_order=true`, `visible_text_excludes_destination_labels=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `3397 -> 3399` from 2 new focused PASS cases.
- New focused assertions: 43.
- New WordPress smoke/example: `wordpress-pdf-named-destination-decoded-collision-source-order-currentbase.php`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.

## Non-Overlap

This does not repeat accepted basic `/Names /Dests` extraction, legacy `/Dests`, ordinary `/Limits` pruning, child `/Kids` limits ordering, duplicate raw-name leaf ordering, decoded-collision alias resolution when physical order is already source-byte sorted, malformed UTF-16 rejection, indirect-array operands, xref/object-stream/trailer-root selection, outline destination action context, link annotation name-tree limits, or PageLabels behavior. The bounded behavior is only reversed physical order for decoded-collision named destinations with distinct raw source bytes.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, string/name decoder, generation-exact object resolver, name-tree walker, metadata extractor, action-review map, link applier, and text extractor. Live OCR, Surya/Texify/Torch model execution, raster rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally outside this no-GPU markerPDF slice.
