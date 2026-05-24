# Independent Audit - 2026-05-24T13:16Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all 12
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history through `ac591e31 Refresh independent audit status`. I did not edit
lane implementation files, launch agents or tmux sessions, push, read secrets,
inspect process environments, credential stores, provider configs, or auth
files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 13:15-13:16
HEAD: ac591e312806
recent history: ac591e31 Refresh independent audit status; be0958c5 Record integration hold status; 861e4570 Record integration hold status; 48b8d1a9 Refresh independent audit status; 5adfaa97 Record integration hold status; fa6f1d51 Record integration hold status
default status rows including untracked: 18192 -> 18204
git diff --shortstat: 329 files changed, 239609 insertions(+), 30638 deletions(-) -> 330 files changed, 239696 insertions(+), 30638 deletions(-)
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-24 12:29:46 UTC from source 89260857cc71; sampled HEAD is ac591e312806
dependency backlog: dependency-backlog.json updated 2026-05-24 12:29:10 UTC with 37 rows (1 blocked, 25 candidate, 11 deferred, 0 active)
root run by this audit: not started
```

Required pre-root process gate:

```text
13:15Z pgrep -af '^php tools/run-tests\.php( |$)':
3203262 php tools/run-tests.php
3205874 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ...

13:16Z pgrep -af '^php tools/run-tests\.php( |$)':
3203262 php tools/run-tests.php

13:16Z owner evidence:
PID 3203262, USER claude, PPID 3203130, elapsed 01:45, state R, command php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. A no-argument root harness was
already active, and the checkout was still changing.

`jq empty` passed for all 12 lane manifests, all 12 lane-status files,
`porting-summary.json`, and `dependency-backlog.json`.

Current manifest/status drift sample:

```text
lane          current manifest/status             dashboard or summary
difftastic    manifest 1099/885, status 3315      1077/851, 3245 pass
dolt          current work REPEAT/REPLACE WHERE   dashboard still says HEX WHERE
esbuild       manifest/status 435                 429 pass / mapped
gitoxide      status 7237 pass                    7152 pass
libsqlite     manifest/status 353                 349 mapped, 348 pass
LightningCSS  manifest 2777, status 4089          2765 mapped, 4065 pass
markerPDF     manifest 401/352, status 489        396/347, 484 pass
pandoc        manifest 1908, status 366; note 1940 1891 mapped, 362 pass
quadrable     status 234 pass                     232 pass
rclone        manifest/status 917                 906 mapped/pass
readability   status 3601 pass                    3545 pass
syncthing     status 8028 pass                    7902 pass
```

## Findings

1. **Critical - the repository is still not an acceptance checkpoint.**
   - Paths: `progress.md:46` through `progress.md:48`,
     `lanes/*/lane-status.json:13`, `goal.md:29`, `goal.md:48`.
   - Goal requirement at risk: small reviewable slices must be committed with
     passing tests, and finished agent work must be verified, committed, cleaned
     up, and assigned onward.
   - Evidence: the dirty tree moved during this audit from `18192` to `18204`
     untracked-inclusive status rows and from `329 files changed, 239609
     insertions(+), 30638 deletions(-)` to `330 files changed, 239696
     insertions(+), 30638 deletions(-)`. Every sampled lane-status
     `latestCommit` is still pending, uncommitted, not committed, or explicitly
     owned by the future supervisor/integrator rather than an accepted commit.

2. **Critical - a no-argument root PHP harness is already active, so this audit
   cannot produce a new root result.**
   - Paths: `tools/run-tests.php`, `progress.md:48`, `goal.md:49`.
   - Goal requirement at risk: repo-wide tests and static checks must run
     periodically and failures must be recorded honestly, without duplicate
     root harnesses on a moving tree.
   - Evidence: the required exact process gate matched active PID `3203262`
     owned by `claude` running `php tools/run-tests.php`; an earlier sample also
     matched focused Syncthing PID `3205874`. I did not start a duplicate. The
     active root process is not yet an audit-acceptable result because the tree
     is not frozen and lane handoffs are still uncommitted.

3. **High - `porting.html` and `porting-summary.json` are stale enough to
   mislead coordination.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `goal.md:3`, `goal.md:45`.
   - Goal requirement at risk: the dashboard must show current denominator,
     mapped tests, PHP pass/fail, phase, audit, current work, blocker, and
     commit.
   - Evidence: the dashboard still publishes source snapshot `89260857cc71`,
     while sampled `HEAD` is `ac591e312806`. Current manifests/statuses now
     exceed the dashboard for Difftastic, esbuild, Gitoxide, libsqlite,
     LightningCSS, markerPDF, Pandoc, Quadrable, rclone, Readability, and
     Syncthing; Dolt current work also moved beyond the dashboard text.

4. **High - manifest/status counts remain non-normalized and internally
   contradictory.**
   - Paths: `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/esbuild/lane-status.json:5` through
     `lanes/esbuild/lane-status.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:288` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:291`,
     `lanes/pandoc/lane-status.json:5` through
     `lanes/pandoc/lane-status.json:10`,
     `goal.md:24`, `goal.md:25`, `goal.md:35`.
   - Goal requirement at risk: each lane needs a defensible upstream
     denominator, mapped upstream tests, PHP passing/failing counts, and honest
     blocker recording.
   - Evidence: esbuild says `mapped: 435` in both manifest and status, while
     the dashboard still says `429`. markerPDF reports `401` total and `352`
     mapped in the manifest while lane status reports `489` PHP behavior tests.
     Pandoc reports `mapped: 1908`, while its warning/latest note says `1,940`
     focused checks and lane status says `366` behavior tests. These may be
     different units, but the schema does not label them clearly enough for
     acceptance or dashboard regeneration.

5. **High - support-library coverage remains planning-only under the
   2026-05-24 11:59 UTC rich-function directive.**
   - Paths: `dependency-backlog.json:3` through `dependency-backlog.json:4`,
     `dependency-backlog.json:7` through `dependency-backlog.json:22`,
     `dependency-backlog.json:81` through `dependency-backlog.json:95`,
     `porting.html:72` through `porting.html:78`,
     `progress.md:17` through `progress.md:33`.
   - Goal requirement at risk: support libraries require the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can honestly run.
   - Evidence: the backlog has 37 rows and `0` active bounded support ports.
     Pandoc's DOC, DOCX/OpenXML, PDF handoff/text extraction, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML, and archive/compression needs are
     routed as candidate/deferred rows, but there are no dependency-specific
     support manifests or pass/fail evidence in the tree. Routing is not
     accepted support-port progress.

6. **High - markerPDF still over-credits external runtime, shell-boundary, and
   package-planning work beside real native PDF progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:548` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:549`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:784` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:815`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:792` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:805`,
     `lanes/markerpdf/lane-status.json:12`,
     `goal.md:1`, `goal.md:30`.
   - Goal requirement at risk: wrappers around JS/Rust/Go/C binaries,
     shell-outs, bridge calls, whole applications, and external converter
     wrappers must not count as the main native deliverable.
   - Evidence: markerPDF has useful native PDF text/resource/filter/outline
     progress. The same manifest also counts Pandoc/XeLaTeX helper plans,
     chunk-convert shell lifecycle, Streamlit/FastAPI runtime plans, Poetry
     package and lock metadata, Tesseract/OCRMyPDF/Ghostscript readiness,
     Texify/Torch/Nougat handoffs, and subprocess command metadata in the
     mapped behavior surface. Those should remain preflight/blocker metadata
     unless split into bounded native support ports with their own denominator
     and PHP evidence.

7. **Medium - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json:4`,
     `goal.md:35` through `goal.md:40`.
   - Goal requirement at risk: passing tests are not enough; upstream tests are
     the source of truth where possible, and hard gaps must be recorded as
     blockers or future slices.
   - Evidence: the dashboard reports `98.3%` average progress and most lanes at
     `98%` or `99%`, while aggregate root verification is only in-flight on a
     moving tree, all current lane batches are uncommitted, support-library work
     has no active bounded ports, and full upstream runners remain static,
     bounded, unexecuted, or intentionally excluded for multiple lanes.

8. **Medium - recent history is dominated by audit/status commits rather than
   accepted implementation integration.**
   - Paths: `progress.md:48`, `audits/latest.md`, `lanes/*/lane-status.json:13`,
     `goal.md:20`, `goal.md:48`.
   - Goal requirement at risk: the supervisor must integrate useful work,
     enforce standards, keep the roadmap honest, and assign the next
     highest-value slice after verification.
   - Evidence: the last sampled commits are `ac591e31 Refresh independent audit
     status`, `be0958c5 Record integration hold status`, `861e4570 Record
     integration hold status`, `48b8d1a9 Refresh independent audit status`,
     `5adfaa97 Record integration hold status`, and `fa6f1d51 Record
     integration hold status`. That history preserves the hold, but it does not
     convert lane-local evidence into accepted, reviewable implementation
     checkpoints.

## Next Intervention

Freeze lane writers, dashboard/status publishers, support-library scouts,
focused runners, root runners, capacity executors, Dolt, and the Dolt runner.
Let the active root PID `3203262` finish only if it can be tied to an unchanged
snapshot; otherwise treat it as non-acceptance evidence. Require two stable
polls of `HEAD`, tracked rows, untracked-inclusive rows, shortstat, exact
process gates, dependency/dashboard counts, status timestamps, and relevant log
mtimes. Then accept exactly one coherent lane batch with manifest/status schema
and count normalization, run focused lane verification plus `git diff --check`,
activate only support-library rows whose base-lane gate is accepted or truly
blocked, add dependency-specific support manifests before counting support
progress, regenerate `porting.html` and `porting-summary.json` from the
accepted commit, and run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate is empty on that frozen snapshot.
