# markerPDF CMap source-key odd-hex width fallback current base

Session: `port-dev-markerpdf-source-width-20260605T034706Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T034706Z`
Base accepted HEAD: `45c71a1afa8b5325fb861f358457c511540bfeeb`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker builds page/block/line/span output.
- The native PHP no-GPU fallback owns PDF text source-byte segmentation, ToUnicode CMap parsing, and CIDFont width advance before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- PDF hex strings with an odd number of nibbles are padded on the right. The accepted text operand path already did this for `<4142434>`; this slice applies the same source syntax to odd ToUnicode CMap source keys such as `<4>`.

## Behavior Added

`PdfTextExtractor::normalizeHexKey()` now right-pads odd-length CMap hex strings before storing source keys, codespace bounds, range operands, and Unicode targets. This keeps a ToUnicode source entry `<4> <0044>` aligned with source byte `0x40`, not `0x04`, so source-width fallback can use the same CIDFont `/W` evidence as the text-showing operand.

Visible text and styled-span geometry now preserve `ABCDEFGH` for a malformed but recoverable Type0 font that uses `<4>` in the ToUnicode CMap and `<4142434>` in the content stream. Before this fix the native fallback decoded `ABC@ EFGH` and could introduce a false paragraph gap.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` now includes a Type0 `/Identity-H` fixture with:

- ToUnicode one-byte codespace `<00> <FF>`;
- an odd source-key row `<4> <0044>`;
- text operand `<4142434>` which PDF right-padding makes `41 42 43 40`;
- descendant CIDFont `/W [64 67 1000 69 72 250]`;
- a second positioned text run that fails if source-key padding or width grouping is wrong.

## Evidence

Red-first probe before the source fix:

```text
array (
  0 => 'ABC@ EFGH',
)
```

Passing direct focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 120 assertions, 0 failures
```

Adjacent CMap/font regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 953 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `odd_cmap_source_key_right_padding_applied=true`, `odd_cmap_source_key_false_at_sign_excluded=true`, `odd_cmap_source_key_false_gap_excluded=true`, `odd_cmap_source_key_span_widths=true`, and the no-Python/no-model/no-external-tool execution flags.

## Status Delta

- `phpPass`: `1372 -> 1373`
- `wordpressScenarios`: `1312 -> 1313`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped font/CMap source-width fallback cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted odd-length text operand padding, zero-padded source-width fallback, predefined Identity-H source widths, predefined UCS2-H fallback, explicit `/W` all-miss fallback, `/DW`-only all-miss fallback, partial metric-miss fallback, horizontal or vertical `TJ` adjustment gaps, one-byte codespace padding fallback, Type0 Encoding CMap CID width priority, UseCMap inheritance, indirect `/W`/`DW`/`W2` operands, CIDSet grouping, Type3 CMap width grouping, bfrange surrogate widths, quote-operator spacing, vertical `/W2`, styled-span width-advance geometry, xref repair, parser stream boundaries, metadata, annotations, forms, or image/filter review.

The bounded behavior is specifically odd-length CMap source-key right-padding before ToUnicode decoding and CIDFont source-width grouping for WordPress paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, predefined CID CMap source tokenizer, CIDFont width metrics, content-token text-positioning path, styled-span bbox path, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
