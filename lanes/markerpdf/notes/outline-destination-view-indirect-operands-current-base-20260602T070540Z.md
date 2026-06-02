# Outline Destination View Indirect Operands

Slice: `markerpdf-outline-destination-view-edge-current-base-20260602T070540Z`

Source truth:
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark extraction to `doc.get_toc(max_depth=...)` and preserves each resolved TOC item's title, level, and page index. The native PHP outline parser therefore needs to resolve PDF destination object references before emitting WordPress review metadata.
- This slice keeps the accepted destination-view behavior and adds the edge where explicit destination arrays store the view mode or coordinate operands as indirect objects.

Implementation:
- `PdfOutlineExtractor::explicitDestinationDetails()` now resolves indirect view-mode and coordinate operands before computing `view_mode`, `view_position`, and named `view_parameters`.
- The existing WordPress destination-view smoke now uses an indirect `/FitH` mode and top coordinate and emits `indirect_view_operands_resolved=true`.

Red-first evidence:
- Before the parser change, a focused synthetic outline destination `[3 0 R 8 0 R 9 0 R 10 0 R 11 0 R]` with object `8 0 obj /XYZ` and numeric coordinate objects returned `view_mode=null`, all-null `view_position`, and empty parameters.

Verification:
- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-destination-view-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: 1 test file, 124 assertions, 0 failures. Previous focused count was 110 assertions.
- `php lanes/markerpdf/examples/wordpress-pdf-destination-view-import.php` passed and emitted `indirect_view_operands_resolved=true` with a `FitH` row at `[700]`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: 58 test files, 2484 assertions, 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:
- MarkerPDF behavior tests move 422 -> 423.
- Mapped source/dependency semantics move 275 -> 276 / 78 with `pdfIndirectDestinationViewOperandBehaviors`.

Dependency closure:
- No new support component is needed. The slice reuses the native PDF object parser, name-tree/destination resolver, and outline destination-view metadata path without Python, pdftext, pypdfium, Poppler, Ghostscript, Streamlit, PIL, or model downloads.

Non-overlap:
- This does not repeat the latest accepted rotated text-markup QuadPoints mapping, XMP/Info encoding fallback, stream-filter name-array handling, cyclic page-tree resource guards, indirect name-tree destination parsing, or base destination-view `/Fit`/`/XYZ` coverage. It only resolves indirect objects inside the already accepted destination view array operands.

Next task:
- Continue markerPDF parser edge work around non-overlapping PDF outline/action, xref/object stream, annotation, form, font, image, and metadata review boundaries that can ship with focused PHP evidence.
