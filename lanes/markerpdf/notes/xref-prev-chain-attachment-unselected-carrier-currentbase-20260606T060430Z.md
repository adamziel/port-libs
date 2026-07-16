# markerPDF xref Prev chain attachment unselected carrier current-base

Date: 2026-06-06 UTC
Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T060430Z`
Base accepted HEAD: `93b3b4a17fab2567420adc36472c6c9eb55618e0`

## Source Truth

Upstream markerPDF delegates searchable-PDF text and attachment discovery to parser-backed PDF dependencies before OCR/model fallback. In the native no-GPU PHP lane, xref `/Prev` chain merging must keep previous compressed object rows bound to the object-stream carrier selected by that previous xref section. A stale type-2 row from `/Prev` must not bind to a replacement `/ObjStm` body that the current xref section never selected.

## Behavior

`PdfAttachmentExtractor` now applies the same inherited type-2 object-stream guard already used by text, metadata, and embedded-file extraction. When a current xref stream replaces an object-stream carrier but does not select that carrier row, inherited previous compressed attachment rows are skipped instead of producing a WordPress attachment preflight row from replacement carrier payload bytes.

This keeps attachment preflight aligned with visible text, document metadata, and `EmbeddedFiles` extraction for the existing unselected-carrier fixture.

## Red Probe

Before the source change, this direct focused probe returned a stale attachment preflight row:

```text
php -r 'require "tools/bootstrap.php"; require "lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php"; $pdf = $xrefPrevChainUnselectedCarrierCurrentBasePdf(); $attachments = (new PortLibs\MarkerPDF\PdfAttachmentExtractor())->extractAttachments($pdf); var_export($attachments); echo "\n";'
```

It emitted `previous-carrier-leak.xml` / `unselected-carrier-leak.xml` with embedded `<wp-export>` bytes.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips previous type-2 attachment rows whose object-stream carrier was never selected
PASS keeps attachment preflight from inheriting previous type-2 rows whose carrier was replaced
1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierPrevMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
6 test files, 617 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unselected-carrier-currentbase.php
```

The smoke reports `attachment_preflight_unselected_carrier_excluded=true`, `unselected_carrier_attachment_excluded=true`, `replacement_object_stream_payload_excluded=true`, `current_page_text_selected=true`, `current_catalog_language_selected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and workspace checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unselected-carrier-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unselected-carrier-currentbase.php

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited cleanly.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat text extraction, document metadata, `EmbeddedFiles` extraction, current object-stream carrier recovery, Prev-free carrier repair, omitted current row graph repair, stale explicit-offset repair, damaged row-owner repair, compressed `/Prev` helper resolution, plus-signed `/Prev` parsing, hybrid table companion xref streams, or live OCR/model/PDFium execution.

The bounded behavior is only attachment preflight inheritance: previous xref type-2 rows are skipped when their original object-stream carrier is replaced by unselected current storage.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref stream decoder, object-stream member decoder, `/Prev` chain merger, attachment preflight extractor, and WordPress smoke path. GPU/OCR/model execution, PDFium rendering, external PDF tools, and upstream visual/model benchmark parity remain intentionally out of scope.
