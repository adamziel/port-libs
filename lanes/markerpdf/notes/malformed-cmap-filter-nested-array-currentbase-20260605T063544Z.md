# markerPDF malformed CMap nested filter-array boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T063544Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, with low-level ToUnicode CMap stream decoding delegated to pdftext/PDFium before Markdown and WordPress paragraphs are assembled.

The native no-GPU PHP lane owns this lower parser boundary. PDF stream `/Filter` operands are decoder names or null identity slots; dictionaries are not decoders. If a malformed ToUnicode CMap hides a dictionary inside nested filter arrays such as `/Filter [ [ [ << ... >> ] ] /FlateDecode ]`, the native review should classify that as a dictionary filter operand and fail closed before decoding the compressed CMap payload.

## Behavior

`PdfTextExtractor::filterOperandBodyContainsDictionary()` now walks nested filter-array operands recursively, and direct filter operand review rows now expose `dictionary_filter_operand`.

The focused fixture keeps the visible page text as `Nested Array Safe Import` through Identity-H fallback, while rejecting a compressed CMap payload that would otherwise map the source bytes to `Nested Array CMap Leak`. Review metadata now reports:

- `dictionary_filter_operand_count=1`
- `malformed_filter_operand_count=0`
- `filter_operand_policy=reject_dictionary_filter_operands`
- `filter_operands[0].dictionary_filter_operand=true`

## Evidence

Red-first inline fixture on accepted base `beecd573326eb942861636d36f425d3bf3ca3af6` before the fix:

```text
text=Nested Dictionary Safe Import
invalid=1
dictionary=0
malformed=1
policy=reject_malformed_filter_operands
operand0_dictionary=false
```

Focused green after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS classifies nested-array CMap Filter dictionaries before current-base text extraction
...
1 test files, 756 assertions, 0 failures
```

Adjacent parser/filter/CMap family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 1530 assertions, 0 failures
```

Broad CMap/filter/DecodeParms glob:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php lanes/markerpdf/tests/PdfParser*Filter*Test.php
Focused test run: 34 selected test files (root lock skipped)
...
34 test files, 1456 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php
nested_array_filter_dictionary_classified=true
nested_array_filter_rejected=true
nested_array_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct dictionary/literal malformed CMap `/Filter` operands, selected indirect non-name operands, selected generation dictionary filters, stale-generation filter references, malformed CMap `/DecodeParms`, null-filter DecodeParms alignment, UseCMap DecodeParms review, post-`endcmap` parser bounding, unsupported/Crypt filter handling, CMap width grouping, xref repair, image filters, metadata, annotations, forms, or model/OCR paths.

The bounded behavior here is specifically recursive dictionary classification for nested filter-array operands before ToUnicode CMap stream decoding.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, array/dictionary token parser, stream filter resolver, CMap parser, Identity-H fallback decoder, and WordPress smoke renderer. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive; no Python, model workers, external PDF tools, OCR, or raster backends were executed.

## Next Task

Continue with non-overlapping no-GPU markerPDF parser/converter boundaries: remaining font/CMap width edges, stream-filter owner recovery, annotations/forms/security review metadata, image/filter metadata, xref repair, page geometry, and supplied-boundary table/equation handoffs.
