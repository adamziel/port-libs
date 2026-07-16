# markerpdf malformed CMap filter boundary current-base 2026-06-08T124237Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T124237Z`
- Base accepted HEAD: `b6b4875d02ae4786542ed2436bf47e7f8fe62fb2`
- Native no-GPU scope only: searchable-PDF text extraction, filtered ToUnicode CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, raster rendering, or external PDF tools.

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text through `marker/pdf/extract_text.py` and `pdftext.extraction.dictionary_output(...)` before Markdown/block conversion. Under the no-GPU markerPDF lane scope, the native PHP parser owns the equivalent searchable-PDF CMap/filter boundary before WordPress import.

Relevant PDF/CMap behavior for this slice: `endcmap`, `beginbfchar`, and related CMap keywords are operators only at token level. Bytes inside balanced PostScript procedure bodies such as `{ ... }` are procedure data and must not prematurely terminate the parser-bounded CMap program or produce mapping blocks.

## Behavior

`PdfTextExtractor::cMapEndOperatorOffset()` and `nextCMapOperatorOffset()` now skip balanced CMap procedure bodies while scanning decoded filtered CMap streams. The scanner already skipped comments, literal strings, hex strings, dictionaries, and arrays; this patch adds the same boundary for `{ ... }` procedure bodies.

The focused fixture uses a FlateDecode ToUnicode CMap with a procedure body containing a decoy `endcmap` token and leak text before the real `begincodespacerange`/`beginbfchar` mapping. Before the source change, the parser bounded the CMap at the procedure-body token and extracted no mapped text. After the change, WordPress-visible text is `Procedure End Safe Import`, while the procedure decoy text, CMap name, CMap operators, and bytes after the real top-level `endcmap` remain excluded.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores endcmap tokens inside filtered CMap procedure bodies before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Procedure End Safe Import',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores endcmap tokens inside filtered CMap procedure bodies before current-base text extraction

1 test files, 42 assertions, 0 failures
```

Adjacent malformed CMap/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayEndOperatorFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFallbackStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
...
8 test files, 1924 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-procedure-end-operator-currentbase.php --self-test
OK markerpdf-malformed-cmap-procedure-end-operator-currentbase
```

The smoke emits `safe_text_imported=true`, `procedure_end_operator_decoy_excluded=true`, `decoded_cmap_count=1`, `filter_operand_policy=filters_resolved`, `filter_decode_policy=filter_decoders_resolved`, `post_endcmap_bytes_excluded=true`, `parser_bounded_cmap_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar/array/literal/dictionary `/Filter` operands, duplicate `/Filter` or `/DecodeParms`, stale/free/indirect filter owner boundaries, escaped filter names, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter EOD enforcement, post-`endcmap` operator payload exclusion, complete second CMap program exclusion, literal-string operator decoys, array-contained `endcmap` tokens, overdeclared literal-string rows, nested bfchar/bfrange target arrays, short or overlong bfrange target arrays, legal split-row recovery, codespace nested-array handling, UseCMap post-name boundaries, CMap source-width fallback, CIDFont widths, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is only operator recognition while bounding and scanning decoded filtered CMap programs when a CMap keyword appears inside a balanced PostScript procedure body before the real top-level operator.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP object scanner, stream dictionary reader, Flate decoder, CMap parser, text extraction pipeline, review metadata path, focused test harness, and WordPress smoke renderer. Live `pdftext`/PDFium execution, Surya/Torch OCR/layout models, Texify, tabled-pdf model inference, Streamlit/FastAPI workers, benchmark downloads, online services, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction; none were executed.

## Next Task

Continue non-overlapping native markerPDF parser work around CMaps/font encodings, stream filter metadata, xref repair, page geometry, annotations/forms/security preflight, image metadata, and supplied-boundary table or equation handoffs.
