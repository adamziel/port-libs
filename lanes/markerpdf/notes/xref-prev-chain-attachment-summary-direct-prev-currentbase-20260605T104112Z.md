# markerPDF xref Prev chain attachment-summary direct Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T104112Z`

Base accepted HEAD: `42ff13c6ab18e3cd15e26c4f396809c9223d5900`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing and attachment decisions to pdftext/PDFium-backed parser behavior before WordPress conversion. The native no-GPU PHP lane owns xref `/Prev` chain selection for current incremental updates, including attachment preflight summaries used before import.

PDF xref streams may use a direct `/Prev` numeric helper object that appears before the current xref stream. When current xref-stream type-1 rows carry damaged explicit offsets, attachment preflight must still select the current direct objects between the resolved `/Prev` offset and the current xref stream, rather than returning no attachments or falling back to stale previous-section FileSpecs.

## Behavior

`PdfAttachmentExtractor` now repairs in-use xref-stream rows with damaged explicit offsets by selecting the matching current direct object definition in the active update window. The repair is scoped to type-1 xref-stream rows after `/Prev` resolves, preserving exact current attachments while excluding stale previous attachments and post-xref helper decoys.

The focused fixture already covered text, metadata, and embedded-file extraction for a direct `/Prev 30 0 R` helper. This slice adds attachment-summary assertions for that same fixture: the preflight summary now reports `current-direct-prev-owner.xml`, the current byte count, non-executing runtime flags, and no stale `stale-direct-prev-owner` rows.

## Red first

After adding the attachment-summary assertions and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL repairs current xref-stream rows when direct Prev helper is shadowed after startxref target (lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 352 assertions, 1 failures
```

## Verification

Focused xref-prev run after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 365 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke emits `attachment_preflight_current_summary_selected=true`, `attachment_preflight_current_bytes_selected=true`, `attachment_preflight_no_runtime_execution=true`, `attachment_preflight_stale_prev_attachment_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted metadata-side damaged-offset repair, embedded-file name-tree repair, indirect `/W` and `/Index` attachment operand repair, classic table `/Prev` row repair, sparse Info inheritance, Info-null suppression, free-row suppression, direct/compressed `/Prev` helper text extraction, object-stream metadata, or xref owner-boundary work.

The bounded behavior here is specifically `PdfAttachmentExtractor::attachmentSummary()` row repair for damaged current type-1 xref-stream offsets after a direct `/Prev` helper resolves the active update window.

## Dependency closure

No new support component is needed. This slice reuses the native direct object scanner, parsed PDF dictionary/value model, xref stream decoder, Flate stream filter, `/Prev` chain walker, attachment preflight summarizer, and WordPress smoke renderer. Full upstream model parity remains intentionally out of scope under the no-GPU markerPDF directive: no Surya/Torch, Texify, live OCR, pypdfium/PDFium rendering, Streamlit/FastAPI workers, model downloads, or external PDF tools were executed.
