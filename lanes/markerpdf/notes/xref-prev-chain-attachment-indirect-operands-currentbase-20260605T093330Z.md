# markerPDF xref Prev chain attachment indirect operands current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T093330Z`

Base accepted HEAD: `2c2ebe381d3997ecc009f7ff86452373d7b92f2f`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF parsing and attachment/metadata decisions through pdftext/PDFium-backed parser boundaries before WordPress conversion. The native PHP lane therefore owns xref-stream dependency behavior for attachment preflight when current incremental updates store xref stream operands indirectly.

PDF xref streams may store `/W`, `/Index`, `/Size`, `/Filter`, and `/Prev` operands as indirect objects. For incremental updates, those helper objects must be selected from the exact direct object generation before the xref stream object. Later same-number direct objects appended after the xref stream are not xref-selected helpers and must not redirect WordPress attachment preflight to stale `/Prev` attachments.

## Behavior

`PdfAttachmentExtractor` now resolves xref-stream dictionary operands through direct object definitions that match the requested generation and occur before the xref stream offset. This allows attachment preflight to decode current xref stream rows when `/W` and `/Index` are indirect helpers, while excluding later same-object-number post-xref decoys and previous `/Prev` name-tree attachments.

The focused fixture keeps an older xref table with `previous-indirect-operands.xml`, appends current catalog/name-tree/FileSpec rows, stores `/W 30 0 R` and `/Index 31 0 R` before the current xref stream, then appends same-number post-xref decoy catalog/name-tree/FileSpec rows. Text, metadata, embedded-file extraction, and attachment preflight now select `current-indirect-operands.xml` and exclude both the previous attachment and the post-xref decoy.

## Red first

Before the source change, the new attachment-preflight assertion failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL resolves xref-stream W and Index helpers before stale post-xref direct objects
Expected: array (
  0 => 'current-indirect-operands.xml',
)
Actual: array (
  0 => 'previous-indirect-operands.xml',
)
```

## Verification

Focused xref-prev run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 357 assertions, 0 failures
```

Adjacent attachment/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
5 test files, 1254 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-attachment-indirect-operands-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-attachment-indirect-operands-currentbase.php
```

The smoke emits `current_attachment_summary_selected=true`, `current_embedded_file_selected=true`, `current_payload_selected=true`, `previous_prev_attachment_excluded=true`, `post_xref_decoy_attachment_excluded=true`, and no Python/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted metadata-side damaged-offset repair, current-generation trailer `/Root` repair, same-generation xref-stream row repair, classic table damaged `/Prev` repair, indirect `/Prev` helper repair, compressed `/Prev` helper repair, sparse latest trailer `/Info` inheritance, free-row suppression, object-stream carrier recovery, or link annotation parent-generation behavior.

The bounded behavior here is specifically `PdfAttachmentExtractor` attachment-summary selection when the current xref stream's `/W` and `/Index` operands are indirect helpers and post-xref same-number decoys exist.

## Dependency closure

No new support component is needed. This slice reuses the native direct object scanner, parsed PDF dictionary/value model, Flate stream decoder, xref table/stream `/Prev` chain walker, embedded-file attachment preflight, and WordPress smoke renderer. Full upstream model parity remains out of scope under the no-GPU markerPDF directive: live OCR, Surya/Torch, Texify, pypdfium/PDFium rendering, tabled-pdf model execution, Streamlit/FastAPI workers, and exact model benchmark parity were not run.
