# markerPDF malformed CMap nested bfchar array current-base slice

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T131440Z`
Session: `port-dev-markerpdf-malformed-cmap-20260605T131440Z`
Base accepted HEAD: `85d87e5511c95d05f1e827c086a3cd7c854b7f4c`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through PDF parser/font/CMap boundaries before OCR or model fallback.
- In this no-GPU native PHP lane, filtered ToUnicode CMaps are decoded and parsed locally; malformed CMap program structure must fail closed or be ignored without calling Python, models, PDFium, or external PDF tools.
- CMap `beginbfchar` rows are top-level source/target pairs. Hex strings nested inside arrays are not row declarations and must not overwrite valid top-level mappings.

## Change

- `PdfTextExtractor::parseToUnicodeCMap()` now parses `beginbfchar` bodies through token-aware top-level hex pairs instead of a block-wide regex.
- The parser reuses the existing CMap top-level token scanner, which skips comments, literal strings, dictionaries, and nested arrays before pairing source and target hex operands.
- Added focused coverage and WordPress smoke evidence for a Flate-decoded ToUnicode CMap whose valid top-level bfchar row is followed by a nested-array decoy for the same source code.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL ignores nested bfchar arrays in filtered CMaps before current-base text extraction
Values are not identical
Expected: array (
  0 => 'Nested Bfchar Safe Import',
)
Actual: array (
  0 => 'Nested Bfchar CMap Leak',
)
1 test files, 1087 assertions, 1 failures
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1116 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `9 test files, 2406 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

Result: emits `nested_bfchar_array_decoded_cmap_count=1`, `nested_bfchar_array_cmap_name=WPNestedBfcharArrayBoundary-H`, `nested_bfchar_array_filter_operand_policy=filters_resolved`, `nested_bfchar_array_decoy_excluded=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, literal filter operands, indirect stale filter references, DecodeParms/null-filter alignment, Crypt identity/private filters, escaped or unsupported filter names, ASCII85/Flate EOD boundaries, decoded operators after `endcmap`, second CMap programs, literal operator decoys, overdeclared literal-string rows, nested `bfrange` target arrays, literal CMapName/usecmap decoys, CMap source-width fallback, CIDFont width handling, or generic stream-filter stack behavior. The bounded behavior is only top-level `beginbfchar` pair parsing after CMap stream filter decoding.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream filter decoder, CMap operator block scanner, top-level CMap token scanner, font ToUnicode mapping path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
