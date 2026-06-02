# markerPDF Indirect CIDFont W2 Vertical Metrics

Session: `port-dev-markerpdf-font14pdf-20260602T1357Z`
Micro-slice: `font-cid-w2-vertical-currentbase-20260602T1357Z`
Base accepted HEAD: `eac78b7f49664cc46073ec250256c335978346bb`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text geometry to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`, then converts those dictionaries to Marker page/block/line/span objects. The PHP fallback preserves that boundary by fixing native PDF text-position grouping before Gutenberg paragraphs are emitted.

Relevant parser behavior:

- markerPDF uses `pdftext` `^0.3.18` and `pypdfium2` at the extraction boundary.
- The PDF reference defines vertical writing mode through CIDFont `/DW2` default metrics and `/W2` per-CID vertical metric arrays. `/DW2` is `[vy w1]`; `/W2` groups are `c [w1y vx vy ...]` or `cfirst clast w1y vx vy`.
- pypdf's CIDFont width-map path resolves descendant font objects before using their metric arrays; this native slice extends the existing object-resolution behavior to vertical CIDFont metric arrays.

Source references:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_cmap.py
- https://www.verypdf.com/document/pdf-format-reference/txtidx0439.htm

## Native Behavior Added

`PdfTextExtractor::fontWidthMetrics()` now resolves indirect `/DW2` and `/W2` array objects with the same object-aware array helper used by page boxes, matrices, and simple-font `/Widths`.

The focused fixture keeps `/WMode 1` and a ToUnicode map direct, but moves CIDFont vertical metrics out of the descendant font dictionary:

- `/DW2 6 0 R` points to `[880 -500]`, so the default CIDs in `Vert` advance exactly to the next `Import` text matrix.
- `/W2 7 0 R` points to `[20 23 -250 500 880]`, so CIDs 20-23 in `Data` advance exactly to the next `Flow` text matrix.

Before this change, both arrays were ignored and the native fallback used default `-1000` vertical displacement, emitting `Vert Import` and `Data Flow`. After the fix, the same PDF emits `VertImport` and `DataFlow`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL resolves indirect CIDFont W2 vertical metrics before WordPress text advance boundaries
Expected: ['VertImport', 'DataFlow']
Actual: ['Vert Import', 'Data Flow']

1 test files, 560 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 564 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cidfont-indirect-w2-vertical-import.php
```

The smoke emits Gutenberg paragraphs `VertImport` and `DataFlow` with `indirect_dw2_resolved=true`, `indirect_w2_resolved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax, JSON, and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfTextExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cidfont-indirect-w2-vertical-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `512 -> 513`.
- Mapped upstream/dependency semantics: `360 -> 361 / 78`.
- Focused `PdfTextExtractorTest.php`: `559 -> 564` assertions for the new passing current-base behavior.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, indirect object resolution, ToUnicode CMap parser, CIDFont metric parser, vertical text-position grouping, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat accepted direct CIDFont `/W2` metrics, predefined `-V` CMap writing-mode detection, Type0 `/Encoding` CMap CID width priority, CIDSet/default-width grouping, ToUnicode row-count/comment parsing, simple-font indirect `/FirstChar`, or FontDescriptor flag resolution. The new behavior is limited to indirect CIDFont `/DW2` and `/W2` arrays for writing-mode 1 text advance grouping.
