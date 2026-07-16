# markerPDF Font CMap Bidi Surrogate Width Boundaries

Session: `port-dev-markerpdf-font31-20260602T094032Z`
Micro-slice: `font-cmap-bidi-surrogate-width-boundaries-currentbase-20260602T094032Z`
Base accepted HEAD: `f50b457cdfd12d887c5fc62e07c8d2bad733a41d`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output`, then converts pdftext dictionaries into Marker page/block/line/span objects. The PHP fallback keeps that boundary by fixing native content-stream text decoding before WordPress paragraph rendering.

Relevant source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

The relevant dependency behavior is pypdf CMap parsing: ToUnicode targets are decoded from UTF-16BE with surrogate support, and font widths are built from source font glyph/code boundaries rather than from the length of decoded Unicode strings. This matters when one PDF source code maps to bidi isolate controls plus a UTF-16 surrogate-pair scalar.

## Native Behavior Added

`PdfTextExtractor::glyphWidthsForTextOperand()` now uses ToUnicode or font CMap source-code boundaries to synthesize the existing default 500-unit glyph advance when no explicit `/Widths`, `/W`, `/DW`, or `/CIDSet` metric exists.

Before this slice, a single source glyph mapped to `U+2067 U+1F600 U+2069` was counted as three decoded Unicode characters for horizontal advance. A following positioned text run at a real word-gap boundary was joined as `...Word`. After the fix, the same source glyph advances as one PDF glyph, so positioned text emits the expected WordPress paragraph gap.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL uses CMap source glyph boundaries for bidi surrogate text advance before WordPress text
Expected: array (
  0 => 'RLI + U+1F600 + PDI Word',
)
Actual omitted the positioned word gap.

1 test files, 467 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 471 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
61 test files, 3059 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-bidi-surrogate-width-boundary.php
```

The smoke emits `bidi_surrogate_space_preserved=true`, `surrogate_scalar_decoded=true`, `bidi_isolate_controls_preserved=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-bidi-surrogate-width-boundary.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests: `456 -> 457`.
- Mapped semantics: `308 -> 309 / 78`.
- Full markerPDF lane remains PHP-green on current base.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, stream decoder, ToUnicode CMap parser, source-code segmentation, positioned text grouping, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

## Non-Overlap

This does not repeat accepted CIDFont decimal `/W` parsing, Type0 `/Encoding` CMap CID width priority, Type0 no-ToUnicode CMap fallback segmentation, ToUnicode codespace fallback, simple-font Encoding Differences, or standard/MacRoman/Symbol encoding slices. The new behavior is limited to no-explicit-width text positioning when ToUnicode CMap source codes decode to bidi controls and UTF-16 surrogate-pair scalars.
