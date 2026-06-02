# markerPDF Type0 CMap Width Fallback Priority

Session: `port-dev-markerpdf-fontmap8-20260602T070836Z`
Micro-slice: `markerpdf-type0-cmap-width-fallback-priority-current-base-20260602T070836Z`
Base accepted HEAD: `ddef7701b1c5b9d5eb284eb986e7477f2ebab827`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` before Marker assembles page blocks. This native PHP slice keeps that reduced fallback boundary by fixing Type0 font text-advance grouping before pdftext-style lines feed WordPress paragraph rendering.

PDF source truth: Type0 font Encoding CMaps map character codes to a descendant-font CID selector, and CIDFont glyph metrics use the descendant CIDFont `/W` and `/DW` entries. pypdf follows the same dependency boundary in `build_font_width_map()` by reading `/DescendantFonts[0]` and using `/DW` plus `/W` CID ranges for composite font widths.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_cmap.py/#L934
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf

## Native Behavior Added

`PdfTextExtractor` now parses indirect Type0 `/Encoding` CMap streams for `begincidchar`, `begincidrange`, `usecmap`, and `/WMode`. The parsed source-code-to-CID map is carried beside the existing ToUnicode map.

When text-advance grouping applies descendant CIDFont `/W`, `/DW`, `/W2`, `/DW2`, or `/CIDSet` data, source character codes now resolve through the Type0 Encoding CMap CID first. Raw source-code width fallback remains available only when no CMap CID is known.

The focused fixture makes raw source-code widths conflict with descendant CIDs:

- source `<01>` through `<09>` have narrow raw-code widths but map to wide CIDs 200-208, so `WideBlock` must remain joined;
- source `<14>` through `<1B>` have wide raw-code widths but map to narrow CIDs 300-307, so `Thin Text` must keep a word gap.

## Evidence

Red-first focused check before the source fix:

```text
Wide Block
ThinText
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 417 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
58 test files, 2480 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-cmap-width-priority-import.php
```

The smoke emits Gutenberg paragraphs `WideBlock` and `Thin Text` with `uses_encoding_cmap_cid_priority=true`, `raw_source_width_fallback_not_used_when_cid_is_mapped=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type0-cmap-width-priority-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, CMap stream decoder, ToUnicode parser, descendant CIDFont metric parser, text-position grouping logic, and WordPress paragraph smoke path. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.
