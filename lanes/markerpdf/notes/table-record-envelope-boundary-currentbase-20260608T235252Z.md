# Table Record Envelope Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T235252Z`

Base accepted HEAD: `d882dae9d858147bc44d510727ef5cac23951c50`

## Source Truth

- Upstream `tabled-pdf==0.1.4` table sidecars serialize row/column bands and
  `SpanTableCell`-style records with `bbox`, `text`, `row_ids`, and `col_ids`.
- Adapter/Pydantic dumps may preserve an individual model object under a
  record key such as `row`, `column`, `cell`, `table_cell`, or `span_cell`
  instead of making that inner `bbox` the top-level record.
- This no-GPU markerPDF lane uses supplied table-recognition artifacts only.
  It does not execute Surya, tabled models, OCR, Python, PDFium/PIL, or external
  PDF tools.

## Patch

- `TableRecognizer` now unwraps geometry-bearing row, column, and cell record
  envelopes before:
  - coordinate-space review and page-image-to-table-crop localization;
  - assigned-cell source boundary detection;
  - row/column/cell normalization;
  - OCR grid-border candidate-cell bbox localization.
- The unwrap is intentionally bounded to geometry-bearing payloads so unrelated
  metadata containers are not treated as table records.

## Evidence

Red-first focused check before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRecordEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL markerpdf table geometry unwraps row column and cell record envelopes before supplied boundary formatting
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.
FAIL markerpdf supplied document converter replaces stale pdf text with record envelope table markdown
Table geometry entries must include a four-value bbox, named bbox fields, two-corner point fields, or four-corner polygon alias.

1 test files, 0 assertions, 2 failures
```

Focused check after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRecordEnvelopeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS markerpdf table geometry unwraps row column and cell record envelopes before supplied boundary formatting
PASS markerpdf supplied document converter replaces stale pdf text with record envelope table markdown

1 test files, 20 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-record-envelope-boundary-currentbase.php
scenario=wordpress-table-record-envelope-boundary-currentbase
coordinate_status=translated_to_table_crop
translated_counts={"rows":3,"cols":3,"cells":6}
active_cell_count=4
excluded_cell_count=2
assigned_table_texts=["Feature","Status","Images","Ready"]
record_envelope_geometry_unwrapped=true
stale_pdftext_and_offband_cells_filtered=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
supplied-boundary table-recognition, localization, and Markdown-formatting
pipeline.

## Next

Continue non-overlapping markerPDF table handoffs around supplied-boundary
spanning/header semantics, malformed table sidecars, or searchable-PDF native
parser gaps. GPU/OCR/model parity remains intentionally out of scope.
