# markerpdf malformed CMap singleton-object declared-count boundary

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T122431Z`
- Base accepted HEAD: `d254c38c5435b40c715dda98fb5188c595a288f7`
- Scope: native PHP searchable-PDF parser behavior only. No OCR, Surya, Texify, Torch, GPU/model workers, PDF action execution, external PDF tools, or live services.

## Behavior

Filtered ToUnicode CMap mapping blocks now count singleton non-hex malformed object rows as one declared row slot during malformed-row recovery. This closes a boundary left by the previous declared-count slice: a `1 beginbfchar` or `1 beginbfrange` block whose first declared row is only a dictionary or array object no longer drops that object without consuming the declared row, so a later valid-looking row outside the declared count cannot replace searchable text.

The fix preserves accepted singleton-hex recovery. Incomplete hex fragments such as `<0009>` before a later recoverable row still do not consume a declared row slot, matching the current bfrange/codespace/orphan-bfchar recovery tests.

## Red-First Evidence

Focused test before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps filtered bfchar declared row counts for singleton dictionary object rows on current base
Expected: array (
  0 => 'Singleton Char Safe Import',
)
Actual: array (
  0 => 'Singleton Char CMap Leakingleton Char Singleton Char CMap Leakafe Import',
)
FAIL keeps filtered bfrange declared row counts for singleton array object rows on current base
Expected: array (
  0 => 'Singleton Range Safe Import',
)
Actual: array (
  0 => 'Singleton Range CMap Leakingleton Range Singleton Range CMap Leakafe Import',
)

1 test files, 2 assertions, 2 failures
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps filtered bfchar declared row counts for singleton dictionary object rows on current base
PASS keeps filtered bfrange declared row counts for singleton array object rows on current base

1 test files, 80 assertions, 0 failures
```

Adjacent declared-count and singleton-recovery family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
9 PASS cases
6 test files, 324 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-singleton-object-declared-count-currentbase.php
```

The smoke emits `Singleton Object Safe Import` with `safe_text_preserved=true`, `singleton_object_declared_row_rejected=true`, `outside_declared_row_rejected=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-singleton-object-declared-count-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- `PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php` adds 2 focused PASS cases and 80 focused assertions.
- `lane-status.json` records `phpPass 2859 -> 2861`.
- `lane-status.json` records `wordpressScenarios 2396 -> 2397`.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, unsupported/escaped filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, row-tail malformed rows, bfrange target-array boundaries, codespace nested-array boundaries, singleton hex row recovery, or the previous dictionary-style declared-count rows with enough tokens to form a row.

The bounded behavior is specifically singleton non-hex object rows in filtered ToUnicode CMap mapping blocks consuming declared row slots before malformed-row cleanup.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, ToUnicode mapping path, CMap stream review metadata, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope for this markerPDF lane and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.
