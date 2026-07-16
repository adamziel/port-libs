# markerPDF parser stream-filter xref owner current-base

Micro-slice: `parser-stream-filter-xref-owner-currentbase-20260602T175653Z`

## Source Truth

Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and through `naive_get_text()` via pypdfium page text. That makes low-level PDF object/xref resolution and stream-filter decoding a dependency boundary for the native PHP parser before WordPress paragraphs are emitted.

For indirect stream filter helpers, the PDF object reference generation is part of the object identity. A content stream dictionary that says `/Filter 20 0 R` must not resolve that helper through object `20 1` merely because the current xref section selects generation 1 for object number 20.

## Implementation

`PdfTextExtractor` now records the xref-selected generation/body for the current object table and uses `indirectObjectBodyForReference()` when resolving indirect stream `/Length`, `/Filter`, `/DecodeParms`, DecodeParms integer operands, and scalar name/number/string helper objects. If a stream helper reference generation differs from the current selected owner generation, decoding fails closed for that stream instead of leaking decoded stale payload text.

The focused fixture builds:

- object `20 0` as stale `/ASCIIHexDecode`;
- object `20 1` as current `/FlateDecode`, selected by the current xref table;
- content stream `4 0` with `/Filter 20 0 R` and Flate bytes containing `Mismatched filter generation leak`;
- content stream `6 0` as a safe direct current page stream.

Native extraction now emits only `Safe current direct page`.

## Red First

Before the resolver patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stale generation Filter references despite current xref owner selection
Expected: ['Safe current direct page']
Actual: ['Mismatched filter generation leak', 'Safe current direct page']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-filter-xref-owner-currentbase.php
```

All syntax checks passed.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stale generation Filter references despite current xref owner selection
1 test files, 10 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParser*Test.php lanes/markerpdf/tests/PdfXref*Test.php lanes/markerpdf/tests/PdfObjectStream*Test.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 946 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-filter-xref-owner-currentbase.php
```

The smoke emitted `stale_generation_filter_rejected=true`, `current_direct_stream_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then one Gutenberg paragraph containing `Safe current direct page`.

```text
git diff --check -- lanes/markerpdf
```

Passed.

## Non-Overlap

This does not repeat the accepted indirect Filter/DecodeParms owner-boundary slice, stream-owned fake DecodeParms object rejection, xref-stream Filter/Length owner review, xref offset owner rejection, object-stream filter-chain helper recovery, xref-stream DecodeParms predictor decoding, nested object-stream filter rejection, or previous object-stream generation guard. The new behavior is specifically generation-aware resolution of indirect stream filter helpers against the current xref-selected owner.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct object scanner, xref table/stream selector, stream filter dispatcher, DecodeParms resolver, page content walker, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
