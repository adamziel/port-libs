# markerPDF stream filter stack stray DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T010931Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF stream `/DecodeParms` values parameterize declared stream filters. When a stream has no `/Filter` or resolves to an empty filter stack, stray malformed `/DecodeParms` operands do not create a decoder and should not suppress otherwise valid unfiltered page content.

## Behavior

`PdfTextExtractor::decodeStream()` now returns an unfiltered stream before resolving `/DecodeParms` when the resolved filter stack is empty. This preserves valid searchable page text for unfiltered content streams that carry stale or malformed DecodeParms helper references, while existing filtered streams still fail closed when their DecodeParms cannot be resolved or validated.

The focused fixture models a page `/Contents` stream with:

- no `/Filter`;
- `/DecodeParms 99 0 R`;
- valid visible text operators in the stream payload;
- a stale `99 0 obj` helper that contains text-looking PDF operators which must not leak into WordPress paragraphs.

## Evidence

Red-first probe before the source change returned no text for the same unfiltered stream:

```text
array (
)
```

Focused test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS applies singleton DecodeParms dictionaries to the only real filter after null stack entries
PASS ignores stray DecodeParms when no stream filters are declared before WordPress text extraction
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses the complete filter stack when stale Length lands before an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 82 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `stray_decodeparms_without_filter_ignored=true`, `stray_decodeparms_helper_excluded=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Stray DecodeParms Visible` plus `Unfiltered Stream Preserved`.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, complete ASCII85/Flate stack recovery, stale or short `/Length` recovery, Flate-first stack recovery, RunLength EOD-aware recovery, singleton DecodeParms alignment after null filter placeholders, malformed DecodeParms fail-closed behavior for real filters, indirect filter-name arrays, object-stream filter ownership, xref-stream filter DecodeParms recovery, image-filter exclusion, or inline-image tokenizer boundaries.

The bounded behavior is specifically stray `/DecodeParms` operands on streams with no declared filters.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, stream filter resolver, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
