# markerPDF Annotation Appearance Resource Boundaries

Slice: `annotation-geometry-appearance-resource-boundaries-20260602T080321Z`

Base accepted HEAD: `60eb156a5f6e6a58dc5d1860263a85a4c543e8e3`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::naive_get_text` and `pdftext.extraction::dictionary_output`, so rendered annotation appearance text is supplied by the PDF renderer/pdftext boundary rather than by scanning arbitrary streams.
- The same upstream conversion keeps page/span `bbox` values from pdftext in `marker/pdf/extract_text.py::pdftext_format_to_blocks`; PDF appearance streams are Form XObjects with their own `/BBox`, `/Matrix`, and `/Resources`, so native text import must honor that graphics/resource boundary before WordPress paragraphs are emitted.
- PDF annotation geometry remains review metadata in this lane. This slice imports only current `/AP /N` appearance text selected from page-referenced annotations while keeping stale/off/unreferenced appearance streams and out-of-BBox text out of visible content.

## Implementation

- `PdfTextExtractor` now passes the selected annotation appearance Form XObject's own `/Matrix` and `/BBox` into the existing Form XObject expansion path.
- Top-level annotation appearance text is clipped through the appearance `/BBox`, matching the already accepted invoked Form XObject clipping behavior.
- Appearance-local and nested appearance Form XObject `/Resources /Font` entries continue to be aliased independently, so page `/F1`, appearance `/F1`, and nested appearance `/F1` can decode different text safely.

## Red-First Evidence

- Before the fix, the new focused fixture failed because `BBox Noise` from outside the annotation appearance `/BBox` leaked into `extractTextLines()`.
- After the fix, `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with `1 test files, 446 assertions, 0 failures`.

## WordPress Smoke

- Updated `examples/wordpress-pdf-annotation-appearance-import.php`.
- The smoke emits `Page Import Body`, `Approved by Editor`, `Visible Review Note`, and `Nested Appearance Resource`.
- Smoke metadata confirms `current_appearance_imported=true`, `direct_normal_appearance_imported=true`, `nested_appearance_resource_imported=true`, `appearance_bbox_clipped_noise=true`, and `stale_appearances_excluded=true`, with no Python/models or external PDF tools.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page annotation traversal, stream decoder, Form XObject expander, CMap/font-resource maps, content tokenizer, matrix handling, and BBox clipping path. Full upstream markerPDF Python/model/pdftext/pypdfium benchmark parity remains dependency-gated.

## Non-Overlap

This does not repeat accepted page annotation geometry review metadata, text-markup QuadPoint rotation, page-invoked Form XObject matrix/BBox behavior, nested page/form resource scoping, optional-content annotation filtering, or annotation border/popup metadata. It is limited to current annotation appearance Form XObjects applying their own appearance resource, matrix, and BBox boundary before WordPress paragraph extraction.
