# markerPDF runtime task tuple-order preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T102257Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T102257Z`
Base accepted HEAD: `b97f6bf2feb7e372488837f87839f3624967856e`

## Source Truth

- Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `convert.py`.
- `convert.py::main` builds task tuples as `task_args = [(f, out_folder, metadata.get(os.path.basename(f)), args.min_length) for f in files_to_convert]`.
- `convert.py::process_single_pdf` unpacks worker arguments positionally as `filepath, out_folder, metadata, min_length = args`.
- This is a runtime admission/review boundary in the no-GPU markerPDF lane: it happens after listing, chunking, metadata loading, conversion summary, and model-handoff planning, but before native review would launch pool workers.

## Patch

- `BatchConverter::runtimeTaskArgIdentityReview()` now records the upstream task tuple source string, worker unpack string, tuple order, arity, tuple rows, metadata slot, min_length slot, and tuple-order preservation.
- The existing runtime preflight test adds a focused case proving filepath/out_folder/metadata/min_length positions for PDF, missing metadata, and non-PDF sidecar task candidates.
- The WordPress runtime preflight smoke now emits tuple-order evidence, including preserved metadata for `ready-for-marker.pdf` and `null` metadata for the selected sidecar.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` and `mappedMarkerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`.
- `lane-status.json` moves `phpPass` `2508 -> 2509` and `wordpressScenarios` `2132 -> 2133`.

## Red-First Evidence

After adding the focused test before the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1197 assertions, 1 failures`.

Failure: `Undefined array key "task_arg_tuple_source"` in the new tuple-order test.

After the source edit and smoke update:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1218 assertions, 0 failures`.

## WordPress Smoke

```bash
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

Result: emitted `runtime_task_tuple_source`, `runtime_task_tuple_unpack=filepath, out_folder, metadata, min_length = args`, `runtime_task_tuple_order=[filepath,out_folder,metadata,min_length]`, `runtime_task_tuple_arity=4`, `runtime_task_tuple_ready_metadata_title=Ready for Marker`, `runtime_task_tuple_sidecar_metadata=null`, `runtime_task_tuple_min_length_position=3`, `executes_python_or_models=false`, `runtime_executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted runtime numeric gate truthiness, metadata JSON loading/shape/scalar/duplicate-key handling, relative metadata-file path resolution, input/output `abspath`, symlink file filtering, duplicate symlink targets, sidecar filetype preflight, result-drain cleanup, worker-count failure, output-folder creation errors, spawn-start-method errors, text-length exceptions, PageLabels, PDF parser/xref/font/image/security/form/annotation/table slices, or any GPU/model execution. The bounded new behavior is only positional `task_args` tuple construction and worker-side unpacking review.

## Dependency Closure

No new support component is needed. This reuses the native PHP runtime preflight planner, existing filesystem/listing review, metadata basename lookup, task identity review, and WordPress smoke path. Live Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled models, model downloads, Streamlit/FastAPI workers, OCR/raster helpers, and external PDF tools remain intentionally outside this no-GPU markerPDF slice.
