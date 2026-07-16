# Named Destinations Surplus Operand Boundary Current Base

Session: `port-dev-markerpdf-named-destinations-20260606T040924Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T040924Z`
Accepted base: `e62b8d8fc83dc7c662f6961074cd1e3f3b0366e0`

## Source Truth

Upstream markerPDF delegates searchable PDF text, navigation, and destination resolution to pdftext/PDFium at the parser boundary. Under the current no-GPU markerPDF scope, this lane maps that boundary in native PHP without OCR, Surya, Texify, Torch, pypdfium/PIL raster execution, JavaScript execution, or external PDF tools.

PDF explicit destinations have a page operand, a view name, and view-dependent coordinate operands. Existing native coverage intentionally tolerates benign numeric surplus operands for Fit-family compatibility, but nonnumeric surplus operands such as strings, names, arrays, and action dictionaries should not make malformed destination arrays countable as valid WordPress navigation/review rows.

## Implementation

`PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, `PdfOutlineExtractor`, and `PdfActionReviewExtractor` now reject explicit destination arrays whose surplus operands resolve to anything other than `null`, an integer, or a float. Required coordinate validation is unchanged, page-only destinations are unchanged, and existing numeric-surplus FitB compatibility remains intact.

The focused fixture covers:

- valid `[page /FitH top]` and numeric-surplus `[page /FitB 111 222]` destinations;
- nonnumeric surplus string, action-dictionary, name, array, and legacy dictionary operands;
- document destination metadata, outline TOC rows, annotation action review, link span promotion, and visible-text isolation.

## Verification

Red-first probe before source edits:

`php -r 'require "tools/bootstrap.php"; ... PdfNamedDestinationExtractor()->extractNamedDestinations(...)'`

Result: accepted malformed `extra` and `xyzextra` rows with surplus string payload operands.

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationSurplusOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 55 assertions, 0 failures`.

Adjacent named-destination/action family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(NamedDestination|OutlineNamedDestination|OutlineDestination|LinkAnnotation.*Destination).*CurrentBaseTest\.php$' | sort)`

Result: `43 test files, 1490 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-surplus-operand-boundary-currentbase.php`

Result: emitted `nonnumeric_surplus_rejected=true`, `numeric_surplus_preserved=true`, `promoted_link_annotation_objects=[7,8,11]`, `visible_text_excludes_surplus_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted named-destination name-tree `/Limits`, byte-limit, duplicate-key, sparse-name-array, action-dictionary, alias-cycle, invalid view-mode, missing coordinate, page-operand, page-only, generation, xref, object-stream, link annotation, or outline action-context slices. The bounded behavior is only nonnumeric surplus operands after an otherwise valid explicit named-destination array.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF parser, generation-aware object resolver, named-destination normalizer, document metadata extractor, outline/action review extractors, link annotation promotion, and WordPress smoke renderer. Full upstream runtime/model parity remains intentionally out of scope under the no-GPU markerPDF directive.
