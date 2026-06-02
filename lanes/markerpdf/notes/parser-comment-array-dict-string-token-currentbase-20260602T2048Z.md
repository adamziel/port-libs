# markerpdf parser comment array dict string token current-base

Micro-slice: `parser-comment-array-dict-string-token-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py::naive_get_text()` and `get_text_blocks()`, delegating syntax recovery to `pypdfium2`/PDFium and `pdftext.dictionary_output` before marker block conversion.
- At the native PHP parser boundary, PDF comments start with `%` and end at a line break. Comments inside arrays and dictionaries are lexical whitespace; delimiters such as `]` and `>>` and fake `n n R` references inside comments must not affect current objects. Percent signs inside literal strings remain string content.

## Behavior

`PdfTextExtractor` now skips PDF comments while reading dictionary tokens, dictionary end offsets, array tokens, indirect value starts, and indirect object-reference pairs. It also treats comments as whitespace before direct/indirect string values. This preserves current optional-content state, outline destinations, and outline title strings when comment-only bytes contain fake dictionary closes, array closes, stale references, or fake string keys.

The focused fixture proves:

- a comment-only `>>` inside catalog `/OCProperties` does not close the dictionary early;
- a comment-only `]` and `99 0 R` inside an outline `/Dest` array do not select a stale page;
- an indirect outline title object beginning with a PDF comment still resolves to the following literal string;
- visible literal text containing `100%` remains visible WordPress content;
- hidden optional-content text remains excluded.

## Red-First Evidence

Before the parser fix, the focused probe returned:

```text
array (
  0 => 'Hidden dictionary comment leak',
  1 => 'Visible 100% literal layer',
)
'Hidden dictionary comment leak
Visible 100% literal layer'
```

After the fix, the same current-base fixture emits only `Visible 100% literal layer`, and outline metadata resolves `Visible 100% outline` to page `0`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips PDF comments across arrays dictionaries and indirect string tokens before current-base text extraction
1 test files, 12 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfParser*Test.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 317 assertions, 0 failures

php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-parser-comment-array-dict-string-token-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-comment-array-dict-string-token-currentbase.php

php lanes/markerpdf/examples/wordpress-pdf-parser-comment-array-dict-string-token-currentbase.php
Emitted markerpdf-parser-comment-array-dict-string-token-currentbase with dictionary_comment_close_ignored=true, array_comment_reference_ignored=true, indirect_string_comment_ignored=true, literal_percent_preserved=true, executes_python_or_models=false, and executes_external_pdf_tools=false.

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'
manifest json ok

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat the accepted `parser-name-array-comment-boundary` slice, which covered comment-only object references in optional-content arrays. This slice adds comment-aware dictionary close handling, array close handling, destination first-reference scanning, and indirect string-token starts, proven through outline metadata plus optional-content text extraction.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, value readers, optional-content parser, outline metadata extractor, content-stream tokenizer, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
