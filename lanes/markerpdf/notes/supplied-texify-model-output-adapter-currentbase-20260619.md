# markerPDF supplied Texify model-output adapter current-base

Micro-slice: `plib-tuzwg.16`
Session: `port_libs Polecat obsidian`
Base accepted HEAD: `d729d9dc5055e878c656009b72fe76e4ac786c70`

## Source Truth

Upstream markerPDF can populate equation regions from Texify model output, but the no-GPU PHP lane must not execute Python, Torch, Texify, PDFium, OCR, or external rendering helpers. The PHP boundary therefore accepts supplied model results and must preserve enough provenance for review while producing the same downstream AST/WordPress shape that a recognized equation would use.

## Behavior

`SuppliedDocumentConverter` now recognizes `equation_results` and `equation_predictions` rows that carry supplied Texify-style output envelopes:

- direct values remain supported through `latex`, `prediction`, or `text`;
- model-output rows may use `model_output`, `output`, `generated_text`, or `decoded_text`;
- token counts may use `token_count`, `tokens`, `input_token_count`, or `source_token_count`;
- optional supplied image handles may use `image`, `equation_image`, `rendered_image`, or `crop_image`.

Supplied model-output rows are routed through `EquationReplacer::getLatexBatchedFromSuppliedOutputs()`, so batch sizing and the upstream max-token sentinel remain represented without live model execution. The converter records `equation_result_boundary_review` metadata with source fields, batch plan, dropped output indexes, per-result page/bbox/score provenance, and explicit `executes_python_or_models=false` / `executes_external_pdf_tools=false` flags.

The focused fixture verifies that one supplied Texify output replaces the source formula text while an over-budget supplied output is dropped and leaves its original searchable-PDF text reviewable.

## Verification

```text
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
php -l lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
```

Both report no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php lanes/markerpdf/tests/EquationReplacerTest.php lanes/markerpdf/tests/ImageExtractorTest.php lanes/markerpdf/tests/ModelPipelinePlannerTest.php
```

Result:

```text
4 test files, 887 assertions, 0 failures
```

Full markerPDF directory gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
php tools/run-tests.php lanes/markerpdf/tests > /tmp/markerpdf-full-20260619T230943Z.log 2>&1
```

The streamed run produced PASS output into late xref Prev-chain cases before the execution harness terminated with code `-1` and no final summary. The quiet rerun logged 977 PASS lines, no FAIL/Fatal output, and stopped during encrypted-permission review with the same harness code `-1`. This full gate is not counted as passed.

## Non-Overlap

This does not reroute live OCR/model work, table structure prediction, image raster extraction, or upstream benchmark execution. Existing supplied table and image boundaries remain represented by the supplied document converter and finalizer. This slice only closes the supplied equation model-result adapter boundary for precomputed Texify-style output envelopes and their review metadata.

## Dependency Closure

No runtime model dependency was introduced. The slice reuses `SuppliedDocumentConverter`, `EquationReplacer`, `ImageExtractor`, and `ModelPipelinePlanner` tests to keep the supplied table/image/equation adapter surface aligned. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, PDFium/PIL rendering, and exact upstream model benchmark parity remain intentionally out of scope.
