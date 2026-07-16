# markerPDF CMap declared-count CID trailing operand source-width fallback

Session: `port-dev-markerpdf-source-width-20260608T193153Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T193153Z`

Base accepted HEAD: `13ef792b9726ca74a5372ce5b45a701d4366670c`

## Source Truth

- Upstream `sddai/markerPDF` at the pinned lane manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through the PDF text/parser boundary before assembling spans, lines, blocks, and Markdown.
- In this native no-GPU PHP fallback, Type0 font `/Encoding` CMaps map source character codes to descendant CIDs, while `/ToUnicode` maps source codes to searchable Unicode text. CIDFont `/W` and `/DW` widths are keyed by the CIDs from the Encoding CMap before WordPress paragraph-gap and styled-span grouping.
- This slice is bounded to declared-count `begincidchar` and `begincidrange` blocks where the declared rows are valid but a malformed trailing operand appears on the same line before the end operator. The trailing operand is outside the declared row count and must not cause the parser to discard the valid source-to-CID rows.

## Behavior

`PdfTextExtractor::cMapTopLevelCidCharRows()` and `PdfTextExtractor::cMapTopLevelCidRangeRows()` now consume CID CMap row tokens left-to-right. Valid rows inside the declared count are preserved even when a malformed operand follows on the same line. Malformed operands still consume row slots when they appear before the declared row budget is exhausted, array-wrapped CID decoys remain ignored without consuming slots, and overlong source-code rows remain non-rows so later valid declared rows can still be accepted.

This keeps the accepted malformed-row fail-closed behavior while fixing the current-base false negative where one same-line trailing dictionary operand erased the entire valid CID row block.

## Focused Fixture

`PdfCMapDeclaredCountCidTrailingOperandSourceWidthCurrentBaseTest.php` adds two Type0 fixtures:

- `4 begincidchar` with four valid same-line source-to-CID rows followed by `<< /Tail true >>`;
- `1 begincidrange` with one valid same-line source-to-CID range followed by `<< /Tail true >>`.

Both fixtures decode searchable text as `Wide Thin`. Correct source-width grouping maps `Wide` through CIDs `60..63` with narrow `/W` metrics and preserves the positioned word gap. Dropping the valid Encoding CMap rows falls back to raw source CIDs and collapses the text to `WideThin`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapDeclaredCountCidTrailingOperandSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps valid same-line declared-count CMap cidchar rows before trailing operand source-width fallback on current base
Expected: array (0 => 'Wide Thin',)
Actual: array (0 => 'WideThin',)
FAIL keeps valid same-line declared-count CMap cidrange rows before trailing operand source-width fallback on current base
Expected: array (0 => 'Wide Thin',)
Actual: array (0 => 'WideThin',)
1 test files, 2 assertions, 2 failures
```

Focused green after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapDeclaredCountCidTrailingOperandSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps valid same-line declared-count CMap cidchar rows before trailing operand source-width fallback on current base
PASS keeps valid same-line declared-count CMap cidrange rows before trailing operand source-width fallback on current base
1 test files, 24 assertions, 0 failures
```

Direct regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapDeclaredCountCidTrailingOperandSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOverlongSourceCodeSourceWidthCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 46 assertions, 0 failures
```

Broader CMap/font source-width family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
Focused test run: 50 selected test files (root lock skipped)
50 test files, 1074 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapDeclaredCountCidTrailingOperandSourceWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-declared-count-cid-trailing-operand-source-width-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-cmap-declared-count-cid-trailing-operand-source-width-currentbase.php
```

The smoke exits 0 and emits one Gutenberg paragraph `Wide Thin` with `visible_text_preserved=true`, `cidchar_declared_rows_preserved=true`, `cidrange_declared_rows_preserved=true`, `trailing_operand_excluded=true`, `false_join_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3433 -> 3435`
- `wordpressScenarios`: `2789 -> 2790`
- Focused assertion count for the new test: `2 failing assertions red-first -> 24 passing assertions after fix`
- Mapped upstream denominator unchanged; this is additive native PHP behavior inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 source widths, `/DW` or partial metric misses, high/lazy CID ranges, UseCMap inheritance, plus/negative/real/missing declared counts, malformed declared-count row-slot consumption before valid rows, underdeclared or malformed codespace blocks, array-wrapped CID decoys, malformed CID target tails, overlong source-code guards, sparse code-space ordering, notdef rows, ToUnicode bfrange cardinality, Type3 CMap spacing, vertical W2 metrics, stream-filter/CMap boundary validation, DCT/image metadata, xref repair, metadata, forms, annotations, OCR, or model execution.

The bounded behavior is specifically preserving valid same-line declared-count CID rows before malformed trailing operands during Type0 Encoding CMap source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap tokenizer/block parser, Type0 Encoding CMap source segmentation, CIDFont width metrics, text-state spacing, styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
