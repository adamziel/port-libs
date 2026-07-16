# PDF/Typst Open Output Viewer Policy

Slice: `plib-wh64a`
Date: 2026-07-01 UTC
Area: Pandoc PDF/Typst boundary provenance

## Behavior

`PdfEngineHandoff` now adds a bounded `openOutputViewerPolicy` only when Typst
`--open` viewer values cross the safe review boundary. The policy classifies
non-empty viewer values as bare commands, relative viewer paths, absolute
viewer paths, URI viewers, or invalid values, then records:

- safe and unsafe viewer counts;
- deterministic viewer-kind counters;
- selected viewer and selected viewer kind;
- unsafe viewer values;
- review issues such as `open-output-viewer-external-boundary`.

The policy is carried through plan provenance, fake-run artifact provenance,
fake-run sequence summaries, diagnostics, and the existing `open-output`
boundary matrix case. Safe command and relative viewer cases remain unchanged.

No Pandoc, Typst, TeX/PDF engine, browser renderer, office suite, zip/unzip,
Node tooling, external validator, online service, live provider test,
citeproc, BibTeX, or Biber process was invoked.

## Accounting

- focused test file: `PdfEngineHandoffTypstOpenOutputViewerPolicyTest.php`
- focused assertions: `+27`
- mapped PDF/Typst open-output viewer policy case: `+1`

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstOpenOutputViewerPolicyTest.php`
  failed on missing `openOutputViewerPolicy` with `1 test files, 1 assertions,
  1 failures`.
- Focused `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstOpenOutputViewerPolicyTest.php`
  passed with `1 test files, 27 assertions, 0 failures`.
- Related `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 3768 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted Typst `--open` side-effect counting, basic viewer
metadata, matrix viewer details, PDF export controls, warning source policy,
timing source policy, dependency sidecar policy, package dependency policy, or
PDF byte provenance work. It owns only the additional boundary policy for
absolute/URI/invalid Typst open-output viewer values.
