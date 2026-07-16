# markerPDF parser trailer xref name comment current base

Micro-slice: `parser-trailer-xref-name-comment-currentbase`

Source truth: upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF page text through `marker/pdf/extract_text.py::naive_get_text()` using pypdfium/PDFium page text and through `get_text_blocks()` using `pdftext.extraction.dictionary_output(...)`. The PHP fallback therefore owns this parser boundary before WordPress paragraph extraction: PDF comments start with `%`, end at the line break, and must not contribute `xref` table trailers or trailer names. PDF names can use `#hh` escapes, so current trailer `/Ro#6ft` and `/Pre#76` keys must be decoded like `/Root` and `/Prev`.

## Behavior

`PdfTextExtractor` now locates xref-table `xref` and `trailer` keywords with a token-aware scanner that skips PDF comments, literal strings, and hex strings. `xrefTableSectionAt()` then requires a real `trailer` keyword followed by a dictionary and continues to use the existing name-decoding trailer value readers.

The focused fixture creates an incremental PDF where:

- the previous xref table points `/Root 1 0 R` at a stale page;
- the current xref table rows point at objects `10..14`;
- a comment line before the real trailer contains `% trailer << /Root 1 0 R /Prev ... /Stale#52oot >>`;
- the real current trailer uses escaped `/Ro#6ft 10 0 R` and `/Pre#76 ...`.

The native extraction must emit only `Current escaped trailer page` and `Token trailer wins`, and must exclude the stale comment-root page.

## Evidence

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

`php -l lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php`

No syntax errors detected in lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php

`php -l lanes/markerpdf/examples/wordpress-pdf-parser-trailer-xref-name-comment-currentbase.php`

No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-trailer-xref-name-comment-currentbase.php

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php`

Focused test run: 1 selected test files (root lock skipped)
PASS skips comment-only trailer dictionaries and decodes escaped xref trailer names before WordPress text extraction

1 test files, 10 assertions, 0 failures

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserTrailerXrefNameCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php`

Focused test run: 5 selected test files (root lock skipped)
PASS skips PDF comments across arrays dictionaries and indirect string tokens before current-base text extraction
PASS ignores commented object references and names inside optional-content arrays before WordPress text extraction
PASS uses latest trailer Encrypt null and ID before stale encrypted Prev trailers
PASS skips comment-only trailer dictionaries and decodes escaped xref trailer names before WordPress text extraction
PASS repairs underdeclared xref stream Size through hybrid Prev chains before WordPress text extraction

5 test files, 67 assertions, 0 failures

`php lanes/markerpdf/examples/wordpress-pdf-parser-trailer-xref-name-comment-currentbase.php`

Emitted `markerpdf-parser-trailer-xref-name-comment-currentbase` with `escaped_root_name_resolved=true`, `escaped_prev_name_resolved=true`, `comment_trailer_dictionary_ignored=true`, `stale_root_page_excluded=true`, and no Python/models/external PDF tools.

`jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`

Passed.

`git diff --check -- lanes/markerpdf`

Passed.

## Status Delta

- PHP behavior tests: `825 -> 826` pass / `0` fail.
- Mapped semantics: `579 -> 580 / 78`.
- WordPress scenarios: `825 -> 826`.

## Non-Overlap

This does not repeat accepted parser-name array comment boundaries, comment-aware array/dictionary/string tokens, escaped stream dictionary keys, trailer Encrypt/ID precedence, trailer `/Root` generation recovery, xref-stream trailer metadata, hybrid `/Prev` size/generation repair, xref offset owner boundaries, object-stream filter repair, or stream-owned xref object header rejection. The bounded behavior is specifically xref-table `trailer` keyword ownership plus escaped trailer `/Root` and `/Prev` names when a fake trailer dictionary appears inside a PDF comment.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, xref table parser, trailer-chain resolver, name decoder, page-tree walker, stream decoder, and content-token text extractor. Full upstream markerPDF runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
