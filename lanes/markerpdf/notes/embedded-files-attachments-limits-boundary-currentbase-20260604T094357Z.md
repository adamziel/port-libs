# markerPDF EmbeddedFiles Attachment Limits Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260604T094357Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260604T094357Z`
Base accepted HEAD: `c7b25dd480694a94bfbc6b3af5e4b6fb5fc71d56`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible page text through `pdftext.dictionary_output()` and pypdfium page text APIs in `marker/pdf/extract_text.py`; attachment FileSpec payloads are not promoted into that visible text path:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDF name trees use `/Names` key/value pairs and `/Limits` lower/upper strings to bound the contiguous key range in leaf or descendant nodes. The `EmbeddedFiles` name tree maps filenames to FileSpec dictionaries for attachments:

- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf
- https://pdfa.org/files-inside-pdf/
- https://pypdf.readthedocs.io/en/6.0.0/user/handle-attachments.html

## Behavior

`PdfEmbeddedFileExtractor` and `PdfAttachmentExtractor` now apply inherited EmbeddedFiles name-tree `/Limits` before importing FileSpec rows. A leaf with `Limits [(current-source.xml) (current-source.xml)]` can no longer surface a valid stale sibling row such as `(zz-stale-source.xml) 20 0 R` as a WordPress attachment.

The implementation mirrors the existing `PdfMetadataExtractor` name-tree boundary:

- merge parent and child `/Limits` as an effective range;
- accept only names inside the effective range;
- keep the previous defensive fallback when a malformed node has `/Limits` that match none of its own names, using the inherited range rather than dropping the subtree;
- retain cycle/depth guards and existing attachment metadata behavior.

## Red-First Evidence

Before the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
FAIL prunes out-of-limits EmbeddedFiles name-tree rows before attachment import
Expected: 1
Actual: 2

php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
FAIL prunes out-of-limits EmbeddedFiles name-tree attachments in WordPress preflight
Expected: 1
Actual: 2
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
1 test files, 352 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 75 assertions, 0 failures
```

Focused delta:

- `PdfEmbeddedFileExtractorTest.php`: 16 PASS cases / 352 assertions after patch.
- `PdfAttachmentExtractorTest.php`: 6 PASS cases / 75 assertions after patch.
- Added 2 focused PASS cases over the prior 1042 PASS baseline, for an expected lane count of 1044.

## WordPress Smoke

`wordpress-pdf-attachments-preflight.php` now includes a stale out-of-limits `zz-stale.csv` FileSpec with a valid embedded stream. The smoke asserts that the attachment summary excludes that name and emits `pruned_out_of_limits_name_tree_entry=true` while still reporting the current name-tree attachment and page FileAttachment annotation.

## Non-Overlap

This does not repeat platform filename source selection, `/EF` key selection, `/AFRelationship` role mapping, Params checksum match state, portfolio `/Collection`, FileSpec `/CI`, PieceInfo, XMP/OutputIntent, page-associated files, action FileSpec, or annotation FileAttachment extraction. The new boundary is only EmbeddedFiles name-tree `/Limits` pruning for stale attachment rows in both the full embedded-file extractor and the lightweight WordPress attachment preflight extractor.

## Dependency Closure

No new support component is needed. The patch reuses native PHP object scanning, dictionary/value parsing, stream decoding, and existing metadata name-tree semantics. Full live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
