# tabled Saved Result Envelope Boundary Current Base

Slice: `markerpdf-table-geometry-boundary-current-base-20260608T143709Z`

Base: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Source Truth

- Upstream tabled-pdf 0.1.4 `extract.py` writes `results.json` as `out_json[name].append(res)`, where `name` is the input filename without extension.
- Upstream tabled-pdf 0.1.4 README describes saved results as a dictionary keyed by input filenames without extensions; each value is a list of table dictionaries with `cells`, `rows`, `cols`, `bbox`, and `image_bbox`.
- This slice uses supplied native artifacts only. It does not run Python, OCR, Surya, tabled models, GPU code, raster rendering, or external PDF tools.

Local source files inspected:

- `/tmp/markerpdf-tabled-src/extract/tabled_pdf-0.1.4/extract.py`
- `/tmp/markerpdf-tabled-src/extract/tabled_pdf-0.1.4/README.md`
- `/tmp/markerpdf-tabled-src/extract/tabled_pdf-0.1.4/tabled/schema.py`

## Implementation

`SuppliedDocumentConverter` now accepts `recognized_tables` as either the existing flat list or an upstream saved-result envelope keyed by source basename/stem. When an envelope is supplied, the converter selects the current PDF's basename-without-extension entry before the existing page-result flattening, table recognition, and geometry-localization flow.

The converter records `metadata.table_result_envelope_review` with the selected key, available keys, source basename/stem, table count, and no-GPU/no-external-tool flags. `metadata.supplied_boundaries` now includes `table-result-envelope` before `table-recognition` for this boundary.

## Red/Green Evidence

Red-first command before source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySavedResultEnvelopeBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 0 assertions, 1 failures
markerPDF supplied document option recognized_tables must be a list.
```

Green focused command after source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometrySavedResultEnvelopeBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 24 assertions, 0 failures
```

Focused table geometry family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
```

Result:

```text
63 test files, 2461 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-table-saved-result-envelope-currentbase.php
```

Result: exits 0 with `saved_result_envelope_selected_by_basename=true`, `decoy_result_excluded=true`, `offcrop_saved_result_cells_filtered_from_assignment=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```bash
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
php -l lanes/markerpdf/tests/TableGeometrySavedResultEnvelopeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-table-saved-result-envelope-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all PHP files report no syntax errors; `git diff --check -- lanes/markerpdf` exits 0.

## Non-Overlap

This patch does not repeat accepted table geometry work for page-result `ExtractPageResult` flattening, `table_cells` aliasing, shared `image_bbox`, coordinate-order aliases, wrapped bbox values, precomputed table blocks, or saved flat table rows/cols. It covers only the upstream saved `results.json` envelope selection boundary before those existing table-localization paths.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP supplied-boundary conversion, table recognition formatting, and crop-local geometry review components.
