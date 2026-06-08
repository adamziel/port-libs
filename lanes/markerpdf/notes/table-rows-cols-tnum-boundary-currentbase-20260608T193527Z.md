# Table rows_cols tnum boundary current-base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T193527Z`
Base accepted HEAD: `88bef356b21ec3553df0dd68cc49fd772a2059fd`

## Source Truth

- `tabled.extract.extract_tables` serializes per-table sidecars with `pnum`,
  `tnum`, `bbox`, `image_bbox`, `rows`, `cols`, and `cells`.
- `tabled.schema.ExtractPageResult` keeps recognizer `rows_cols` as a parallel
  list of `TableResult` entries. Direct native artifacts can therefore carry a
  saved table record with `tnum` and a parallel `rows_cols` list.
- This slice is no-GPU and supplied-boundary only. It does not run live OCR,
  Surya, tabled models, Python model workers, or external PDF tools.

## Behavior

`TableRecognizer` now selects `rows_cols[$tnum]` when a direct saved table
record has a multi-entry `rows_cols` list. Existing contracts remain unchanged:
direct inline `rows_cols` wrappers still win first, and one-entry
`rows_cols[0]` wrappers still canonicalize as before.

The selected container provides row/column geometry aliases and crop-bbox
candidates before table-crop localization, so WordPress supplied-boundary
conversion ignores stale decoy row/column bands from other table indexes.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsTnumBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL selects rows_cols table result by tnum before table crop assignment
FAIL surfaces tnum selected rows_cols through supplied WordPress conversion
1 test files, 12 assertions, 2 failures
```

Fixed focused:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryRowsColsTnumBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS selects rows_cols table result by tnum before table crop assignment
PASS surfaces tnum selected rows_cols through supplied WordPress conversion
1 test files, 31 assertions, 0 failures
```

Adjacent rows_cols/page-result family:

```text
php tools/run-tests.php \
  lanes/markerpdf/tests/TableGeometryRowsColsBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/TableGeometryRowsColsSavedResultOrderBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/TableGeometryRowsColsCropBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/TableGeometryPageResultBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/TableGeometryPageResultRowsColsAliasBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 146 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-rows-cols-tnum-boundary-currentbase.php
rows_cols_tnum_selected=true
decoy_rows_cols_ignored=true
stale_pdftext_table_line_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Final hygiene:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
php -l lanes/markerpdf/tests/TableGeometryRowsColsTnumBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-rows-cols-tnum-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
all passed
```

## Dependency Closure

No new support component is needed. This reuses the existing native
`TableRecognizer` supplied-boundary geometry path and existing
`SuppliedDocumentConverter` WordPress table insertion path.

## Next

Continue table geometry work on non-overlapping supplied-boundary gaps such as
explicit cell/row/column polygon variants, equation handoff boundaries, or
page-result metadata that is not already covered by rows_cols alias,
coordinate-space, `image_bbox`, and `tnum` selection tests.
