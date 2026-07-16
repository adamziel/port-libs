# Pandoc PDF Typst Timings Source Provenance

Date: 2026-06-30

Slice: `plib-66c86`

## Boundary

Typst `--timings` sidecars were already declared as bounded expected engine artifacts. This slice adds runtime provenance for source paths found inside the safe timing JSON artifact without executing Typst or any external engine.

The handoff now reads the planned safe timings artifact from fake-run files, extracts likely source paths from trace events, and classifies each path against the Typst `--root` boundary:

- `inside-root`
- `outside-root`
- `unbounded`
- `external-source`
- `unknown-source`

External URLs and Typst package references reuse the existing dependency path normalization so provenance matches the rest of the PDF/Typst boundary model.

## Review Surface

The timing source policy is carried through:

- fake-run result `typstTimingSourcePolicy`
- artifact provenance review `typstTimingSourcePolicy`
- fake-run sequence `finalTypstTimingSourcePolicy`
- runtime Typst boundary matrix case `timing-provenance`
- diagnostics for review status, source count, outside-root count, and external count

Timing source findings are review provenance only. They do not fail a fake run that otherwise produced the requested PDF artifact.

## Verification

Focused coverage added in `PdfEngineHandoffTest.php` maps timing trace source paths for inside-root files, outside-root files, remote URLs, and Typst packages into the artifact review and Typst boundary matrix.
