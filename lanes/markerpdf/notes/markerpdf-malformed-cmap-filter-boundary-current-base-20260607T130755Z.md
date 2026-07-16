# markerpdf malformed CMap filter boundary current-base 2026-06-07T130755Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T130755Z`
- Accepted base: `a989768d2c262cd7bae2b6b8b64bfb53b95429cb`
- Native no-GPU scope only: searchable-PDF text extraction, filtered CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, or external PDF tools.

## Source Truth

PDF CMap programs are token streams; line breaks are whitespace. A valid filtered ToUnicode `beginbfchar`, `beginbfrange`, or `begincodespacerange` row can split its operands across lines. The native parser still must reject malformed row tails, nested arrays, overlong target arrays, and malformed stream filter operands, but it should not classify legal whitespace splitting as a malformed row.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes filtered ToUnicode bfchar rows split across PDF whitespace before current-base text extraction
Values are not identical
Expected: array (
  0 => 'Split Bfchar Import',
)
Actual: array (
)
FAIL decodes filtered ToUnicode bfrange rows split across PDF whitespace before current-base text extraction
Values are not identical
Expected: array (
  0 => 'Split Bfrange Import',
)
Actual: array (
)

1 test files, 2 assertions, 2 failures
```

## Implementation

- `PdfTextExtractor` now has token-stream row helpers for CMap `bfchar`, `bfrange`, and codespace rows.
- The line-based malformed-row fallback still handles row-tail guards. When the only issue is legal row operands split across PDF whitespace, the parser uses the token-stream rows instead of dropping the mapping.
- Declared CMap row counts still bound token-stream recovery, so an overdeclared row after a split first row cannot overwrite the declared mapping.
- Added `PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php` with compressed `/FlateDecode` ToUnicode CMap fixtures for split `bfchar`, split `bfrange`, and split-row declared-count boundaries.
- Added `wordpress-pdf-cmap-split-row-filter-boundary-currentbase.php` to prove the WordPress import path preserves mapped paragraphs and excludes CMap program bytes.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes filtered ToUnicode bfchar rows split across PDF whitespace before current-base text extraction
PASS decodes filtered ToUnicode bfrange rows split across PDF whitespace before current-base text extraction
PASS keeps filtered ToUnicode bfrange declared counts when split rows force token recovery before current-base text extraction

1 test files, 122 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1986 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-split-row-filter-boundary-currentbase.php
```

Result: exits 0 and emits paragraphs for `WordPress Split Bfchar Import` and `WordPress Split Bfrange Import` with `decoded_cmap_count=1`, `filters_resolved`, `filter_decoders_resolved`, `cmap_program_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed scalar or array `/Filter` operand boundaries, stale or free indirect filter owner boundaries, duplicate `/Filter` or `/DecodeParms`, escaped filter keys/names, unsupported/Crypt filters, post-Length/post-DecodeParms extra operands, CMap EOD boundaries, declared row-count guards, singleton malformed rows, nested row tails, short or overlong bfrange target arrays, literal target source-width fallback, or Type0 source-width work. The bounded behavior is specifically valid filtered ToUnicode and codespace row operands split across PDF whitespace lines.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP stream filters, PDF tokenizer, CMap parser, and WordPress smoke harness. GPU/model OCR, external PDF tools, pypdfium/PDFium, PIL, Surya/Torch, Texify, Streamlit/FastAPI workers, and live benchmark/model parity remain intentionally out of scope under the markerPDF no-GPU directive.

## Next Task

Continue non-overlapping native markerPDF parser work around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs.
