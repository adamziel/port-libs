# markerPDF classic xref rebuild boundary current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T172004Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260605T172004Z`
Base accepted HEAD: `10c44986a474bf4de69ac440690f264b5c9f43b9`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
delegates searchable PDF text, metadata, and attachments to parser-backed
PDF extraction before OCR/model fallback. Under the current no-GPU scope, this
lane owns the native PHP xref-table rebuild boundary so WordPress imports do
not select stale page text, stale XMP/Info metadata, or stale EmbeddedFiles
when producer xref rows are damaged.

## Behavior

Some damaged PDFs have a valid current classic xref table whose first
subsection is complete, followed by a later trailing subsection that starts
with one valid-looking row and then becomes malformed. The prior parser treated
that partial trailing subsection as invalidating the entire current table.
When the final `startxref` operand was also damaged, rebuild then fell back to
the previous xref table and selected stale `/Prev` page text, metadata, and
attachments.

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and
`PdfAttachmentExtractor` now preserve entries from completed prior xref
subsections when a later subsection is partially malformed. Malformed rows in
the first subsection still fail the candidate table closed, preserving the
accepted malformed-row behavior.

## Red-First Evidence

After adding the focused fixture and before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
FAIL preserves completed classic xref subsections before partial malformed trailing subsections
Expected: Current partial-trailing xref page / Partial trailing subsection ignored
Actual: Stale partial-trailing xref page / Partial trailing subsection leak
1 test files, 608 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
1 test files, 634 assertions, 0 failures
```

Adjacent xref boundary check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicMalformedStartxrefBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicZeroCountRebuildBoundaryCurrentBaseTest.php
3 test files, 693 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-partial-trailing-subsection-currentbase.php
```

The smoke exits 0 and reports `current_text_selected=true`,
`current_metadata_selected=true`, `current_info_selected=true`,
`current_attachment_selected=true`,
`attachment_preflight_uses_current_root=true`,
`stale_prev_text_excluded=true`, `stale_prev_metadata_excluded=true`,
`stale_prev_attachment_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed first-section row rejection, punctuation row
suffix rejection, zero-count subsection rejection, comment-only row skipping,
commented `xref`/`startxref` tokens, composite/name/literal/hex decoys,
post-`startxref` trailers, stream-owned trailer dictionaries, direct object
offset repair, `/Prev` generation repair, xref-stream object-stream repair,
or inline-image/ActualText tokenizer behavior.

The bounded behavior here is only preserving already-completed current classic
xref subsections when a later trailing subsection has a valid partial row and
then fails.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct
object scanner, classic xref table parser, startxref rebuild selection,
metadata extractor, text extractor, embedded-file extractor, attachment
preflight summarizer, and WordPress smoke path. OCR, Surya/Texify/Torch model
execution, PDFium rendering, external OCR/rendering helpers, and exact
upstream model benchmark parity remain intentionally outside the no-GPU
markerPDF scope.
