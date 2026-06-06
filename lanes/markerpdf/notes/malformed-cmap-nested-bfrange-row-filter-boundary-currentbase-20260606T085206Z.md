# markerPDF malformed CMap nested bfrange-row filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T085206Z`

Base accepted HEAD: `9bad70694349fdf8df2944b1d0fdaa86a6613e3b`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF text/font extraction before Markdown assembly. Under the current no-GPU lane scope, native PHP owns the CMap stream boundary for searchable PDFs: decoded ToUnicode programs may provide text mappings, but malformed nested program fragments inside arrays must not be treated as executable mapping rows or leak into WordPress paragraphs.

## Behavior

`PdfTextExtractor::parseToUnicodeRanges()` now consumes `beginbfrange` rows through a token-aware top-level scanner instead of a broad regex over the decoded block. Valid rows of these forms still work:

```text
<start> <end> <target>
<start> <end> [<target0> <target1> ...]
```

The scanner skips comments, literal strings, dictionaries, and nested arrays while preserving a top-level array token as the target operand. A complete decoy row hidden inside a target array is therefore ignored:

```text
1 beginbfrange
[ <004E> <004E> <004E0065007300740065006400200042006600720061006E0067006500200052006F007700200043004D006100700020004C00650061006B> ]
endbfrange
```

Before the fix, that nested row was matched by the range regex and visible text became `Nested Bfrange Row CMap Leakested Bfrange Row Safe Import`. After the fix, the CMap stream still decodes for review metadata, but the nested row cannot replace fallback Identity-H source text and WordPress-visible text remains `Nested Bfrange Row Safe Import`.

## Evidence

Red-first focused run after adding the test and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores nested full bfrange rows in filtered CMaps before current-base text extraction
Expected: array (
  0 => 'Nested Bfrange Row Safe Import',
)
Actual: array (
  0 => 'Nested Bfrange Row CMap Leakested Bfrange Row Safe Import',
)

1 test files, 1524 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1553 assertions, 0 failures
```

Adjacent CMap/filter/font/text extraction run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 2808 assertions, 0 failures
```

Broader CMap and DecodeParms family:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php
Focused test run: 40 selected test files (root lock skipped)
40 test files, 3096 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-nested-bfrange-row-currentbase.php
```

The smoke emits `safe_text_imported=true`, `nested_bfrange_row_decoy_excluded=true`, `cmap_name_not_imported_as_text=true`, `decoded_cmap_count=1`, `cmap_stream_count=1`, `filter_operand_policy=filters_resolved`, `filter_end_marker_policy=filter_end_markers_resolved`, `decoded_with_current_operands=true`, `unsupported_filter_count=0`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with `Nested Bfrange Row Safe Import` rendered as a Gutenberg paragraph.

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-nested-bfrange-row-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-nested-bfrange-row-currentbase.php

php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Status Delta

- `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` adds 1 focused PASS case and 29 net focused assertions after the red-first failure.
- `lane-status.json` records `phpPass 2477 -> 2478`.
- `lane-status.json` records `wordpressScenarios 2110 -> 2111`.

## Non-Overlap

This does not repeat accepted malformed CMap `/Filter` operands, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, unsupported/escaped filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, literal-string CMapName/usecmap decoys, overdeclared literal-string rows, nested bfrange target arrays, or nested bfchar arrays.

The bounded behavior is specifically a complete `beginbfrange` row hidden inside a decoded filtered CMap target array before ToUnicode replacement and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, ToUnicode mapping path, CMap stream review metadata, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope for this markerPDF lane and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.
