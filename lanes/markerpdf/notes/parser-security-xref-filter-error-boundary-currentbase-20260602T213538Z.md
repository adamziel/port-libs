# markerPDF parser security xref filter error-boundary current-base

Micro-slice: `parser-security-xref-filter-error-boundary-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured page text to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` uses pypdfium page text extraction. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

This native PHP boundary must therefore treat the latest `startxref` xref stream as the current object graph source. If that selected xref stream has a malformed filter chain, it must fail closed instead of scanning stale previous xref tables and promoting older page text into WordPress paragraphs.

## Behavior

The focused fixture builds an incremental PDF with:

- a stale first revision containing a valid xref table and visible page text;
- a current revision redefining the same generation-zero catalog/page/content objects;
- a latest `startxref` pointing at `/Type /XRef` with `/Filter /FlateDecode`, `/Prev` to the stale table, and malformed non-deflate xref bytes.

Before the fix, `PdfTextExtractor` treated the failed xref-stream decode as "no startxref entries" and fell back to the previous scanned xref table, leaking `Stale xref filter fallback leak` into extracted WordPress text. After the fix, `startxrefXrefStreamFilterDecodeFailed()` detects only this direct `/Type /XRef` stream filter decode failure and returns no page text; invalid offsets and stream-owned fake xrefs keep the existing repair path.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when current startxref xref-stream filter decoding errors before stale table fallback
Expected: array (
)
Actual: array (
  0 => 'Stale xref filter fallback leak',
  1 => 'Stale table selected text',
)
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when current startxref xref-stream filter decoding errors before stale table fallback
1 test files, 11 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 723 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-security-xref-filter-error-boundary-currentbase.php
```

The smoke emits `malformed_current_xref_stream_failed_closed=true`, `stale_xref_table_excluded=true`, `current_malformed_stream_text_excluded=true`, `page_count=0`, `page_labels=[]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta: behavior tests `855 -> 856`; mapped parser semantics `601 -> 602 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream DecodeParms row decoding, stale generation `/Filter` owner rejection, stream-owned fake xref table offset rejection, object-stream carrier exclusion, nested filter fail-closed behavior, direct stream dictionary escaping, xref-stream `/Prev` generation repair, or stale `/Length` startxref recovery.

The new behavior is specifically latest-startxref direct `/Type /XRef` stream filter decode failure. It prevents fallback to older xref tables and latest-direct object scans when the current xref stream itself is selected but undecodable.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, latest `startxref` lookup, xref-stream dictionary parser, stream filter-chain decoder, page-tree walker, fallback stream enumerator, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
