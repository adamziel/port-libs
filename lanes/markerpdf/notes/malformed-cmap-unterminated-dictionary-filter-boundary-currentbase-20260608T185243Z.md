# Malformed CMap Unterminated Dictionary Filter Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T185243Z`

Base accepted HEAD: `f9bba6f9c783bd48666fbc44e7bc915ba6249e5f`

## Source Truth

Upstream markerPDF delegates searchable-PDF CMap extraction to PDF text parsing paths before model/OCR handoff. Under the no-GPU markerPDF scope, this patch keeps the native PHP parser aligned with PDF lexical boundaries: a dictionary opened with `<<` must close with `>>`; CMap operators or `/CMapName` directives that appear after an unterminated dictionary in a filtered CMap stream are not trusted as top-level CMap program directives.

## Behavior

The focused fixture builds filtered ToUnicode CMaps whose malformed dictionaries contain later `bfchar`, `bfrange`, and forged `/CMapName` directives. Before the fix, the forged base CMap name could be accepted after the unterminated dictionary, letting a derived `usecmap` resolve the malformed base and replace visible source text `OK` with `NO`.

The parser now stops CMap lexical scans at unterminated dictionaries:

- CMap names after malformed dictionaries are ignored.
- `usecmap` names after malformed dictionaries are ignored.
- `WMode` directives after malformed dictionaries are ignored.
- Mapping block data after malformed dictionaries is excluded.
- Valid text before the malformed boundary still imports through source-width fallback.

## Evidence

Red-first focused check before the parser fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnterminatedDictionaryFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 71 assertions, 1 failures`; the forged CMap mapping produced `NO` instead of expected `OK`.

Focused check after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnterminatedDictionaryFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 81 assertions, 0 failures`.

Adjacent CMap/filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnterminatedDictionaryFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureDirectiveFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapHexStringFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDanglingFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectNullFilterTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNamedUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLengthOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapProcedureUseCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php`

Result: `15 test files, 2508 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-unterminated-dictionary-filter-currentbase.php`

Result: exits `0` with `forged_cmap_name_rejected=true`, `forged_base_not_referenced=true`, `source_width_fallback_preserved=true`, `visible_text="OK"`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF parser, existing stream filter decoding, and CMap scanner. No Python, OCR/model execution, multiprocessing, or external PDF tools were invoked.

Root harness: not run - isolated micro-slice.
