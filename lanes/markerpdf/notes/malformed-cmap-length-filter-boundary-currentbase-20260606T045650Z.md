# markerPDF malformed CMap Length/filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260606T045650Z`

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T045650Z`

Base accepted HEAD: `b31c2c96194cda376adb4409356e49f96c468cf4`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is direct stream `/Length` tail validation before filtered ToUnicode CMap decoding. A CMap stream with a valid `/Filter` array and `/Length N /ASCIIHexDecode` is malformed because the decoder-name tail is not a key/value dictionary entry. The native parser must not trust that length and decode the forged CMap.

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text through PDF text/font extraction before Markdown assembly. Under the current no-GPU lane scope, the PHP fallback owns the native parser boundary: malformed filtered ToUnicode CMap dictionaries must fail closed so forged CMap mappings do not replace WordPress-visible fallback text.

## Red-First Evidence

Before the fix, a no-file probe built a filtered CMap stream with:

```text
<< /Type /CMap /CMapName /LengthExtraBoundary-H /Filter [ /FlateDecode ] /Length N /ASCIIHexDecode >>
```

The current parser accepted the numeric length, decoded the Flate CMap, and returned:

```text
Length Extra CMap Leakength Extra Safe Import
```

The review reported `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_decode_policy=filter_decoders_resolved`, and `decoded_with_current_operands=true`.

## Implementation

`PdfTextExtractor::streamLengthOperandIsWellFormed()` now checks the tail after direct numeric and indirect `/Length` operands. The tail is accepted only if it reaches the dictionary close or contains complete key/value pairs.

To preserve existing diagnostics, malformed `/Filter` operands still own their old review path: when filter resolution is already invalid, the length-tail guard does not mask the filter operand classification. Valid filter stacks, including `/Filter [ /FlateDecode ]`, now fail closed on malformed direct length tails before stream payload use.

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 22 assertions, 0 failures
```

Adjacent CMap/filter stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
```

Result:

```text
3 test files, 1888 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-filter-boundary-currentbase.php --self-test
```

The smoke emits `self_test_passed=true`, `malformed_cmap_stream_rejected_before_decode=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-length-filter-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2400 -> 2401`
- `wordpressScenarios`: `2049 -> 2050`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+22`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream dictionary parser, stream filter resolver, Flate decoder, ToUnicode CMap parser, content-token fallback decoder, and WordPress smoke renderer.

Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted malformed CMap `/Filter` operands, post-`/Length` malformed filter operand review classification, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, escaped or unsupported CMap filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, nested CMap target arrays, stale CMap stream `/Length` recovery, generic stream filter stack recovery, image-filter exclusion, CMap width grouping, or encrypted-PDF preflight.

The new boundary is specifically malformed direct numeric `/Length` tails before filtered ToUnicode CMap stream decoding when the filter operands themselves resolve cleanly.

## Next Task

Continue with non-overlapping native no-GPU markerPDF parser behavior around font/CMap widths, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
