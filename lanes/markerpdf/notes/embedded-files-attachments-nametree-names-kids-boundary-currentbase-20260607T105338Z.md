# EmbeddedFiles Attachment Name-Tree Names/Kids Boundary

Session: `port-dev-markerpdf-attachments-20260607T105338Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T105338Z`
Base accepted HEAD: `7024533eae898cea81b789321e8a4eb61cd2cb35`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF page text through `pdftext.dictionary_output()` and PDFium-facing page text APIs; embedded-file payloads are not visible text sources. In this native no-GPU lane, EmbeddedFiles and associated FileSpec rows are WordPress import review metadata only.

PDF EmbeddedFiles are represented by a catalog `/Names /EmbeddedFiles` name tree. Name-tree intermediate nodes carry `/Kids`; leaf nodes carry `/Names`. A malformed current-base PDF can put both keys on one node, making stale local `/Names` entries appear before valid child traversal. The native preflight now treats nodes with `/Kids` as intermediate nodes, skipping their local `/Names` values while still walking child nodes.

## Behavior Implemented

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now process local `/Names` entries only when the current EmbeddedFiles name-tree node has no `/Kids`. Valid child attachments are preserved; stale or malformed local leaf entries on an intermediate node are excluded from full embedded-file extraction, lightweight attachment summaries, and visible text.

The red-first fixture had object `6` with both `/Names [(local-stale.xml) 10 0 R]` and `/Kids [7 0 R]`. Before the fix the current base returned both `local-stale.xml` and `child-source.xml`. After the fix only the child node attachment `child-source.xml` remains.

## Verification

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentNameTreeNamesKidsBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-nametree-names-kids-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentNameTreeNamesKidsBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-nametree-names-kids-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeNamesKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats EmbeddedFiles nodes with Kids as intermediate before WordPress attachment review

1 test files, 48 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php
Focused test run: 48 selected test files (root lock skipped)
...
48 test files, 3532 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-nametree-names-kids-boundary-currentbase.php
```

The smoke exits 0 and emits `child_attachment_preserved=true`, `local_names_entry_excluded=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

Exited 0 with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/AF`, catalog `/AF`, FileAttachment annotation, direct FileSpec mirrors, platform `/EF` key order, checksum/Params, stream-filter stack, encryption/EFF, generation/xref, duplicate-key, Portfolio folder/schema/PieceInfo, child `/Kids /Limits` ordering, leaf `/Names` sorting, indirect `/Kids` arrays, or PDFDocEncoding byte-limit slices. The bounded behavior is only the malformed name-tree node boundary where `/Kids` makes the node intermediate and local `/Names` rows are excluded.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, name-tree traversal, FileSpec dictionary parsing, stream-filter decoding, and WordPress smoke pattern. GPU/model OCR, Surya/Texify/Torch execution, pypdfium raster rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
