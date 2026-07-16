# markerPDF CIDFont Descriptor Width Defaults

Session: `port-dev-markerpdf-fontcid8-20260602T065630Z`
Micro-slice: `markerpdf-cidfont-descriptor-width-defaults-current-base-20260602T065630Z`
Base accepted HEAD: `d996e29518eddf17fb370f6a58a506c6c4327497`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` converts pdftext dictionary spans into Marker page blocks after delegating low-level PDF text/font geometry to `pdftext.extraction.dictionary_output`. This slice keeps that upstream boundary in the native PHP fallback parser: it fixes CIDFont text-advance grouping before the reduced `pdftext`-style text lines are emitted.

Relevant PDF parser source truth is PDF 32000-1:2008 Table 117. A CIDFont dictionary has required `/FontDescriptor`, optional `/DW`, optional `/W`, optional `/DW2`, and optional `/W2`; absent `/DW` defaults to `1000`, and absent `/W` means all glyphs use `/DW`.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py

## Native Behavior Added

`PdfTextExtractor::fontWidthMetrics()` now treats any descendant `/Subtype /CIDFontType0` or `/CIDFontType2` body as carrying the CIDFont default horizontal width of `1000` when `/DW` is omitted, even if the font only has a `/FontDescriptor` and no `/W` array or `/CIDSet` stream.

Before this slice, the native parser only attached default CID widths when `/W` or `/CIDSet` appeared. A descriptor-only CIDFont fell back to the simple 0.5-em advance heuristic, so two adjacent `Tm` positioned text runs rendered as `Wide Block`. After the fix, the same PDF uses the CIDFont default width and renders `WideBlock`.

## Evidence

Red-first focused check before the source fix:

```text
array (
  0 => 'Wide Block',
)
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 406 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
58 test files, 2436 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cidfont-descriptor-width-defaults-import.php
```

The smoke emits one Gutenberg paragraph containing `WideBlock` with `kept_joined_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and metadata checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cidfont-descriptor-width-defaults-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, descendant CIDFont parser, ToUnicode CMap decoding, text positioning, and WordPress paragraph smoke path. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.
