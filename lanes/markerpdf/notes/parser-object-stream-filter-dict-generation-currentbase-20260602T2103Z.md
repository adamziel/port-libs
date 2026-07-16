# markerPDF parser object-stream filter dictionary generation current-base

Micro-slice: `parser-object-stream-filter-dict-generation-currentbase`

Base accepted HEAD: `c246260033e061f468722755bd7ed5aed0b39863`

## Source Truth

Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` uses pypdfium page text extraction:

https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

That makes object-stream decoding a native parser/dependency boundary for this PHP lane. PDFium's object-stream parser validates `/Type /ObjStm`, requires valid `/N` and `/First`, loads filtered stream bytes, and parses member objects from the decoded object table:

https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp

## Behavior

This slice extends `PdfTextExtractor::extractObjectStreamStreamDictionaryGenerationReview()` so object-stream `/Filter` operands now report whether a current xref-selected helper is still unusable as a decoder because it resolves to a dictionary. This separates two boundaries:

- xref ownership: the current generation `8 1 R` is selected instead of stale generation `8 0 R`;
- filter usability: the current generation value is a dictionary, not a filter name or filter array, so object-stream expansion fails closed.

The focused fixture includes a live `/ObjStm` with `/Filter 8 1 R`, stale generation `8 0 obj /FlateDecode`, and current generation `8 1 obj << ... >>`. WordPress text extraction keeps the safe direct page and excludes compressed object-stream members and dictionary text. Review metadata records `invalid_filter_operand_count=1`, `dictionary_filter_operand_count=1`, `filter_resolution_failed=true`, and `filter_operand_policy=reject_dictionary_filter_operands`.

## Red Baseline

Before the source change, the new focused test failed because the review did not expose the invalid dictionary filter operand:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "invalid_filter_operand_count" in lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php on line 98
FAIL rejects current-generation dictionary Filter operands on object streams before WordPress extraction
Values are not identical
Expected: 1
Actual: NULL

1 test files, 13 assertions, 1 failures
```

## Verification

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects current-generation dictionary Filter operands on object streams before WordPress extraction

1 test files, 32 assertions, 0 failures
```

Additional verification run after the source, example, status, and note edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS fails closed on dictionary entries inside stream Filter arrays before current-base text extraction
PASS rejects current-generation dictionary Filter operands on object streams before WordPress extraction
PASS uses current object-stream stream dictionary helper generations before WordPress extraction
PASS recovers xref-selected object streams whose filter chain operands are compressed helpers

4 test files, 106 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-filter-dict-generation-currentbase.php
<!-- markerpdf-parser-object-stream-filter-dict-generation-currentbase-smoke {"executes_python_or_models":false,"executes_external_pdf_tools":false,"native_boundary":"PDF object-stream Filter operands use current xref-selected generations but dictionary-shaped filters are rejected before WordPress import","safe_page_visible":true,"object_stream_members_excluded":true,"filter_dictionary_text_excluded":true,"object_stream_review_count":1,"xref_selected_operand_count":1,"invalid_filter_operand_count":1,"dictionary_filter_operand_count":1,"filter_resolution_failed":true,"filter_operand_policy":"reject_dictionary_filter_operands"} -->
<!-- wp:paragraph -->
<p>Safe page before object stream filter dictionary</p>
<!-- /wp:paragraph -->
```

Expected smoke metadata:

- `safe_page_visible=true`
- `object_stream_members_excluded=true`
- `filter_dictionary_text_excluded=true`
- `invalid_filter_operand_count=1`
- `dictionary_filter_operand_count=1`
- `filter_resolution_failed=true`
- `filter_operand_policy=reject_dictionary_filter_operands`

## Status Delta

- `phpPass`: `818 -> 819`
- `wordpressScenarios`: `818 -> 819`
- added focused test: `PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php`
- added WordPress smoke: `wordpress-pdf-parser-object-stream-filter-dict-generation-currentbase.php`

## Non-Overlap

This does not repeat accepted object-stream stream-dictionary generation ownership for valid `/N`, `/First`, `/Length`, `/Filter`, and `/DecodeParms` helper generations; xref-selected object-stream filter-chain expansion; direct page stream dictionary generation ownership; page content `Filter` array dictionary rejection; object-stream carrier fallback exclusion; xref object-stream generation-zero member guards; xref-stream `/Filter`/`/Length` owner review; or stream-owned fake xref offset rejection.

The bounded behavior here is specifically the object-stream `/Filter` operand resolving to a current xref-selected dictionary helper. The parser must not fall back to stale same-number valid filter generations, and the review path must classify the rejection before WordPress paragraph import.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, generation-aware xref table/stream selector, object-stream decoder, stream-filter dispatcher, object-stream review metadata path, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
