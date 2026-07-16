# markerPDF font encoding width boundary current-base

Session: `port-dev-markerpdf-font6pdf-20260602T120021Z`

Micro-slice: `font-encoding-width-boundary-currentbase-20260602T120021Z`

Base accepted HEAD: `9a835382f553ae4ed672a05be91f90fb19cffed3`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF page text extraction through `marker/pdf/extract_text.py` into `pdftext.extraction.dictionary_output`, so native PHP extraction must preserve PDF parser text-position boundaries before Marker/WordPress paragraph assembly.

PDF parser source truth: simple-font `/Widths` arrays are indexed from `/FirstChar`, and PDF dictionary numeric operands can be indirect objects. A subset font can therefore combine an indirect `/Encoding` dictionary, indirect `/FirstChar`, and direct or indirect `/Widths`; text advance grouping must resolve the current `/FirstChar` object before applying glyph widths.

## Native Behavior

`PdfTextExtractor::simpleFontExplicitWidths()` now resolves `/FirstChar` through the existing object-aware numeric helper before mapping `/Widths` entries to character codes.

The focused fixture uses two subset Type1 fonts that share an indirect `/Encoding` dictionary with `/Differences` glyph names:

- a wide font with `/FirstChar 3 0 R` and direct `/Widths`, where `Wide` and `Block` must remain joined as `WideBlock`;
- a thin font with `/FirstChar 8 0 R` and indirect `/Widths 10 0 R`, where `Thin Text` must keep the word gap.

Before the source fix, the local red-first probe emitted `Wide Block` because indirect `/FirstChar` prevented explicit widths from being applied and the extractor fell back to default 500-unit advances.

## Evidence

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 533 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-simple-font-indirect-firstchar-widths.php
```

The smoke emitted Gutenberg paragraphs `WideBlock` and `Thin Text` with `indirect_firstchar_widths_resolved=true`, `wide_subset_not_split=true`, `thin_subset_gap_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed-file syntax and metadata checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-simple-font-indirect-firstchar-widths.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Full lane-scoped markerPDF gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
64 test files, 3790 assertions, 0 failures
```

## Status Delta

- Behavior tests: `492 -> 493`.
- Focused `PdfTextExtractorTest.php` assertions: `528 -> 533`.
- Mapped upstream/dependency semantics: `340 -> 341 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, indirect object resolution, simple-font Encoding Differences parser, explicit width parser, text-position grouping, and WordPress smoke path. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Non-Overlap

This does not repeat accepted Standard/MacRoman/Symbol encoding, subset glyph-name decoding, Base14/direct simple-font width metrics, Type3 CharProc widths, CIDFont decimal/default/vertical widths, Type0 Encoding CMap CID width priority, or indirect Type0 Encoding name behavior. The new boundary is specifically indirect simple-font `/FirstChar` resolution before `/Widths` advance grouping for subset `/Encoding` fonts.
