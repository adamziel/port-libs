# EmbeddedFiles Attachment Name-Tree Duplicate Node Boundary Current Base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T223952Z`

Base accepted HEAD: `8b51ce617f0cd7f708fa5f08a3c5adecb0304924`

## Scope

This patch keeps both attachment review APIs aligned on a malformed PDF
name-tree boundary: an EmbeddedFiles child node whose dictionary repeats
traversal keys such as `/Names`, `/Kids`, or `/Limits` is skipped before
FileSpec extraction. Clean sibling nodes still contribute review attachments.

The boundary is native no-GPU PDF parser behavior only. It does not execute
Python, OCR, Surya, Texify, Torch, PDFium rendering, decryption, attachment
payloads, or external PDF tools.

## Evidence

Red-side probe before edit on accepted base:

```text
php <<'PHP'
... duplicate /Names child-node fixture ...
PHP
```

Result: both `PdfAttachmentExtractor::attachmentSummary()` and
`PdfEmbeddedFileExtractor::extractEmbeddedFiles()` returned
`["clean.xml", "malformed.xml"]`, admitting the duplicate-key child node.

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeDuplicateNodeKeyBoundaryCurrentBaseTest.php
```

Result: `1 test files, 59 assertions, 0 failures`.

Adjacent attachment name-tree family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeDuplicateNodeKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentDuplicateNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDuplicateInvalidNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php
```

Result: `5 test files, 222 assertions, 0 failures`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-nametree-duplicate-node-currentbase.php
```

The smoke emits `malformed_duplicate_node_skipped=true`,
`clean_sibling_preserved=true`, `payload_bytes_omitted_from_summary=true`,
`visible_text_excludes_attachment_payloads=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted attachment work for duplicate name-tree row keys,
malformed-first duplicate FileSpecs, duplicate FileSpec or `/EF` dictionary
keys, escaped duplicate keys, indirect name keys, kid `/Limits` ordering,
catalog/page `/AF` mirrors, encrypted EFF suppression, related-file `/RF`
review, portfolio/PieceInfo metadata, stream-filter decoding, object streams,
xref repair, annotations, forms, image/filter metadata, OCR/model workers, or
supplied-boundary table/equation handoffs. The bounded behavior is only
duplicate traversal keys on an EmbeddedFiles name-tree node before attachment
review.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP PDF
dictionary tokenization, raw duplicate-key counting, name-tree traversal,
FileSpec extraction, embedded-stream review, and WordPress smoke patterns.
