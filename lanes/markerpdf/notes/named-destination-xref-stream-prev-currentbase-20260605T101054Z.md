# markerPDF Named Destination Xref Stream Prev Current Base

## Scope

This isolated markerPDF slice maps one native no-GPU PDF parser boundary: catalog `/Names /Dests` named-destination review must walk xref-stream `/Prev` chains before falling back to unselected same-number object bodies. The current xref stream remains authoritative for `/Root`, while previous xref-stream rows provide older selected objects that the latest sparse section omits.

This follows the same source-truth boundary as upstream markerPDF's searchable-PDF parser handoff before OCR/model fallback: native object selection, catalog/name-tree metadata, and WordPress review rows must be decided before any model path. No Python, PDFium, OCR, Surya, Texify, Torch, browser rendering, or external PDF tools are executed.

## Behavior

- `PdfNamedDestinationExtractor` now merges previous xref-stream rows through `/Prev` when the latest xref stream omits an object id.
- Latest xref-stream rows still override older rows, including free rows.
- A sparse latest xref stream with `/Root 10 0 R` and `/Prev <previous-xref-stream>` now resolves the current catalog while preserving selected destination objects from the previous stream.
- Same-number fallback object bodies that were never selected by the xref chain no longer replace named-destination name-tree or destination-dictionary objects in WordPress review metadata.
- Visible WordPress paragraph text still comes only from page content streams; destination names and stale fallback operands stay out of extracted text.

## Evidence

Red probe before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationXrefStreamPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL walks xref-stream Prev chains before named-destination fallback object scanning
FAIL keeps unselected stale xref-stream fallback destinations out of WordPress text and review metadata
1 test files, 4 assertions, 2 failures
```

Focused run after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationXrefStreamPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS walks xref-stream Prev chains before named-destination fallback object scanning
PASS keeps unselected stale xref-stream fallback destinations out of WordPress text and review metadata
1 test files, 21 assertions, 0 failures
```

Named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*.php
Focused test run: 18 selected test files (root lock skipped)
18 test files, 436 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-xref-stream-prev-currentbase.php
```

The smoke exits 0 and emits `prev_xref_stream_destinations_preserved=true`, `stale_fallback_destination_objects_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted named-destination coverage for legacy `/Dests`, name-tree `/Limits`, malformed name keys, invalid page operands, unknown view modes, GoTo action dictionaries, page-only destinations, object-stream type-2 members, current trailer `/Root`, classic xref offset selection, or same-object generation traversal. The new behavior is only xref-stream `/Prev` chain selection for named-destination objects before fallback duplicate scanning.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF tokenizer, xref-stream decoder, object selector, name-tree walker, named-destination normalizer, text extractor, and WordPress smoke harness. Full upstream markerPDF runner parity remains intentionally out of no-GPU scope because it depends on pdftext/PDFium, Surya/OCR/layout models, Texify/Torch, rendering/image model paths, and Streamlit/FastAPI runtime workers.
