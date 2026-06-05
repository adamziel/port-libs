# markerPDF Stream Filter Stack Overdeclared Length Boundary

## Source Truth

- Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium boundaries. In the native no-GPU PHP lane, the PDF stream decoder is the source-compatible boundary for searchable page content before WordPress paragraph import.
- PDF stream `/Filter` values are ordered decoder stacks. When a declared `/Length` is overlong, a complete native filter stack can prove the earlier stream terminator without swallowing following indirect objects.

## Implementation

`PdfTextExtractor` now applies native filter-stack terminator recovery for overdeclared `/Length` values. If the declared byte range extends past the first valid `endstream`, the parser accepts an earlier terminator only when the candidate payload decodes through a verifiable native filter stack. The same bounded recovery is used by payload extraction, repaired direct stream bodies, and the direct-object scanner so later content objects remain discoverable.

The focused fixture uses a Flate page content stream whose `/Length` extends into the next content object. Before the fix the first stream was dropped and only the later object imported. After the fix both WordPress paragraphs import and stream syntax stays excluded.

## Red-First Evidence

Before the production change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers overdeclared native filter-stack lengths before later content objects
Expected: ['Overdeclared Flate Stack Before', 'Visible After Overdeclared Stack']
Actual: ['Visible After Overdeclared Stack']
1 test files, 1 assertions, 1 failures
```

After the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers overdeclared native filter-stack lengths before later content objects
1 test files, 10 assertions, 0 failures
```

Focused family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
10 test files, 925 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-overdeclared-length-currentbase.php
```

The smoke exits 0 and emits `overdeclared_length_recovered_at_filter_stack=true`, `later_content_object_preserved=true`, `stream_syntax_excluded=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and the paragraphs `Overdeclared Flate Stack Before` and `Visible After Overdeclared Stack`.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85/Flate stack recovery, stale or short `/Length` recovery, Flate-first stack recovery, RunLength/LZW EOD recovery, compact DecodeParms alignment, all-null filter arrays, default or explicit identity `/Crypt`, parser-comment split indirect references, malformed DecodeParms fail-closed behavior, DCT overdeclared JPEG EOI recovery, CCITT image-boundary recovery, xref/object-stream filter-chain helper resolution, image-filter exclusion, or inline-image tokenizer boundaries.

The bounded behavior is specifically overdeclared `/Length` recovery for ordinary native-decodable stream filter stacks before later content objects are swallowed or skipped.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, Flate/native filter decoding, direct-object scanner, content-token parser, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI workers, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
