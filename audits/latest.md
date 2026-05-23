# Independent Audit - 2026-05-23T22:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
lane status file for cross-checking, recent Git history, current worktree
state, process state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled `HEAD` moved during the audit:

```text
initial: 6dc2187e0c5d
final:   642809b9c1b9
```

Latest sampled worktree/process state:

```text
5920 total git status rows
270 tracked dirty files
270 files changed, 111024 insertions(+), 11874 deletions(-)
116 tmux sessions
57 active repo worker/status/test-control process matches
138 commits since sampled implementation commit b75226d1
```

The exact root-harness gate was checked before any possible root run:

```text
2079387 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2079387 claude  2079191  00:59   R+   php tools/run-tests.php
```

A later gate found multiple active root/focused PHP harnesses:

```text
2110084 php tools/run-tests.php
2115039 php tools/run-tests.php
2115368 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
2115589 php tools/run-tests.php lanes/syncthing/tests/IndexHandlerConnectionCoordinatorTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2110084 claude  2059568  00:29   Rs   php tools/run-tests.php
2115039 claude  2114622  00:20   R+   php tools/run-tests.php
2115589 claude  2115196  00:20   R+   php tools/run-tests.php lanes/syncthing/tests/IndexHandlerConnectionCoordinatorTest.php ...
```

`2115368` exited before owner sampling. Because active no-argument root
harnesses were present, I did not start `php tools/run-tests.php`.

The final exact gate returned no rows, but I still did not start a root run
because the tree was not stable enough: `HEAD` had moved, active writer/test
loops were still present, and tracked dirt increased to `270` files.

## Findings

1. **Critical - there is still no trustworthy integration baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:25`,
     `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `.tmux-team/*`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision,
     reviewable committed slices, integration cleanup, and honest repo-wide
     verification.
   - Evidence: the required pre-root gate found active no-argument root
     harnesses owned by `claude` (`2079387`, then `2110084` and `2115039`).
     The repo is not quiescent: `HEAD` moved from `6dc2187e0c5d` to
     `642809b9c1b9`, and the latest sample still showed `116` tmux sessions,
     `57` active repo worker/status/test-control matches, `5920` status rows,
     and `270` tracked dirty files. This directly contradicts `progress.md:25`
     documenting a target of two implementation lanes plus one auditor and
     `progress.md:31` through `progress.md:42` reporting every lane session as
     `stopped`.

2. **Critical - the public dashboard and human progress file do not show the
   current project state.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `progress.md:31` through `progress.md:42`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: `porting.html` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source snapshot
     `bda83c6b93d4`, while final sampled `HEAD` is `642809b9c1b9`. Current
     manifest/status values materially disagree with the dashboard: Difftastic
     is `359 / prose-724` versus dashboard `160 / 417`; Gitoxide is
     `2711 / 2877` versus `1432 / 2877`; libsqlite is `277 / 1589` versus
     `149 / 1454`; LightningCSS is `1722 / 3532` versus `773 / 3532`;
     markerPDF is `273 / 323` versus `159 / 78`; Pandoc is `1001 / prose-2276`
     versus `426 / 2028`; rclone is `668 / 1601` versus `291 / 327`; and
     Syncthing is `572 / 658` versus `235 / 658`.

3. **High - manifests and statuses are still being rewritten while audits are
   reading them.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:34`,
     `audits/latest.md`, `progress.md`, and all live lane manifests/statuses
     touched by active lane agents.
   - Goal requirement at risk: `goal.md:3`, `goal.md:29`,
     `goal.md:39`, and `goal.md:48` require reproducible generated artifacts,
     reviewable commits, and coherent integration handoff.
   - Evidence: during this audit, Pandoc's manifest `mapped` count changed
     from `989` in the initial read to `1001` at `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`.
     Active lane agents, dashboard/evaluator/watchdog/capacity loops, broad
     Dolt BATS, and duplicate root/focused PHP harnesses were running at the
     same time. A moving manifest set cannot be used as an accepted baseline or
     as a stable input for dashboard generation.

4. **High - lane progress is mostly pending dirty handoff, not accepted
   implementation history.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:4`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:13`,
     and `lanes/syncthing/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices with passing
     tests, meaningful parity, and committed handoff.
   - Evidence: sampled lane estimates are now mostly `88%` to `99%`, but the
     same lane status files say `pending`, `uncommitted`, or `not committed`
     in `latestCommit`. The history is still status/audit/integration-hold
     dominated: `138` commits since sampled implementation commit `b75226d1`,
     with recent `git log` led by `Refresh independent audit status` and
     `Record integration hold status`. These percentages do not represent
     accepted, reviewable implementation commits.

5. **High - denominator, mapped, and PHP pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2241`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     and `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: Difftastic, esbuild, Pandoc, and Quadrable still store
     `benchmarkDenominator.total` as prose strings; Dolt's top-level
     denominator starts with `mapped: 613` and puts a prose `total` much later;
     markerPDF uses a static behavior/reference denominator because upstream
     has zero committed Python tests. `phpPass` alternates between PHP test
     cases, behavior checks, assertions, or lane-specific pass units. These
     units cannot safely feed one dashboard coverage or average-progress
     calculation.

6. **Medium - blocker fields still blur slice-local green checks with full-port
   blockers.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond local passing tests,
     and explicit hard-feature gaps.
   - Evidence: several blocker fields begin with `No ... blocker` while the
     same record admits uncommitted work, pending root verification, unexecuted
     full upstream runners, excluded provider credentials/services, or heavy
     model/runtime paths. Slice-local PHP green checks need a separate field
     from full-port blockers; otherwise dashboard readers can mistake a focused
     pass for upstream parity.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root harnesses owned by
`claude` (`2079387`, then `2110084` and `2115039`), plus focused Syncthing
PHP shards. A final exact gate returned no rows, but the tree was still not
stable enough for a root run because `HEAD`, status counts, and active
worker/test loops were still moving. Starting an aggregate run in that state
would not produce an accepted baseline.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
