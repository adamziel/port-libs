# Type3 CharProcs Boundary Current Base - 2026-06-03

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260603T090446Z`

Source-truth behavior:

- Upstream markerPDF delegates searchable PDF text extraction to PDFium/pdftext page text APIs, so Type3 `/CharProcs` glyph streams are font programs, not standalone document pages.
- Native PHP fallback previously used all decoded non-image streams when a lightweight fixture had no page tree. That fallback could leak Type3 glyph-program text before the actual content stream.

Implementation:

- `PdfTextExtractor::allDecodedStreams()` now builds an exact object-generation set for Type3 `/CharProcs` stream references and excludes those streams from stream-only fallback text extraction.
- Page-tree extraction remains unchanged: authoritative `/Page /Contents` streams still define page text, while Type3 CharProc streams continue to supply `d0`/`d1` width metrics for text grouping.

Focused evidence:

- Red-first before source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php`
  failed with actual lines `['GHOST', 'ABCD']`.
- Passing after source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php`
  passed: `1 test files, 5 assertions, 0 failures`.

WordPress scenario:

- `examples/wordpress-pdf-type3-charprocs-fallback-boundary-currentbase.php` emits only the real paragraph `ABCD` and a review comment proving CharProc payload text stayed out of visible import text.

Dependency closure:

- No new support component is needed. This reuses the existing native PDF object parser, stream decoder, Type3 font metric path, and focused PHP runner.
- No Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser service, or external PDF tool was run.

Next task:

- Continue native searchable-PDF boundary work, preferably around parser/font/image/security edges that can be verified with focused PHP fixtures and no model execution.
