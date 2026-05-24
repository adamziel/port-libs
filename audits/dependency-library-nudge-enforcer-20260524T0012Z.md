# Dependency Library Nudge Enforcer Audit 2026-05-24T00:12Z

## Inputs Read

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `porting-summary.json`
- `tools/generate-dashboard.php`
- all 12 `lanes/*/lane-status.json` files
- all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files
- `git status --short --branch`

The status sample was `main...origin/main [ahead 600, behind 68]` at `HEAD`
`f3108e404b1a`, with a broad moving dirty tree. A later count showed 8912 short
status rows. I did not inspect or edit lane source, tests, fixtures, examples,
provider credentials, secrets, live-service remotes, dashboard publication
artifacts, or root test harness state beyond the required metadata reads.

## Backlog Counts

Initial `dependency-backlog.json` count at read time: 22 items.

- Priority: 4 critical, 8 high, 10 medium.
- Status: 12 candidate, 10 deferred, 0 active.
- `porting-summary.json` also reported 22 dependency items because it was
  generated before this audit.

Post-audit `dependency-backlog.json` count: 23 items.

- Priority: 4 critical, 8 high, 11 medium.
- Status: 13 candidate, 10 deferred, 0 active.
- Activation gates now include `pandoc-template-writer-next` with 1 candidate.
- All dependency rows still have the required fields: `id`, `name`, `source`,
  `neededBy` or `lanes`, `essentialCapability`, `scopeBoundary`, `priority`,
  `status`, `activationGate`, `testExpectation`, `reuseNotes`, and `blocker`.

## Missing-Item Decision

Added one bounded support-library row:

- `pandoc-doctemplates-core`: current Pandoc status and manifest now explicitly
  map `jgm/doctemplates` writer-template behavior, including the supplemental
  doctemplates `.test` corpus, partial resolution, recursion guards, pipes,
  alignment, chomp/newline behavior, and diagnostics. The prior 22-item backlog
  covered Pandoc rich formats, ZIP/XML/HTML, Unicode/charset, tables,
  citation/CSL, math/TeX, and PDF handoffs, but did not have a denominator row
  for this upstream writer-template library.

The new row is intentionally bounded to Pandoc writer rendering. It excludes a
general web templating framework, arbitrary plugin execution, filesystem
includes outside supplied template roots, Haskell runtime embedding, and
shelling out to Pandoc or doctemplates binaries. Status is `candidate`, not
`active`, behind `pandoc-template-writer-next`.

## Non-Additions

- No broad JSON/YAML/TOML parser dependency was added. Current YAML/TOML/SQL
  evidence is Difftastic base-lane syntax/diff behavior, not a shared support
  blocker exposed by the lane statuses.
- No separate filetype/MIME row was added. markerPDF currently has native
  filetype preflight evidence, but the lane status presents it as a lane-local
  adapter slice, not as a cross-lane dependency blocker.
- No wrappers or full application ports were added for LibreOffice/OpenOffice,
  Pandoc, Tesseract/OCRMyPDF/Ghostscript, PDFium/PIL/Poppler, model stacks,
  live cloud providers, Node/Rust/Go binaries, or service applications.

## Changes

- Updated `dependency-backlog.json` timestamp and added
  `pandoc-doctemplates-core`.
- Added a concise Auxiliary Dependency Backlog note to `progress.md`.
- Created this audit artifact.
- Did not edit `porting-summary.json`, `porting.html`, GitHub Pages files, or
  lane source/test/fixture/example files. `porting-summary.json` remains a
  stale 22-item publication snapshot until a dashboard owner regenerates it.

## Validation

- Integrator rerun for acceptance:
  `jq empty dependency-backlog.json porting-summary.json`: exit 0, no output.
- Integrator rerun for acceptance:
  `php -l tools/generate-dashboard.php`: exit 0,
  `No syntax errors detected in tools/generate-dashboard.php`.
- Integrator rerun for acceptance:
  `git diff --check`: exit 0, no output.
- Integrator staged-owned-files check:
  `git diff --cached --check`: exit 0, no output, with only owned tracking
  artifacts staged.
- Did not run root `php tools/run-tests.php`.

## Next Dependency Work

- When `pandoc-template-writer-next` opens, create a dependency-specific
  doctemplates denominator from `jgm/doctemplates`, map Pandoc writer fixtures,
  record PHP pass/fail evidence, include malformed/corrupt template cases such
  as unclosed delimiters and recursive/deep partials, and keep shell-outs out of
  progress credit.
- Keep the first activation gates focused on base-lane progress:
  `shared-zip-package-core`, `xml-html5-dom-core`, `pdf-text-dictionary-core`,
  and `layout-ocr-result-core`.
- Regenerate dashboard publication artifacts only from an accepted snapshot by
  the dashboard/integration owner.
