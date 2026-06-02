# markerPDF Type0 Descriptor Width Review Current Base

Session: `port-dev-markerpdf-font28pdf-20260602T1613Z`

Micro-slice: `font-type0-descriptor-width-review-currentbase-20260602T1613Z`

Base accepted HEAD: `2f20b5234597a7e6d34ec77bef5a304a8b8e0c15`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction and font geometry to `pdftext.extraction.dictionary_output` before converting pdftext dictionaries into Marker `Span`, `Line`, and `Block` objects.

Relevant upstream/dependency references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml`
- `https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_cmap.py`

The relevant parser behavior is object-aware Type0/CIDFont width extraction: pypdf descends through `/DescendantFonts`, resolves `/DW` with `get_object()`, then uses `/W` width differences when present. The native fallback mirrors that for WordPress text-gap grouping without running Python, pdftext, pypdfium, or model workers.

## Native Behavior

`PdfTextExtractor::fontWidthMetrics()` now resolves descendant CIDFont horizontal `/DW` operands through the existing object-aware numeric helper before falling back to the CIDFont default width.

The focused PDF uses:

- a Type0 `/Encoding /Identity-H` font with a descendant CIDFont;
- `/FontDescriptor 6 0 R` to keep the descriptor-only review boundary present;
- indirect `/DW 7 0 R` where object `7` contains the actual `1000`-unit default width;
- no `/W` differences, so every glyph must use the resolved `/DW`.

Before the fix, the parser read the object number `7` as a literal width and emitted `Wide Block` and `Data Flow`. After the fix, the same content resolves object `7` to `1000` and emits `WideBlock` and `DataFlow`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect Type0 CIDFont DW descriptor widths before WordPress text gaps
Expected: ['WideBlock', 'DataFlow']
Actual: ['Wide Block', 'Data Flow']
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect Type0 CIDFont DW descriptor widths before WordPress text gaps
1 test files, 6 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
5 test files, 644 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-descriptor-width-review-currentbase.php
```

The smoke emits `indirect_default_width_resolved=true`, `descriptor_font_boundary_preserved=true`, `indirect_object_number_width_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock` and `DataFlow`.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type0-descriptor-width-review-currentbase.php
```

All passed.

Final whitespace/JSON gate:

```text
git diff --check -- lanes/markerpdf
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
```

Final run status: pass.

Status delta: behavior tests `538 -> 539`; mapped semantics `385 -> 386 / 78`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, indirect numeric operand resolver, Type0 font map, descendant CIDFont metric parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat direct CIDFont `/DW`, descriptor-only default `/DW 1000`, direct or indirect `/W` differences, named Type0 CMap resources, Type0 no-ToUnicode CMap segmentation, vertical `/DW2`/`/W2`, CIDSet grouping, simple-font width resolution, or FontDescriptor style flags. The new boundary is specifically an indirect descendant CIDFont `/DW` default-width operand under a Type0 font with a FontDescriptor present before WordPress paragraph grouping.
