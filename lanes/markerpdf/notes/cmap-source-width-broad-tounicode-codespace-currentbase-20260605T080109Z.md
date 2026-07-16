# markerPDF CMap Source Width Broad ToUnicode Codespace

Session: `port-dev-markerpdf-source-width-20260605T075331Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T075331Z`
Base accepted HEAD: `1b72408ed94109ba862fc9360cd5e316e7f53484`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction to `pdftext.extraction.dictionary_output`; Marker then turns those page dictionaries into blocks/spans for output. This native PHP slice stays inside that no-GPU boundary by fixing CMap source-code text extraction before WordPress paragraph rendering.

Relevant PDF parser behavior: explicit ToUnicode `bfchar`/`bfrange` source rows are source-code mappings. If a malformed broad `begincodespacerange` conflicts with explicit rows, the explicit rows must still be usable for decoding and for glyph-width grouping when the broader chunks have no direct font metric evidence.

## Native Behavior Added

`PdfTextExtractor` now lets visible ToUnicode decoding prefer explicit source rows over a broader malformed codespace. The font-width path also has a bounded fallback that uses those explicit mapped source rows only when the broad chunks lack direct CIDFont width evidence, preserving the existing partial-CID-metric behavior.

The focused fixture declares a malformed `<0000> <FFFF>` ToUnicode codespace while also declaring explicit one-byte rows `<41>` through `<48>`. Before the fix the extractor decoded the page as two source chunks (`䅂䍄 䕆䝈`) and used two default-width spans. After the fix the WordPress text path emits `ABCD EFGH`, preserves `ABCD`/`EFGH` text runs, and keeps span bboxes at `[0,0,48,12]` and `[48,0,60,12]`.

## Evidence

Red-first focused check after adding the test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL prefers explicit ToUnicode source rows over malformed broad codespace before source-width fallback on current base
Expected: array (
  0 => 'ABCD EFGH',
)
Actual: array (
  0 => '䅂䍄 䕆䝈',
)

1 test files, 185 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 196 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke reports `broad_tounicode_codespace_explicit_rows_recovered=true`, `broad_tounicode_codespace_runs_preserved=true`, `broad_tounicode_codespace_decoy_chunks_excluded=true`, `broad_tounicode_codespace_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused behavior cases: `1595 -> 1596`.
- Focused source-width assertions: `184 -> 196` after adding this case.
- WordPress scenarios: `1478 -> 1479`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, CIDFont metric parser, source-code segmentation, text-position grouping, and WordPress paragraph smoke path. Full upstream markerPDF model parity remains outside this lane under the no-GPU/model directive.

## Non-Overlap

This does not repeat the accepted zero-padded source-width fallback, ToUnicode usecmap, row-count, comment stripping, CID CMap malformed-codespace recovery, CID range remapping, Type0 Encoding CMap width priority, vertical width, or Type3 width slices. The new behavior is limited to explicit ToUnicode source rows winning over a malformed broader ToUnicode codespace when the broad chunks have no direct font metric evidence.
