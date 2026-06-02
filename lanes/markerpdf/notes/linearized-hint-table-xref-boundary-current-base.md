# markerPDF Linearized Hint Table Xref Boundary

Slice: `markerpdf-linearized-hint-table-xref-boundary-current-base-20260602T0625Z`

## Source Truth

Upstream markerPDF delegates page text extraction to `marker/pdf/extract_text.py` through pypdfium/pdftext page text APIs, so PDF linearization hint tables are parser metadata and must not become visible document text. A local `qpdf --linearize` oracle confirmed the standard layout: the first object carries `/Linearized` and `/H [ offset length ]`, the hinted stream object is listed in the first-page xref section, and the terminal xref chain remains the source of current object reachability.

## Implementation

`PdfTextExtractor` now reads the first direct object's `/Linearized` dictionary, parses direct `/H` offset/length pairs, removes matching hint-table direct objects from the native object map, and skips streams whose dictionary/payload offsets fall inside those byte ranges during raw stream fallback.

This is intentionally narrower than changing all unlisted-object handling. Existing stream-only fallback fixtures and accepted startxref/object-stream recovery behavior remain intact.

## WordPress Path

`examples/wordpress-pdf-linearized-hint-table-import.php` models a damaged linearized upload that falls back to raw stream extraction. It emits only `Linearized current fallback` and `Hint table boundary` Gutenberg paragraphs while proving the text-looking hint stream is excluded without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF tokenizer/object/xref parser plus a local `qpdf` inspection only as source-truth evidence; `qpdf` is not required by the PHP implementation or tests.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-linearized-hint-table-import.php` passed.
- `php lanes/markerpdf/examples/wordpress-pdf-linearized-hint-table-import.php` emitted `Linearized current fallback` and `Hint table boundary` with `excludes_hint_table_text=true`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed on integration base: 1 file, 333 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests` passed on integration base: 56 files, 2284 assertions, 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat the accepted startxref/object-stream rebuild, xref stream `/Index`/zero-width `/W`, hybrid `/Prev`, object-generation free-entry, indirect DecodeParms predictor, page-box/UserUnit, TJ comment-boundary, or image-filter slices. The new behavior is specifically the linearized `/H` hint-table byte-range exclusion before fallback text extraction.
