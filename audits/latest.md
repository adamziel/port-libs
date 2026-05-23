# Independent Audit - 2026-05-23T23:34Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
`dependency-backlog.json`, every `lanes/*/lane-status.json`, and recent Git
history.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, `porting-summary.json`, and
`dependency-backlog.json`.

## Current Snapshot

```text
pre-audit-edit HEAD: 0add1f30fdf2
final observed HEAD before commit: c05ad8c3c542
latest visible commits: c05ad8c3 Record integration hold status; 0add1f30 Refresh independent audit status; 23d21c6c Record integration hold status
commits since 2026-05-23 00:00 UTC: 756 total, 433 audit/status-like by subject sampling
git status tracked rows: 279
git diff HEAD --shortstat: 279 files changed, 120879 insertions(+), 12609 deletions(-)
tmux sessions: 139
```

The required exact pre-root gate found active harnesses, including a
no-argument root run, so this audit did not start `php tools/run-tests.php`:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
169251 php tools/run-tests.php
197903 php tools/run-tests.php lanes/syncthing/tests/PullScannerTest.php ... lanes/syncthing/tests/WordPressOptionFolderStatisticsStoreTest.php
198493 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
199632 php tools/run-tests.php lanes/readability/tests
```

Owner evidence captured immediately after the gate:

```text
169251 claude 169199 01:14 R+ php tools/run-tests.php
197903 claude 197446 00:18 R+ php tools/run-tests.php lanes/syncthing/tests/PullScannerTest.php ...
198493 claude 197705 00:17 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

A final handoff gate after the first audit-only commit attempt again found an
active no-argument root harness:

```text
249017 claude 248791 00:23 R+ php tools/run-tests.php
249807 claude 249405 00:22 R+ php tools/run-tests.php lanes/syncthing/tests/PullScannerTest.php ...
```

## Findings

1. **Critical - duplicate-root and stability gates are blocked by live test and
   worker processes.**
   - Paths: `progress.md:32`, `progress.md:38` through `progress.md:49`,
     `.tmux-team/`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-team-watchdog.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable commits with passing tests, cleanup before reassignment, and
     honest repo-wide verification.
   - Evidence: the documented launch target is still two implementation lanes
     plus one auditor and the Active Lanes table still says every lane is
     `stopped`, while this audit sampled 139 tmux sessions, active lane/
     dashboard/evaluator/watchdog/capacity/integrator/auditor loops, and an
     active no-argument root harness PID `169251` owned by `claude`; the final
     handoff gate later found root PID `249017` owned by `claude`. A new root
     run would be a duplicate moving-tree sample.

2. **Critical - `porting.html` and `porting-summary.json` are stale relative to
   the current manifests and `HEAD`.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`, and
     `goal.md:45` require current denominator, mapped-test, PHP pass/fail,
     WordPress scenario, audit, blocker, and commit fields.
   - Evidence: the dashboard publishes snapshot `main 1a112c6ebaef`, but the
     sampled `HEAD` is `0add1f30fdf2`. Current manifest/status counts have
     advanced past the dashboard: Difftastic `373` mapped vs dashboard `367`;
     esbuild `308` vs `306`; Gitoxide `2743/2877` vs `2720/2877`; libsqlite
     `285` vs `283`; LightningCSS `1731` vs `1728`; markerPDF `329/279` vs
     `328/278`; Pandoc `1055` vs `1039`; rclone `697` vs `686`; Readability
     PHP `203` vs `200`; Syncthing `657/658` vs `654/658`.

3. **High - every lane handoff is still pending, uncommitted, or dirty-batch
   prose rather than an accepted implementation commit.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:48` require small,
     reviewable slices with passing tests, then verified integration and
     reassignment.
   - Evidence: lane commit fields still say `pending`, `uncommitted`,
     `not committed`, or explanatory dirty-worktree text. Recent history is
     dominated by audit/status/integration-hold commits, while the tracked
     dirty diff is still 279 files.

4. **High - near-complete percentages overstate accepted native parity.**
   - Paths: `porting.html:32`, `porting.html:56` through
     `porting.html:67`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
     `goal.md:38`, and `goal.md:40` require meaningful fixture parity,
     upstream tests as source of truth, explicit slices, and blockers for hard
     features.
   - Evidence: the dashboard reports 97.4% average progress and 98-99% for
     most lanes, but root verification is blocked, many full upstream runners
     remain unexecuted or excluded, and current evidence is mostly focused
     lane slices. These are useful slices, not proof of accepted full-port
     parity.

5. **High - essential optional-library coverage is still only a gated backlog,
   not manifest-backed dependency-port work.**
   - Paths: `progress.md:17` through `progress.md:22`,
     `porting.html:71` through `porting.html:78`,
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:12`,
     `goal.md:15`, `goal.md:18`, `goal.md:25`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require rich conversion/runtime behavior,
     real denominators, no bridge/shell-out progress credit, and explicit hard
     blockers.
   - Evidence: all 18 optional dependency items remain `candidate` or
     `deferred` with `blocker: none`; none has its own manifest,
     upstream/spec denominator, PHP pass/fail evidence, accepted owner, commit,
     or dashboard row. The most important bounded gaps remain
     `shared-zip-package-core`, `xml-html5-dom-core`,
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, DOCX/OpenXML,
     legacy DOC/CFB, EPUB/ODF, table geometry, Unicode/encoding, source maps,
     tree-sitter subsets, protobuf wire format, checksums, archives, and
     glob/pathspec matching.

6. **Medium - markerPDF and Readability still overstate rich-content parity
   relative to dependency and fixture evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/readability/lane-status.json`, `porting.html:62`,
     `porting.html:66`.
   - Goal requirement at risk: `goal.md:9`, `goal.md:11`,
     `goal.md:25`, `goal.md:30`, `goal.md:35`, and `goal.md:40` require
     native implementation progress, meaningful fixture parity, rich document
     behavior, and explicit blockers for unported binary/model/runtime
     formats.
   - Evidence: markerPDF has a 329-unit static inventory with 279 mapped
     semantics, but full behavior depends on unported or external-heavy pieces
     such as pdftext, pypdfium2, Surya/Torch, Texify, OCRMyPDF/Tesseract/
     Ghostscript, Streamlit, FastAPI/Uvicorn, PIL, multiprocessing, benchmark
     archives, and model downloads. Readability reports `1984/1984` mapped
     upstream tests but still relies on copied fixtures and local oracle
     checks without a frozen accepted root baseline.

7. **Medium - manifest/status schemas remain non-normalized and hard to compare
   across lanes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real denominators, explicit slices, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` alternates between integers and
     long prose; Dolt keeps denominator detail as prose while `mapped` is
     numeric; Pandoc and Quadrable use prose totals; runner status alternates
     between strings and objects; PHP counts mix behavior tests, assertions,
     PASS cases, files, and lane-local checks.

8. **Medium - blocker fields still mix slice-local green status with full-port
   blockers.**
   - Paths: `lanes/dolt/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/gitoxide/lane-status.json`,
     `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:31` and `goal.md:40` require precise
     blockers and no silent skipping of hard features.
   - Evidence: multiple lanes say "No current implementation blocker" or
     "No focused blocker" while the same status text lists unexecuted full
     runners, live/provider suites, external model/runtime stacks, broad
     upstream dependency graphs, credential-bearing integrations, or pending
     root aggregate verification.

## Test Gate

I did not run `php tools/run-tests.php`. The required exact duplicate-root gate
matched active no-argument root PID `169251` owned by `claude`, plus focused
lane harnesses; the final handoff gate later matched root PID `249017` owned by
`claude`. Starting another root harness would violate the audit instructions.

Verification performed by this audit:

```text
read goal.md, progress.md, porting.html, porting-summary.json
read every lanes/*/UPSTREAM_TEST_MANIFEST.json and every lanes/*/lane-status.json
read dependency-backlog.json and recent git history
sampled live process/worktree/tmux state
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json porting-summary.json dependency-backlog.json
```

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator/watchdog
loops, capacity jobs, focused PHP shards, broad upstream runners, and duplicate
root harnesses. Then validate manifests from the frozen tree, accept or reject
dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/blocker/commit schemas, add manifest-backed optional dependency lanes
only behind concrete base-lane blockers, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from one accepted
commit, and only then run a quiesced root `php tools/run-tests.php` if the exact
duplicate-root gate remains clear.
