# markerPDF named-destination indirect string-key sparse current base

Slice: `markerpdf-named-destinations-boundary-current-base-20260608T165133Z`

Accepted base: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

## Source Truth

Upstream markerPDF delegates searchable-PDF navigation extraction to PDF parser/PDFium boundaries. In native PHP, catalog `/Names /Dests` name arrays must stay tolerant of damaged sparse arrays without promoting malformed operands to WordPress navigation or visible text.

This slice covers a bounded name-tree edge: a malformed name array omits the value for one string key, then stores the following real key as an indirect PDF string object before an explicit destination array. The native parser now recovers that indirect string key when the next operand is an explicit destination array or GoTo dictionary, while preserving legitimate indirect string alias values when they are followed by ordinary string/name keys.

No OCR, Surya, Texify, Torch, PDFium/PIL raster execution, PDF action execution, JavaScript execution, or external PDF tools are used.

## Implementation

- `PdfNamedDestinationExtractor` recovers indirect string keys in sparse `/Names /Dests` arrays only when the following operand starts an explicit destination container.
- `PdfMetadataExtractor` applies the same recovery for `document_destinations` review metadata.
- `PdfActionReviewExtractor` applies the same recovery for annotation link action review.
- `PdfOutlineExtractor` applies the same recovery for TOC/navigation destination resolution.
- `wordpress-pdf-named-destination-indirect-string-key-sparse-currentbase.php` proves WordPress-facing link promotion and visible-text isolation.

The first focused run after updating the parser/action/metadata paths exposed the missing outline walker:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringKeySparseBoundaryCurrentBaseTest.php`

Result: `1 test files, 39 assertions, 1 failures`; `Recovered Key Outline` was absent.

After applying the same bounded recovery to `PdfOutlineExtractor`:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringKeySparseBoundaryCurrentBaseTest.php`

Result: `1 test files, 43 assertions, 0 failures`.

Adjacent named-destination coverage:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationSparseNameArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectStringAliasBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectOperandTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationActionAliasCycleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php`

Result: `6 test files, 254 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-string-key-sparse-currentbase.php`

Result: exits `0` with `destination_names=["Actual Target","Indirect Alias","Recovered Indirect Key","LegacyTail"]`, `promoted_link_objects=[7,9,10]`, `missing_key_promoted=false`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.

## Non-Overlap

This does not repeat accepted direct sparse name-array recovery, indirect string alias preservation, tailed indirect operand rejection, generation-exact named destinations, name-tree `/Limits`, duplicate `/Dests` key rejection, object-stream destination recovery, link generation boundaries, outline action chains, annotation URI promotion, xref repair, image/filter metadata, font/CMap parsing, or OCR/model handoffs. The bounded behavior is only indirect PDF string keys that follow a missing value in `/Names /Dests` arrays and precede an explicit destination container.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, name-tree walkers, destination normalization, metadata review, outline TOC extraction, annotation link promotion, and WordPress smoke renderer. Full upstream runtime/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

Root harness: not run - isolated micro-slice.
