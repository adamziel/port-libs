# markerPDF xref object-stream omitted-carrier current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T105017Z`  
Accepted base: `e7428ba9eda23e1e08d47b2021a4ef6e529d4e53`  
Scope: native no-GPU searchable-PDF parser behavior only.

## Source Truth

Upstream markerPDF delegates searchable-PDF parsing to pdftext/PDFium before OCR/model fallback. In the native PHP no-GPU lane, xref streams and PDF 1.5 object streams are parser dependency boundaries: type-2 xref rows identify an object-stream carrier and a member index, then the selected member dictionaries feed page-tree traversal before WordPress text import.

Malformed producer output can include valid current type-2 rows for compressed catalog/page dictionaries while omitting the carrier object's own direct row from the current xref stream. When the carrier is a direct `/Type /ObjStm` object in the same current revision window, the parser can safely infer that direct carrier instead of dropping all compressed page dictionaries or scanning stale free streams.

## Behavior Added

- `PdfTextExtractor::repairCurrentObjectStreamCarrierRows()` now repairs missing carrier rows, not only malformed existing carrier rows.
- The repair is bounded to direct `/ObjStm` definitions whose byte offset is after the previous xref section and before the current xref section.
- Added `PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php`, where the current xref stream maps catalog/page dictionaries as type-2 members of carrier `6 0 R`, omits row 6, keeps the content stream direct, and marks a stale direct stream free.
- Added a WordPress smoke that renders only the current object-stream page paragraphs and reports the carrier inference as review metadata without executing Python, models, or external PDF tools.

## Red-First Evidence

Before the carrier-row repair, the focused omitted-carrier fixture returned no page text because the selected object map never included object-stream carrier `6`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL infers a current direct object-stream carrier omitted from xref-stream rows before text extraction
Expected: ['Current omitted carrier row page','Direct carrier inferred']
Actual: []
1 test files, 1 assertions, 1 failures
```

The final fixture keeps top-level stream objects out of `/ObjStm`; only catalog/page dictionaries are compressed, matching the existing parser boundary that rejects compressed stream members.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS infers a current direct object-stream carrier omitted from xref-stream rows before text extraction
1 test files, 14 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 60 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-omitted-carrier-currentbase.php
```

The smoke emits `current_compressed_page_selected=true`, `direct_carrier_inferred=true`, `stale_free_stream_excluded=true`, `compressed_entry_count=3`, `object_4_owner_policy=xref_selected_object_stream_carrier`, `page_count=1`, and no Python/model/external PDF tool execution.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream member-index repair, escaped `/Type /XRef` and `/Type /ObjStm` name decoding, filter-chain operand recovery, duplicate member-offset rejection, stream-member rejection, object-stream free-entry `/Prev` handling, replaced-carrier generation handling, hybrid table free-entry precedence, linearized hint-range object-stream member exclusion, or xref-stream malformed `/W`/`Index` validation.

The bounded behavior here is only current-revision direct `/ObjStm` carrier inference when current type-2 xref rows reference that carrier but omit the carrier's own row.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, startxref/xref-stream parser, xref `/Prev` windowing, Flate decoder, object-stream member parser, page-tree walker, content-stream text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium execution, external PDF tools, and exact upstream model benchmark parity remain intentionally outside this no-GPU markerPDF scope.
