# Dependency Rich-Format Tracker Audit - 2026-05-23 23:02 UTC

## Inputs Read

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `tools/generate-dashboard.php`
- `porting-summary.json`
- all 24 lane status/manifest JSON files under `lanes/*/lane-status.json` and `lanes/*/UPSTREAM_TEST_MANIFEST.json`
- current `git status --short --branch`

## Exact Changes

- Reconciled `dependency-backlog.json` to 18 gated dependency candidates and updated its timestamp to `2026-05-23 23:03:28 UTC`.
- Added `legacy-doc-cfb-core` as a bounded Word 97-2003 `.doc` / Compound File Binary extraction candidate for the Pandoc lane, explicitly separate from `docx-openxml-core`.
- Strengthened every dependency `testExpectation` to require a dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and no progress credit for shelling out to external converters, renderers, OCR/model engines, parser generators, archive tools, or hash tools.
- Updated only the `Auxiliary Dependency Backlog` section of `progress.md` with the reconciliation note and full-suite/spec evidence policy.
- Extended `tools/generate-dashboard.php` to read `dependency-backlog.json`, add a compact `Auxiliary Dependency Backlog` section to `porting.html`, and add a compact `dependencyBacklog` object to `porting-summary.json`.
- Regenerated `porting.html` and `porting-summary.json` with the new dependency backlog section/object. No dependency row was marked active; the generated counts show 12 `candidate` and 6 `deferred` items.

## Candidates Added

- `legacy-doc-cfb-core`: added because rich conversion support named legacy DOC as a bounded native format need, and `.doc`/CFB/MS-DOC extraction is not covered by DOCX/OpenXML/ZIP support.

## Candidates Rejected Or Not Added

- OpenOffice/LibreOffice/Word applications: rejected as whole applications, not bounded native dependency components.
- `antiword`, `wv`, Pandoc, or external binary document converters: rejected as implementation dependencies; they may only be used as fixture/oracle references if a future lane explicitly records that.
- Tesseract, OCRMyPDF, Ghostscript, PDFium, PIL/Poppler, Torch, Surya, Texify, Nougat, Streamlit, FastAPI/Uvicorn, Poetry/publish tooling, and live cloud-provider applications: kept out of the backlog as direct ports because existing entries track supplied-result contracts or planning boundaries instead of whole engines/apps.
- Separate LZ4 compression candidate: not added in this reconciliation. Syncthing manifests show BEP LZ4 compression as lane runtime behavior, but it is not yet a cross-lane optional dependency blocker distinct from existing protocol/compression tracking.

## Dashboard Impact

- `porting.html` now shows backlog counts by priority/status, top candidate/active activation gates, and item rows with id/name/needed-by/priority/gate/status/test expectation summary.
- `porting-summary.json` now includes `dependencyBacklog.updated`, `policy`, `count`, `countsByPriority`, `countsByStatus`, `topGates`, and compact item rows.
- Top generated gates: `pandoc-rich-format-next` (4), `markerpdf-ocr-next` (3), `shared-infra-after-base-green` (3), `esbuild-rich-output-next` (1), and `markerpdf-table-next` (1).

## Verification

- `php -l tools/generate-dashboard.php`: passed.
- `php tools/generate-dashboard.php`: passed and generated `porting.html` / `porting-summary.json` with 12 lanes.
- `jq empty dependency-backlog.json porting-summary.json`: passed.
- `git diff --check`: passed.

## Remaining Blockers

- `dependency-backlog.json` is still untracked in this shared worktree; commit/staging is intentionally not attempted by this worker.
- Dependency candidates are planning gates only. No dependency implementation progress should be credited until a lane creates a denominator manifest, maps fixtures, records PHP pass/fail evidence, and labels any fixture-only or static-only evidence honestly.
- The broader worktree remains dirty and moving across lane files owned by other workers; this audit intentionally did not edit lane source, lane tests, lane fixtures, or lane examples.
