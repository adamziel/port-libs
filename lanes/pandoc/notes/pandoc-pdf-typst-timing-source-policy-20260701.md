# Pandoc PDF Typst timing source policy slice

## Scope

`plib-wuznt` extends fake-run Typst timing sidecar provenance so source entries are summarized with the same package-aware buckets used by warning/error source policies. Timing source policy output now keeps legacy entry counts and adds located/distinct source counts, source kind/class counts, boundary status counts, package reference counts, package references, and total/distinct source issue counts.

## Review surfaces

- `typstTimingSourcePolicy`
- `artifactProvenanceReview.typstTimingSourcePolicy`
- `fakeRunSequence.finalTypstTimingSourcePolicy`
- `typstBoundaryMatrix` `timing-provenance` details
- diagnostics such as `typst-timing-source-kind:*`, `typst-timing-source-class:*`, `typst-timing-source-packages:*`, and total `typst-timing-source-issues:*`

## Verification boundary

Coverage uses native fake-run tests with synthetic timing sidecar JSON. It does not execute Pandoc, Typst, PDF engines, office suites, browser engines, unzip/zip, Node, Jupyter, or external validators.
