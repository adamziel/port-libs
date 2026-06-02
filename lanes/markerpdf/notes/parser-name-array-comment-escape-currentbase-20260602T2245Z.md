# markerPDF parser name array comment escape current base

Micro-slice: `parser-name-array-comment-escape-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::naive_get_text()` with pypdfium and through `get_text_blocks()` with `pdftext.extraction.dictionary_output(...)`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF syntax allows `#hh` escapes in name objects, `%` comments through end-of-line, and array objects as dictionary values. Xref-stream dictionaries use `/W`, `/Index`, and `/Size` to decode rows. The native PHP fallback owns this reduced parser boundary before WordPress paragraph extraction.

## Behavior

`PdfTextExtractor` now reads xref-stream `/W`, `/Index`, and `/Size` through the existing decoded-name and comment-aware PDF value scanner instead of regexes. Numeric xref arrays are tokenized with `pdfArrayItems()` so numbers inside `%` comments do not become width or range operands.

The focused fixture uses a current startxref xref stream whose dictionary spells:

- `/Si#7ae` for `/Size`;
- `/In#64ex [ % 10 3 is comment-only stale direct range\n 1 5 ]`;
- `/#57 [ 1 4 % 9 9 is comment-only malformed width\n 1 ]`.

After that current xref section, the fixture appends stale direct catalog/page/content objects without a newer `startxref`. Current xref parsing must keep `Current escaped xref array page` and `Comment-safe Index wins`, while excluding `Stale unreferenced direct page`.

## Evidence

Red-first focused run before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameArrayCommentEscapeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses escaped xref-stream name arrays with comments before stale current-base direct objects
Expected: array (
  0 => 'Current escaped xref array page',
  1 => 'Comment-safe Index wins',
)
Actual: array (
  0 => 'Stale unreferenced direct page',
  1 => 'Escaped array parser leak',
)
```

Final focused parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameArrayCommentEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
PASS skips PDF comments across arrays dictionaries and indirect string tokens before current-base text extraction
PASS ignores commented object references and names inside optional-content arrays before WordPress text extraction
PASS uses escaped xref-stream name arrays with comments before stale current-base direct objects
PASS splits adjacent escaped PDF names at array boundaries before optional-content text extraction
PASS skips comment-only trailer dictionaries and decodes escaped xref trailer names before WordPress text extraction
PASS repairs underdeclared xref stream Size through hybrid Prev chains before WordPress text extraction
PASS keeps current xref-stream generation Index rows before stale Prev duplicates in metadata imports
PASS preserves Prev type-2 rows when current sparse Index carrier row keeps the same offset despite generation noise
PASS keeps first current xref stream Index row before duplicate stale Prev row

9 test files, 89 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserNameArrayCommentEscapeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-name-array-comment-escape-currentbase.php
```

All reported no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-name-array-comment-escape-currentbase.php
```

The smoke emitted `escaped_index_name_resolved=true`, `escaped_w_name_resolved=true`, `array_comment_numbers_ignored=true`, `stale_currentbase_direct_catalog_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with Gutenberg paragraphs `Current escaped xref array page` and `Comment-safe Index wins`.

## Status Delta

- Behavior tests: `930 -> 931` pass / `0` fail.
- Mapped current-base semantics: `654 -> 655 / 78`.
- WordPress scenarios: `930 -> 931`.

## Non-Overlap

This does not repeat accepted optional-content comment-array reference skipping, escaped resource name splitting, trailer `/Root` and `/Prev` escaped-name parsing, xref-stream `/Prev` Index duplicate handling, no-`/Index` `/Size` row-count repair, object-stream carrier generation ownership, xref stream Filter/Length operand ownership, or stream-owned xref keyword rejection.

The new behavior is specifically xref-stream row decoding when `/W`, `/Index`, and `/Size` keys are escaped PDF names and their arrays contain comment-only numeric decoys before stale later direct objects.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, startxref xref-stream decoder, decoded PDF name scanner, comment-aware array tokenizer, page-tree walker, stream decoder, content-token text extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
