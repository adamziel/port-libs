# Support Library Direction Nudge - 2026-05-24T12:24:10Z

Scope: tracking-only update for the latest support-library nudge. I did not
edit lane source, lane tests, implementation examples, provider configs, or
secret-bearing files. I did not inspect process environments, credential
stores, live service configs, or secret values.

## Inputs Read

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `lanes/*/lane-status.json`
- `lanes/*/UPSTREAM_TEST_MANIFEST.json`
- `audits/essential-rich-dependency-scout-20260524T115940Z.md`
- `audits/essential-rich-dependency-scout-review-20260524T120640Z.md`
- `audits/essential-rich-dependency-correction-review-20260524T121620Z.md`
- `audits/pandoc-rich-format-dependency-scout-20260524T120230Z.md`
- `audits/shared-runtime-dependency-scout-20260524T120230Z.md`
- `audits/pdf-marker-support-dependency-scout-20260524T120230Z.md`
- `.tmux-team/prompts/support-library-direction-nudge-20260524T122410Z.md`

## Decision

Tracker edit is safe and useful, but only as a refinement to an existing row.
No new dependency row is justified and no row should be activated from the
current evidence.

The backlog remains 37 gated rows: 25 `candidate`, 11 `deferred`, 1 `blocked`,
and 0 `active`. The one tracker change is to `layout-ocr-result-core`, keeping
its status and activation gate unchanged while making the markerPDF supplied
reading-order contract explicit.

## Tracker Update

Updated row: `layout-ocr-result-core`.

- Status remains `candidate`.
- Activation gate remains
  `markerpdf-ocr-layout-result-next-or-markerpdf-scanned-pdf-handoff-next`.
- `source` now names `marker/layout/order.py` supplied reading-order evidence.
- `essentialCapability` and `scopeBoundary` now include supplied
  reading-order result handoff.
- `testExpectation` now requires mapped supplied `order.py`/reading-order
  fixtures and malformed reading-order position cases.
- `blocker` now says activation requires OCR/layout/reading-order result
  ingestion, not just generic layout-result ingestion.

This is the smallest useful native PHP boundary for the PDF/markerPDF nudge:
supplied result ingestion and deterministic ordering diagnostics. It does not
track OCR engines, model stacks, raster renderers, service wrappers, or
converter shell-outs as dependency ports.

## Rows Not Added

Pandoc rich-format coverage is already represented by existing rows:
`legacy-doc-cfb-core`, `docx-openxml-core`, `pdf-text-dictionary-core`,
`pandoc-pdf-engine-handoff-core`, `epub3-package-core`,
`odf-open-document-core`, `pandoc-doctemplates-core`,
`citation-bibliography-csl-core`, `math-tex-conversion-core`,
`table-geometry-core`, `shared-zip-package-core`, `xml-html5-dom-core`,
`unicode-text-repair-width`, `charset-encoding-core`, and
`archive-compression-streams`.

markerPDF/PDF coverage is already represented by existing rows:
`pdf-text-dictionary-core`, `pdf-page-render-plan-core`,
`layout-ocr-result-core`, `table-geometry-core`, `charset-encoding-core`,
`unicode-text-repair-width`, `shared-zip-package-core`,
`checksum-hash-suite`, and `pandoc-pdf-engine-handoff-core`.

Shared runtime coverage is already represented by existing rows for Git wire,
WebDAV/XML, URL encoding, source maps, browser target data, JS package
resolution, tree-sitter grammar subsets, sequence diff/merge, protobuf, QR,
checksums, JSON/JSON5, SQL expression/storage, MySQL wire, glob/pathspec, and
provider metadata normalization.

## Activation Notes

No support row is active because the current lane evidence is still
lane-local/pending rather than accepted from a frozen base-lane slice or
accepted-blocked on the support component. Missing tooling is not recorded as a
final blocker here because no dependency runner was selected for activation.
A bounded `sudo -n` install attempt would be premature and outside this
tracking-only slice.

Dashboard artifacts were regenerated because the tracker/status artifacts were
updated.

## Validation

- `jq empty dependency-backlog.json`: passed.
- Duplicate ID check
  `jq -e '([.items[].id] | length) == ([.items[].id] | unique | length)' dependency-backlog.json`:
  passed and printed `true`.
- `php tools/generate-dashboard.php`: passed and regenerated `porting.html`
  and `porting-summary.json` with 12 lanes.
- `git diff --check -- dependency-backlog.json progress.md porting-summary.json porting.html audits/support-library-direction-nudge-20260524T122410Z.md .tmux-team/prompts/support-library-direction-nudge-20260524T122410Z.md`:
  passed.
- `git diff --no-index --check -- /dev/null audits/support-library-direction-nudge-20260524T122410Z.md`:
  reported no whitespace warnings; exit 1 is expected because the file differs
  from `/dev/null`.
- `git diff --no-index --check -- /dev/null .tmux-team/prompts/support-library-direction-nudge-20260524T122410Z.md`:
  reported no whitespace warnings; exit 1 is expected because the prompt file
  is untracked.

## Unresolved Blockers

- Root aggregate verification and integrator acceptance remain outside this
  worker's scope.
- All dependency rows remain gated; activation requires a concrete accepted or
  accepted-blocked base-lane slice.
