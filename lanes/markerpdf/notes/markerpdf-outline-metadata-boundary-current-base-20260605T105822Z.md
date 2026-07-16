# markerPDF outline metadata direct-root boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T105822Z`
Base: `ae7980d439439707292252a5e771e15fd3153fb9`

## Source-truth behavior

Pinned upstream markerPDF receives PDF outline rows through PDFium/pdftext before model execution and keeps them as review/navigation metadata, not body text. PDF outline roots may be catalog dictionaries; when the root has no indirect object, a root-level item with missing `/Parent` is still a valid lightweight import boundary, but a later sibling with an explicit unrelated `/Parent` is not owned by that direct root and must not enter document metadata, TOC/navigation rows, or remote-action review.

## Patch

- `PdfMetadataExtractor::documentOutlineItemParentMatches()` now treats a `null` expected parent as a direct-root boundary: missing `/Parent` is accepted, explicit `/Parent` is rejected.
- `PdfOutlineExtractor::outlineItemParentMatches()` applies the same direct-root parent boundary across TOC, destination-view, navigation, and remote-action traversal.
- `PdfTextExtractor::pdfTocFromObjects()` now accepts direct catalog `/Outlines` root dictionaries for lightweight outline metadata and applies the same explicit-parent boundary.
- Added `PdfOutlineMetadataDirectRootBoundaryCurrentBaseTest.php` covering lightweight outline metadata, document metadata, richer TOC/navigation rows, remote action exclusion, and visible text isolation.
- Added WordPress smoke `wordpress-pdf-outline-direct-root-boundary-currentbase.php`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 9 assertions, 2 failures`; document metadata counted 2 outline items and TOC/navigation admitted `Stale Direct Root Explicit Parent`.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataDirectRootBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 36 assertions, 0 failures`.

Adjacent outline metadata gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`

Result: `24 test files, 1129 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-direct-root-boundary-currentbase.php`

Result: emits `stale_explicit_parent_rejected=true`, `stale_remote_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline `/Last`, `/Prev`, parent object, missing parent under indirect roots, root type, root count, malformed UTF-16 title, titleless bridge, indirect title scalar, xref-owner, trailer-root, remote action, named-destination, or page-review outline slices. The bounded behavior is only direct catalog `/Outlines << ... >>` root dictionaries where no root object can own explicit `/Parent` references.

## Dependency closure

No new support component is needed. The patch reuses native PHP PDF object/dictionary parsing and no-GPU metadata/navigation review paths. GPU/OCR/Surya/Texify/model execution remains intentionally out of scope.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
