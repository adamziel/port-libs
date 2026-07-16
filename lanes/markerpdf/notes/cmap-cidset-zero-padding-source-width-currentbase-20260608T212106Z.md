# CMap CIDSet Zero-Padding Source Width Fallback

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T212106Z`

Accepted base: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Source Truth

Upstream markerPDF routes searchable PDF text through parser-backed text extraction before OCR/model stages. In the no-GPU PHP lane, this maps to native PDF font parsing: Type0 Encoding CMaps map source codes to descendant CIDs, descendant CIDFont `/W` widths are keyed by those CIDs, and `/CIDSet` constrains which subset CIDs are present for default-width evidence.

This slice covers a malformed but import-relevant boundary where the Encoding CMap has both padded two-byte source rows and one-byte suffix rows. The padded rows point at CIDs absent from `/CIDSet`; the suffix rows point at present CIDs with explicit `/W` widths. Source-width fallback must not treat the absent padded CIDs as width evidence merely because `/DW` exists.

## Red-First Evidence

Before the source change, an inline probe of the new fixture decoded `ABCD EFGH` but used the absent padded CID fallback geometry:

- first span bbox: `[0.0, 0.0, 24.0, 12.0]`
- second span bbox: `[24.0, 0.0, 48.0, 12.0]`

After the change, the same fixture uses the present suffix CIDs:

- first span bbox: `[0.0, 0.0, 48.0, 12.0]`
- second span bbox: `[48.0, 0.0, 60.0, 12.0]`

## Implementation

`PdfTextExtractor::zeroPaddedSourceKeysForFontWidths()` now:

- requires exact mapped zero-padding candidates to have usable font-width evidence before accepting them;
- checks combined candidates through `cidForWidthSourceKey()` instead of raw `hexdec($combined)`;
- allows a suffix CID to win when a combined padded source has an Encoding CMap mapping but that mapped CID lacks usable `/CIDSet`/width evidence.

`fontWidthMapContainsCid()` and `sourceKeyHasFontWidthEvidence()` now treat a CID absent from `/CIDSet` as missing width evidence before trusting `/DW`, while still preserving explicit `/W` metrics.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapCidSetZeroPaddingSourceWidthCurrentBaseTest.php
```

Result: `1 test files, 12 assertions, 0 failures`.

Adjacent regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapCidSetZeroPaddingSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyCidRangeSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyCidRangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php
```

Result: `10 test files, 487 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-cmap-cidset-zero-padding-source-width-currentbase.php
```

Result: exits 0 and emits `source_width_boundaries_preserved=true`, `wide_suffix_cids_used=true`, `absent_padded_cid_width_excluded=true`, `thin_suffix_cids_used=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, CIDSet parser, styled text grouping path, and WordPress smoke renderer. GPU/model OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope for markerPDF under the current no-GPU lane direction.

## Non-Overlap

This does not repeat Identity-H/UCS2 fallback, lazy CID ranges, lazy ToUnicode bfranges, repeated zero padding, notdef ordering, malformed CMap declared-count handling, Type3 CMap/CIDSet defaults, vertical CIDSet grouping, classic xref rebuild, stream-filter, metadata, annotation, or image/filter slices. The new behavior is specifically CIDSet-aware source-width fallback when zero-padded Encoding CMap rows map to absent subset CIDs but suffix CIDs have explicit widths.
