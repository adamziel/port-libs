# markerPDF outline metadata indirect root type boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T204803Z`
Base accepted HEAD: `627856fecb6c375f49d0287135d6ea760a6f7f42`

## Source-truth behavior

Pinned upstream markerPDF receives PDF bookmarks from the PDF parser/PDFium
boundary as TOC/navigation metadata and keeps that metadata separate from page
text. PDF catalog `/Outlines` roots may declare `/Type` as the name
`/Outlines`; when that name is stored in an indirect object, the native PHP
metadata path should resolve it before deciding whether the root is a valid
outline hierarchy. Explicit non-outline typed roots such as indirect `/Page`
must continue to fail closed before document metadata, TOC/navigation review,
remote action review, or lightweight `pdf_toc` rows are emitted.

## Patch

- `PdfMetadataExtractor` now resolves indirect `/Type` name operands while
  validating catalog `/Outlines` roots for `document_outline` metadata.
- `PdfTextExtractor` now applies the same indirect root `/Type` resolution to
  lightweight `extractOutlineMetadata()` / `pdf_toc` traversal.
- Added `PdfOutlineMetadataIndirectRootTypeBoundaryCurrentBaseTest.php` with
  valid indirect `/Type /Outlines` root coverage and an indirect `/Type /Page`
  spoof regression guard.
- Added WordPress smoke
  `wordpress-pdf-outline-indirect-root-type-boundary-currentbase.php`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectRootTypeBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 22 assertions, 1 failures`.
The valid indirect `/Type /Outlines` root was missing from
`document_outline`.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectRootTypeBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 41 assertions, 0 failures`.

Focused outline metadata regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadata*CurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php`

Result: `33 test files, 1286 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-indirect-root-type-boundary-currentbase.php`

Result: emits `imported_item_count=2`, two outline/navigation titles,
`visible_text_excludes_outline_metadata=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted direct root-type rejection, typed item rejection,
`/Last`, `/Prev`, parent/missing-parent, zero-count, root-count, titleless,
malformed title, destination view, destination alias, page label, object stream,
xref owner, trailer-root, or action-chain outline slices. The bounded behavior
is valid indirect `/Type /Outlines` root acceptance while preserving indirect
non-outline root rejection.

## Dependency closure

No new support component is needed. This reuses native PHP PDF object,
dictionary, name, outline, metadata, and lightweight `pdf_toc` parsing. GPU,
OCR, Surya/Texify/Torch, pypdfium/PDFium execution, and external PDF tools
remain intentionally out of scope under the current markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations,
forms, page geometry, image/filter metadata, and supplied-boundary table or
equation handoffs.
