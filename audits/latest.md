# Independent Audit - 2026-05-23T22:07Z

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
initial: aa79cd1fd86e
final:   4eeab524cf86
```

Latest sampled worktree/process state:

```text
6123 total git status rows
273 tracked dirty files
273 files changed, 111895 insertions(+), 11904 deletions(-)
124 tmux sessions
31 active repo worker/status/test-control process matches
recent history led by audit/status/integration-hold commits
```

The exact root-harness gate was checked before any possible root run:

```text
2291766 php tools/run-tests.php
2291951 php tools/run-tests.php lanes/difftastic/tests
2292036 php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
2292056 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2292119 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
2292679 php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php ...
```

Owner evidence:

```text
PID     USER    PPID     ELAPSED STAT COMMAND
2291766 claude  2291501  00:06   R+   php tools/run-tests.php
2292036 claude  2291580  00:05   R+   php tools/run-tests.php lanes/markerpdf/tests lanes/pandoc/tests lanes/readability/tests
2292056 claude  2291618  00:05   R+   php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
2292119 claude  2291658  00:05   R+   php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests lanes/difftastic/tests lanes/esbuild/tests
```

`2291951` and `2292679` exited before owner sampling. Because an active
no-argument root harness was present, I did not start `php tools/run-tests.php`.

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
   - Evidence: the required pre-root gate found active no-argument root PID
     `2291766` owned by `claude`, plus focused lane PHP shards. The repo is not
     quiescent: `HEAD` moved from `aa79cd1fd86e` to `4eeab524cf86`, and latest
     samples show `124` tmux sessions, `31` active repo worker/status/test
     control matches, `6123` status rows, and `273` tracked dirty files. This
     contradicts `progress.md:25` documenting a two-worker-plus-auditor target
     and `progress.md:31` through `progress.md:42` reporting every lane as
     `stopped`.

2. **Critical - the dashboard and progress table are materially stale.**
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
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `4eeab524cf86`. Current manifest/status values disagree
     with the dashboard: Difftastic is `359 / prose-724` versus `160 / 417`,
     Gitoxide `2711 / 2877` versus `1432 / 2877`, libsqlite `278 / 1589`
     versus `149 / 1454`, LightningCSS `1723 / 3532` versus `773 / 3532`,
     markerPDF `274 / 324` versus `159 / 78`, Pandoc `1001 / prose-2276`
     versus `426 / 2028`, rclone `672 / 1601` versus `291 / 327`, Readability
     `1984 / 1984` versus `1031 / 1984`, and Syncthing `572 / 658` versus
     `235 / 658`.

3. **High - most current lane progress is pending dirty handoff, not accepted
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`,
     `goal.md:36`, and `goal.md:48` require small correct slices with passing
     tests, meaningful parity, and committed handoff.
   - Evidence: every current lane status sampled uses `pending`,
     `uncommitted`, or similar commit deferral language. Recent history is led
     by `Refresh independent audit status` and `Record integration hold status`
     rather than accepted implementation commits. The lane percentages and
     green focused-test anecdotes are therefore not an accepted integration
     baseline.

4. **High - denominator, mapped, and PHP pass-count schemas remain
   non-normalized.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2241`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/*/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: Difftastic, esbuild, Pandoc, and Quadrable store
     `benchmarkDenominator.total` as prose strings; Dolt's denominator puts
     `mapped` where dashboard tooling expects a top-level numeric total and a
     prose `total` thousands of lines later; lane statuses publish `phpPass`
     without denominators or consistent units. The dashboard cannot safely
     compute average progress or compare lane coverage from these fields.

5. **Medium - blocker fields still blur slice-local green checks with full-port
   parity gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers and explicit hard-feature gaps.
   - Evidence: these blockers start with local-green framing such as no
     focused PHP blocker, while the same records admit pending root
     verification, uncommitted work, unexecuted full upstream runners, live
     provider/service requirements, or heavy runtime dependencies. Slice-local
     readiness needs a separate field from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The required pre-root gate matched active no-argument root PID `2291766` owned
by `claude`, plus focused lane PHP shards. The tree was also not stable enough
for an accepted aggregate run because active lane agents,
dashboard/evaluator/watchdog/capacity/integrator loops, broad Dolt BATS
activity, 124 tmux sessions, and a 273-file tracked dirty tree were present.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
duplicate-root gate remains empty.
