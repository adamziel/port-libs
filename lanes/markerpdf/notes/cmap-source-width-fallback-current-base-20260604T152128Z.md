# markerPDF CMap Source Width Fallback Current Base

Session: `port-dev-markerpdf-source-width-20260604T152128Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260604T152128Z`

Base accepted HEAD: `8c67081dd95b93a80d08ab1af951474dab61463f`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker assembles page/block/line/span output.
- The native PHP fallback must preserve PDF text source-code boundaries and font metrics before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- This slice extends the accepted source-width fallback only for the boundary where predefined `/Identity-H` chunks do not have direct CID metric evidence, but the ToUnicode source keys do have explicit CIDFont `/W` or CIDSet metric evidence. In that case the PHP fallback uses the ToUnicode source keys for width grouping instead of applying default width to invalid combined chunks.

## Behavior Added

`PdfTextExtractor::textOperandSourceKeysForFontWidths()` now checks Identity-H/CID code-space source chunks before using them for glyph advance. If those chunks have no direct CID metric evidence and the ToUnicode source keys are more granular and directly covered by `/W` or CIDSet metrics, the extractor falls back to the ToUnicode source keys for width grouping.

Visible text decoding is unchanged. The change is bounded to text advance, same-line positioned gap decisions, source-space counting, and native styled-span bboxes.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 `/Identity-H` font fixture with:

- a ToUnicode CMap that declares one-byte source codes `<41>` through `<48>`;
- one-byte text operands `<41424344>` and `<45464748>`;
- descendant CIDFont `/W [65 68 1000 69 72 250]`;
- a second `Tm` positioned so Identity-H chunking of `4142`/`4344` creates a false WordPress word gap, while ToUnicode source-key widths keep `ABCDEFGH` joined.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base
Expected: array (0 => 'ABCDEFGH',)
Actual: array (0 => 'ABCD EFGH',)
1 test files, 31 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
PASS falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base
1 test files, 40 assertions, 0 failures
```

Adjacent regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 758 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `ABCD EFGH` for the accepted default-width fallback and `ABCDEFGH` for the new Identity-H metric-miss fallback, with `identity_metric_miss_tounicode_widths_applied=true`, `identity_metric_miss_false_gap_excluded=true`, `identity_metric_miss_span_widths=true`, and native-only execution flags.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

All passed. `git diff --check -- lanes/markerpdf` is recorded in the final handoff.

## Status Delta

- `phpPass`: `1066 -> 1067`
- `wordpressScenarios`: `1066 -> 1067`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font-width source boundary.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, CIDFont default `/DW` source fallback, ToUnicode codespace fallback, Identity-H/V no-ToUnicode fallback, Type0 Encoding CMap CID width priority, indirect `/W`/`DW`/`W2` parsing, CIDSet default-width grouping, Type3 CMap/CIDSet width grouping, source-space word spacing, vertical UCS2 CMap spacing, styled-span CID resource width, or FontDescriptor review. The new boundary is specifically metric-miss fallback from predefined Identity-H chunks to explicit ToUnicode source-key metrics before WordPress paragraph grouping.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, CIDFont width parser, CMap source tokenizer, content-token text-positioning path, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
