# markerPDF stream-filter stack trailing null DecodeParms fallback boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T084503Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the pdftext/PDFium-backed text path before OCR/layout/model stages.
- ISO 32000 stream dictionaries apply `/Filter` entries in order. When `/DecodeParms` is an array, each entry aligns to the same-position filter entry; null filter entries are identity/default placeholders and must not require a real DecodeParms object.

## Behavior

This current-base slice makes catalogless fallback stream scanning use the same filter-aware DecodeParms alignment already used by normal page `/Contents` decoding:

```text
/Filter [ /FlateDecode null ]
/DecodeParms [ << /Predictor 1 >> 99 0 R ]
```

The unresolved `99 0 R` operand is aligned to the trailing null filter slot, so the Flate stream is valid and searchable text is preserved. A sibling stream with the unresolved operand aligned to the real `FlateDecode` slot remains rejected:

```text
/Filter [ /FlateDecode null ]
/DecodeParms [ 99 0 R null ]
```

Before this patch, catalogless fallback scans dropped the valid stream because `allDecodedStreams()` used the stricter non-filter-aware DecodeParms resolver. After this patch, fallback scans preserve the safe stream and still fail closed for the invalid sibling.

## Red Probe

Before the source change, the catalogless fallback fixture returned only:

```text
Visible After Fallback Boundary
```

The expected valid Flate stream text, `Fallback Trailing Null DecodeParms Applies`, was missing even though the same filter stack was accepted through the normal page `/Contents` path.

## Evidence

Focused stream-filter stack test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores unresolved DecodeParms entries aligned to trailing null filters in fallback stream scans
1 test files, 183 assertions, 0 failures
```

Adjacent parser/filter family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
11 test files, 1801 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-trailing-null-decodeparms-currentbase.php
```

The smoke emits `fallback_trailing_null_decodeparms_preserved=true`, `unresolved_real_filter_decodeparms_fail_closed=true`, `trailing_null_helper_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders only `Fallback Trailing Null DecodeParms Applies` and `Visible After Fallback Boundary`.

## Non-Overlap

This does not repeat accepted all-null filter arrays, null DecodeParms slots aligned to real filters, compact DecodeParms arrays, extra non-null DecodeParms rejection, indirect null filter objects, CMap Crypt identity handling, image filter review/exclusion, inline-image abbreviations, object-stream/xref-stream filter owner repair, xref repair, or live OCR/model work.

The bounded behavior is specifically fallback raw stream enumeration for catalogless/searchable PDFs whose filter stack has trailing null filter placeholders with DecodeParms entries that must be ignored for those null slots.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter-stack resolver, DecodeParms alignment logic, Flate decoder, content-token parser, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed.
