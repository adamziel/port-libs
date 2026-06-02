# markerPDF Font CID UseCMap Width Current Base

Session: `port-dev-markerpdf-font38pdf-20260602T182324Z`

Micro-slice: `font-cid-usecmap-width-currentbase-20260602T182324Z`

Base accepted HEAD: `b5e63149f6bdacc97639051ac95e06ff079481ce`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level digital PDF text extraction to `pdftext.extraction.dictionary_output` before Marker converts text dictionaries into page blocks and spans:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml

PDF CMap source truth: a CMap stream dictionary `/UseCMap` may be a predefined CMap name or a CMap stream used as the base for the derived CMap. Adobe's CMap/CIDFont source also requires the base `usecmap` to be applied before local mapping operations, so local CMap rows can extend or override the base:

- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf
- https://www.adobe.com/content/dam/acom/en/devnet/font/pdfs/5014.CIDFont_Spec.pdf

The reduced native PHP boundary is therefore: Type0 `/Encoding` CMap inheritance must feed source-code-to-CID mappings into descendant CIDFont `/W` widths before WordPress paragraph grouping. This slice covers object-valued `/UseCMap` streams that are valid base CMaps even when the base stream is not named.

## Native Behavior Added

`PdfTextExtractor::decodedCMapBody()` now carries a small object-recursion guard into CMap stream dictionary prelude generation.

`PdfTextExtractor::cMapStreamDictionaryPrelude()` keeps the existing named `/UseCMap` behavior unchanged. When `/UseCMap` is an object reference and the referenced CMap stream does not expose a usable `/CMapName`, the decoded base CMap is inlined before the derived CMap body. That lets the existing CMap parser see the base `begincodespacerange` and `begincidrange` rows before local rows, matching PDF CMap inheritance without adding a new parser.

The focused PDF declares:

- Type0 font `/Encoding 3 0 R`;
- CMap object `3` with dictionary `/UseCMap 7 0 R` and local CIDs 60-67;
- unnamed base CMap object `7` with source bytes 01-09 mapped to CIDs 40-48;
- descendant CIDFont `/W [40 48 1000 60 67 250]`;
- ToUnicode text for `WideBlock` and `Thin Text`.

Before the fix, object `7` was ignored because it had no CMap name, so source bytes 01-09 used raw-source fallback widths and emitted `Wide Block`. After the fix, the base CMap supplies CIDs 40-48 and the same content emits `WideBlock`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses object-valued Type0 UseCMap streams before CIDFont width grouping on current base
Expected: ['WideBlock', 'Thin Text']
Actual: ['Wide Block', 'Thin Text']
1 test files, 1 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses object-valued Type0 UseCMap streams before CIDFont width grouping on current base
1 test files, 7 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 649 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cid-usecmap-width-currentbase.php
```

The smoke emits Gutenberg paragraphs for `WideBlock` and `Thin Text` with `object_usecmap_stream_applied=true`, `base_cid_widths_selected=true`, `derived_cid_widths_selected=true`, `raw_source_width_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and whitespace gates:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-usecmap-width-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Status delta:

- Behavior tests: `639 -> 640`.
- Mapped upstream/dependency semantics: `466 -> 467 / 78`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, object recursion guard pattern, Type0 Encoding CMap CID mapping, descendant CIDFont width parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/PIL raster execution, and external PDF/model tooling.

## Non-Overlap

This does not repeat named Type0 `/Encoding /CMapName` resource resolution, direct inline `usecmap`, CMap stream dictionary `/UseCMap /Name`, CMap stream dictionary `/WMode` inheritance, predefined vertical CMap names, direct or indirect CIDFont `/W`, `/DW`, `/W2`, `/DW2`, CIDSet/default-width grouping, simple-font widths, Type3 CharProc widths, ToUnicode `usecmap`, page font resource scoping, or current xref/font-resource boundary work.

The new boundary is specifically object-valued Type0 CMap dictionary `/UseCMap <stream-ref>` where the base CMap stream is unnamed but still supplies CID mappings for descendant CIDFont `/W` width grouping before WordPress paragraph rendering.
