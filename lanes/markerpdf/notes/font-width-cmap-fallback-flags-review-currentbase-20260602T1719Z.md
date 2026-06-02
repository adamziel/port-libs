# markerPDF Font Width CMap Fallback Flags Review Current Base

Session: `port-dev-markerpdf-font32pdf-20260602T1719Z`
Micro-slice: `font-width-cmap-fallback-flags-review-currentbase-20260602T1719Z`
Base accepted HEAD: `49180e79432b8b918699ff28f84476d5fe362bc7`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`, then converts pdftext dictionaries into Marker `Span`, `Line`, and `Block` objects. Marker preserves pdftext font names, sizes, weights, and descriptor flags through `font_flags_decomposer()` before Markdown/WordPress assembly.

Relevant dependency behavior: pypdf resolves a font `/Encoding` object with `get_object()` before handling predefined CMap names such as `/Identity-H` and `/Identity-V`. The native PHP fallback already handled direct predefined vertical CMap names and indirect horizontal names; this slice covers the missing indirect predefined vertical-name boundary before CIDFont vertical width grouping and style-flag review.

Source references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py`
- `https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py`

## Native Behavior

`PdfTextExtractor::fontWritingMode()` now resolves `/Encoding` through the existing object-aware PDF name helper before checking predefined CMap name suffixes. A Type0 font with `/Encoding 8 0 R` where object `8` is `/UniJIS-UCS2-V` now selects vertical writing mode exactly like the direct `/Encoding /UniJIS-UCS2-V` path.

The focused PDF uses:

- Type0 `/Encoding 8 0 R` with object `8 0 obj /UniJIS-UCS2-V endobj`;
- `/ToUnicode` text for `VertImport` and `DataFlow`;
- descendant CIDFont `/DW2`, `/W2`, `/CIDSet`, and a `/FontDescriptor /Flags` serif/non-symbolic/italic combination.

Before the fix, the indirect predefined CMap name was not considered by `fontWritingMode()`, so horizontal grouping split the imported text into `Vert`, `Import`, `Data`, and `Flow`. After the fix, vertical `/W2` advances preserve `VertImport` and `DataFlow`, and the styled span still carries `IndirectVerticalSerifItalic_serif_non_symbolic_italic`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect predefined vertical CMap names before width grouping and font flags
Expected: ['VertImport', 'DataFlow']
Actual: ['Vert', 'Import', 'Data', 'Flow']
1 test files, 1 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect predefined vertical CMap names before width grouping and font flags
1 test files, 9 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-cmap-fallback-flags-currentbase.php
```

The smoke emits `indirect_predefined_cmap_name_resolved=true`, `vertical_width_boundaries_preserved=true`, `horizontal_split_excluded=true`, `descriptor_flags_preserved=true`, `font=IndirectVerticalSerifItalic_serif_non_symbolic_italic`, and native-only execution flags, followed by Gutenberg paragraphs for `VertImport` and `DataFlow`.

Adjacent font/text focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 688 assertions, 0 failures
```

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-cmap-fallback-flags-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, indirect name resolver, Type0 font map assembly, ToUnicode parser, CIDFont `/W2`/`/DW2`/`/CIDSet` metrics, FontDescriptor flag extraction, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat direct predefined vertical CMap writing-mode detection, direct `/Identity-H`/`/Identity-V` fallback decoding, indirect horizontal `/Identity-H` CIDSet grouping, named CMap resource resolution, Type0 Encoding CMap CID width priority, ToUnicode row-count or surrogate handling, direct/indirect CIDFont `/W` or `/DW`, vertical `/W2` parsing itself, simple-font widths, or indirect FontDescriptor field resolution. The new boundary is specifically object-valued predefined vertical CMap names before vertical width grouping and descriptor-flag review.
