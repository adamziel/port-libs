# markerPDF Type0 CIDSet Descriptor Default Current Base

Session: `port-dev-markerpdf-font43pdf-20260602T1935Z`

Micro-slice: `font-type0-cidset-descriptor-default-currentbase`

Base accepted HEAD: `2a344ae8c1b485daa88b3fe8a487f8ab30d2feff`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text geometry to `pdftext.extraction.dictionary_output` before Marker turns pdftext dictionaries into `Span`, `Line`, `Block`, and WordPress-facing Markdown output.

The relevant dependency boundary is object-aware Type0/CIDFont parsing: composite fonts resolve `/DescendantFonts`, then descendant CIDFont `/FontDescriptor`, `/CIDSet`, `/DW`, and `/W` metrics before text-position grouping. This native slice mirrors that boundary without running Python, pdftext, pypdfium, model workers, raster engines, or external PDF tools.

References:

- `marker/pdf/extract_text.py` in upstream markerPDF at the pinned manifest commit.
- pypdf `_cmap.py` font-width map behavior, which dereferences composite font descendants before width lookup.
- PDF Type0/CIDFont semantics for object-valued array operands and FontDescriptor CIDSet/default-width handling.

## Native Behavior

`PdfTextExtractor::descendantFontBodies()` now resolves `/DescendantFonts` through the existing object-aware array helper. Direct arrays still follow the same path, while object-valued arrays such as `/DescendantFonts 8 0 R` now expose the descendant CIDFont body.

The focused fixture uses:

- a Type0 `/Encoding /Identity-H` font with `/DescendantFonts 8 0 R`;
- object `8` containing `[4 0 R]`;
- descendant object `4` containing a CIDFont with `/FontDescriptor 6 0 R`;
- descriptor object `6` containing `/CIDSet 7 0 R`, `/FontName /IndirectCIDSetSerif`, and serif/non-symbolic flags;
- no `/DW` or `/W`, so subset CIDs use the CIDFont default `/DW 1000`;
- text CIDs outside `/CIDSet` still use the reduced fallback width, preserving real word gaps.

Before the fix, the descendant CIDFont was not discovered, so all glyphs used generic 500-unit fallback widths and `WideBlock` became `Wide Block`. After the fix, CIDSet member glyphs use the default CID width and descriptor flags reach styled spans, while non-CIDSet `Thin Text` remains separated.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect Type0 DescendantFonts CIDSet descriptor defaults before WordPress text gaps
Expected: ['WideBlock', 'Thin Text']
Actual: ['Wide Block', 'Thin Text']
1 test files, 1 assertions, 1 failures
```

Passing focused check after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect Type0 DescendantFonts CIDSet descriptor defaults before WordPress text gaps
1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
5 test files, 628 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-cidset-descriptor-default-currentbase.php
```

The smoke emits Gutenberg paragraphs `WideBlock` and `Thin Text` with `indirect_descendant_fonts_resolved=true`, `cidset_default_width_preserves_subset_join=true`, `non_cidset_glyphs_keep_gap=true`, `descriptor_flags_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType0CidSetDescriptorDefaultCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type0-cidset-descriptor-default-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Status delta: behavior tests `718 -> 719`; mapped semantics `516 -> 517 / 78`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, indirect array resolver, stream decoder, Type0 font map, descendant CIDFont metric parser, FontDescriptor flag path, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat direct Type0 `/DescendantFonts [4 0 R]`, indirect `/DW`, indirect `/W`, direct descriptor default-width, Type0 object-valued `/Encoding` names, object-valued UseCMap streams, vertical `/W2`/`/DW2`, Type3 CIDSet/CharProc behavior, or simple-font width resolution. The new boundary is specifically object-valued Type0 `/DescendantFonts` array resolution before descendant CIDFont FontDescriptor `/CIDSet` default-width grouping and descriptor flag extraction.
