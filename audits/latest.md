# Independent Audit - 2026-05-23T22:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files for cross-checking, recent Git history, current worktree/process
state, and the required duplicate root-harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

Sampled repository state:

```text
HEAD: 46f6b9ccacb3
6505 total git status rows
271 tracked dirty files
6234 untracked paths
271 files changed, 112992 insertions(+), 11882 deletions(-)
119 tmux sessions
active control loops: dashboard updater, evaluator, watchdog, capacity controller, capacity executor
146 commits since latest sampled non-audit/status implementation commit b75226d1
```

The exact root-harness gate was checked before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
# no matching process
```

I still did not start `php tools/run-tests.php`: even with no exact
no-argument root harness matched at this instant, the tree is not stable enough
for an accepted aggregate run because active control loops and 119 tmux sessions
are still mutating/testing a 6505-row dirty worktree.

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
   - Evidence: current samples show `119` tmux sessions, active
     dashboard/evaluator/watchdog/capacity loops, `6505` status rows, `271`
     tracked dirty files, and `271 files changed, 112992 insertions(+), 11882
     deletions(-)`. That contradicts `progress.md:25`, which documents a
     two-implementation-lane-plus-auditor target, and `progress.md:31` through
     `progress.md:42`, which report every lane as `stopped`.

2. **Critical - the dashboard and progress table are materially stale.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json`, `progress.md:31` through `progress.md:42`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require durable coordination files and a
     visible dashboard with current denominator, mapped tests, PHP pass/fail,
     phase, audit, blocker, and commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source snapshot
     `bda83c6b93d4`, while sampled `HEAD` is `46f6b9ccacb3`. Current
     manifest/status values disagree with the dashboard: Difftastic is now
     `361 / 727` mapped versus dashboard `160 / 417`; LightningCSS is
     `1724 / 3532` versus `773 / 3532`; markerPDF is `274 / 324` versus
     `159 / 78`; rclone is `676 / 1601` versus `291 / 327`; Readability is
     `1984 / 1984` versus `1031 / 1984`; Syncthing is `580 / 658` versus
     `235 / 658`. The progress table remains older still, with stopped lanes
     and low estimates such as Gitoxide `66%`, while lane statuses now claim
     mostly `89%` to `99%`.

3. **High - all current lane progress is pending dirty handoff, not accepted
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and
     `goal.md:48` require small correct slices, meaningful parity, passing
     tests, committed handoff, and integration cleanup.
   - Evidence: every sampled lane `latestCommit` field says `pending`,
     `uncommitted`, `not committed`, or equivalent deferral language. Recent
     Git history is audit/status/integration-hold dominated; the latest sampled
     non-audit/status implementation commit is `b75226d1`, `146` commits behind
     current `HEAD`.

4. **High - near-complete percentages overstate full-port parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:12`,
     `lanes/readability/lane-status.json:4`,
     `lanes/readability/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, and
     `goal.md:40` require not counting non-native/generated/oracle work as
     progress, meaningful fixture parity, and explicit hard-feature blockers.
   - Evidence: Difftastic, Pandoc, Quadrable, Rclone, Readability, and
     Syncthing now claim `99%`, and Gitoxide/libsqlite/markerPDF claim `98%`,
     while their own blocker fields still admit unexecuted full upstream
     runners, unexecuted root verification, blob-filter/no-checkout caches,
     heavy dependency/model downloads, live provider/service requirements,
     copied fixture inventories, or local oracle coverage. These are substantial
     remaining parity gaps, not 1-2% tail work.

5. **High - denominator, mapped, and PHP pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2247`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:37`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     upstream tests as source of truth, and comparable dashboard fields.
   - Evidence: Difftastic, esbuild, Pandoc, and Quadrable store
     `benchmarkDenominator.total` as prose strings; Dolt has `mapped` at line
     14 and a stale prose `total` at line 2247 that still describes an older
     `21:56 UTC` slice even though current status claims a `22:13 UTC` slice.
     Status files mix behavior counts and assertion counts: Gitoxide reports
     `phpPass: 5526` against `2712 / 2877` mapped, Syncthing reports
     `phpPass: 4214` against `580 / 658`, markerPDF reports `phpPass: 408`
     against `274 / 324`, and Readability reports `phpPass: 197` while its
     manifest claims `1984 / 1984` mapped. The dashboard cannot safely compute
     average progress or compare lane coverage from these fields.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate matched no active no-argument root harness at
the sampling instant, but the stability gate failed because active control loops,
119 tmux sessions, and a 6505-row dirty tree were present. Starting a fresh root
run from this state would not produce an auditable baseline and could race with
ongoing lane writers/status publishers.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
