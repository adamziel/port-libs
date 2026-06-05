# markerPDF outline metadata typed-item boundary current base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T193321Z`
Base: `6ff88ec34ca05033c964fe86bcd7b8e0e8bce591`

## Source-truth behavior

Pinned upstream markerPDF receives outline/TOC rows from PDFium/pdftext before
model execution and treats them as navigation metadata, not body text. A PDF
outline sibling chain should contain outline item dictionaries; if a linked row
is a typed non-outline object such as `/Type /Annot`, its `/Title`, `/Next`, and
action keys are not a trustworthy bookmark boundary. WordPress imports should
fail closed before those spoof rows reach document metadata, TOC/navigation
review, remote action review, or lightweight `pdf_toc` metadata.

## Patch

- `PdfOutlineExtractor` rejects known typed non-outline dictionaries while
  walking outline siblings for TOC, destination-view rows, composite
  navigation, action review, and remote GoTo review.
- `PdfMetadataExtractor` applies the same typed-item guard while building
  document-level `document_outline` metadata.
- `PdfTextExtractor` applies the same guard to lightweight
  `extractOutlineMetadata()` / `pdf_toc` traversal.
- Added `PdfOutlineMetadataItemTypeBoundaryCurrentBaseTest.php` with typed
  `/Annot` spoof coverage across document metadata, rich TOC/navigation,
  remote action review, lightweight `pdf_toc`, and visible-text isolation.
- Added WordPress smoke
  `wordpress-pdf-outline-item-type-boundary-currentbase.php`.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataItemTypeBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 9 assertions, 2 failures`; the
typed annotation spoof and the tail reachable through its `/Next` were admitted
as outline rows.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataItemTypeBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 40 assertions, 0 failures`.

Adjacent outline metadata gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataItemTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadata*BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineTitleEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php`

Result: `31 test files, 1442 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-item-type-boundary-currentbase.php`

Result: emits `typed_spoof_excluded=true`,
`tail_after_typed_spoof_excluded=true`, `stale_remote_actions_excluded=true`,
`visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`,
and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted outline direct-root, `/Last`, `/Prev`, parent,
missing-parent, root type, root count, zero-count child, titleless bridge,
indirect title scalar, malformed UTF-16 title, generation, xref owner,
trailer-root, destination view, named-destination/action, page-label,
transition/thread, or remote action slices. The bounded behavior is only typed
non-outline objects linked inside an otherwise valid outline sibling chain.

## Dependency closure

No new support component is needed. The patch reuses native PHP PDF
object/dictionary parsing and no-GPU metadata/navigation review paths. GPU/OCR,
Surya/Texify/model execution, and external PDF tools remain intentionally out
of scope for this markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around
fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page
geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
