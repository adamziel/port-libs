# markerPDF stream filter null DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T003712Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF stream `/Filter` arrays are ordered stacks. A `null` array entry is an identity placeholder, and a singleton `/DecodeParms` dictionary belongs to the only real decoder in a stack rather than to the null placeholder.

## Behavior

`PdfTextExtractor` now applies a singleton `/DecodeParms` dictionary to the only non-null filter when a stack contains null entries, for example:

```text
/Filter [ null /FlateDecode ]
/DecodeParms << /Predictor 12 /Columns ... >>
```

Before this slice, the decoder looked up DecodeParms by the raw array index, so the Flate predictor parameters were missed and predictor-encoded searchable text failed closed.

## Red Probe

Before the source change, the focused probe returned no text for the null-plus-Flate predictor stream:

```text
array (
)
```

After the source change, the same probe returned:

```text
array (
  0 => 'Null Filter DecodeParms',
  1 => 'Singleton Dict Applies',
)
```

The committed fixture uses visible text `Null Filter Predictor` so negative metadata checks can still assert that raw `/DecodeParms` names are not leaked into WordPress paragraphs.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses ASCII85 EOD markers before accepting missing-Length filter-stack endstream boundaries
PASS applies singleton DecodeParms dictionaries to the only real filter after null stack entries
PASS uses the complete ASCII85 and Flate stack before accepting missing-Length endstream boundaries
PASS uses the complete filter stack when declared Length points at an encoded fake endstream boundary
PASS uses the complete filter stack when stale Length lands before an encoded fake endstream boundary
PASS uses a complete Flate then ASCII85 stack before accepting compressed fake endstream boundaries
PASS requires RunLength EOD before accepting missing or stale filter-stack endstream boundaries

1 test files, 73 assertions, 0 failures
```

Adjacent parser/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
11 test files, 797 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `singleton_decodeparms_after_null_filter_stack_entry=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Null Filter Predictor` plus `Singleton Dict Applies`.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85-to-Flate missing-Length stack recovery, stale `/Length` before encoded fake `endstream`, Flate-to-ASCII85 compressed-boundary recovery, RunLength EOD-aware recovery, DCT/JPEG SOI/EOI framing, indirect filter-name arrays, stream-owned fake DecodeParms rejection, xref-stream DecodeParms recovery, object-stream filter ownership, image-filter exclusion, inline-image tokenizer boundaries, or page-resource inheritance.

The bounded behavior is specifically `/DecodeParms` alignment when null filter-stack placeholders precede the only real decoder.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary reader, filter resolver, DecodeParms parser, Flate predictor decoder, content-token parser, inline-image filtered-byte helper, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
