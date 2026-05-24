# Independent Audit - 2026-05-24T16:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
`lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, and recent Git history through
`ce556025 Record integration hold status`. I did not edit lane implementation
files, launch agents or tmux sessions, push, read secrets, inspect process
environments, credential stores, provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 15:54:55, 15:55-15:57, 16:00:10
observed HEAD: 2b242ec4e117 -> ce556025cd05
recent history: ce556025 Record integration hold status; 2b242ec4 Record integration hold status; a3a7b62e Refresh independent audit status; 2849a3d1 Refresh independent audit status
tracked dirty files: 329 -> 330 -> 331
default status rows including untracked: 19423 -> 19429 -> 19434
git diff --shortstat: 329 files changed, 258880 insertions(+), 32484 deletions(-) -> 330 files changed, 259223 insertions(+), 32431 deletions(-) -> 331 files changed, 259445 insertions(+), 32514 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred), updated 2026-05-24 12:29:10 UTC
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required pre-root process gate:

```text
15:55Z pgrep -af '^php tools/run-tests\.php$': no rows
15:56Z pgrep -af '^php tools/run-tests\.php$': no rows
15:57Z pgrep -af '^php tools/run-tests\.php$': no rows
16:00Z pgrep -af '^php tools/run-tests\.php$': no rows
related integration-hold evidence: audits/integration-status.md records transient no-argument root PID 1037362 (`php tools/run-tests.php`) at 2026-05-24 15:56:26 UTC
```

I did not start `php tools/run-tests.php`. The exact gate was empty in my
samples, but the checkout changed in every stability sample and the latest
integration hold recorded root-harness churn. A root result from this source
would not be a frozen acceptance checkpoint.

Current manifest/status sample versus the published dashboard:

```text
lane          current manifest/status                 dashboard
difftastic    manifest 1010/1213, status 3494 pass    3245 pass, 851/1077
dolt          manifest 613/613, status 436 pass       425 pass, 613/613
esbuild       manifest 455/2567, status 455 pass      429 pass, 429/2567
gitoxide      manifest 2877/2877, status 7397 pass    7152 pass, 2877/2877
libsqlite     manifest 367/1589, status 367 pass      348 pass, 349/1589
LightningCSS  manifest 2872/3548, status 4189 pass    4065 pass, 2765/3548
markerPDF     manifest 368/417, status 505 pass       484 pass, 347/396
pandoc        manifest 2065/2276, status 2106 checks / 382 pass  362 pass, 1891/2276
quadrable     manifest 55/55, status 244 pass         232 pass, 55/55
rclone        manifest 954/1601, status 956 pass      906 pass, 906/1601
readability   manifest 1984/1984, status 3714 assertions / 277 behavior entries  3545 pass, 1984/1984
syncthing     manifest 658/658, status 8398 pass      7902 pass, 658/658
```

## Findings

1. **Critical - the repo is still a moving dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:50`, `audits/integration-status.md:5-8`,
     `audits/integration-status.md:17-23`,
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable slices, verified/committed
     handoffs, and a visible stable baseline for every lane.
   - Evidence: tracked dirty files moved `329 -> 331`, default status rows
     moved `19423 -> 19434`, `HEAD` moved from `2b242ec4e117` to
     `ce556025cd05`, and shortstat moved from
     `258880 insertions / 32484 deletions` to
     `259445 insertions / 32514 deletions` during this audit. The latest
     committed integration hold says no lane output, dashboard artifact, or
     support row was accepted, and every active lane still reports pending or
     uncommitted handoff ownership.

2. **Critical - `porting.html` and `porting-summary.json` are stale against every lane.**
   - Paths: `porting.html:34-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`, `porting-summary.json:10-35`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`
     require current denominator, mapped tests, PHP pass/fail, phase, audit,
     blocker, and commit in the dashboard.
   - Evidence: the dashboard still publishes source `89260857cc71` generated
     `2026-05-24 12:29:46 UTC`, while current `HEAD` is `ce556025cd05`.
     Every lane has newer counts. Examples: Difftastic is now `1010/1213`
     mapped with `3494` pass but dashboard says `851/1077` and `3245`;
     markerPDF is now `368/417` with `505` pass but dashboard says `347/396`
     and `484`; Syncthing is now `8398` pass but dashboard says `7902`.

3. **High - there is still no valid no-argument root acceptance run.**
   - Paths: `tools/run-tests.php`, `progress.md:50`,
     `audits/integration-status.md:27-31`,
     `lanes/*/lane-status.json:12-13`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; the user also forbids duplicate
     no-argument root harnesses.
   - Evidence: my exact `pgrep -af '^php tools/run-tests\.php$'` samples at
     15:55Z, 15:56Z, 15:57Z, and 16:00Z returned no rows, but the current
     integration hold records transient no-argument root PID `1037362` at
     15:56:26 UTC. I did not start a duplicate or a fresh root run because the
     worktree and `HEAD` were changing in the same window and no owner-free
     lane batch had been accepted.

4. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:3-4`,
     `dependency-backlog.json:7-22`, `dependency-backlog.json:81-95`,
     `dependency-backlog.json:129-176`,
     `dependency-backlog.json:179-230`,
     `dependency-backlog.json:233-267`,
     `dependency-backlog.json:272-337`,
     `dependency-backlog.json:340-426`,
     `dependency-backlog.json:629-646`,
     `porting.html:72-78`.
   - Goal requirement at risk: the latest support-library directives require a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, bounded install attempts where
     relevant, and as much upstream/spec-suite evidence as can honestly run.
   - Evidence: the tracker has 37 rows and 0 active support ports. Pandoc's
     DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument,
     templates, citations, math, tables, package containers, XML/HTML,
     Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression categories are visible as gated rows, but none has a
     support-library manifest, PHP ledger, malformed/corrupt evidence,
     accepted activation record, or bounded install-attempt/ruled-out note.

5. **High - Pandoc's ledger is internally inconsistent and still misses the original rich-kernel bar.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:342-346`,
     `lanes/pandoc/tests/MarkdownReaderTest.php:2157`.
   - Goal requirement at risk: Pandoc must be a document conversion kernel
     with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: lane status says `2,106` focused checks and `382` PHP behavior
     tests, while the manifest canonical `benchmarkDenominator.mapped` remains
     `2065` and its blocker text still says `381` behavior tests. Full
     Haskell runner parity remains unexecuted. `rg` finds WXR only in a
     Markdown-reader test string, not as a visible WXR reader/writer
     capability. PDF/package/citation/math support remains gated and inactive.

6. **High - markerPDF still mixes native PDF slices with plan-only external/runtime evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1160-1215`,
     `lanes/markerpdf/lane-status.json:10-14`,
     `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and the latest
     support-library directives say whole applications, converter wrappers,
     model/runtime stacks, and hidden shell-outs are non-progress unless they
     are explicit temporary oracle tooling.
   - Evidence: the manifest status/denominator folds many `*-plan`, server,
     app, upload, runtime, install, model, workflow, and benchmark-runner
     entries beside native PDF extraction behavior. The native xref-stream PDF
     slice may be useful, but richer PDF/OCR/table work should be credited only
     through bounded `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core` support rows after acceptance.

7. **Medium - Dolt metadata is still not normalized after the latest green focused run.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:28-30`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:68-70`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2577`,
     `lanes/dolt/lane-status.json:5-14`,
     `porting.html:57`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, `goal.md:44`, and
     `goal.md:45` require durable coordination by denominator, mapped tests,
     PHP pass/fail, current work, blocker, and commit.
   - Evidence: lane status reports the CHAR slice green at `436` pass / `0`
     fail, the manifest still exposes `nativeImplementation.phpBehaviorTests`
     as `430`, another manifest PHP evidence section still records `434`, and
     the dashboard still reports `425` pass. This is a coordination/data-
     normalization problem even if the lane-local focused tests are green.

8. **Medium - 98-99 percent dashboard progress overstates accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `lanes/*/lane-status.json:12-14`.
   - Goal requirement at risk: `goal.md:35-40` requires meaningful fixture
     parity, edge-case coverage, error behavior, upstream tests as source of
     truth, and explicit blockers for hard features.
   - Evidence: the dashboard reports `98.3%` average and most lanes at
     `98-99%`, but the active source is unaccepted, root aggregate verification
     is pending/non-attributable, many upstream runners are static-only or
     bounded, the public dashboard is stale, and no support-library row is
     active.

## Next Intervention

Freeze or wait out lane workers, focused/root runners, dashboard/status
publishers, support-library scouts, capacity rows, evaluator/auditor loops,
and integration-hold writers long enough to get two stable polls of `HEAD`,
tracked/default status counts, shortstat, exact root gate
`pgrep -af '^php tools/run-tests\.php$'`, dashboard/dependency counts, lane
status timestamps, and relevant log mtimes. Then accept or reject exactly one
owner-free lane batch at a time. First normalize manifest/status/dashboard
counts and stale next-task metadata, especially Pandoc `2065` versus `2106`
and Dolt `430`/`434` versus `436`. After focused verification plus
`git diff --check`, regenerate dashboard artifacts from the accepted commit
and run one serialized no-argument root result only on that frozen snapshot.
