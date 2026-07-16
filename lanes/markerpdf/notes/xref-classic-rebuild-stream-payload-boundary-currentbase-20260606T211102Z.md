# markerPDF classic xref rebuild stream-payload boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260606T211102Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260606T211102Z`
Base accepted HEAD: `88ddfe94849d1a826e6777df8358e3d94635ff84`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
routes searchable-PDF text, metadata, attachment review, and fallback parser
boundaries through PDF parser dependencies before OCR/model fallback. Under the
current no-GPU scope, this lane owns native PHP classic xref rebuild boundaries
so WordPress imports do not select stale attachment names, stale link
annotations, or stale object rows from bytes that are still owned by a stream.

## Behavior

Some damaged PDFs have a valid current classic xref table, followed by an
unreferenced stream object whose payload contains the byte sequence `endobj`,
then a fake classic `xref` table and stale trailer, before the real
`endstream` and final `endobj`. The final `startxref` may also point at a
damaged offset, causing classic rebuild scans to search backward.

`PdfAttachmentExtractor` previously found direct object bodies with a broad
`obj ... endobj` regex. That let fake `endobj` bytes inside the stream payload
end the direct-object range early, so the fake payload `xref` appeared
top-level and could replace current EmbeddedFiles review with stale attachment
rows. `PdfXrefFreeObjectMap` used the same first-`endobj` boundary for rebuild
candidate scanning, so a payload xref could also resurrect a freed annotation.

`PdfAttachmentExtractor` and `PdfXrefFreeObjectMap` now scan direct-object
boundaries token-aware enough to skip comments, strings, dictionaries, arrays,
hex strings, and stream payloads through `endstream` before accepting `endobj`.
Classic xref rebuild therefore ignores xref-looking bytes embedded inside a
still-open stream payload, keeping the current attachment and current free row.

## Red-First Evidence

After adding the focused fixture and before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php
FAIL keeps stream-owned fake classic xref tables out of rebuild before WordPress attachment review
Expected: array (
  0 => 'current-stream-owned-xref.xml',
)
Actual: array (
  0 => 'stale-stream-owned-xref.xml',
)
1 test files, 13 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildStreamPayloadBoundaryCurrentBaseTest.php
1 test files, 23 assertions, 0 failures
```

Classic xref family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfXrefClassic*Test.php' | sort)
19 test files, 1225 assertions, 0 failures
```

Attachment and embedded-file family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfAttachment*Test.php' -o -name 'PdfEmbeddedFile*Test.php' -o -name 'PdfEmbeddedFiles*Test.php' \) | sort)
39 test files, 3014 assertions, 0 failures
```

Freed annotation xref family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationFreedActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDamagedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationDuplicateRowCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationCompressedPrevOwnerCurrentBaseTest.php
8 test files, 89 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-stream-payload-boundary-currentbase.php
```

The smoke exits `0` and reports `current_text_selected=true`,
`current_attachment_selected=true`, `embedded_file_payload_current=true`,
`freed_annotation_suppressed=true`, `stream_owned_fake_xref_excluded=true`,
`stale_link_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted classic rebuild handling for malformed first rows,
partial trailing subsections, EOF-bounded trailing xref garbage, commented
tokens, literal/composite/name decoys, object-owned startxref tokens,
stream-owned trailer dictionaries, xref-stream owner boundaries, linearized
hint-table exclusion, xref `/Prev` repair, object-stream carrier recovery, or
metadata/text-side stream owner rows.

The bounded behavior here is stream-payload ownership for classic xref table
rebuild scans in attachment preflight and the xref free-object map when stream
payload bytes include a fake `endobj` before a fake `xref`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP token
parser helpers, direct-object scanner, classic xref rebuild selector, attachment
preflight summarizer, EmbeddedFiles extractor, link annotation free-map guard,
and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution,
PDFium rendering, external OCR/rendering helpers, and exact upstream model
benchmark parity remain intentionally outside the no-GPU markerPDF scope.
