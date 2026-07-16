# markerPDF compact DecodeParms stream-filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T021609Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered stacks. The native no-GPU PHP parser treats `null` filter-array entries as identity placeholders; compact `/DecodeParms` arrays must bind to the real filters, not to the skipped identity entries.

## Behavior

`PdfTextExtractor` now aligns compact `/DecodeParms` arrays to non-null real filters when a stream filter stack includes `null` placeholders. The focused fixture uses:

```text
/Filter [ null /ASCII85Decode /FlateDecode ]
/DecodeParms [ null << /Predictor 2 /Columns 1 >> ]
```

The raw ASCII85 bytes contain a line-start fake `endstream` before the ASCII85 `~>` EOD marker. The parser now skips the null identity filter, applies the first compact DecodeParms entry to ASCII85 as `null`, applies the predictor dictionary to Flate, and accepts the stream boundary only after the complete ordered stack decodes.

WordPress-visible paragraphs:

```text
Compact Params Stack Before
Compact Params Stack After
```

The encoded fake `endstream`, filter names, predictor metadata, and raw binary marker bytes remain excluded from Gutenberg paragraphs.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS applies singleton DecodeParms dictionaries to the only real filter after null stack entries
PASS aligns compact DecodeParms arrays to real filters after null placeholders in stream stacks
PASS ignores stray DecodeParms when no stream filters are declared before WordPress text extraction
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses the complete filter stack when stale Length lands before an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 93 assertions, 0 failures
```

Focused assertion delta for this file: `82 -> 93`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `compact_decodeparms_ignore_null_filter_placeholders=true`, `requires_complete_filter_stack_before_boundary=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85-to-Flate missing-Length stack recovery, stale declared `/Length` fake-boundary recovery, Flate-to-ASCII85 compressed-boundary recovery, RunLength EOD-aware recovery, singleton DecodeParms mapping after a null filter, stray DecodeParms-without-filter handling, DCT/JPEG SOI/EOI framing, indirect filter-name arrays, stream-owned fake DecodeParms rejection, xref-stream DecodeParms recovery, object-stream filter ownership, image-filter exclusion, inline-image tokenizer boundaries, or page-resource inheritance.

The bounded behavior is specifically compact multi-filter `/DecodeParms` arrays whose indexes omit null identity filter placeholders while the stream boundary still requires the complete filter stack.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary reader, filter resolver, DecodeParms parser, ASCII85 decoder, Flate decoder, missing/stale stream-boundary recovery, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
