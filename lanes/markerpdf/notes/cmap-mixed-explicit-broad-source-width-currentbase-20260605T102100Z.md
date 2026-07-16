# markerpdf CMap mixed explicit/broad source-width fallback current base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T102100Z`
Base accepted HEAD: `5f2f75b325b968a05bd7a84a452d971a290e2ad6`

## Source Truth

- Native no-GPU markerPDF scope only: searchable-PDF text, CMaps, font widths, and styled span geometry. No OCR, model, Surya/Texify/Torch, Streamlit/FastAPI, or upstream model benchmark execution.
- PDF CMap parsing keeps source character-code boundaries from ToUnicode CMap mappings and codespace ranges, while CIDFont `/W` widths are keyed by the source CID/code used for glyph advance. A broad codespace such as `<0000> <FFFF>` should not collapse explicit one-byte `bfchar` rows that appear before an unmapped broad fallback tail.
- Prior markerPDF CMap width slices already covered malformed broad codespace fallback, high-CID range source widths, Identity-H partial metric fallbacks, CMap name indirection, vertical `/W2`, Type3 fallback exclusion, and source-width bbox preservation. This slice is intentionally bounded to mixed explicit non-zero source rows plus an unmapped broad fallback tail.

## Implementation

- `PdfTextExtractor::decodeHexStringWithToUnicodeMap()` can now prefer a shorter explicit non-zero mapped source prefix before an unmapped broader codespace source when decoding text, so `<41> <0041>` wins at the start of `<4142...>` even when a malformed `<0000> <FFFF>` codespace is present.
- Width source-key fallback remains conservative: normal `textOperandSourceKeys()` keeps the existing broader source chunks, and a new partial-metric fallback splits only the broad chunks that lack direct width evidence into mapped source keys that all have width evidence.
- This preserves the accepted Identity-H partial metric behavior where a direct metric on `00410042` must stay a single wide source advance, while still using explicit one-byte metrics for `41`, `42`, `43`, and `44` before broad fallback `0045`/`0046`.

## Evidence

Red-first focused check after adding the regression before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 227 assertions, 1 failures
```

Failure: the new mixed CMap case decoded `䅂䍄EF` instead of `ABCDEF`.

Final focused check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 236 assertions, 0 failures
```

Adjacent CMap/font/text extractor family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfCMap|PdfFont|PdfParserMalformedCMap|PdfTextExtractor).*Test\.php$' | sort)
52 test files, 2546 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-mixed-source-width-currentbase.php
```

The smoke emits `explicit_source_rows_precede_broad_codespace=true`, `mixed_tail_fallback_preserved=true`, `source_width_bbox_preserved=true`, `broad_codespace_prefix_pairs_excluded=true`, `nul_bytes_excluded=true`, and no Python/model/external-tool execution flags.

## Non-overlap

Avoids the accepted classic xref rebuild, xref stream repair, metadata, outline, annotation, image, encryption, page-geometry, object-stream, Type3, vertical CMap, high-CID range, Identity-H partial metric, and malformed broad CMap fallback clusters. This patch only adjusts source-code boundary choice when explicit non-zero CMap rows precede an unmapped broad fallback codespace in searchable PDF text.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF parser, ToUnicode CMap parser, font-width map, and styled text extraction path.

## Next Task

Continue markerPDF native no-GPU parser work on a non-overlapping searchable-PDF behavior such as font encodings and widths, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
