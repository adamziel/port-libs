# markerPDF malformed CMap nested target-array filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T123919Z`

Base accepted HEAD: `b075679df11f2da22eb4cf1f317dbce011ea97e8`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF text/font extraction before Markdown assembly. Under the current no-GPU lane scope, the native PHP parser owns the pre-import CMap boundary: malformed filtered ToUnicode CMap target arrays must not replace fallback source text or leak decoded CMap program bytes into WordPress paragraphs.

## Behavior

`PdfTextExtractor::parseToUnicodeRanges()` now reads `beginbfrange` target arrays with a token-aware top-level hex scanner. Literal strings, dictionaries, nested arrays, comments, and malformed nested array tails are skipped instead of being searched with a raw hex regex.

The focused fixture uses a FlateDecode ToUnicode stream with a malformed bfrange target array:

```text
<004E> <004E> [ [<004E00650073007400650064002000540061007200670065007400200043004D006100700020004C00650061006B>] ]
```

Before the fix, the nested array hex string was treated as a real target and visible text became `Nested Target CMap Leakested Target Safe Import`. After the fix, the CMap stream still decodes for review metadata, but the malformed nested target is ignored and WordPress-visible text remains `Nested Target Safe Import`.

## Evidence

Red-first focused run after adding the test and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores nested bfrange target arrays in filtered CMaps before current-base text extraction
Expected: array (
  0 => 'Nested Target Safe Import',
)
Actual: array (
  0 => 'Nested Target CMap Leakested Target Safe Import',
)

1 test files, 1057 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1086 assertions, 0 failures
```

Adjacent CMap/filter/text extractor run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 2376 assertions, 0 failures
```

Broader CMap and DecodeParms family:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php
Focused test run: 23 selected test files (root lock skipped)
23 test files, 1689 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
```

The smoke emits `nested_target_array_decoded_cmap_count=1`, `nested_target_array_cmap_name=WPNestedTargetArrayBoundary-H`, `nested_target_array_filter_operand_policy=filters_resolved`, `nested_target_array_decoy_excluded=true`, `leaking_cmap_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with `Nested Target Safe Import` rendered as a paragraph.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap `/Filter` operands, indirect/stale filter owner selection, malformed DecodeParms parameter rejection, null-filter DecodeParms alignment, all-null filter stacks, identity/private Crypt filter policy, unsupported/escaped filter names, explicit filter EOD enforcement, post-`endcmap` cleanup, complete second-program exclusion, literal-string CMapName/usecmap decoys, or overdeclared literal-string bfchar rows.

The bounded behavior is specifically nested arrays inside decoded filtered CMap `beginbfrange` target arrays before ToUnicode replacement and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, ToUnicode mapping path, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity remains intentionally out of scope for this markerPDF lane and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, Texify, tabled-pdf, model downloads, Streamlit/FastAPI workers, and external OCR/rendering helpers.
