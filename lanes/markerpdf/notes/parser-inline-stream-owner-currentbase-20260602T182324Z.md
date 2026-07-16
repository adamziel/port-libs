# markerPDF parser inline stream owner current-base

Micro-slice: `parser-inline-stream-owner-currentbase-20260602T182324Z`

Base accepted HEAD: `b5e63149f6bdacc97639051ac95e06ff079481ce`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates low-level page text parsing to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` reads bounded page text through pypdfium/PDFium. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

At that upstream/dependency boundary, page content stream bytes are parsed as content operators, but inline image payload bytes are not visible text and do not own direct-object delimiters.

## Implementation

`PdfTextExtractor` now uses a content-aware `endstream` scan for unfiltered content-like streams when a declared `/Length` is absent or stale. The scan skips PDF comments, literal strings, hex strings, arrays, dictionaries, and `BI ... ID ... EI` inline image regions before accepting an `endstream` terminator.

The scan is not applied to object streams, xref streams, image streams, metadata/XML streams, embedded-file streams, or filtered streams.

## Focused Behavior

The focused fixture builds one page `/Contents` stream with no `/Length`, then the same stream with a stale short `/Length`. Its inline image payload contains:

- text-looking PDF operators;
- a fake `endstream`;
- a fake `endobj`;
- a fake `20 0 obj` stream object header.

Native extraction preserves `Before Inline Owner Boundary` and `After Inline Owner Boundary`, while excluding the inline payload text and fake owner tokens from WordPress paragraphs.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps missing or stale stream owners outside raw inline image endstream decoys
Expected: ['Before Inline Owner Boundary', 'After Inline Owner Boundary']
Actual: ['Before Inline Owner Boundary']
1 test files, 1 assertions, 1 failures
```

## Verification

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-parser-inline-stream-owner-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-inline-stream-owner-currentbase.php
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps missing or stale stream owners outside raw inline image endstream decoys
1 test files, 20 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfTokenStreamObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 681 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-inline-stream-owner-currentbase.php
```

The smoke emitted `visible_text_imported=true`, `inline_payload_text_excluded=true`, `fake_object_header_excluded=true`, `fake_endstream_owner_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then two Gutenberg paragraphs: `Before Inline Owner Boundary` and `After Inline Owner Boundary`.

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Non-Overlap

This does not repeat accepted token-aware direct stream owner lookup for fake object headers inside PDF strings, fallback stream enumeration through current xref-selected direct objects, stream-filter object boundaries, indirect `/Length` repair, object-stream inline image filter helper recovery, inline image filter-array abbreviation/null alignment, stream-owned DecodeParms owner rejection, xref offset-owner rejection, or object-stream filter-owner boundaries.

The new behavior is specifically unfiltered missing/stale-length content stream ownership where raw inline image bytes contain direct-object boundary decoys before the real inline-image `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, content tokenizer, inline-image skipper, stream dictionary parser, and WordPress smoke path. Full live upstream parity remains gated on `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark/model downloads, and optional OCR/rendering tools.
