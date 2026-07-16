# Form XObject Matrix And BBox Current Base

## Scope

Implemented a bounded native `PdfTextExtractor` slice for invoked Form XObjects:

- tracks the caller current transformation matrix through `q`, `Q`, and `cm` while expanding `/Do`;
- composes that matrix with the form dictionary `/Matrix`;
- rewrites form text positioning operators into page-space `Tm` operands that the existing native text extractor can consume;
- clips text-showing operators, including text at the implicit `BT` origin, whose form-local text point is outside the form `/BBox`;
- preserves the accepted recursive form `/Resources` font aliasing and cyclic form re-entry guard.

## Source Truth

markerPDF upstream commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `marker/pdf/extract_text.py::naive_get_text` through `pypdfium2` text pages and `pdftext.extraction::dictionary_output`. The pinned pdftext source delegates character coordinates to PDFium (`pdftext/pdf/chars.py::get_pdfium_chars`), so rendered text observes PDF graphics-state matrices, Form XObject `/Matrix`, and form clipping before markerPDF converts lines/spans for import.

This native slice ports only that bounded PDF graphics/form boundary. It does not execute Python, pypdfium, pdftext, model workers, or external PDF tools.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xobject-form-matrix-bbox-import.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed on current integration base `0bcfdd12c` with `1 test files, 440 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-xobject-form-matrix-bbox-import.php` emitted `form_matrix_spacing_preserved=true`, `form_bbox_clipped_hidden_text=true`, and Gutenberg paragraphs for `Page Before Matrix Form`, `Data base`, and `Page After Matrix Form`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed on current integration base `0bcfdd12c` with `59 test files, 2596 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` passed.

## Counters

- `phpPass`: `433 -> 434`
- mapped focused semantics: `286 -> 287 / 78`

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, content tokenizer, stream decoder, Form XObject expander, resource-font aliasing, and existing text positioning logic. Full upstream runner parity remains dependency-gated on Poetry plus pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch, Nougat comparison tooling, and model downloads.

## Non-Overlap

This does not repeat the accepted Form XObject invocation, nested resource scoping, optional-content Form XObject visibility, page-box/UserUnit preview, CIDFont width, object-stream, or Filespec payload-boundary slices. It adds only the current graphics-state matrix, form `/Matrix`, and form-local `/BBox` behavior for invoked form text.
