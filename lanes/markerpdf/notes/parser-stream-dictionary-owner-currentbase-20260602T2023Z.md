# markerPDF parser stream dictionary owner current-base

Micro-slice: `parser-stream-dictionary-owner-currentbase`

Base accepted HEAD: `1d0255efc342976ccd01090ebca142bc846d342a`

## Source Truth

Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and through `naive_get_text()` via pypdfium page text:

https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

That makes low-level PDF object generation, xref selection, and stream dictionary parsing a dependency boundary for native WordPress paragraph extraction.

## Behavior

The focused fixture builds a current xref table that selects generation `1` for the page `/Contents` stream and for its `/Length`, `/Filter`, and `/DecodeParms` helper objects. Stale generation `0` objects with the same object numbers are present earlier in the file:

- stale stream object `4 0` contains `Stale stream dictionary leak`;
- stale helper `8 0` says `/ASCIIHexDecode`;
- stale helper `9 0` has malformed `/Predictor /Twelve`;
- current stream `4 1` uses `/Filter 8 1 R`, `/DecodeParms 9 1 R`, and `/Length 7 1 R`.

Native extraction emits only `Current stream dictionary owner` and `Current DecodeParms helper applied`. The current parser already preserves this boundary; this handoff locks it with focused current-base coverage and a WordPress smoke.

## Focused Evidence

```text
php -l lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-owner-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-owner-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps current xref stream dictionary owner before stale same-number dictionaries

1 test files, 11 assertions, 0 failures
```

Adjacent parser/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
...
10 test files, 708 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-owner-currentbase.php
```

The smoke emitted two Gutenberg paragraphs and metadata flags:

- `uses_current_stream_dictionary_owner=true`
- `current_decodeparms_helper_applied=true`
- `stale_stream_dictionary_excluded=true`
- `stale_helper_names_excluded=true`
- `fake_dictionary_owner_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Status Delta

- `phpPass`: `773 -> 774`
- mapped parser semantics: `549 -> 550 / 78`
- added focused test: `PdfParserStreamDictionaryOwnerCurrentBaseTest.php`
- added WordPress smoke: `wordpress-pdf-parser-stream-dictionary-owner-currentbase.php`

JSON and whitespace checks:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok
```

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors were reported.

## Non-Overlap

This does not repeat accepted escaped stream dictionary names, indirect Filter/DecodeParms stale-generation rejection, stream-owned fake DecodeParms object rejection, xref-stream Filter/Length owner review, inline-image owner recovery, nested token stream boundaries, or xref offset-owner rejection. This slice is specifically the current xref-selected page content stream dictionary generation and its matching Length/Filter/DecodeParms helper generations before WordPress text extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct object scanner, xref table selector, generation-aware object-reference resolver, stream filter dispatcher, DecodeParms predictor decoder, page content walker, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
