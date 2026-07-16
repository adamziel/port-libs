# markerPDF Font ToUnicode Surrogate CID Width Review Current Base

Session: `port-dev-markerpdf-font30pdf-20260602T1640Z`
Micro-slice: `font-tounicode-surrogate-cid-width-review-currentbase-20260602T1640Z`
Base accepted HEAD: `2c21071f7e9064c624f93392d27c864177463373`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` in `marker/pdf/extract_text.py`, then converts the supplied dictionaries into Marker Page/Block/Line/Span objects. The native PHP fallback keeps this boundary by fixing the PDF font/CMap text and width extraction before WordPress paragraph assembly.

Relevant upstream/dependency references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml`
- `https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py`

The relevant dependency boundary is that ToUnicode targets can decode through UTF-16 surrogate pairs, while descendant CIDFont `/W` widths are selected by Type0 Encoding CMap CIDs before text-gap grouping. The existing native ToUnicode parser already honored declared `bfchar`/`bfrange` row counts; this slice applies the same declared-row-count discipline to Type0 Encoding `begincidchar` and `begincidrange` blocks so stale over-declared CID rows cannot alter width selection.

## Native Behavior Added

`PdfTextExtractor::parseCidCMap()` now parses Type0 Encoding CID CMap `begincidchar` and `begincidrange` blocks through the existing `cMapOperatorBlocks()` helper, preserving the declared row count for each block. New `parseCidChars()` and the updated `parseCidRanges()` slice parsed rows to that declared count before updating the source-code-to-CID map.

The focused PDF maps `<0100>` and `<0109>` to UTF-16 surrogate-pair emoji through ToUnicode. Its Type0 Encoding CMap declares one `begincidrange` row for `<0100>` through `<0109>`, then includes an extra stale row for `<0200>` through `<0207>`. The descendant CIDFont has `/W [700 709 1000 800 807 250]`. Before this fix, the stale row mapped `Data`/`Flow` to narrow CID 800-series widths and inserted a false WordPress text gap as `Data Flow`. After this fix, the stale row is ignored, the `<0200>` source codes fall back to `/DW 500`, and WordPress text emits `DataFlow` while still decoding `😀ImportWP😃`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors declared Type0 CID CMap row counts before surrogate ToUnicode width grouping on current base
Expected: ['😀ImportWP😃', 'DataFlow']
Actual: ['😀ImportWP😃', 'Data Flow']
1 test files, 1 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS honors declared Type0 CID CMap row counts before surrogate ToUnicode width grouping on current base
1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
6 test files, 652 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-tounicode-surrogate-cid-width-review-currentbase.php
```

The smoke emits `surrogate_scalars_decoded=true`, `declared_cid_range_count_honored=true`, `stale_cid_width_row_excluded=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `😀ImportWP😃` and `DataFlow`.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-tounicode-surrogate-cid-width-review-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Status Delta

- Behavior tests: `564 -> 565`.
- Mapped semantics: `404 -> 405 / 78`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, stream decoder, CMap parser, Type0 Encoding CID map parser, ToUnicode UTF-16 decoder, descendant CIDFont width parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat no-explicit-width bidi surrogate glyph-boundary grouping, named Type0 `/Encoding /CMapName` resource resolution, direct/indirect CIDFont `/W` or `/DW`, decimal `/W` parsing, vertical `/W2`, CIDSet/default-width grouping, ToUnicode `bfchar`/`bfrange` declared row counts, ToUnicode `usecmap`, simple-font encodings, or page-resource font scoping. The new boundary is specifically declared row-count handling for Type0 Encoding CID CMap `begincidchar`/`begincidrange` rows before descendant CIDFont width selection for ToUnicode surrogate-pair text.
