# Independent Audit - 2026-05-24T14:03Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, `audits/latest.md`, and recent Git history
through `5ad792ff Record integration hold status`. I did not edit lane
implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:58-14:03
HEAD moved during audit: a5847d4a -> eb893809033b -> 5ad792ff672a
recent history: 5ad792ff Record integration hold status; eb893809 Record integration hold status; a5847d4a Refresh independent audit status; b7a82c45 Record integration hold status; 7dafaf8f Record integration hold status
branch sample: main...origin/main [ahead 901, behind 68]
tracked dirty rows: 329 -> 330 -> 329
default status rows including untracked: 18500 -> 18504
git diff --shortstat: 329 files changed, 245715 insertions(+), 31376 deletions(-) -> 329 files changed, 245342 insertions(+), 30809 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71; sampled HEAD is 5ad792ff672a
dependency backlog: dependency-backlog.json has 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
json validation: jq empty passed for all 12 lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
```

Required pre-root process gate:

```text
13:58Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
14:01Z pgrep -af '^php tools/run-tests\.php( |$)': 3763428 php tools/run-tests.php lanes/quadrable/tests
14:01Z owner evidence: PID 3763428, user claude, elapsed 00:07, cmd php tools/run-tests.php lanes/quadrable/tests
14:02Z pgrep -af '^php tools/run-tests\.php( |$)': 3790399 php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php
14:02Z owner sample: PID 3790399 exited before ps owner capture
14:03Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The process gate was occupied during
the audit window, then cleared only after `HEAD` and dirty counts had already
moved. A no-argument root result from this shared dirty tree would not be
attributable to one reviewed acceptance snapshot.

Current dashboard drift sample:

```text
lane          current status/manifest              dashboard
difftastic    status 3336 pass, manifest 909/1123  3245 pass, 851/1077
dolt          status 427 pass/0 fail               425 pass/0 fail
esbuild       status/manifest 443 pass/mapped      429 pass/mapped
gitoxide      status 7296 pass                     7152 pass
libsqlite     status/manifest 357 mapped/pass      348 pass, 349 mapped
LightningCSS  status 4129 pass, manifest 2815      4065 pass, 2765 mapped
markerPDF     status 493 pass, manifest 356/405    484 pass, 347/396
pandoc        status 371 pass, manifest 1987/2276  362 pass, 1891/2276
quadrable     status 237 pass                      232 pass
rclone        status/manifest 931 pass/mapped      906 pass/mapped
readability   status 3630 pass                     3545 pass
syncthing     status 8143 pass                     7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:48`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and completed agent work must be verified, committed,
     cleaned up, and assigned onward.
   - Evidence: `HEAD` moved twice during this audit while dirty counts and
     shortstat changed. Every sampled lane still reports `pending`,
     `uncommitted`, `not committed`, or stale shared-worktree commit ownership
     instead of an accepted implementation commit. Dolt's previous focused
     failure is now recorded as fixed (`427` pass, `0` fail), but the fix is
     still uncommitted and mixed into the broad dirty lane aggregate.

2. **Critical - a no-argument root run remains invalid from this audit.**
   - Paths: `tools/run-tests.php`, `goal.md:49`, `progress.md:48`.
   - Goal requirement at risk: repo-wide tests and static checks must be run
     and recorded honestly from a stable snapshot, without duplicating an
     active harness.
   - Evidence: the exact pre-root gate matched focused Quadrable PID `3763428`
     owned by `claude` and then a focused Readability PID before clearing. By
     the time it cleared, `HEAD` had advanced to `5ad792ff672a` and dirty counts
     had changed again. Starting root here would blend unaccepted lane work and
     moving status updates into one ambiguous result.

3. **High - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:56`, `porting.html:67`, `porting-summary.json:1`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: the dashboard still claims snapshot `89260857cc71` generated at
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `5ad792ff672a`. The
     drift table above shows every lane has newer pass/mapped counts or status
     text than the published dashboard.

4. **High - support-library coverage is still backlog-only, not lane-grade evidence.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:4`,
     `dependency-backlog.json:7`, `dependency-backlog.json:81`,
     `dependency-backlog.json:129`, `dependency-backlog.json:145`,
     `dependency-backlog.json:163`, `dependency-backlog.json:179`,
     `dependency-backlog.json:214`, `dependency-backlog.json:233`,
     `dependency-backlog.json:256`, `dependency-backlog.json:272`,
     `dependency-backlog.json:322`, `dependency-backlog.json:340`,
     `dependency-backlog.json:365`, `dependency-backlog.json:629`,
     `progress.md:17`, `porting.html:72`.
   - Goal requirement at risk: support libraries require bounded native PHP
     components, activation gates, dependency-specific upstream/spec
     denominators, mapped fixtures, PHP pass/fail evidence, malformed/corrupt
     cases where relevant, and as much upstream/spec-suite evidence as can run.
   - Evidence: the backlog has 37 rows and `0` active rows. Excluding
     `.upstream-cache`, the only `UPSTREAM_TEST_MANIFEST.json` files in the
     repo are the 12 base-lane manifests. Pandoc's required DOC, DOCX/OpenXML,
     PDF handoff/text extraction, EPUB, ODT/OpenDocument, templates, citations,
     math, tables, package containers, XML/HTML, Unicode/charset, and
     archive/compression needs are represented as gated rows, but none has a
     dependency-specific manifest, PHP pass/fail ledger, malformed/corrupt-case
     evidence, or bounded `sudo -n` install attempt/ruled-out note.

5. **High - markerPDF still mixes native PDF progress with external runtime and shell-boundary plans.**
   - Paths: `goal.md:1`, `goal.md:30`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:858`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:860`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as native deliverables.
   - Evidence: markerPDF has real native PDF extraction slices, now `356/405`
     mapped with `493` local behavior tests. The same manifest/status surface
     still carries Streamlit/FastAPI/Uvicorn app/server plans, chunk-convert
     shell lifecycle plans, live PIL/pypdfium/PDFium, Poppler/Ghostscript,
     OCR/Tesseract/Texify/Torch/Nougat dependencies, Poetry packaging, and
     Pandoc/XeLaTeX helper execution as part of the lane evidence surface. Those
     are blockers, supplied-runner contracts, or explicit non-goals, not native
     progress.

6. **Medium - Pandoc's current manifest still has stale internal slice metadata.**
   - Paths: `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:313`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:317`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1247`, `goal.md:3`,
     `goal.md:35`.
   - Goal requirement at risk: lane status must precisely track current work,
     blockers, mapped upstream tests, and PHP pass/fail evidence.
   - Evidence: current Pandoc status and denominator note describe the
     `2026-05-24 14:02 UTC` HTML writer MathJax/KaTeX slice with `1,987`
     mapped checks and `371` PHP passes. The manifest's
     `nativeImplementation.latestSlice` field still describes the older HTML
     raw-block/div slice, leaving the machine-readable "latest" implementation
     field inconsistent with the lane status and denominator note.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56`, `porting.html:67`,
     `lanes/*/lane-status.json:4`, `goal.md:35`, `goal.md:37`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be blockers or
     future slices.
   - Evidence: the public dashboard still reports `98.3%` average progress, and
     most lane statuses report `98` or `99`, while all current lane handoffs are
     pending/uncommitted, no serialized root result exists for the current
     tree, full upstream runners remain static/bounded/unexecuted for several
     lanes, and support-library work has no active bounded port.

8. **Medium - recent history remains status/hold dominated rather than accepted integration.**
   - Paths: `audits/latest.md`, `audits/integration-status.md:1`,
     `progress.md:46`, `goal.md:20`, `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the latest sampled commits are integration-hold or audit/status
     commits, not accepted lane implementation commits: `5ad792ff`,
     `eb893809`, `a5847d4a`, `b7a82c45`, and `7dafaf8f`.

## Next Intervention

Freeze lane writers, root/focused runners, dashboard/status publishers,
support-library scouts, capacity rows, and integration-hold writers. Require two
stable polls of `HEAD`, tracked/default status counts, shortstat, exact
`pgrep -af '^php tools/run-tests\.php( |$)'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Accept exactly one owner-free lane
batch at a time, normalizing manifest/status schema and commit fields before
claiming progress. Add support-library manifests only behind accepted base-lane
gates or true component blockers. Regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the accepted commit. Run one serialized
no-argument `php tools/run-tests.php` only after the exact process gate remains
empty on that frozen snapshot.
