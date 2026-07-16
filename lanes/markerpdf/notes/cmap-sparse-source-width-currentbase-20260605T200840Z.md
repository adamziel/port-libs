# markerPDF CMap sparse source-width current base

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T200840Z`

Session: `port-dev-markerpdf-source-width-20260605T200840Z`

Base accepted HEAD: `b04f57c7230c881432b7183ac804ada5839368dd`

## Source truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) delegates searchable PDF text extraction to `pdftext.extraction.dictionary_output(...)` and pypdfium/PDFium page text before Markdown assembly. Under the no-GPU markerPDF lane scope, this native PHP slice owns the parser/font boundary that decides text spans, bboxes, and word gaps before WordPress paragraph import.

Relevant PDF behavior: CMap codespace ranges may define sparse multi-byte source codes. A CID range over `<000000> <FF0000>` advances through valid source codes such as `<000000>`, `<010000>`, ..., `<200000>`; it must not count every integer byte sequence between those values when deriving the CID used for `/W` source-width metrics.

## Implementation

`PdfTextExtractor::codeSpaceSequenceOffsetInCidRange()` now computes an analytic rank for single sparse codespace ranges before falling back to the existing bounded scan. This preserves previous bounded behavior for complex multiple-range cases while allowing later sparse source codes to resolve to their CIDFont `/W` metrics without long scans or false WordPress word gaps.

The focused fixture maps visible text through explicit ToUnicode rows and maps source widths through an Encoding CMap CID range. The second run uses `<200000>...<230000>`, which is past the old scan cap but only 32 valid codes into the sparse codespace sequence.

## Evidence

Red-first focused run after adding the test and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

The run reached the new sparse case after 31 PASS rows and was killed after more than 60 seconds because the old scanner walked non-code integer byte sequences before resolving the later sparse source codes.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files, 321 assertions, 0 failures`.

Related CMap/font-width family gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMap*.php lanes/markerpdf/tests/PdfFont*CMap*.php lanes/markerpdf/tests/PdfFont*Width*.php`

Result: `29 test files, 983 assertions, 0 failures`.

Syntax and diff hygiene:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-sparse-source-width-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => clean.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-sparse-source-width-currentbase.php`

The smoke emits `sparse_codespace_source_widths_resolved=true`, `visible_text_imported=true`, `false_word_gap_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, and all Python/model/external-tool execution flags false.

## Non-overlap

This does not repeat accepted zero-padded source keys, Identity-H/UCS2 fallback, default width fallback, partial metric misses, TJ adjustment gaps, vertical W2 fallback, odd hex padding, UseCMap ordering, notdef source rows, large contiguous CID ranges, large ToUnicode bfrange rows, or small code-space sequence tests. The bounded behavior is specifically sparse multi-byte codespace CID sequence offset calculation past the previous scan cap for source-width metrics.

## Dependency Closure

No new support component is needed. This reuses the native CMap parser, CIDFont width metrics, text token extraction, styled-span geometry, WordPress smoke rendering, and PHP test runner. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope and were not executed.
