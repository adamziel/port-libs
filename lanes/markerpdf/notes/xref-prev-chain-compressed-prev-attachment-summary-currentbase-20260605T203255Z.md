# xref Prev Chain Compressed Prev Attachment Summary Current Base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T203255Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260605T203255Z`
Base accepted HEAD: `bd7df865700dfaabbc10e2b866ce008e83e43e09`

## Source Truth

Upstream markerPDF delegates searchable-PDF xref parsing to native PDF parser behavior. In this no-GPU PHP lane, xref repair is owned locally: an incremental xref stream may store `/Prev` as an indirect generation-zero object, and that helper can itself be an ordinary member of a current `/ObjStm` carrier before the xref stream. Only safe scalar numeric helper bodies are resolved for this boundary.

## Behavior

`PdfEmbeddedFileExtractor` now records generation metadata when a safe compressed `/Prev` helper is injected into its temporary operand object map, so direct reference resolution can read that helper before xref-stream row repair.

`PdfAttachmentExtractor` now resolves safe `/Prev` numeric helpers from direct object-stream members before decoding current xref-stream rows. The lookup is bounded to generation-zero helpers, direct `/ObjStm` carriers before the xref stream offset, and helper bodies that contain only an integer token. Current attachment summary rows then repair stale offset-zero xref rows and select current EmbeddedFiles metadata instead of stale previous-section attachments.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`  
  Result before source changes after adding the summary case: `1 test files, 455 assertions, 2 failures`.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`  
  Result: `1 test files, 481 assertions, 0 failures`.
- Focused regression: `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php`  
  Result: `8 test files, 1478 assertions, 0 failures`.
- Syntax/JSON: `php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-compressed-helper-attachment-summary-currentbase.php`; `php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json OK\n";'`  
  Result: no syntax errors and `lane-status.json OK`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-xref-prev-compressed-helper-attachment-summary-currentbase.php`  
  Result: exits 0 and reports `attachment_count=1`, `current-compressed-prev-summary.xml`, `total_bytes=67`, `compressed_prev_helper=true`, `native_pdf_boundary=true`, and no Python/model/external PDF tool execution.
- Whitespace: `git diff --check -- lanes/markerpdf`  
  Result: exits 0.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat direct `/Prev` helpers, indirect classic-table `/Prev` helpers, damaged explicit offset repair, hybrid xref table/xref-stream merging, object-stream selected metadata dictionaries, or attachment object-stream FileSpec preflight. The new boundary is the compressed object-stream `/Prev` numeric helper feeding current xref-stream row repair for embedded-file extraction and lightweight attachment summaries.

## Dependency Closure

No new support component is needed. This reuses native PHP direct object scanning, xref table/stream walking, Flate stream decoding, object-stream member selection, embedded-file extraction, and attachment-summary preflight. OCR, Surya, Texify, Torch, PDFium execution, raster rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF override.

## Next

Continue with non-overlapping native PDF parser fidelity around xref repair edges that affect forms, annotations, page geometry, image/filter metadata, font/CMap selection, and security preflight.
