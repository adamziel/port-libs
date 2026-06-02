# markerPDF Parser Name Array Comment Boundary Current Base

Micro-slice: `parser-name-array-comment-boundary-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output()` and through `naive_get_text()` into pypdfium page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The native PHP parser owns the reduced PDF token boundary before WordPress paragraphs. PDF comments begin with `%` and end at a line break; references and names inside those comments must not participate in optional-content visibility arrays.

## Implementation

- `PdfTextExtractor::objectReferences()` now scans token-aware instead of using a whole-string regex.
- The scanner skips PDF comments, literal strings, hex strings, dictionary delimiters, and name tokens before accepting `n n R` indirect object references.
- The focused fixture keeps the existing escaped optional-content resource-name boundary, then adds `% 20 0 R /Hidden#4Cayer ...` inside `/ON[...]`. The visible OCG remains real; the hidden OCG reference is comment-only and must not be enabled.

## Red/Green Evidence

Before the source change, an in-memory probe emitted:

```text
Hidden comment-array leak
Visible comment-array layer
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores commented object references and names inside optional-content arrays before WordPress text extraction

1 test files, 11 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameEscapeArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
11 test files, 1171 assertions, 0 failures
```

Syntax, smoke, and hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-name-array-comment-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-parser-name-array-comment-boundary-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

The smoke emitted `commented_hidden_reference_ignored=true`, `visible_reference_preserved=true`, `comment_name_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with one Gutenberg paragraph: `Visible comment-array layer`.

## Status Delta

- Behavior tests: `781 -> 782` pass / `0` fail.
- Mapped parser semantics: `555 -> 556 / 78`.
- WordPress scenario: comment-only hidden OCG references inside optional-content arrays no longer leak hidden PDF layer text into Gutenberg paragraphs.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, optional-content state parser, content-stream tokenizer, name decoder, stream boundary handling, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted escaped stream dictionary keys, indirect `/Filter` name arrays, stream-filter fail-closed boundaries, inline-image filter-array abbreviations, optional-content visibility basics, stream token owner boundaries, Type0/font resource name escape coverage, or the accepted raw-slash PDF name token split. The new behavior is specifically comment-aware indirect-reference scanning inside PDF arrays before optional-content visibility state is applied.
