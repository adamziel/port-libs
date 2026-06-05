# markerPDF stream-filter stack indirect multi-name boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T140409Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the native/pdftext stream-decoding path before OCR/layout/model stages.
- PDF stream `/Filter` is one name or an array of filter names. An indirect `/Filter` object may therefore resolve to one name or one array value, but an indirect object whose top-level body is multiple bare names is malformed and must not be treated as a valid stack.

## Behavior

`PdfTextExtractor` now resolves indirect stream filter operands as exactly one top-level filter value. This preserves a valid top-level indirect array:

```text
/Filter 12 0 R
12 0 obj
[ /ASCII85Decode /FlateDecode ]
endobj
```

but rejects a malformed indirect object with multiple bare top-level names:

```text
/Filter 10 0 R
10 0 obj
/ASCII85Decode /FlateDecode
endobj
```

The malformed filtered stream fails closed before WordPress text import, while later valid page streams remain visible.

## Red-First Probe

Before the source change, the malformed indirect filter object decoded and leaked page text:

```text
array (
  0 => 'Malformed Indirect Multi Filter Leak',
  1 => 'Indirect Array Filter Preserved',
  2 => 'Visible After Malformed Filter Object',
)
```

The expected result keeps only the valid indirect filter array and the later unfiltered visible text.

## Evidence

Focused stream-filter stack run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect filter objects with multiple bare top-level names before page text import
1 test files, 216 assertions, 0 failures
```

Adjacent stream/filter ownership run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 321 assertions, 0 failures
```

Accepted CMap post-boundary policy spot-check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

Broader attempted adjacent run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
11 test files, 946 assertions, 2 failures
```

The two failures are legacy `PdfTextExtractorTest.php` ToUnicode `usecmap` cases that expect a base CMap named only after `endcmap` to be registered. The current accepted post-`endcmap` CMap boundary spot-check above passes and is non-overlapping with this stream-filter stack slice.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php | rg 'markerpdf:pdf-stream-filter-stack-boundary|Malformed Indirect|Indirect Array|Visible After'
```

The smoke emits `malformed_indirect_multi_name_filter_rejected=true`, `valid_indirect_filter_array_preserved=true`, `malformed_indirect_multi_filter_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, and renders `Indirect Array Filter Preserved` plus `Visible After Malformed Filter Object`.

## Non-Overlap

This does not repeat accepted all-null filter arrays, indirect null filter objects, null DecodeParms slot alignment, compact DecodeParms arrays, extra DecodeParms fail-closed behavior, parser-comment split indirect references, default Identity `/Crypt`, explicit Identity `/Crypt`, stream trailing-payload boundaries, object-stream filter ownership, xref-stream DecodeParms recovery, CMap/image filters, inline images, or OCR/model execution.

The bounded behavior is specifically indirect `/Filter` operands that resolve to multiple bare top-level names instead of one name or one array.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, indirect object resolver, filter-stack resolver, DecodeParms alignment logic, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
