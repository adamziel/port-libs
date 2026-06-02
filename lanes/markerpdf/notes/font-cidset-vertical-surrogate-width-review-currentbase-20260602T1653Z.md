# markerPDF Vertical CIDSet Surrogate Width Review

Session: `port-dev-markerpdf-font31pdf-20260602T1653Z`
Micro-slice: `font-cidset-vertical-surrogate-width-review-currentbase-20260602T1653Z`
Base accepted HEAD: `16897955fedbe8eb586eccc43fee984b6415532f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`, then converts page dictionaries into Marker page/block/line/span objects. Its pinned dependency set includes `pdftext ^0.3.18` and `pypdfium2`-backed PDF extraction. The PHP fallback keeps that boundary by preserving native PDF text-position grouping before Gutenberg paragraph rendering.

Relevant dependency behavior is the pypdf CMap/font-width path: `build_char_map_from_dict()` builds a font width map from the font dictionary, descends into `/DescendantFonts[0]`, resolves `/DW` and `/W`, and parses ToUnicode CMaps with surrogate-pass UTF-16 decoding. pypdf documents that `/DW2` and `/W2` are vertical metrics but does not implement them; this lane already ports the vertical boundary natively, so this slice keeps CIDSet subset membership consistent with the existing horizontal fallback rule.

Source references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml`
- `https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py`

## Native Behavior Added

`PdfTextExtractor::glyphVerticalDisplacementsForTextOperand()` now consults `/CIDSet` when applying default vertical CIDFont displacement. Explicit `/W2` rows still win. Embedded CIDs keep `/DW2` or the existing writing-mode default vertical displacement. CIDs absent from `/CIDSet` use the same 500-unit fallback boundary as horizontal CIDSet width lookup, preserving positioned word gaps instead of pretending the missing glyph used the embedded CIDFont default metrics.

The focused fixture uses:

- `/Encoding /Identity-V`;
- a descendant CIDFont with `/DW2 [880 -1000]`;
- a compressed `/CIDSet` that includes CIDs 2, 3, and 4 but excludes CID 1;
- `/ToUnicode` source CID `0001` mapping to `U+2067`, `U+1F600`, and `U+2069`;
- source CID `0002` positioned at a real vertical word-gap boundary after CID 1;
- embedded CIDs `0003` and `0004` positioned to prove included subset CIDs still join as `VertJoin`.

Before the source fix, the excluded CID used the default `-1000` vertical displacement, so the first line joined `Word` directly after the `U+2069` bidi terminator without the source-position gap. After the fix, excluded CID 1 falls back to 500-unit vertical advance, preserving the WordPress paragraph gap, while embedded CIDs still emit `VertJoin`.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps vertical CIDSet fallback gaps for surrogate ToUnicode glyph boundaries on current base

1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
6 test files, 652 assertions, 0 failures
```

Full markerPDF lane check:

```text
php tools/run-tests.php lanes/markerpdf/tests
91 test files, 5724 assertions, 0 failures
```

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cidset-vertical-surrogate-width-review-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cidset-vertical-surrogate-width-review-currentbase.php
```

The smoke emits `surrogate_scalar_decoded=true`, `bidi_isolate_controls_preserved=true`, `excluded_cid_fallback_gap_preserved=true`, `included_cid_default_vertical_width_preserved=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta:

- Behavior tests: `577 -> 578`.
- Mapped upstream/dependency semantics: `414 -> 415 / 78`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, descendant CIDFont metric parser, CIDSet bit reader, vertical text-position grouping path, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/PIL raster execution, and external PDF/model tooling.

## Non-Overlap

This does not repeat accepted direct or indirect `/W2` parsing, predefined `-V` CMap writing-mode detection, Type0 `/Encoding` CMap CID width priority, horizontal CIDSet/default-width grouping, descriptor-only CIDFont `/DW` defaults, indirect Type0 `/DW`, named CMap resource `/W` resolution, or the previous bidi/surrogate source-width fallback. The new boundary is specifically vertical default displacement for CIDs excluded by `/CIDSet`, while preserving source-CID segmentation when ToUnicode emits bidi controls plus a surrogate-pair scalar.
