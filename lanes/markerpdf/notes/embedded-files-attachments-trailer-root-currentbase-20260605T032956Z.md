# markerPDF Embedded Files Attachment Trailer Root Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T032956Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T032956Z`

Base accepted HEAD: `5d60921e945ff92c944b35b49c220c1aa96c873a`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through parser-backed page text APIs; FileSpec dictionaries and embedded-file payload bytes are not visible paragraph text.
- PDF attachment preflight is a native no-GPU lane boundary for WordPress review. `/Names /EmbeddedFiles`, catalog/page `/AF`, and page FileAttachment annotations should be traversed from the active document catalog named by the latest trailer `/Root`, not from every orphan catalog-shaped object that remains in the xref table.
- Orphan catalog objects can remain in-use in repaired or incrementally updated files. They are not the document root and must not surface stale attachment filenames, page-associated rows, or embedded payload text in WordPress import summaries.

## Behavior

`PdfAttachmentExtractor` now identifies the valid latest trailer `/Root` catalog when a startxref trailer or xref stream provides one, then scopes all lightweight attachment traversal to that catalog:

- EmbeddedFiles name-tree entries;
- catalog-associated `/AF` FileSpecs;
- page-associated `/AF` FileSpecs from the root catalog page tree;
- page FileAttachment annotations from the root catalog page tree.

The existing no-xref fallback remains for simple fixture PDFs without a usable trailer root.

## Red/Green Evidence

Red-first after adding the focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses latest trailer Root catalog before orphan catalog attachment rows
Expected: 3
Actual: 5
1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses latest trailer Root catalog before orphan catalog attachment rows
1 test files, 46 assertions, 0 failures
```

Attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
6 test files, 899 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-trailer-root-currentbase.php
```

Result: emitted `attachment_count=2`, filenames `current-root-nametree.xml` and `current-root-page.xml`, `trailer_root_catalog_selected=true`, `orphan_catalog_attachments_excluded=true`, `page_af_from_root_pages_selected=true`, `payload_bytes_omitted=true`, and no Python/model/external PDF tool execution.

Syntax:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentTrailerRootBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachments-trailer-root-currentbase.php
```

Result: no syntax errors detected in all three changed PHP files.

Status JSON and whitespace:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
lanes/markerpdf/lane-status.json: valid JSON
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json: valid JSON

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted platform FileSpec filename selection, `/EF` key preference, `/AFRelationship` role mapping, checksum and declared-size review, related-file `/RF` rows and name pairs, EmbeddedFiles `/Limits` pruning, indirect `/Names` arrays, catalog/page `/AF` extraction, FileAttachment annotation mirrors, direct FileSpec mirror dedupe, EOF-bounded object scanning, xref-selected same-object rows, xref-stream object-stream FileSpec recovery, portfolio/PieceInfo/XMP/OutputIntent metadata, or attachment payload exclusion.

The bounded behavior is only trailer-root catalog selection for the lightweight attachment preflight when multiple catalog objects are present.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, startxref trailer/xref-stream parsing, page-tree traversal, FileSpec parser, stream filter decoder, checksum review, and WordPress smoke pattern. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF no-GPU slice.
