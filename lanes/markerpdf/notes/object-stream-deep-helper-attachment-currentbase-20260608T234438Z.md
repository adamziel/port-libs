# markerpdf-object-stream-xref-parser-current-base-20260608T234438Z

Accepted base: `04878c2d5c57d16172dcae66b4ced2d6a4447658`

Scope: native no-GPU markerPDF object-stream xref parsing for WordPress attachment summaries.

## Source Truth

- PDF 1.5 object streams can carry ordinary non-stream objects selected by xref type-2 rows.
- This slice keeps the existing upstream-shaped fail-closed behavior for malformed object-stream dictionaries, but aligns the lightweight `PdfAttachmentExtractor` fixed-point expansion depth with the native text/metadata/embedded parser paths.
- The fixture uses a current xref stream with `W [1 4 1]` and `/Index [1 9 20 10 30 24]`. The selected FileSpec is object `4 0` in object stream `20`, while its carrier dictionary `/N`, `/First`, and `/Length` operands are themselves compressed members of a nine-hop helper chain.

## Red-First Evidence

Before the source change, the focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeepHelperAttachmentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL expands deep compressed object-stream dictionary helpers before WordPress attachment review
Values are not identical
Expected: 1
Actual: 0

1 test files, 7 assertions, 1 failures
```

## Patch

- `PdfAttachmentExtractor::withCompressedObjectStreamObjects()` now runs eight fixed-point passes instead of four.
- This mirrors `PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` object-stream expansion depth and lets attachment summaries see deeply chained compressed dictionary helper operands before resolving the selected FileSpec.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeepHelperAttachmentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands deep compressed object-stream dictionary helpers before WordPress attachment review

1 test files, 20 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-deep-helper-attachment-currentbase.php
exits 0; smoke reports attachment_count=1, attachment_filename=deep-helper-current.csv, compressed_entry_count=25, filespec_carrier_resolved=true, payload_bytes_omitted_from_summary=true, executes_python_or_models=false, executes_external_pdf_tools=false.
```

Additional focused verification:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamDeepHelperAttachmentCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-deep-helper-attachment-currentbase.php
No syntax errors detected in all changed PHP files.
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDeepHelperAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
4 test files, 85 assertions, 0 failures
```

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
lane-status.json valid
```

```text
git diff --check -- lanes/markerpdf
no whitespace errors
```

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP stream filtering, xref-stream row parsing, object-stream member selection, indirect integer resolution, and attachment-summary extraction. No Python, OCR/models, GPU/model workers, PDFium/PIL, online services, or external PDF tools are invoked.

## Non-Overlap

This does not repeat accepted object-stream coverage for plus-signed headers, duplicate offsets/object numbers, stale carrier replacement, `/Prev` carrier generation repair, hybrid `/XRefStm`, xref-stream width/index/size row boundaries, or Type3 CharProcs stream-generation fallback. The behavior here is the attachment extractor's object-stream fixed-point depth for deeply chained compressed dictionary helpers.
