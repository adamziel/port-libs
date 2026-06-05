# markerPDF stream-filter null DecodeParms slot boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T035611Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level PDF stream decoding before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered decoder stacks. `null` entries are identity placeholders. Decode parameters aligned to identity placeholders must not poison later real filters, while malformed or unresolved DecodeParms aligned to a real filter still fail closed before visible text extraction.

## Behavior

`PdfTextExtractor` now has content-stream-only DecodeParms alignment for filter stacks. Page, Form XObject, and appearance content streams can ignore unresolved DecodeParms entries paired with `null` filters, then still apply the DecodeParms dictionary paired with the real Flate/LZW filter.

The stricter parser/review paths remain unchanged: CMap streams, object streams, inline image preview-prefix handling, image stream review, xref-stream operands, and generic stream review still classify malformed trailing DecodeParms operands as fail-closed.

Focused fixture shape:

```text
/Filter [ null /FlateDecode ]
/DecodeParms [ 99 0 R << /Predictor 12 /Columns N >> ]
```

The unresolved `99 0 R` belongs to the null filter slot and is ignored. A sibling stream with:

```text
/Filter [ /FlateDecode null ]
/DecodeParms [ 99 0 R null ]
```

still fails closed and does not leak its compressed fallback text.

## Red-First Probe

Before the source change, a one-off fixture probe returned no page text:

```text
array (
)
```

During implementation the focused test was also red until indirect DecodeParms references were tokenized as a single array item:

```text
FAIL ignores unresolved DecodeParms entries aligned to null filters while failing closed on real filters
Expected: Null Slot DecodeParms Ignored, Real Flate Still Decodes, Visible After Null Slot Boundary
Actual: Visible After Null Slot Boundary
```

## Evidence

Focused stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
1 test files, 125 assertions, 0 failures
```

Adjacent parser/text/inline boundary sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
8 test files, 961 assertions, 0 failures
```

Image/CMap regression sweep after scoping leniency to content streams:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
3 test files, 901 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `unresolved_decodeparms_on_null_filter_slot_ignored=true`, `unresolved_decodeparms_on_real_filter_slot_fail_closed=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Null Slot DecodeParms Ignored`, `Real Flate Still Decodes`, and `Visible After Null Slot Boundary` while excluding `Real Filter DecodeParms Leak`.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85/Flate missing-Length recovery, stale or short `/Length` recovery, Flate-first stack recovery, RunLength EOD-aware recovery, singleton DecodeParms mapping after null filters, compact DecodeParms arrays, abbreviated filter names, stray DecodeParms without `/Filter`, all-null filter arrays, malformed DecodeParms fail-closed behavior for real filters, indirect filter-name arrays, object-stream filter ownership, xref-stream DecodeParms recovery, CMap malformed operand classification, image-filter exclusion, inline-image tokenizer boundaries, or Form XObject resource inheritance.

The bounded behavior is specifically unresolved DecodeParms operands aligned to `null` identity filter slots in page/Form/appearance content streams, while real-filter DecodeParms operands stay fail-closed.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, DecodeParms parser, Flate predictor decoder, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
