# markerpdf-cmap-malformed-codespace-declared-count-source-width-current-base-20260608T155329Z

## Scope

This isolated markerPDF patch stays in the no-GPU native searchable-PDF parser scope. It covers Type0 source-width fallback when a custom `/Encoding` CID CMap has a malformed `begincodespacerange` declared count such as `-1` or `1.5`.

Upstream markerPDF routes searchable PDF text through parser-backed pdftext/PDFium behavior before OCR/model fallback. This PHP slice ports the native CMap/font-width boundary needed before WordPress import without invoking Python, OCR, Surya, Texify, Torch, raster rendering, live services, or external PDF tools.

## Behavior

`PdfTextExtractor::cMapHasUnderdeclaredCodeSpaceRangeBlock()` now treats negative and non-integer CMap code-space declared counts as malformed. That makes local ToUnicode or Encoding CMap source rows fail closed when the code-space block cannot be trusted, matching the existing underdeclared-row boundary.

The focused fixture uses valid `/ToUnicode` text for `Wide Thin`, raw descendant CID widths for source codes `0x10..0x13`, and a malformed local Encoding CMap with a decoy `begincidrange <10> <13> 60`. Before the fix, malformed code-space counts left the decoy CID map active without a valid code-space boundary, so the first span measured `[0, 0, 12, 12]`. After the fix, source-width fallback ignores the local malformed CID rows and measures `[0, 0, 48, 12]`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedCodespaceDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores negative codespace declared-count Encoding CMap rows before source-width fallback on current base
PASS ignores real-number codespace declared-count Encoding CMap rows before source-width fallback on current base

1 test files, 24 assertions, 0 failures
```

Adjacent CMap/source-width regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedCodespaceDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapUnderdeclaredEncodingCodespaceSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapRealDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
7 test files, 530 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-codespace-declared-count-source-width-currentbase.php
```

The smoke exits 0 and emits `plain_text_preserved=true`, `runs_preserved=true`, `malformed_codespace_count_cid_widths_rejected=true`, `false_join_excluded=true`, `encoding_cmap_program_visible_text_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ToUnicode underdeclared codespace fail-closed behavior, Type0 Encoding underdeclared codespace rows, malformed declared-count CID row-slot consumption, plus/negative/real CID mapping block counts, decoy unrelated CID rows, UseCMap inheritance, WMode handling, overlong/short source-width fallback, lazy CID ranges, sparse code-space ordering, notdef rows, or stream-filter CMap boundaries. The bounded behavior is specifically malformed `begincodespacerange` declared counts before local Encoding CMap CID rows can affect source-width geometry.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP CMap parser, CIDFont width lookup, styled text geometry, and WordPress smoke path under `pdf-text-dictionary-core`.

Root harness not run - isolated micro-slice.
