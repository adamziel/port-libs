# markerPDF LZW Short-Length Stream-Filter Stack Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T065153Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level stream filters before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered decoder stacks. LZWDecode requires the LZW EOD code before the decoded bytes are complete, so a short or stale `/Length` must not truncate an LZW-to-Flate content stream before the whole stack can decode.

## Behavior

The existing native PHP decoder already recovers this current-base boundary. This slice locks it with focused coverage:

```text
/Length <short stale byte count>
/Filter [ /LZWDecode /FlateDecode ]
```

The first stream contains a valid LZW literal-coded zlib stored block. The declared `/Length` stops halfway through the encoded LZW bytes, so the parser must continue to the real `endstream`, require the LZW EOD code, then inflate the Flate stage before WordPress paragraph extraction.

A sibling LZW-to-Flate stream with the LZW EOD bytes truncated remains fail-closed, and a later unfiltered stream still imports normally.

## Evidence

Current-base probe before source edits returned the expected WordPress-visible lines, so no production source change was needed:

```text
LZW Short Length Before
LZW Short Length After
Visible After LZW Boundary
```

Focused test before this coverage addition:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 156 assertions, 0 failures
```

Focused test after this patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers short Length LZW and Flate stacks only after the LZW EOD code
1 test files, 166 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `short_declared_length_lzw_stack_recovers_after_eod=true`, `malformed_lzw_stack_fail_closed=true`, `requires_lzw_eod_before_flate_stack_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85/Flate missing-Length recovery, stale or short ASCII85 declared-Length recovery, Flate-first stack recovery, RunLength EOD-aware recovery, singleton or compact DecodeParms alignment around null filters, all-null filter arrays, indirect null filter objects, identity/private Crypt stack boundaries, object-stream filter ownership, xref-stream DecodeParms recovery, DCT/CCITT/JPX/JBIG2 preview-only image filters, inline-image tokenizer boundaries, or malformed CMap filter review.

The bounded behavior is specifically short declared `/Length` recovery for LZWDecode-to-FlateDecode content streams, with malformed LZW EOD-truncated stacks excluded before WordPress text parsing.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream dictionary reader, stream filter resolver, LZW decoder, Flate decoder, stale stream-boundary recovery, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
