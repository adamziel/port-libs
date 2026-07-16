# Outline Metadata Comment Boundary Current Base

- Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T024026Z`.
- Base accepted HEAD: `e6f1e5608047d4cad7cbaa5023f70e18fa90d5e2`.
- Source truth: PDF comments are lexical whitespace outside literal strings; this extends the already-mapped native parser comment-token boundary into `PdfMetadataExtractor`'s document outline metadata reader.
- Non-overlap: this does not repeat the accepted outline root/title/last/prev/parent/xref-owner/trailer-root slices or the parser-level `PdfParserCommentArrayDictStringTokenCurrentBaseTest`. This patch targets the separate metadata-reader path used for `/Outlines` review rows.

## Behavior

`PdfMetadataExtractor` now skips PDF comments while reading document outline metadata operands:

- `/First` and `/Last` outline root references.
- `/Title` direct and indirect string operands.
- `/Dest` direct arrays and indirect arrays.
- `/Next`, `/C`, and `/F` item metadata operands.
- Nested dictionaries and arrays while scanning dictionaries so comment-only delimiters cannot close the current dictionary or array.

The focused fixture includes stale comment-only title, destination, sibling, color, style, and indirect destination operands. The current outline titles, resolved destinations, bold/color metadata, and declared `/Last` boundary are preserved, while stale comment-only text remains excluded from document metadata, TOC/navigation review, and visible WordPress paragraphs.

## Evidence

Red-first before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php`

Result: `1 test files, 15 assertions, 1 failures`; the metadata reader returned `first_item_object = null` because the comment after `/First` hid the real `6 0 R` reference.

Focused after patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php`

Result: `1 test files, 53 assertions, 0 failures`.

Adjacent outline/comment regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php`

Result: `9 test files, 418 assertions, 0 failures`.

Core metadata/outline regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataTrailerInfoNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php`

Result: `6 test files, 1384 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-comment-boundary-currentbase.php`

Expected output emits the `markerpdf-outline-metadata-comment-boundary-currentbase` HTML comment with current outline titles, `resolved_destination_count=2`, comment-only operands excluded, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is required. The slice reuses the existing native PHP PDF object, dictionary, array, string, and outline metadata readers. GPU/OCR/model execution, Surya/Texify/Torch, Streamlit/FastAPI workers, external PDF tools, and live service providers remain intentionally out of scope for this no-GPU markerPDF lane.
