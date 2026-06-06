# markerPDF named-destination kid Limits action-map boundary

Session: `port-dev-markerpdf-named-destinations-20260606T080438Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T080438Z`
Base accepted HEAD: `abd9b455aa8ac5c4b63d3568f04bfd5d77b5e0b4`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through parser-backed PDF text/link extraction before OCR/model handoff. Under the current no-GPU markerPDF scope, the native PHP equivalent is catalog `/Names /Dests` resolution for document review and link annotation promotion.

PDF name-tree child nodes are ordered by their `/Limits`. The standalone named-destination and metadata extractors already sort fully bounded `/Kids` by effective lower-limit bytes. Before this slice, `PdfActionReviewExtractor` built its annotation/link destination map in physical `/Kids` array order, so a malformed overlapping duplicate destination could make WordPress span promotion follow a stale physically later child while document metadata selected the current logical `/Limits`-ordered destination.

## Implementation

- `PdfActionReviewExtractor` now sorts destination name-tree `/Kids` by effective child `/Limits` before collecting destination map rows.
- The sort preserves source order for equal lower bounds and falls back to original physical order when any child is not a fully bounded indirect dictionary, matching the accepted standalone destination extractor behavior.
- `PdfNamedDestinationKidLimitsActionBoundaryCurrentBaseTest.php` proves standalone destinations, document metadata, link annotation extraction, span promotion, and visible text isolation agree on the logical current destination.
- `wordpress-pdf-named-destination-kid-limits-action-currentbase.php` emits a WordPress smoke summary showing `promoted_link_pages=[1,null]`, `promoted_link_modes=["XYZ",null]`, and stale duplicate exclusion without Python, models, or external PDF tools.

## Evidence

Red-first focused run before source edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidLimitsActionBoundaryCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 2 failures`; the link/action path resolved `DuplicateReview` to stale page `0`.

Focused behavior after source edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidLimitsActionBoundaryCurrentBaseTest.php`

Result: `1 test files, 28 assertions, 0 failures`.

Adjacent destination/link/action family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfLinkAnnotationExtractor|PdfLinkAnnotationNameTreeLimits|PdfAnnotationExtractorTest).*Test\.php$' | sort)`

Result: `39 test files, 1236 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-kid-limits-action-currentbase.php`

Result: emitted `promoted_link_pages=[1,null]`, `promoted_link_modes=["XYZ",null]`, `stale_physical_child_hidden=true`, `visible_text_excludes_destination_metadata=true`, `visible_text_excludes_uri_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named-destination `/Limits` pruning, malformed child-limit fallback, kid `/Limits` ordering for standalone/document metadata, duplicate-key replacement after valid normalization, action-dictionary rejection, alias cycles, PDFDocEncoding byte comparisons, page operands, coordinate validation, object-stream/xref repair, outline destination action context, PageLabels, annotations generally, forms, security, image/filter, font/CMap, or supplied table/equation behavior. The bounded behavior is only annotation/link action-map ordering of catalog destination name-tree children by effective `/Limits`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, page-tree indexer, name-tree limit parser, destination map, action review extractor, link annotation promotion, Markdown post-processing, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms/security, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
