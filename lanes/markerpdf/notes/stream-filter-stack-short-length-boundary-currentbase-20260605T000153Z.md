# markerPDF short stale-Length stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T000153Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF stream parsing to pdftext/PDFium before OCR/layout/model stages.
- The native no-GPU PHP lane owns the searchable-PDF parser boundary. PDF `/Filter` arrays are ordered stacks, so a stale `/Length` that lands inside encoded bytes must not let a generic fake `endstream` boundary win before the complete filter stack can decode at a later terminator.

## Behavior

`PdfTextExtractor` now checks verifiable filtered-stream endstream candidates at or beyond the stale declared `/Length` before falling back to generic `endstream` scanning. This preserves native text extraction for `/Filter [ /ASCII85Decode /FlateDecode ]` streams where `/Length` points into the ASCII85 bytes just before an encoded fake `endstream`.

The focused fixture recovers only:

- `Short Length Stack Before`
- `Short Length Stack After`

and excludes raw fake boundary bytes from visible WordPress paragraphs.

## Evidence

Red probe before the source change returned no page text for the short stale-Length fixture:

```text
array (
  0 => 80,
  1 => 75,
  2 =>
  array (
  ),
  3 => '',
)
```

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses the complete filter stack when stale Length lands before an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 64 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `short_declared_length_before_encoded_fake_endstream=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent parser/filter/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
11 test files, 788 assertions, 0 failures
```

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85-to-Flate missing-Length stack recovery, declared `/Length` exactly pointing at an encoded fake `endstream`, Flate-to-ASCII85 compressed-boundary recovery, RunLength EOD-aware recovery, DCT/JPEG SOI/EOI framing, DecodeParms fail-closed behavior, indirect filter-name arrays, object-stream filter ownership, xref-stream DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or encrypted-PDF preflight.

The bounded behavior is specifically stale `/Length` landing before an encoded fake `endstream`, where the payload reader must prefer a later complete filtered terminator over generic scanning.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary reader, stream filter resolver, ASCII85 decoder, Flate decoder, filtered terminator recovery, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
