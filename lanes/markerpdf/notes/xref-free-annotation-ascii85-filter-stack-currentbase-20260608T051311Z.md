# markerPDF xref free annotation ASCII85 filter stack current base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T051311Z`

Accepted base: `a7130e39566f87e0f070ab864cbb860b9ffe3872`

## Source Truth

PDF xref streams are stream objects and can use normal PDF stream filters. The existing native free-object map already decoded raw, `/FlateDecode`, and `/ASCIIHexDecode` plus Flate xref streams before suppressing stale annotations from older xref sections. This slice extends that same bounded no-GPU parser path to `/ASCII85Decode` or `/A85` xref-stream wrappers before Flate rows are read.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationAscii85FilterStackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL suppresses stale free annotations from current xref streams with ASCII85 and Flate filters (lanes/markerpdf/tests/PdfXrefFreeAnnotationAscii85FilterStackCurrentBaseTest.php)
The free-object map must decode ASCII85 xref-stream filter stacks before reading free rows.

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfXrefFreeObjectMap` now accepts `/ASCII85Decode` and `/A85` in its lightweight xref-stream filter loop.
- The local decoder requires a `~>` EOD marker, honors PDF filter whitespace, supports `z` zero tuples, rejects invalid partial groups and tuple overflow, and then passes the decoded bytes to the existing Flate stage.
- This is scoped to free-object map preflight for xref streams; it does not change OCR/model behavior, raster rendering, or the broader text stream decoder.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationAscii85FilterStackCurrentBaseTest.php
PASS suppresses stale free annotations from current xref streams with ASCII85 and Flate filters

1 test files, 8 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefFreeAnnotationAscii85FilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefFreeAnnotationFilterStackCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainFreeAnnotationIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
PASS applies xref stream filter DecodeParms before current-base object selection
PASS suppresses stale free annotations from current xref streams with ASCII85 and Flate filters
PASS suppresses stale free annotations from current xref streams with ASCIIHex and Flate filters
PASS suppresses stale page annotations when current xref-stream free rows use indirect W and Index operands
PASS suppresses stale page annotations when latest xref-stream Prev is an indirect numeric helper

5 test files, 44 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-free-annotation-ascii85-filter-stack-currentbase.php
<!-- markerpdf-xref-free-annotation-ascii85-filter-stack-currentbase {"native_boundary":"current xref-stream ASCII85 plus Flate filter stack free rows suppress stale page annotations","xref_stream_filters":["ASCII85Decode","FlateDecode"],"free_annotation_suppressed":true,"stale_link_excluded":true,"stale_review_payload_excluded":true,"executes_python_or_models":false,"executes_external_pdf_tools":false} -->
<p>Current ASCII85 xref free annotation smoke page</p>
```

Additional final lint/diff checks are recorded in the final handoff response.

## Non-Overlap

This does not repeat accepted page content stream filter-stack recovery, attachment stream filter stacks, image/inline-image ASCII85 boundaries, ASCIIHex xref free-row stacks, xref object-stream member-tail boundaries, xref `/Prev` free-row ownership, xref DecodeParms helper resolution, object-stream annotation repair, or GPU/OCR/model execution.

## Dependency Closure

No new support component is needed. This reuses the native xref-stream row parser, existing Flate support through PHP zlib, annotation/link free-object suppression, and a small local ASCII85 decoder bounded to xref-stream free-row preflight. Full upstream model/OCR parity remains intentionally out of scope for the current no-GPU markerPDF lane.
