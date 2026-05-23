# Independent Audit - 2026-05-23T21:49:21Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree state,
process state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD`: `a42b60048b72`.

Latest sampled worktree/process state:

```text
5470 total git status rows
264 tracked dirty files
264 files changed, 108645 insertions(+), 11665 deletions(-)
126 tmux sessions
133 commits since sampled implementation commit b75226d1
latest 14 commits are audit/status/integration-hold records
```

The exact root-harness gate was checked before any possible root run:

```text
1781250 php tools/run-tests.php
1787504 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerRegistryTest.php lanes/syncthing/tests/IndexHandlerTest.php lanes/syncthing/tests/PlatformMetadataApplierTest.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1781250 claude  1781119  00:58   R+   php tools/run-tests.php
1787504 claude  1787248  00:28   R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerRegistryTest.php ...
```

Because an exact no-argument root harness was active, I did not start
`php tools/run-tests.php`.

A post-edit handoff gate also found active no-argument root PID `1880756`
owned by `claude`:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1880756 claude  1880254  00:23   R+   php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `.tmux-team/*`,
     `progress.md:25`, and `progress.md:31` through `progress.md:42`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, reviewable
     committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the duplicate-root gate found active no-argument root PID
     `1781250` owned by `claude`, plus a focused Syncthing PHP shard. The
     tree is not quiescent: `git status` reported `5470` rows, `264`
     tracked dirty files, and `264 files changed, 108645 insertions(+),
     11665 deletions(-)`. `tmux list-sessions` reported `126` sessions,
     while `progress.md:25` still documents a two-worker-plus-auditor target
     and every Active Lanes row in `progress.md:31` through `progress.md:42`
     says `stopped`.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and
     a visible dashboard with denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `a42b60048b72`. Current manifest/status rows
     materially disagree with the dashboard: Difftastic is `357 / prose-720`
     while the dashboard is `160 / 417`; Dolt is `613 mapped` and `340`
     focused PHP PASS cases while the dashboard is `242 / 613` and `193`
     pass; esbuild is `297 / prose-2567` while the dashboard is `164 /
     2567`; Gitoxide is `2710 / 2877` while the dashboard is `1432 /
     2877`; libsqlite is `276 / 1589` while the dashboard is `149 / 1454`;
     LightningCSS is `1720 / 3532` while the dashboard is `773 / 3532`;
     markerPDF is `272 / 322` while the dashboard is `159 / 78`; Pandoc is
     `989 / prose-2276` while the dashboard is `426 / 2028`; rclone is
     `663 / 1601` while the dashboard is `291 / 327`; Readability is `1984 /
     1984` while the dashboard is `1031 / 1984`; Syncthing is `564 / 658`
     while the dashboard is `235 / 658`.

3. **High - lane progress is mostly pending dirty handoff, not accepted
   implementation history.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`,
     `goal.md:48`, and `goal.md:49` require small correct slices, passing
     tests, verified handoff, and committed work.
   - Evidence: every sampled `latestCommit` field says `pending`, `not
     committed`, `uncommitted`, or dirty-batch prose. The latest 14 commits
     are audit/status/integration-hold records, and `HEAD` is `133` commits
     past the latest sampled implementation commit `b75226d1`.

4. **High - manifest denominator and pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` is a number in some lanes and
     prose in Difftastic, esbuild, Pandoc, and Quadrable; Dolt stores mapped
     count near the top while denominator details are mixed with latest-slice
     prose and inventory fields. Lane-status `phpPass` alternates between
     behavior count, PASS-case count, assertion count, and dashboard-style
     pass count. The generated average progress is therefore not a stable or
     comparable native-port parity measure.

5. **High - near-complete percentages overstate full native-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/esbuild/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, prohibit counting
     oracle/bridge evidence as progress, and require explicit hard-feature
     gaps.
   - Evidence: many lanes report `98%` or `99%` progress despite pending
     commits, no accepted root baseline, and major unexecuted full-runner,
     live-provider, model, server, FUSE, browser/WASM, full Cargo/Cabal/Go,
     and benchmark paths. Readability maps `1984 / 1984` after copying all
     Mozilla fixtures, but the native PHP surface is still `194` or `195`
     focused behavior tests depending on the sampled status field. markerPDF
     reports `98%` while live Python/model/FastAPI/Streamlit/benchmark and
     publish/CLA paths remain unexecuted.

6. **Medium - blocker fields still blur slice-local green checks with
   full-port blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing
     tests, and explicit hard-feature gaps.
   - Evidence: blocker fields start with `No ... blocker` while the same
     fields admit pending root verification, uncommitted work, unexecuted full
     upstream runners, excluded live providers/servers/model paths, or broad
     parity gaps. Slice-local green status needs a separate field from
     full-port blocker status.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `1781250` owned
by `claude`, plus a focused Syncthing PHP shard. A post-edit handoff gate
matched active no-argument root PID `1880756` owned by `claude`. Starting
another aggregate root run would duplicate an existing harness and would not
produce an accepted baseline while the worktree remains broad and
non-quiescent.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
