# markerPDF named-destination action subtype boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T200330Z`

Accepted base: `04e99b68d5dc6e073f4bb0aa436e72dabb16d510`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF structure through native parser extraction before OCR, layout, table, equation, or model handoffs. In the native PHP boundary, named destinations feed document metadata, outline TOC import, annotation review, and WordPress link promotion only after the destination resolves to a local document destination.

PDF action dictionaries can represent local navigation when `/S /GoTo` supplies `/D`. Non-GoTo action dictionaries such as `/URI` or `/Launch`, and subtype operands that resolve through a trailing action payload, must not be treated as local named destinations just because a `/D` entry is also present.

## Behavior

`PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, `PdfOutlineExtractor`, and `PdfActionReviewExtractor` now share the same fail-closed boundary:

- bare destination dictionaries with `/D` remain valid;
- action dictionaries with `/S /GoTo` and `/D` remain valid;
- non-GoTo `/S` values are rejected before destination metadata, outline TOC rows, annotation action chains, and link promotion;
- indirect `/S` operands are rejected when the referenced object contains a valid leading name plus trailing action payload.

The focused fixture proves this for catalog name-tree destinations, legacy catalog `/Dests`, outline `/Dest` references, link annotation `/Dest` names, safe direct URI annotations, and visible WordPress paragraph rendering.

## Red-First Evidence

Before the source edits, the focused test failed because an indirect `/S /Launch` destination and a tailed indirect `/S /GoTo << /S /URI ... >>` destination were still accepted by metadata/link-promotion paths:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionSTypeOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects non-GoTo and tailed action subtype operands before destination metadata and outlines
FAIL keeps malformed action subtype destination rows out of link promotion and visible WordPress text

1 test files, 4 assertions, 2 failures
```

## Verification

Focused test:

```text
php -d opcache.enable_cli=0 tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionSTypeOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects non-GoTo and tailed action subtype operands before destination metadata and outlines
PASS keeps malformed action subtype destination rows out of link promotion and visible WordPress text

1 test files, 62 assertions, 0 failures
```

Adjacent named-destination/action family:

```text
php -d opcache.enable_cli=0 tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionSTypeOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationSurplusOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationCoordinateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationViewBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
...
8 test files, 345 assertions, 0 failures
```

WordPress smoke:

```text
php -d opcache.enable_cli=0 lanes/markerpdf/examples/wordpress-pdf-named-destination-action-s-type-boundary-currentbase.php
```

The smoke exits `0` and emits `destination_count=3`, `document_destination_count=3`, `promoted_link_objects=[7,11,12]`, `malformed_destination_promoted=false`, `direct_uri_promoted=true`, `visible_text_imported=true`, `executes_pdf_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named-destination behavior for duplicate direct `/Names` versus `/Dests`, decoded name collisions, name-tree limits ordering, indirect arrays, invalid Kids ordering, unknown view modes, missing/nonnumeric required coordinates, surplus destination operands, GoTo alias cycles, xref/object-stream/trailer-root selection, or action-dictionary direct non-GoTo checks in standalone destination extraction.

The bounded delta is action subtype operand validation for named-destination dictionaries across metadata, outlines, annotation action review, link promotion, and visible WordPress rendering.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, object/value resolver, name-tree parser, destination view parser, metadata extractor, outline extractor, annotation/link extractors, text extractor, and WordPress Markdown smoke renderer. Full OCR/model layout parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext/PDFium rendering, Surya/Torch, Texify, tabled-pdf, Streamlit/FastAPI workers, model downloads, and external OCR/rendering helpers; none were executed.

## Next Task

Continue with non-overlapping native markerPDF searchable-PDF parser behavior around fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
