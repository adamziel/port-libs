# markerPDF stream filter nested DecodeParms boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T221252Z`

Accepted base: `8939d9ec1b75b1ccc78dcd11b00b99d8e8fa44a9`

## Source truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium owning low-level stream filter decoding before OCR/layout/model stages.
- PDF stream `/DecodeParms` may be a dictionary or an array with one dictionary or `null` entry per filter. A nested array in one slot is malformed and must not be reinterpreted as a valid predictor dictionary for WordPress text extraction.

## Behavior

`PdfTextExtractor` now resolves each slot in a filter-aligned DecodeParms array through a stricter scalar parser:

- accepts `null`;
- accepts direct dictionaries;
- accepts indirect references to `null` or dictionaries;
- rejects direct nested arrays such as `[ << /Predictor 1 >> ]`;
- rejects indirect helper objects that resolve to nested arrays.

This keeps malformed Flate/LZW predictor streams fail-closed while preserving later safe page content streams.

## Verification

Red probe before source edit:

```bash
php -r '...focused fixture with /DecodeParms [ [ << /Predictor 1 >> ] ] ...'
```

Output included both `Nested DecodeParms Leak` and `Visible After Nested Params`, proving the malformed slot was accepted before the fix.

Focused passing checks:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
```

Result: `1 test files, 280 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentBoundaryCurrentBaseTest.php
```

Result: `7 test files, 352 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-nested-decodeparms-currentbase.php
```

The smoke emitted `direct_nested_decodeparms_rejected=true`, `indirect_nested_decodeparms_rejected=true`, `visible_fallback_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Known baseline note: the broader `PdfTextExtractorTest.php` currently has two unrelated ToUnicode `usecmap` failures. A clean `HEAD` archive of accepted base `8939d9ec1b75b1ccc78dcd11b00b99d8e8fa44a9` reproduced the same `1 test files, 626 assertions, 2 failures`, so this slice does not attempt to fix or count that CMap behavior.

## Non-overlap

This does not repeat accepted ASCII85/Flate stack recovery, stale or short `/Length` recovery, RunLength/LZW EOD recovery, singleton or compact DecodeParms alignment around null filters, all-null stacks, stray DecodeParms handling, indirect null filter objects, identity/default/private Crypt stack semantics, extra DecodeParms fail-closed behavior, fallback trailing-null DecodeParms scans, parser-comment split indirect references, duplicate stream Filter/DecodeParms key rejection, duplicate DecodeParms parameter rejection, object-stream filter-chain helper resolution, CMap-specific DecodeParms handling, image-filter exclusion, or inline-image tokenizer boundaries.

The bounded behavior is specifically malformed nested array entries inside ordinary page content stream DecodeParms arrays.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenized indirect-reference parser, stream dictionary reader, stream filter resolver, DecodeParms parser, predictor decoder, content-token parser, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
