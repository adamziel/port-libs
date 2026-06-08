# markerPDF xref Prev-chain classic sparse offset-owner repair

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260608T044455Z`
Base accepted HEAD: `6aca64b1e7abf114b75d86b491d7c036d94a8253`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
routes searchable-PDF extraction through pdftext/PDFium-backed parsing. Under
the current no-GPU lane scope, this PHP port owns the native xref parser
boundary that selects the current revision's page text, metadata, and
attachments before WordPress import.

PDF incremental updates may append a latest classic xref table with a sparse
trailer that omits `/Root` and `/Info`, relying on `/Prev` to inherit those
trailer references. If the latest table's subsection header is damaged, rows
can be assigned to the wrong object numbers even though their explicit byte
offsets point at valid current-update objects. Those offset owners must be
selected before stale previous rows are merged.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and
`PdfAttachmentExtractor` now remap latest classic-table in-use rows to the
direct object that owns the explicit offset when that owner is inside the
current update window between `/Prev` and the latest xref table. The stale
wrong row key is removed, matching the existing xref-stream offset-owner
repair policy and preventing previous rows from reviving stale text,
metadata, or embedded-file payloads.

## Evidence

Red-first after adding the focused fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL repairs sparse latest classic table rows whose subsection object numbers point at current offset owners
Expected: array (
  0 => 'Current sparse misnumbered table page',
  1 => 'Offset owner rows selected',
)
Actual: array (
  0 => 'Stale sparse misnumbered table page',
)
1 test files, 586 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
32 PASS cases
1 test files, 612 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-classic-sparse-offset-owner-currentbase.php
```

The smoke exits `0` and reports current text, current XMP title, current Info
title, current language, current EmbeddedFiles payload, current attachment
summary, sparse latest trailer `/Prev` inheritance, stale previous payload
exclusion, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP classic
xref table parser, `/Prev` traversal, direct-object offset ownership checks,
metadata extraction, embedded-file extraction, and attachment summary
preflight already present in markerPDF.

## Non-Overlap

This does not repeat accepted xref-stream stale explicit-offset repair,
xref-stream malformed `/Index` owner repair, sparse xref-stream trailer Root
inheritance, direct/indirect `/Prev` helper repair, zero-width xref-stream
offset repair, free-row suppression, root-null/Info-null inheritance
boundaries, object-stream carrier repair, hybrid `/XRefStm` precedence, or
DCTDecode/filter-boundary work. This slice is only the latest classic xref
table sparse-trailer case where damaged subsection object numbers must be
repaired from current direct offset owners before previous rows are merged.
