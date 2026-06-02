# markerPDF CIDFont ToUnicode CMap Width Boundaries

Session: `port-dev-markerpdf-cid24-20260602T0928Z`
Micro-slice: `cidfont-tounicode-cmap-width-boundaries-currentbase-20260602T0928Z`
Base accepted HEAD: `07766d1f64500fb4c9b9de57714488cdfcff759e`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text/font geometry to `pdftext.extraction.dictionary_output`, then converts pdftext dictionaries into Marker `Span`, `Line`, and `Block` objects. The native PHP fallback keeps that boundary by preserving decoded ToUnicode text while using CIDFont width metrics to decide line grouping before Gutenberg paragraphs are emitted.

`pdftext` 0.3.18 is the pinned markerPDF dependency and describes its own source of text and font information as pypdfium2-backed character extraction. For this bounded native fallback, the relevant parser behavior is that CIDFont `/W` width operands are PDF numeric values, not integer-only tokens, and the width lookup still follows ToUnicode source-code boundaries for the displayed text.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://pypi.org/project/pdftext/0.3.18/

## Native Behavior Added

`PdfTextExtractor::cidWidthsFromWArray()` now parses `/W` array-form and range-form width values with the existing PDF numeric operand parser. CID range endpoints remain integer CIDs, but width operands such as `1000.5` are no longer split into integer fragments or skipped.

Before this slice, `/W [1 [1000.5 ...] 20 27 1000.5]` was interpreted as alternating `1000` and `5` widths in array form, while the decimal range width was ignored. The focused PDF decoded the intended glyphs through a ToUnicode CMap but grouped positioned text as `Wide Block` and `Data Flow`. After the fix, the same content emits `WideBlock` and `DataFlow`.

## Evidence

Red-first focused check before the source fix:

```text
FAIL uses decimal CIDFont W widths with ToUnicode CMap boundaries before WordPress text
Expected: ['WideBlock', 'DataFlow']
Actual: ['Wide Block', 'Data Flow']

1 test files, 462 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 466 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
61 test files, 2954 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cidfont-decimal-widths-import.php
```

The smoke emits `WideBlock` and `DataFlow` with `decimal_widths_preserve_joined_blocks=true`, `integer_fragment_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cidfont-decimal-widths-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, ToUnicode CMap parser, CIDFont metric parser, text positioning path, and WordPress paragraph smoke path. Full upstream markerPDF conversion parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.
