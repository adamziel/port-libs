# markerPDF Parser Stream Dictionary Escape Boundary

Micro-slice: `parser-stream-dictionary-escape-boundary-currentbase-20260602T1443Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output()` and through `naive_get_text()` into pypdfium page text. The upstream boundary is parsed PDF page text, not raw stream bytes or model-side repair.
- The locked `pdftext` 0.3.18 dependency is a thin pypdfium-backed text extraction layer; pypdfium/PDFium honors PDF token grammar before exposing page text. For the native PHP fallback parser, stream dictionary keys such as `/Length`, `/Filter`, and `/DecodeParms` must be read as dictionary names, not as text inside comments, literal strings, hex strings, arrays, or nested dictionaries.
- PDF names can use `#hh` escapes, so `/Fil#74er` and `/Len#67th` are equivalent to `/Filter` and `/Length`.

## Implementation

- `PdfTextExtractor` now has a token-aware PDF name lookup that skips comments, literal strings, hex strings, arrays, and nested dictionaries and decodes `#hh` name escapes.
- Stream dictionaries and DecodeParms now use strict top-level key lookup for `/Length`, `/Filter`, `/DecodeParms`, `/Width`, `/Height`, `/ColorSpace`, predictor keys, and related image/stream metadata.
- The generic `nameValueOffset()` remains recursive and token-aware so existing outline/action metadata such as nested `/A << /D [...] >>` destinations keep working.

## Red/Green Evidence

Before the production parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
FAIL uses escaped top-level stream dictionary names and ignores literal/comment noise before WordPress text extraction
FAIL ignores nested stream dictionary names before the real top-level filter and length entries
1 test files, 2 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
1 test files, 14 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php
6 test files, 616 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-escape-boundary.php
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-escape-boundary.php
git diff --check -- lanes/markerpdf
```

The smoke emits `escaped_filter_key_resolved=true`, `escaped_length_key_resolved=true`, `literal_filter_noise_excluded=true`, `comment_filter_noise_excluded=true`, `page_labels=["1"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `521 -> 523`.
- Mapped parser semantics: `369 -> 370 / 78`.
- WordPress scenario: escaped stream dictionary keys and fake dictionary-key text inside literals/comments no longer block paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, stream filter resolver, Flate decoder, DecodeParms guard, and content-stream text tokenizer. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya/Torch models, tabled-pdf, Texify, runtime server/app tooling, and live benchmark dependencies.

## Non-Overlap

This does not repeat accepted current-xref stream object selection, top-level stream dictionary detection, stale `/Length` endstream recovery, indirect filter-name arrays, stream-filter error fail-closed behavior, object-stream length/filter recovery, object-stream nested token boundaries, linearized hint-table exclusion, PieceInfo private-stream exclusion, or rich-media embedded-file review metadata. This slice is specifically dictionary-key token grammar for stream dictionaries: escaped top-level names are accepted, while literal/comment/nested fake names are ignored.
