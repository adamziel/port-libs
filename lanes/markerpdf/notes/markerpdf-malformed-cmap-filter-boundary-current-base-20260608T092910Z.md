# markerpdf malformed CMap filter boundary current-base 2026-06-08T092910Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T092910Z`
- Base accepted HEAD: `fb5a3e0b78929bd36e27241d21430a239276e33e`
- Native no-GPU scope only: searchable-PDF text extraction, filtered ToUnicode CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, raster rendering, or external PDF tools.

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text through `marker/pdf/extract_text.py` and `pdftext.extraction.dictionary_output(...)` before Markdown/block conversion. Under the no-GPU lane scope, the native PHP parser owns the equivalent searchable-PDF boundary before WordPress import.

Relevant PDF/CMap behavior for this slice: `endcmap` is a CMap/PostScript operator only at token level. If the bytes `endcmap` appear inside a PDF array in a decoded CMap program, they are array data and must not prematurely terminate the parser-bounded ToUnicode program. Bytes after the real top-level `endcmap` still remain excluded from parsing and visible WordPress text.

## Behavior

`PdfTextExtractor::cMapEndOperatorOffset()` now skips PDF arrays while scanning decoded CMap streams for `endcmap`, matching the existing string, comment, hex-string, and dictionary token guards. This preserves valid mappings after malformed array data that contains an `endcmap` token, while retaining the existing fail-closed post-real-`endcmap` boundary.

The focused fixture uses a FlateDecode ToUnicode CMap with:

- a `beginbfrange` target array containing `endcmap` plus decoy text;
- a later valid `beginbfchar` row mapping `<0001>` to `Array End Safe Import`;
- normal CMap trailer bytes after the real `endcmap`.

Before the fix, the parser bounded at the array-contained `endcmap`, omitted the later valid row, and extracted no text. After the fix, WordPress-visible text is `Array End Safe Import`; the decoy array text, CMap name, CMap operators, and post-real-`endcmap` bytes stay out of visible text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores endcmap tokens inside filtered CMap arrays before current-base text extraction
Values are not identical
Expected: array (
  0 => 'Array End Safe Import',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores endcmap tokens inside filtered CMap arrays before current-base text extraction

1 test files, 42 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 1882 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-array-end-operator-currentbase.php --self-test
OK markerpdf-malformed-cmap-array-end-operator-currentbase
```

The smoke emits `safe_text_imported=true`, `array_end_operator_decoy_excluded=true`, `decoded_cmap_count=1`, `filter_operand_policy=filters_resolved`, `filter_decode_policy=filter_decoders_resolved`, `post_endcmap_bytes_excluded=true`, `parser_bounded_cmap_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar/array/literal/dictionary `/Filter` operands, duplicate `/Filter` or `/DecodeParms`, stale/free/indirect filter owner boundaries, escaped filter names, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter EOD enforcement, post-`endcmap` operator payload exclusion, complete second CMap program exclusion, literal-string operator decoys, overdeclared literal-string rows, nested bfchar/bfrange target arrays, short/overlong bfrange target arrays, legal split-row recovery, codespace nested-array handling, UseCMap post-name boundaries, CMap source-width fallback, CIDFont widths, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is only `endcmap` token recognition while bounding a decoded filtered CMap program when the token appears inside a PDF array before the real top-level program terminator.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object scanner, stream dictionary reader, Flate decoder, CMap parser, text extraction pipeline, review metadata path, and WordPress smoke renderer. Live `pdftext`/PDFium execution, Surya/Torch OCR/layout models, Texify, tabled-pdf model inference, Streamlit/FastAPI workers, benchmark downloads, online services, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction; none were executed.

## Next Task

Continue non-overlapping native markerPDF parser work around CMaps/font encodings, stream filter metadata, xref repair, page geometry, annotations/forms/security preflight, image metadata, and supplied-boundary table or equation handoffs.
