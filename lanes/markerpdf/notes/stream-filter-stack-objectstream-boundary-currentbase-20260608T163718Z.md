# markerPDF object-stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T163718Z`
Base accepted HEAD: `5b8ea24af48dcb3ad921ab7b94f34569273f4087`

## Source Truth

- Upstream markerPDF delegates PDF object loading to pdftext/PDFium before extracting searchable page text. A native fallback must preserve the same parser ownership boundary: a PDF object stream (`/Type /ObjStm`) is a compressed-object carrier, not visible page content.
- PDF stream filter stacks are ordered decoders. Filters with explicit or discoverable end markers, such as ASCII85 and Flate, must end at the selected stream boundary. Non-whitespace bytes after an explicit filter EOD marker make the carrier malformed for compressed-object member expansion.
- This slice stays in the no-GPU markerPDF scope. It does not run OCR, Surya, Texify, Torch, pypdfium rendering, Streamlit/FastAPI workers, Python models, PDF actions, or external PDF tools.

## Implementation

`PdfTextExtractor::decodedObjectStreamMemberTable()` now expands object-stream carriers through `decodeStreamObject(..., requireBoundedFilterEndMarkers: true)`. This reuses the existing bounded native filter-stack policy already used by CMaps and Type3 CharProcs.

The new fixture builds an xref-stream PDF where object 4 is a page dictionary stored inside object stream 6. Object stream 6 has `/Filter [ /ASCII85Decode /FlateDecode ]` and valid ASCII85 `~>` EOD, then non-whitespace text-like tail bytes. Before the fix, the ASCII85 decoder ignored the tail and expanded object 4, leaking the compressed page into WordPress text. After the fix, object stream 6 remains unresolved, direct page 8 is preserved, and both the compressed page text and post-EOD tail text are excluded.

## Red Probe

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects non-whitespace bytes after object-stream filter EOD before xref member expansion
Expected: array ('Direct object-stream boundary guard page')
Actual: array ('Direct object-stream boundary guard page', 'Tailed object-stream compressed page leak')
1 test files, 1 assertions, 1 failures
```

## Passing Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects non-whitespace bytes after object-stream filter EOD before xref member expansion
1 test files, 23 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNullFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 567 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-objectstream-currentbase.php
exits 0 with direct_page_preserved=true, tailed_object_stream_rejected=true, compressed_page_text_excluded=true, post_eod_tail_text_excluded=true, executes_pdf_actions=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted page-content stream stack recovery, trailing-payload page stream rejection, Type3 CharProc EOD handling, all-null filter stack handling, default `/Crypt` identity stacks, CMap filter/decode boundaries, inline-image EOD surplus tokenization, xref-stream DecodeParms recovery, object-stream filter owner exclusion, nested object-stream filter rejection, or null-filter DecodeParms slot alignment.

The bounded new behavior is specifically object-stream carrier expansion: xref type-2 member tables now fail closed when a real object-stream filter stage has non-whitespace bytes after its bounded EOD marker.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream parser, stream dictionary reader, filter-stack resolver, DecodeParms alignment logic, bounded filter end detection, object-stream member parser, text extractor, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction.
