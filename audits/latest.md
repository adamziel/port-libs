# Independent Audit - 2026-05-23T21:43:19Z

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

Sampled `HEAD`: `2e7b9ed4a709`.

Latest sampled worktree/process state:

```text
5305 total git status rows
263 tracked dirty files
263 files changed, 107788 insertions(+), 11665 deletions(-)
116 tmux sessions
131 commits since sampled implementation commit b75226d1
latest 8 commits are audit/status/integration-hold records
```

The required exact root-harness gate was checked before any possible root run:

```text
1673837 php tools/run-tests.php
1685287 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
1685366 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerRegistryTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1673837 claude  1673570  62      R+   php tools/run-tests.php
1685366 claude  1685164  33      R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerRegistryTest.php ...
```

PID `1685287` exited before owner sampling. Because an exact no-argument root
harness was active, I did not start `php tools/run-tests.php`.

A post-edit handoff gate also found active no-argument root PID `1724230` owned
by `claude`:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
1724230 claude  1724172  53      R+   php tools/run-tests.php
```

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `.tmux-team/*`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, reviewable
     committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the duplicate-root gate found active no-argument root PID
     `1673837` owned by `claude`, plus focused Syncthing PHP shards. The
     tree is not quiescent: `git status` reported `5305` rows, `263`
     tracked dirty files, and `263 files changed, 107788 insertions(+),
     11665 deletions(-)`. `tmux list-sessions` reported `116` sessions,
     while `progress.md:25` still documents a two-worker-plus-auditor target
     and every Active Lanes row in `progress.md:31` through
     `progress.md:42` says `stopped`.

2. **Critical - `porting.html` and `porting-summary.json` are stale and
   contradict current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:4`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current coordination files and
     a visible dashboard with denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `2e7b9ed4a709`. Current manifest/status rows
     disagree with the dashboard: Difftastic is `355 / prose-716` while the
     dashboard is `160 / 417`; Dolt is `613 mapped` while the dashboard is
     `242 / 613`; esbuild is `297 / prose-2567` while the dashboard is
     `164 / 2567`; Gitoxide is `2708 / 2877` while the dashboard is
     `1432 / 2877`; libsqlite is `276 / 1589` while the dashboard is
     `149 / 1454`; LightningCSS is `1720 / 3532` while the dashboard is
     `773 / 3532`; markerPDF is `272 / 322` while the dashboard is
     `159 / 78`; Pandoc is `974 / prose-2276` while the dashboard is
     `426 / 2028`; rclone is `663 / 1601` while the dashboard is
     `291 / 327`; Readability is `1984 / 1984` while the dashboard is
     `1031 / 1984`; Syncthing is `564 / 658` while the dashboard is
     `235 / 658`.

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
   - Evidence: sampled `latestCommit` fields say `pending`, `not
     committed`, `uncommitted`, or dirty-batch prose for every lane sampled.
     Recent history remains audit/status dominated: the latest eight commits
     are `Record integration hold status` or `Refresh independent audit
     status`, and `HEAD` is `131` commits past the latest sampled
     implementation commit `b75226d1`.

4. **High - manifest denominator schemas remain non-normalized and some
   manifests changed during this audit.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2227`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable
     dashboard fields.
   - Evidence: `benchmarkDenominator.total` is a number in some lanes and
     prose in Difftastic, esbuild, Pandoc, and Quadrable; Dolt has
     `benchmarkDenominator.mapped` near the top and another prose `total`
     buried under native implementation metadata. The mapped count changed
     while reading: markerPDF moved from `319 / 269` to `322 / 272`, and
     esbuild moved from `296` mapped to `297` mapped. The dashboard average
     is therefore not a stable or comparable native-port parity measure.

5. **High - near-complete percentages overstate full native-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/quadrable/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/readability/lane-status.json:4`, and
     `lanes/syncthing/lane-status.json:4`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, prohibit counting
     oracle/bridge evidence as progress, and require explicit hard-feature
     gaps.
   - Evidence: eight lanes report `98%` or `99%` progress and one reports
     `94%`, despite pending commits, no accepted root baseline, and major
     unexecuted full-runner/live-provider/model/server paths. markerPDF is
     `98%` while its latest status includes publish/CLA workflow planning
     and many live Python/model/GitHub Actions paths not executed. Readability
     maps `1984 / 1984` after copying all Mozilla fixtures, but lane status
     still reports only `194` focused PHP tests and root verification pending.

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

The required pre-root gate matched active no-argument root PID `1673837` owned
by `claude`, plus focused Syncthing PHP harnesses. A post-edit handoff gate
also matched active no-argument root PID `1724230` owned by `claude`. Starting
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
