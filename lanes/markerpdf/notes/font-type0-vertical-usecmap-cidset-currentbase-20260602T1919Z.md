# font-type0-vertical-usecmap-cidset-currentbase-20260602T1919Z

Micro-slice: `font-type0-vertical-usecmap-cidset-currentbase`

Base accepted HEAD: `4dc1f21b98948ff243f10a6054e126d012098006`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` converts `pdftext.extraction.dictionary_output` dictionaries into Marker page blocks for digital PDF text extraction: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext` documents structured extraction as pypdfium2-backed text, line, bbox, and font output, so this native fallback must preserve low-level PDF font/code boundaries before WordPress block conversion when Python/PDFium/model execution is unavailable: https://github.com/datalab-to/pdftext
- PDF CMap/CIDFont source truth: `usecmap` inherits base CMap codespace/mappings before local rows, `Identity-V` supplies vertical writing mode and 2-byte CID source boundaries, and `/CIDSet` subset membership plus `/DW2` default vertical displacement affects text positioning. Adobe's Acrobat SDK API exposes Type0 width fields including `dw2`/`w2`: https://opensource.adobe.com/dc-acrobat-sdk-docs/acrobatsdk/apireference/PDFEdit_Layer/PDSysFont.html

## Implemented Behavior

`PdfTextExtractor::parseCidCMap()` now recognizes predefined `Identity-H` and `Identity-V` CMaps when they are inherited through `usecmap`, not only when they are the font's direct `/Encoding` name.

The focused fixture builds a Type0 font whose `/Encoding` is a CMap stream dictionary with `/UseCMap /Identity-V`, no local `begincodespacerange`, no `/ToUnicode`, and a descendant CIDFont with `/DW2` plus a compressed `/CIDSet`. The native fallback now inherits the 2-byte source-code boundaries before text decoding and before CIDSet vertical displacement selection:

- CID 0x0041 is absent from `/CIDSet`, so it falls back to a 500-unit vertical displacement and preserves the positioned gap in `A Word`.
- CIDs used by `VertImport` are present in `/CIDSet`, so they keep the `/DW2` default vertical displacement and do not split into `Vert Import`.
- UTF-16-style Identity-V source bytes decode without NUL leakage.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL inherits predefined Type0 vertical UseCMap codespace before CIDSet width grouping on current base
Actual: NUL-prefixed source bytes and `Vert Import`
1 test files, 1 assertions, 1 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS inherits predefined Type0 vertical UseCMap codespace before CIDSet width grouping on current base
1 test files, 7 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-vertical-usecmap-cidset-currentbase.php
predefined_vertical_usecmap_inherited=true
identity_v_codespace_decoded=true
excluded_cid_fallback_gap_preserved=true
included_cid_default_vertical_width_preserved=true
paragraphs: A Word, VertImport
```

## Focused Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type0-vertical-usecmap-cidset-currentbase.php
No syntax errors detected.

php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 635 assertions, 0 failures

jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
Both passed with no output.
```

## Non-Overlap

This does not repeat accepted inline `usecmap`, ToUnicode `usecmap` inheritance, CMap dictionary `/UseCMap /Name` with named base streams, object-valued Type0 `UseCMap` width grouping, direct `/Identity-V` font encodings, predefined vertical CMap writing-mode detection, direct/indirect `/W2` parsing, direct vertical CIDSet fallback, Type0 descriptor `/DW`, horizontal `/W`, Type3 CMap/CIDSet, simple-font widths, or page font-resource scoping.

The new boundary is specifically predefined `Identity-H`/`Identity-V` base CMap inheritance through `usecmap` inside a Type0 `/Encoding` CMap, preserving source-code boundaries for native fallback text and CIDSet vertical grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, CMap parser, Type0 font map assembly, descendant CIDFont metric parser, CIDSet bit reader, vertical text-position grouping, and WordPress paragraph smoke path. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/PIL raster execution, and external PDF/model tooling.

## Follow-Up

Next useful font/CMap work: object-valued `/UseCMap` predefined-name aliases with local override rows and malformed predefined CMap names, if a real current-base fixture exposes them.
