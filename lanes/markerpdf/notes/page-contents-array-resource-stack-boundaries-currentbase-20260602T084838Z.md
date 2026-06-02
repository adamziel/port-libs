## Page Contents Array Resource Stack Boundaries

Slice: `page-contents-array-resource-stack-boundaries-currentbase-20260602T084838Z`

Source truth:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/extract_text.py::naive_get_text`, extracts text page-by-page through PDFium text pages and appends one newline per page.
- Native PHP must therefore resolve each page's `/Contents` source before falling back to all decoded streams, and it must keep the page-local `/Resources` stack for reused font resource names while emulating the PDFium/pdftext boundary without Python, pdftext, pypdfium, or models.

Behavior implemented:

- `PdfTextExtractor` now resolves `/Contents` values that point to an indirect array object, including guarded nested array references, before decoding page streams.
- The page extraction path keeps page-local font resource maps for the resolved content streams instead of falling back to global stream scanning.
- Unrelated decoded streams are still excluded when catalog page content can be resolved.

Red-first evidence:

- Added `resolves indirect page Contents arrays while preserving page resource stacks`.
- Before the source fix, focused `PdfTextExtractorTest.php` failed: page one decoded through page two's `/F1` ToUnicode map and `Indirect Contents fallback leak` appeared in extracted text.

Verification:

- After the fix, `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with `1 test files, 457 assertions, 0 failures`.
- `examples/wordpress-pdf-page-contents-import.php` now emits `Indirect Array Page One`, `Indirect Array Page Two`, and `Shared Resource Still Active`, with smoke flags for indirect array resolution, resource-stack preservation, and unreferenced stream exclusion.

Dependency closure:

- No new support component is needed. This slice reuses the native PDF object parser, array/token readers, stream decoder, page tree traversal, ToUnicode font maps, and text extraction path. Full upstream benchmark/model parity remains gated on the existing Python/pdftext/pypdfium/Surya dependency stack.

Blocker:

- Full upstream runner parity is unchanged: live `benchmarks/overall.py`, pdftext dictionary extraction, pypdfium rendering/text pages, Streamlit/FastAPI runtime paths, and model downloads remain dependency-gated and were not executed for this isolated parser slice.

Next task:

- Continue with bounded native PDF import fidelity gaps around remaining page/resource/action/parser boundaries, especially cases that prevent fallback stream leakage or preserve page-local metadata before WordPress paragraph rendering.
