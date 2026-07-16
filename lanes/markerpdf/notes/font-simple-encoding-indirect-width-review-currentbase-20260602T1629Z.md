# markerPDF Simple Font Indirect Encoding Width Review Current Base

Session: `port-dev-markerpdf-font29pdf-20260602T1629Z`

Micro-slice: `font-simple-encoding-indirect-width-review-currentbase-20260602T1629Z`

Base accepted HEAD: `ce4d02651156db0ca80cec00a035bd5f5795584e`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF page text extraction to `pdftext.extraction.dictionary_output` before Marker block assembly in `marker/pdf/extract_text.py`. The native PHP boundary therefore has to preserve PDF parser font decoding and glyph advance decisions before WordPress paragraph grouping.

Relevant parser behavior is object-aware simple-font parsing: PDF dictionary and array operands can be indirect objects, so `/Encoding` dictionaries can carry object-valued `/BaseEncoding` and `/Differences`, and `/Widths` arrays can contain indirect numeric operands. The native fallback now resolves those values before decoding subset glyph names or computing text advances, without running Python, pdftext, pypdfium, models, or external PDF tools.

## Native Behavior

`PdfTextExtractor::fontEncodingMap()` now resolves `/BaseEncoding` names and `/Differences` arrays through the existing object-aware name/array helpers.

`PdfTextExtractor::simpleFontExplicitWidths()` now reads simple-font `/Widths` with a top-level array item parser and resolves each numeric item through the existing object-aware number helper. This avoids treating `20 0 R` object-reference tokens as literal width values.

The focused PDF uses:

- a Type1 wide subset font with `/Encoding 6 0 R`;
- an indirect encoding dictionary whose `/BaseEncoding 8 0 R` resolves to `/WinAnsiEncoding`;
- an indirect `/Differences 9 0 R` array mapping source codes to `WideBlock` and `Thin Text`;
- a wide `/Widths` array whose entries are all indirect references to object `20`, containing `1000`;
- a thin `/Widths 22 0 R` array whose entries are indirect references to object `21`, containing `250`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect simple-font encoding and width operands before WordPress text gaps
Expected: ['WideBlock', 'Thin Text']
Actual: ['ABCD EFGHI', 'TUVW XYZ[']
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect simple-font encoding and width operands before WordPress text gaps
1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 652 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-simple-font-encoding-indirect-width-currentbase.php
```

The smoke emits `indirect_encoding_decoded=true`, `indirect_width_entries_resolved=true`, `narrow_width_gap_preserved=true`, `raw_source_codes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock` and `Thin Text`.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-simple-font-encoding-indirect-width-currentbase.php
```

All passed.

Final whitespace/JSON gate:

```text
git diff --check -- lanes/markerpdf
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
```

Final run status: pass.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `558 -> 559`.
- Focused new test: `8` assertions.
- Adjacent text/font gate: `6` files / `652` assertions / `0` failures.
- Mapped upstream/dependency semantics: `399 -> 400 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, object-aware name/array/number resolution helpers, simple-font Encoding Differences parser, simple-font explicit width parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat accepted Standard/MacRoman/Symbol simple-font encodings, direct Encoding Differences, subset ligature glyph names, Base14 width metrics, direct or array-object `/Widths`, indirect `/FirstChar`, Type3 CharProc widths, CIDFont decimal/default/vertical widths, Type0 Encoding CMap CID width priority, Type0 indirect `/DW`, or indirect Type0 Encoding name behavior. The new boundary is specifically object-valued simple-font `/BaseEncoding`, `/Differences`, and numeric `/Widths` entries before WordPress paragraph grouping.
