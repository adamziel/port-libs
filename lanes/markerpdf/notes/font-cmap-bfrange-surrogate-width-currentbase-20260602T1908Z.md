# markerPDF Font CMap Bfrange Surrogate Width Current Base

Session: `port-dev-markerpdf-font41pdf-20260602T1908Z`
Micro-slice: `font-cmap-bfrange-surrogate-width-currentbase`
Base accepted HEAD: `78dacbd21ee6b9a83b42fbcf69facc371244266b`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` before Marker converts pdftext dictionaries into page, line, span, and block objects.

Relevant dependency behavior is pypdf CMap parsing: ToUnicode `beginbfrange` rows increment destination strings and decode UTF-16BE targets, including surrogate pairs, while descendant CIDFont `/W` metrics remain based on the source CIDs rather than decoded Unicode string length.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Native Behavior Added

`PdfTextExtractor::parseToUnicodeRanges()` no longer increments `beginbfrange` target strings through native `hexdec()` plus `dechex()`. Long UTF-16BE strings such as a surrogate pair followed by additional BMP code units can exceed native integer precision and become floats in PHP. The parser now increments the fixed-width hex string directly before decoding each target.

The focused PDF uses:

- a Type0 `/Encoding` CMap whose `begincidrange` rows map source codes to descendant CIDs;
- a ToUnicode `beginbfrange` scalar target beginning with `D83DDE00` plus extra UTF-16BE code units;
- a ToUnicode array-form `beginbfrange` surrogate target;
- a descendant CIDFont `/W` array that keeps the surrogate line gap and `Data Flow` gap aligned to source CIDs.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL increments long ToUnicode bfrange surrogate targets before CID width grouping on current base (lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php)
dechex(): Argument #1 ($num) must be of type int, float given

1 test files, 0 assertions, 1 failures
```

Focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS increments long ToUnicode bfrange surrogate targets before CID width grouping on current base

1 test files, 9 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFont*CurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
13 test files, 691 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cmap-bfrange-surrogate-width-currentbase.php
```

The smoke emits `long_bfrange_surrogate_targets_decoded=true`, `array_bfrange_surrogate_target_decoded=true`, `cid_width_gap_preserved=true`, `surrogate_line_gap_preserved=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-cmap-bfrange-surrogate-width-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests: `679 -> 680`.
- Mapped semantics: `493 -> 494 / 78`.
- WordPress scenarios: `679 -> 680`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, Type0 Encoding CMap CID parser, descendant CIDFont metric parser, text-position grouping path, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Non-Overlap

This does not repeat accepted bidi surrogate no-explicit-width source-boundary handling, Type0 ToUnicode surrogate/CID `bfchar` width grouping, CIDSet vertical surrogate fallback, Type0 `/Encoding` CMap width priority, object-valued `/UseCMap` width grouping, named CMap resources, indirect CIDFont `/W`, vertical `/W2`, simple-font width resolution, or declared CMap row-count boundaries. The new boundary is specifically long ToUnicode `beginbfrange` surrogate target increments before CIDFont width grouping on the current base.
