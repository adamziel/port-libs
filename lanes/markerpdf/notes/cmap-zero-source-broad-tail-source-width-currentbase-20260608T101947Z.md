# markerPDF CMap Zero Source Broad Tail Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T101947Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T101947Z`

Base accepted HEAD: `0eb5a37aa6bfca15c74db9c73a3d7e19e138d884`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker assembles page/block/line/span output.
- The native PHP fallback must preserve PDF CMap source-code boundaries and descendant CIDFont width evidence before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- PDF CMap source code `00` is not always padding. If the ToUnicode CMap contains a direct `<00>` source row, that row can be real text and must be preserved before the extractor falls back to a broader unmapped code-space tail.

## Behavior Added

`PdfTextExtractor::shouldPreferMappedCMapSourcePrefix()` now treats an all-zero mapped prefix as padding only when the prefix is not a direct ToUnicode CMap row. For direct zero rows, the extractor also requires the following source key to be mappable before preferring the shorter prefix over an unmapped broad code-space. That keeps `<00>` as a real source for `Z` in `<00><41><0045>`, while preserving broad fallback decoding for the tail `<0045>` and `<0046>`.

The change is bounded to text decoding chunk selection before source-width grouping. Existing zero-padded source-width fallback cases remain covered by adjacent tests.

## Focused Fixture

`PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php` adds a Type0 font fixture with:

- a ToUnicode CMap containing `<00> <005A>` and `<41> <0041>`;
- a broad fallback code-space `<0000> <FFFF>` whose tail values `<0045>` and `<0046>` have no direct ToUnicode rows;
- an Encoding CMap with direct CID rows for `<00>` and `<41>` plus a broad CID range for `<0045>` through `<0047>`;
- descendant CIDFont widths proving the first text span remains `[0.0, 0.0, 18.0, 12.0]` and the second span starts at `18.0`.

Before the fix the native extractor returned `AE F`, dropping the real zero source row as if it were padding. After the fix it returns `ZAE F` and preserves `['ZAE', 'F']` runs.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps explicit zero source rows before unmapped broad CMap source-width tails on current base
Expected: array (
  0 => 'ZAE F',
)
Actual: array (
  0 => 'AE F',
)

1 test files, 1 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps explicit zero source rows before unmapped broad CMap source-width tails on current base

1 test files, 11 assertions, 0 failures
```

Adjacent regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyCidRangeZeroPaddedSourceWidthCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 420 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-zero-source-broad-tail-currentbase.php
```

The smoke exits 0 and emits `<p>ZAE F</p>` with `explicit_zero_source_mapping_preserved=true`, `text_runs_preserved=true`, `broad_tail_width_fallback_preserved=true`, `stale_zero_padding_drop_excluded=true`, `false_zero_tail_split_excluded=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapZeroSourceBroadTailSourceWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-zero-source-broad-tail-currentbase.php
```

All passed.

## Status Delta

- `phpPass`: `3038 -> 3039`
- `wordpressScenarios`: `2512 -> 2513`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font-width source boundary.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded ToUnicode bfrange fallback, zero-padded Encoding CMap cidrange fallback, repeated zero-padding collapse, broad ToUnicode code-space explicit non-zero row recovery, mixed explicit non-zero rows plus broad fallback tails, late CMap usecmap inheritance, malformed declared-count handling, or CID target tail rejection. The new boundary is specifically a direct all-zero CMap source row before a mappable one-byte prefix and an unmapped broad two-byte fallback tail.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, Encoding CMap parser, CIDFont width parser, content-stream text operator path, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
