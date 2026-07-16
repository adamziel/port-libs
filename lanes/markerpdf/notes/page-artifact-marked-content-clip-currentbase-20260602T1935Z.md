# Page Artifact Marked-Content Clip Current Base

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py` and pdftext/PDFium before Marker block cleanup. The native PHP port therefore needs to honor PDF graphics-state text visibility boundaries before Gutenberg paragraph import. Relevant PDF parser behavior for this slice: `re` appends a rectangular path, `W` / `W*` intersect the current clipping path, `n` clears the path, and `q` / `Q` save and restore clipping state. Marked-content `/Artifact` with `/ActualText` or `/Alt` should not bypass clipping.

## Change

- `PdfTextExtractor` now tracks rectangular clip paths from `re` plus `W` / `W*` through `q` / `Q`.
- The active clip is applied consistently to text runs, text lines, and styled-span extraction.
- Clipped text marks active marked-content replacements as emitted, so clipped `/Artifact /ActualText` and glyph text do not leak later at `EMC`.
- Added `PdfPageArtifactMarkedContentClipCurrentBaseTest.php` and `wordpress-pdf-page-artifact-marked-content-clip-currentbase.php`.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php` failed before implementation with clipped header replacement and clipped footer text in the actual output.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-artifact-marked-content-clip-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php` passed with `1 test files, 10 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php` passed with `2 test files, 607 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-page-artifact-marked-content-clip-currentbase.php` passed and emitted only `Body inside clipped region`, `Visible Artifact Caption`, and `Tail outside clip`.

## Status Delta

- Behavior tests: `718 -> 719` pass / `0` fail.
- Mapped semantics: `516 -> 517 / 78`.
- WordPress scenarios: `718 -> 719`.

## Non-Overlap

This does not repeat accepted text rendering mode `Tr` visibility, Form XObject `/BBox` clipping, annotation appearance `/BBox` clipping, optional-content group visibility, marked-content `/ActualText` replacement alone, page graphics-state `cm` positioning, or StructTree MCID reading order. The new behavior is direct page rectangular clipping path application before marked-content replacement can emit visible WordPress text.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, content tokenizer, text state tracking, marked-content replacement handling, and graphics-state stack. Full upstream parity remains gated on live pdftext/PDFium, pypdfium2, Surya/Texify/Torch model dependencies, OCR/PIL/rendering tools, and marker runtime services.
