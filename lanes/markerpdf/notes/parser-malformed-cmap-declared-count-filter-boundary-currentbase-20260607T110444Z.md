# markerPDF malformed CMap declared-count filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T110444Z`

Base accepted HEAD: `d1f81c69ab23ace51c62630abe5908fd9b84065f`

## Source Truth

Upstream markerPDF routes searchable PDF text through PDF text/font extraction before Markdown assembly. Under the current no-GPU markerPDF scope, native PHP owns the searchable-PDF CMap stream boundary: filtered ToUnicode programs may map source codes, but the declared row count operand on `beginbfchar`, `beginbfrange`, and `begincodespacerange` bounds the mapping rows before malformed-row cleanup.

## Behavior

`PdfTextExtractor` now applies declared row limits inside malformed-row recovery for top-level CMap row scanners. A malformed dictionary-style first declared row consumes its declared row slot, so a later valid-looking decoy row outside the declared count cannot become the replacement mapping after the malformed row is filtered out. Incomplete singleton fragments still remain recoverable by the accepted malformed-row fallback.

This closes the filtered CMap boundary for `bfchar`, `bfrange`, and codespace row recovery while preserving decoded CMap stream review metadata and safe fallback text extraction.

## Evidence

Red-first scratch before the source edit showed the leak:

```text
plain: Declared Count CMap Leakeclared Count Safe Import
expected: Declared Count Safe Import
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps filtered bfchar declared row counts before malformed-row recovery on current base
PASS keeps filtered bfrange declared row counts before malformed-row recovery on current base

1 test files, 78 assertions, 0 failures
```

Adjacent CMap gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 353 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-declared-count-filter-boundary-currentbase.php
```

The smoke emits `safe_text_preserved=true`, `outside_declared_row_rejected=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with `Declared Count Safe Import` rendered as the Gutenberg paragraph.

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-declared-count-filter-boundary-currentbase.php
php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- `PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php` adds 2 focused PASS cases and 78 focused assertions.
- `lane-status.json` records `phpPass 2844 -> 2846`.
- `lane-status.json` records `wordpressScenarios 2385 -> 2386`.

## Non-Overlap

This does not repeat accepted malformed CMap filter operands, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, unsupported/escaped filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, row-tail malformed rows, bfrange target-array boundaries, codespace nested-array boundaries, negative/plus-signed declared-count source-width fallback, image XObject review, xref repair, metadata, annotations, forms, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically declared row count ordering before malformed-row recovery in decoded filtered CMap blocks.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, ToUnicode mapping path, CMap stream review metadata, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope for this markerPDF lane and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.
