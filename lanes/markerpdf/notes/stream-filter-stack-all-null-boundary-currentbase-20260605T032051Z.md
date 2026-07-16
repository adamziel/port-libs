# markerPDF all-null stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T032051Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level PDF stream decoding before OCR/layout/model stages.
- PDF stream filter arrays are ordered decoder stacks. `null` entries are identity placeholders; a stack that contains no real filter names is an empty/identity decoder stack. DecodeParms only parameterize real filters, so stale DecodeParms helper operands must not suppress otherwise valid unfiltered page content.

## Behavior

`PdfTextExtractor::streamFilters()` now normalizes resolved filter stacks that contain no real filter names to an empty stack. This covers direct arrays like:

```text
/Filter [ null ]
/DecodeParms 99 0 R
```

The resulting content stream is treated like an unfiltered searchable page stream. The stale DecodeParms helper object remains excluded from WordPress paragraphs. Real filtered streams still resolve DecodeParms normally and still fail closed on malformed or unresolved DecodeParms.

## Red-First Probe

Before the source change, the new focused case failed with empty extracted text:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
FAIL treats all-null filter arrays as an empty stack before resolving stray DecodeParms
Expected: array (
  0 => 'All Null Filter Visible',
  1 => 'Identity Stack Preserved',
)
Actual: array (
)
1 test files, 106 assertions, 1 failures
```

## Evidence

Focused stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats all-null filter arrays as an empty stack before resolving stray DecodeParms
1 test files, 115 assertions, 0 failures
```

Adjacent stream/filter boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
6 test files, 176 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `all_null_filter_array_decodeparms_ignored=true`, `all_null_decodeparms_helper_excluded=true`, `fake_endstream_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `All Null Filter Visible` plus `Identity Stack Preserved`.

## Non-Overlap

This does not repeat accepted ASCII85 EOD recovery, ASCII85/Flate stack recovery, stale or short `/Length` recovery, Flate-first stack recovery, RunLength EOD-aware recovery, singleton or compact DecodeParms alignment around mixed null and real filters, abbreviated filter names, stray DecodeParms with no `/Filter`, malformed DecodeParms fail-closed behavior for real filters, indirect filter-name arrays, object-stream filter ownership, xref-stream DecodeParms recovery, image-filter exclusion, inline-image tokenizer boundaries, or Form XObject resource inheritance.

The bounded behavior is specifically all-null stream filter arrays resolving to an identity stack before DecodeParms resolution.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, stream filter resolver, DecodeParms parser, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
