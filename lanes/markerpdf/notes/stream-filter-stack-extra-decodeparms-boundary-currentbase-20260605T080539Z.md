# markerPDF stream-filter stack extra DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T080539Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the pdftext/PDFium-backed text path before OCR/layout/model stages.
- ISO 32000 stream dictionaries apply `/Filter` entries in order. When multiple filters have non-default parameters, `/DecodeParms` is an array whose entries correspond to the filter entries; null entries are identity/default placeholders.

## Behavior

This current-base slice adds a fail-closed boundary for streams where `/DecodeParms` contains a non-null value that is not consumed by any real filter:

```text
/Filter /FlateDecode
/DecodeParms [ null << /Predictor 1 >> ]
```

Before this patch, the stream decoded and leaked `Extra DecodeParms Leak` into WordPress paragraphs. After this patch, the ambiguous stream is rejected, while the next valid page content stream remains visible.

Null DecodeParms values and DecodeParms entries aligned to `null` identity filter slots remain accepted by the existing stack-boundary cases.

## Red Probe

Before the source change, the in-memory probe returned:

```text
Extra DecodeParms Leak
Visible After Extra DecodeParms
array (
  0 => 'Extra DecodeParms Leak',
  1 => 'Visible After Extra DecodeParms',
)
```

## Evidence

Focused stream-filter stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects extra non-null DecodeParms entries that are not aligned to stream filters
1 test files, 175 assertions, 0 failures
```

Adjacent parser/filter family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
11 test files, 1720 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-extra-decodeparms-currentbase.php
```

The smoke emits `extra_decodeparms_rejected=true`, `visible_fallback_preserved=true`, `predictor_dictionary_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders only `Visible After Extra DecodeParms`.

## Non-Overlap

This does not repeat accepted direct/null filter placeholders, indirect null filter objects, all-null filter arrays, compact DecodeParms arrays, abbreviated filters, stray DecodeParms with no `/Filter`, malformed DecodeParms parameters on real filters, unresolved DecodeParms owner rejection, CMap Crypt identity handling, object-stream/xref-stream filter operand recovery, image-filter exclusion, inline-image tokenizer boundaries, or live OCR/model work.

The bounded behavior is specifically extra non-null DecodeParms values that are not aligned to any stream filter before native searchable text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, DecodeParms alignment logic, Flate decoder, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
