# markerpdf malformed CMap filter boundary current-base 2026-06-08T110319Z

## Scope

- Lane: `markerpdf`
- Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T110319Z`
- Base accepted HEAD: `2685bfd2918d5f146742ce08f1f4ded2aa11745d`
- Native no-GPU scope only: searchable-PDF text extraction, filtered CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, or external PDF tools.

## Behavior

PDF lexical whitespace is limited to NUL, tab, line feed, form feed, carriage return, and space. Vertical tab is not PDF whitespace. The native CMap row tokenizer used PHP `ctype_space()`, so a filtered ToUnicode stream could treat vertical-tab-separated operands as a valid `beginbfchar` or `beginbfrange` row and replace otherwise safe fallback text with decoded CMap text.

This slice makes CMap declared-count and row-token whitespace checks use the lane's PDF whitespace predicate. Filtered ToUnicode streams still decode for review metadata when their `/Filter /FlateDecode` operands are valid, but vertical-tab-separated row operands no longer form valid CMap mappings.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapVerticalTabFilterBoundaryCurrentBaseTest.php
```

Result before implementation:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects filtered ToUnicode bfchar rows separated by vertical tab before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapVerticalTabFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Vertical Tab Char Safe Import',
)
Actual: array (
  0 => 'Vertical Tab Char CMap Leakertical Tab Char Safe Import',
)
FAIL rejects filtered ToUnicode bfrange rows separated by vertical tab before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapVerticalTabFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Vertical Tab Range Safe Import',
)
Actual: array (
  0 => 'Vertical Tab Range CMap Leakertical Tab Range Safe Import',
)

1 test files, 2 assertions, 2 failures
```

## Implementation

- `PdfTextExtractor::cMapDeclaredOperatorCountBefore()` now trims only PDF whitespace before CMap block operators.
- `PdfTextExtractor::cMapTopLevelBfRangeTokens()` and `cMapTopLevelCidTokens()` now skip only PDF whitespace while scanning CMap rows, so vertical tab becomes a malformed row token instead of a row separator.
- Added `PdfParserMalformedCMapVerticalTabFilterBoundaryCurrentBaseTest.php` for filtered ToUnicode `bfchar` and `bfrange` rows separated by vertical tab.
- Added `wordpress-pdf-malformed-cmap-vertical-tab-filter-currentbase.php` to prove WordPress paragraph import preserves fallback text and keeps CMap filter metadata review-only.

## Verification

Focused test after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapVerticalTabFilterBoundaryCurrentBaseTest.php
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects filtered ToUnicode bfchar rows separated by vertical tab before current-base text extraction
PASS rejects filtered ToUnicode bfrange rows separated by vertical tab before current-base text extraction

1 test files, 82 assertions, 0 failures
```

Malformed-CMap family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfParserMalformedCMap*CurrentBaseTest.php' | sort)
```

```text
43 test files, 4510 assertions, 0 failures
```

Broader CMap family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name '*CMap*CurrentBaseTest.php' | sort)
```

```text
89 test files, 5705 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-vertical-tab-filter-currentbase.php
```

The smoke exits 0 and emits `safe_text_preserved=true`, `vertical_tab_cmap_row_rejected=true`, `payload_excluded=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `decoded_with_current_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat scalar or array `/Filter` operand rejection, post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, UseCMap inheritance, WMode, malformed or underdeclared codespace rows, short or overlong `bfrange` target arrays, nested array row tails, `bfchar` same-width codespace rejection, overlong source-width fallback, Type0 Encoding CID declared-count slots, or classic xref vertical-tab boundaries. The new boundary is specifically successfully decoded filtered ToUnicode CMap row text where vertical tab had been accepted as row whitespace.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF object scanner, stream decoder, CMap parser, source-width fallback path, review metadata extractor, focused test harness, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, pypdfium/PIL, Surya, Texify, Torch, live-service workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
