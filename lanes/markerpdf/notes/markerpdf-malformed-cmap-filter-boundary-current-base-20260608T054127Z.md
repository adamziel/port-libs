# markerpdf malformed CMap filter boundary current-base 2026-06-08T054127Z

## Scope

- Lane: `markerpdf`
- Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T054127Z`
- Base accepted HEAD: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`
- Native no-GPU scope only: searchable-PDF text extraction, filtered CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, or external PDF tools.

## Behavior

PDF CMap `begincodespacerange` rows define valid source-code byte windows. The existing native parser already applied that same-width codespace boundary to `beginbfrange` rows, but `beginbfchar` rows were applied even when their source key had the same width as a declared codespace and fell outside that codespace. A malformed filtered ToUnicode stream could therefore replace otherwise safe fallback text with decoded CMap replacement text.

This slice makes filtered ToUnicode `beginbfchar` rows match the existing `beginbfrange` boundary: when a same-width codespace exists and the source key is outside it, the row is ignored before text replacement. Accepted longer explicit source-key fallback is preserved because the check only runs when a same-width codespace is present.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects filtered ToUnicode bfchar source keys outside same-width codespace before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Same Width Safe Import',
)
Actual: array (
  0 => 'Same Width CMap Leakame Width Same Width CMap Leakafe Import',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::parseToUnicodeCMap()` now checks `beginbfchar` source keys against same-width CMap code-space ranges before adding a ToUnicode mapping.
- Added `PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php` for a Flate-decoded ToUnicode CMap with `/Filter /FlateDecode`, a same-width codespace `<0000> <0001>`, and an out-of-range `bfchar` source key that must not replace fallback searchable text.
- Added `wordpress-pdf-malformed-cmap-bfchar-codespace-boundary-currentbase.php` to prove WordPress paragraph import preserves safe text and keeps decoded CMap program text review-only.

## Verification

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects filtered ToUnicode bfchar source keys outside same-width codespace before current-base text extraction

1 test files, 41 assertions, 0 failures
```

Adjacent CMap/filter/source-width family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayTargetOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOverlongToUnicodeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
```

```text
10 test files, 2265 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfchar-codespace-boundary-currentbase.php
```

The smoke emits `safe_text_preserved=true`, `outside_same_width_codespace_rejected=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat earlier malformed CMap filter-boundary slices for scalar or array `/Filter` operands, post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, UseCMap inheritance, WMode, malformed or underdeclared codespace rows, short or overlong `bfrange` target arrays, nested array row tails, overlong source-width fallback, or Type0 Encoding CID CMap declared-count row slots. The new boundary is specifically a successfully decoded filtered ToUnicode `beginbfchar` source key outside an existing same-width CMap codespace.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object scanner, stream decoder, CMap parser, source-width fallback path, review metadata extractor, focused test harness, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, pypdfium/PIL, Surya, Texify, Torch, live-service workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
