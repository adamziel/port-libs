# markerPDF outline object-value boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T205152Z`
Base: `1b3fe88990b65afe2a6b8fd66611ed6fc3887e29`

## Source-truth behavior

Pinned upstream markerPDF consumes PDF outline data from native/PDFium PDF structures before any model work and keeps that data as navigation/review metadata rather than body text. A referenced outline root or item object must be one PDF dictionary object value. If an object has a valid outline dictionary followed by another top-level token, that tail token is outside the object's trusted outline value and must not seed stale `/Next`, `/A`, title, destination, document metadata, lightweight `pdf_toc`, navigation rows, action review, or visible WordPress text.

## Patch

- `PdfMetadataExtractor` now requires referenced outline root and item objects to contain exactly one top-level PDF value before document outline metadata traversal.
- `PdfTextExtractor` now applies the same single-value object boundary to lightweight `extractOutlineMetadata()` `pdf_toc` traversal while preserving existing nested direct action dictionaries inside valid outline item dictionaries.
- Added `PdfOutlineMetadataObjectValueBoundaryCurrentBaseTest.php` covering document metadata, TOC/navigation review, lightweight metadata, and visible text isolation.
- Added WordPress smoke `wordpress-pdf-outline-object-value-boundary-currentbase.php`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataObjectValueBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 16 assertions, 2 failures`; document metadata counted the stale outline tail object and lightweight `pdf_toc` imported `Stale Tail Outline Appendix`.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataObjectValueBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 34 assertions, 0 failures`.

Adjacent outline metadata/navigation gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php`

Result: `47 test files, 2120 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-object-value-boundary-currentbase.php`

Result: emits `stale_outline_object_value_excluded=true`, `stale_action_payload_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline `/Last`, `/Prev`, parent, direct-root, indirect-title scalar, typed item/root, root stream, object-stream, root count, zero-child, generation, trailer-root, xref-owner, metadata stream/reference/operand, color, duplicate-key, or malformed title slices. The bounded behavior is only the object-value boundary for referenced outline root/item objects with extra top-level tokens after the dictionary.

## Dependency closure

No new support component is needed. The patch reuses native PHP PDF object, dictionary, and value parsing. GPU/OCR/Surya/Texify/Torch/model execution and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF lane.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
