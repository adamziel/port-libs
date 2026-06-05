# markerPDF indirect null stream filter stack boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T050150Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream decoding to pdftext/PDFium before OCR, layout, or model stages.
- PDF stream filter arrays are ordered decoder stacks. `null` entries are identity placeholders, including when the array entry is an indirect object that resolves to `null`. DecodeParms aligned to identity placeholders must not be applied to real decoder stages, and stale helper operands aligned to those placeholders must not suppress otherwise valid searchable text.

## Behavior

This current-base slice adds a focused regression case for:

```text
/Filter [ 7 0 R /FlateDecode ]
/DecodeParms [ 99 0 R << /Predictor 12 /Columns N >> ]
7 0 obj
null
endobj
```

The indirect `null` filter object is treated as an identity stack slot. The unresolved `99 0 R` DecodeParms helper is ignored because it is aligned to that identity slot, while the predictor dictionary still applies to the real `/FlateDecode` stage. The helper object text stays out of WordPress paragraphs.

## Current-Base Probe

The accepted base already handled this behavior. This patch locks it into the focused stack-boundary denominator instead of changing production parser code.

```text
php -r '... indirect null /Filter slot probe ...'
array (
  0 => 'Indirect Null Filter Predictor',
  1 => 'Indirect Null DecodeParms Applies',
  2 => 'Visible After Indirect Null Filter',
)
```

## Evidence

Focused stream-filter stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats indirect null filter objects as identity stack slots before DecodeParms alignment
1 test files, 146 assertions, 0 failures
```

Adjacent stream/filter boundary run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
7 test files, 835 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `indirect_null_filter_object_decodeparms_aligned=true`, `indirect_null_decodeparms_helper_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Indirect Null Filter Predictor`, `Indirect Null DecodeParms Applies`, and `Visible After Indirect Null Filter`.

## Non-Overlap

This does not repeat accepted direct null filter placeholders, all-null filter arrays, compact DecodeParms arrays, abbreviated filters, stray DecodeParms with no `/Filter`, malformed DecodeParms fail-closed behavior for real filters, indirect filter-name arrays with direct null entries, dictionary filter operands, object-stream filter ownership, xref-stream DecodeParms recovery, CMap Crypt identity handling, image-filter exclusion, inline-image tokenizer boundaries, or live OCR/model work.

The bounded behavior is specifically indirect `null` objects inside page content stream filter stacks and their DecodeParms alignment.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, indirect object resolver, stream filter resolver, DecodeParms slot alignment, Flate predictor decoder, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
