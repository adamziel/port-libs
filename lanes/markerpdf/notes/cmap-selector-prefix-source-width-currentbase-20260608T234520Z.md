# markerPDF CMap Selector-Prefix Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260608T234520Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260608T234520Z`

Base accepted HEAD: `04878c2d5c57d16172dcae66b4ced2d6a4447658`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` boundary before Markdown/WordPress assembly.
- Under the current no-GPU markerPDF scope, the native PHP fallback owns searchable-PDF text, CMap, and CIDFont width behavior before paragraph grouping. No pdftext, pypdfium/PDFium, Python, OCR/model workers, or external PDF tools were invoked.
- This slice is bounded to malformed Type0 CMap source-code boundaries where an Encoding CMap source has a private nonzero selector prefix and ToUnicode only maps the trailing source bytes. The prefix must not become visible WordPress text, while the full Encoding CMap source remains authoritative for CIDFont width grouping.

## Behavior Added

`PdfTextExtractor::decodeHexStringWithToUnicodeMap()` now has a guarded CID-source suffix fallback. It activates only when:

- the CID Encoding CMap can segment the whole operand into CID-mapped source keys;
- each CID source key is directly mapped by ToUnicode or has an explicit mapped suffix;
- at least one longer CID source key uses that mapped suffix.

That keeps selector bytes such as the leading `20 00` in `<200041>` private, decodes the trailing `<41>` through ToUnicode, and still uses the full `<200041>` Encoding CMap source to apply descendant CIDFont `/W` metrics. Existing broad-code, explicit zero-source, sparse CMap, and zero-padding fallbacks remain on the previous path unless the suffix guard is satisfied.

## Focused Fixture

`PdfCMapSelectorPrefixSourceWidthCurrentBaseTest.php` builds a Type0 fixture with:

- an Encoding CMap codespace/range `<200041> <200048>` mapping to CIDs `65..72`;
- a ToUnicode CMap with explicit one-byte rows `<41>` through `<48>`;
- descendant CIDFont `/W [65 68 1000 69 72 250]`;
- two positioned text operands whose correct source-width grouping preserves `ABCD EFGH`.

Before the fix, a red-first probe decoded the selector byte `0x20` as visible spaces, producing ` A B C D E F G H` while styled span geometry already used the longer CID source widths. After the fix, visible text is `ABCD EFGH`, runs are `ABCD` and `EFGH`, and span bboxes remain `[0,0,48,12]` and `[48,0,60,12]`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSelectorPrefixSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps nonzero selector-prefix CMap source bytes private before source-width fallback on current base

1 test files, 11 assertions, 0 failures
```

Adjacent CMap/font source-width family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
Focused test run: 55 selected test files (root lock skipped)
55 test files, 1143 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-selector-prefix-source-width-currentbase.php
```

The smoke exits 0, emits one paragraph `<p>ABCD EFGH</p>`, and reports `selector_prefix_visible_text_excluded=true`, `source_width_runs_preserved=true`, `cid_source_widths_applied=true`, `false_selector_spaces_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapSelectorPrefixSourceWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-selector-prefix-source-width-currentbase.php
```

All passed.

## Status Delta

- `phpPass`: `3595 -> 3596`
- `wordpressScenarios`: `2901 -> 2902`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped native CMap/font source-width boundary.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padding collapse, explicit zero-source broad-tail preservation, broad ToUnicode code-space explicit-row recovery, sparse bfrange/cidrange source ordering, malformed declared-count CID rows, array decoys, overlong source-code rejection, notdef rows, Type3 CharProcs, xref repair, stream filters, metadata, annotations, forms, image/filter review, supplied tables/equations, OCR, or model execution. The new boundary is specifically nonzero selector-prefix bytes in a longer Type0 Encoding CMap source whose visible ToUnicode text comes from explicit trailing suffix rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, CIDFont width parser, content-stream text operator path, styled-span extraction path, and WordPress smoke renderer. Full upstream model/OCR/PDFium parity remains intentionally out of scope under the current markerPDF no-GPU directive.
