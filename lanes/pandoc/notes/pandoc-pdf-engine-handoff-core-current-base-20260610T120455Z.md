# Pandoc PDF/Typst Boundary Provenance Current-Base Slice

Slice: native Typst dependency sidecar provenance for PDF handoff fake runs.

## Behavior

`PdfEngineHandoff::plan()` now recognizes Typst dependency sidecars requested
with `--deps=...`, `--deps ...`, `--make-deps=...`, or `--make-deps ...` engine
options. The planned sidecar is retained as `engineDependencyFile`, added to the
expected engine artifacts, and surfaced through `pdf-engine-dependency-file:*`
diagnostics without invoking Typst.

`PdfEngineHandoff::fakeRun()` now treats `.d` and `.deps` sidecars as bounded
engine dependency artifacts. Make-style depfile content is parsed natively,
including line continuations and escaped spaces, so reviewer packets preserve:

- local source inputs that must be present in the fake run;
- external inputs such as absolute font paths as review metadata;
- declared output targets;
- dependency sidecar hashes and artifact provenance status.

Missing local Typst inputs fail the fake run with `missing-engine-input-file`,
matching existing TeX recorder dependency behavior.

## Evidence

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Focused result: 1 file, 1460 assertions, 0 failures.
Full Pandoc PHP gate after rebase: 44 files, 59946 assertions, 0 failures.

## Scope

This slice does not render Typst, run Pandoc, run Typst, run TeX/PDF engines,
invoke browser renderers, invoke external PDF validators, fetch resources, or
shell out to dependency scanners. It only preserves planned Typst depfile
provenance and validates fake-runner sidecar content in native PHP.
