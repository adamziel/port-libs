# Malformed CMap Array Filter Tail Boundary Current Base

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T053513Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level font and ToUnicode CMap decoding delegated to pdftext/PDFium before Markdown/WordPress text assembly.

The native no-GPU PHP lane owns the parser boundary before that text import path. A CMap stream dictionary whose array-valued `/Filter` operand is followed by an unkeyed top-level name before `/Length` is malformed; the native extractor must fail closed for the CMap remap and preserve safe page fallback text.

## Behavior

`PdfTextExtractor::streamFilters()` now rejects array-valued `/Filter` operands when a trailing unkeyed top-level name appears before the first `/Length` key. The same extra operand is attached to CMap filter review metadata so WordPress import review can explain why the ToUnicode CMap was not decoded.

Covered boundaries:

- `/Filter [ /FlateDecode ] /ASCIIHexDecode /Length ...`
- `/Filter [ /FlateDecode ] /UnknownArrayFilterTail /Length ...`

Both previously decoded the compressed CMap and let the malformed ToUnicode map replace visible text. They now preserve the safe page text, report `decoded_cmap_count=0`, and record `filter_operand_policy=reject_malformed_filter_operands`.

## Evidence

Red-first on accepted base `3c4016bd308122a1aac381d958362c4f9e9dd199` after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when a CMap Filter array is followed by an unkeyed decoder name before Length
Expected: ['Array Decoder Safe Import']
Actual: ['Array Decoder CMap Leakrray Decoder Safe Import']
FAIL fails closed when a CMap Filter array is followed by an unknown unkeyed name before Length
Expected: ['Array Unknown Safe Import']
Actual: ['Array Unknown CMap Leakrray Unknown Safe Import']
1 test files, 2 assertions, 2 failures
```

Focused green after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when a CMap Filter array is followed by an unkeyed decoder name before Length
PASS fails closed when a CMap Filter array is followed by an unknown unkeyed name before Length
1 test files, 108 assertions, 0 failures
```

Adjacent CMap/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMap*Test.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 2724 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-array-filter-tail-currentbase.php
```

The smoke emits safe paragraphs only plus `decoded_cmap_count=0`, `invalid_filter_operand_count=1`, `malformed_filter_operand_count=1`, `filter_operand_policy=reject_malformed_filter_operands`, `extra_filter_name=ASCIIHexDecode`, `extra_filter_name=UnknownArrayFilterTail`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat scalar `/Filter /FlateDecode /ASCIIHexDecode` rejection, indirect filter reference extra-name rejection, duplicate `/Filter` declarations, dictionary/literal filter operands, escaped filter names, unsupported filters, CMap filter EOD handling, DecodeParms fail-closed behavior, post-`/Length` array-tail ignore behavior, UseCMap inheritance boundaries, xref owner selection, object stream filter repair, or non-CMap stream filter stack handling.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, xref owner selection, stream dictionary tokenizer, filter resolver, DecodeParms validator, ToUnicode CMap parser, and WordPress smoke renderer. Full upstream model parity remains out of scope under the current no-GPU direction: pdftext/PDFium, Surya/Torch, Texify, Streamlit/FastAPI model workers, live OCR, and external PDF renderers were not executed.
