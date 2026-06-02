# markerPDF parser stream token boundary current base

Micro-slice: `parser-stream-token-boundary-currentbase`

Base accepted HEAD: `2a344ae8c1b485daa88b3fe8a487f8ab30d2feff`

## Source truth

- Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` delegates `get_text_blocks()` to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` to pypdfium/PDFium page text extraction. At that boundary, PDF content-stream bytes are parsed by PDF token grammar before markerPDF cleanup sees text.
- Native PHP fallback extraction must therefore treat nested arrays and dictionaries inside content streams as token-owned payload. A line-delimited `endstream`, `endobj`, or fake `N 0 obj` sequence inside a standalone nested array must not close the owner stream or create visible WordPress paragraphs.

## Red probe

Before the fix, this current-base probe returned only the text before the nested array:

```text
array (
  0 => 'Before nested token boundary',
)
```

The fixture was a lengthless page `/Contents` stream containing:

- visible text before a standalone PDF array;
- a nested array/dictionary payload;
- line-delimited `endstream`, `endobj`, and `20 0 obj` decoys inside that array;
- visible text after the array.

## Implementation

`PdfTextExtractor::readArrayToken()` now tracks nested array depth and skips nested dictionaries, literal strings, hex strings, and comments while reading the array token. The same token reader is used by content-stream scanning, inline-image dictionary parsing, and text-token extraction, so lengthless content-stream recovery no longer accepts decoy stream/object delimiters inside nested array payloads.

## Focused evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps nested arrays inside content streams from owning endstream and object tokens

1 test files, 10 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS keeps unfiltered object stream members inside nested token boundaries before WordPress text extraction
PASS keeps missing or stale stream owners outside raw inline image endstream decoys
PASS uses escaped top-level stream dictionary names and ignores literal/comment noise before WordPress text extraction
PASS ignores nested stream dictionary names before the real top-level filter and length entries
PASS uses current xref direct stream objects before filtered fallback text extraction
PASS ignores nested stream-looking tokens inside current stream payload boundaries
PASS keeps nested arrays inside content streams from owning endstream and object tokens
PASS keeps direct stream owners token-aware when object-like text appears before stream data

6 test files, 74 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-token-boundary-currentbase.php
```

The smoke emitted two Gutenberg paragraphs, `Before nested token boundary` and `After nested token boundary`, with `nested_array_payload_excluded=true`, `fake_object_header_excluded=true`, `fake_endstream_owner_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-token-boundary-currentbase.php
```

All three PHP syntax checks reported no syntax errors.

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` completed with no output.

## Status delta

- `phpPass`: `718 -> 719`
- mapped parser semantics: `516 -> 517 / 78`
- Added focused test: `PdfParserStreamTokenBoundaryCurrentBaseTest.php`
- Added WordPress smoke: `wordpress-pdf-parser-stream-token-boundary-currentbase.php`

## Non-overlap

This does not repeat accepted inline-image owner recovery, token-aware direct stream owner lookup for fake object headers inside PDF strings, current xref-selected fallback stream enumeration, escaped stream dictionary names, stream-length `startxref` recovery, indirect filter/DecodeParms owner repair, object-stream nested token boundaries, or xref stream owner-boundary slices. This slice is specifically nested array/dictionary token depth in content stream scanning before `endstream` ownership.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, content-stream terminator scanner, content-token parser, stream dictionary parser, page-tree walker, and WordPress smoke path. Full upstream runner parity remains gated by `pdftext`, pypdfium/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, OCR/rendering helpers, and benchmark/model dependencies.
