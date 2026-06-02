# markerPDF Stream Filter Stack Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260602T234448Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream parsing to the PDF parser/pdftext/PDFium boundary before OCR/layout/model stages.
- PDF stream filter arrays are ordered stacks. A missing `/Length` stream that uses an ASCII85 filter stack must not accept a line-start `endstream` byte sequence inside the encoded payload until the ASCII85 EOD marker `~>` has also been reached.

## Behavior

- `PdfTextExtractor` now uses stricter stream-filter validation only while recovering missing-length `endstream` boundaries for verifiable filtered streams.
- During that boundary search, ASCIIHex and ASCII85 filter stages require their explicit in-band EOD markers before a candidate `endstream` can be accepted.
- Ordinary length-bounded stream decoding remains unchanged, including existing permissive ASCII85/ASCIIHex decoding used by accepted fixtures.

## Evidence

Red-first check before the decoder change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
Values are not identical
Expected: array (
  0 => 'Before ASCII85 Stack Boundary',
  1 => 'After ASCII85 Stack Boundary',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
9 test files, 691 assertions, 0 failures
```

Syntax and smoke checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits two Gutenberg paragraphs, `Before ASCII85 Stack Boundary` and `After ASCII85 Stack Boundary`, with `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ASCII85 success-path decoding, length-bounded ASCIIHex/RunLength stack decoding, indirect filter-name arrays, DecodeParms alignment/fail-closed behavior, stream-owned helper object rejection, object-stream filter-chain recovery, xref-stream DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or stale stream `/Length` recovery. The new behavior is specifically missing-`/Length` stream boundary recovery for ordered filter stacks where ASCII85/ASCIIHex encoded data can contain a fake `endstream` keyword before the filter EOD marker.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter resolver, stream filter dispatcher, content-token parser, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
