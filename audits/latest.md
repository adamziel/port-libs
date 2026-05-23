# Independent Audit - 2026-05-23T18:39:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
sampled `lanes/*/lane-status.json`, recent Git history, current worktree state,
and process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle fixtures,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `ecebabe2ec5f` (`Refresh independent audit status`). The latest
59 commits after `b75226d1` are audit-only refresh commits; the nearest recent
implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2695`
`git status --short --untracked-files=all` rows, `230` tracked dirty rows, and
`230 files changed, 109782 insertions(+), 9348 deletions(-)`.

I did not run `php tools/run-tests.php`. The required pre-root gate was not
clear:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3500927 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3500927 claude 3448628  00:24   Rs   php tools/run-tests.php lanes/syncthing/tests
```

An earlier exact gate also matched PID `3485857` with the same focused
Syncthing lane command, but it exited before owner sampling. Process sampling
found `29` matching repo worker/status/test processes, including dashboard
updater, team watchdog, evaluator, capacity controller, Dolt runner, integrator,
auditor, primary lane agents, capacity agents, broad Dolt BATS activity, and
the focused Syncthing PHP harness. `progress.md` still reports every lane as
`stopped`.

Current manifests/status files and the published dashboard disagree:

| Lane | Current manifest mapped / denominator | Current lane PHP | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 326 / 630 prose artifacts | 326 / 0 | 160 / 417 | 160 / 0 |
| dolt | 601 / prose field with 613 embedded | 323 / 0 | 242 / 613 | 193 / 0 |
| esbuild | 273 / 2,567 prose entry points | 273 / 0 | 164 / 2,567 | 164 / 0 |
| gitoxide | 2,573 / 2,877 | 4,994 / 0 | 1,432 / 2,877 | 2,646 / 0 |
| libsqlite | 261 / 1,589 | 261 / 0 | 149 / 1,454 | 149 / 0 |
| lightningcss | 1,522 / 3,532 | 1,721 / 0 | 773 / 3,532 | 906 / 0 |
| markerPDF | 252 / 304 | 385 / 0 | 159 / 78 | 264 / 0 |
| pandoc | 836 / 2,276 prose artifacts | 247 / 0 | 426 / 2,028 | 164 / 0 |
| quadrable | 55 / 55 prose paths | 170 / 0 | 55 / 55 | 108 / 0 |
| rclone | 584 / 1,601 | 584 / 0 | 291 / 327 | 291 / 0 |
| readability | 1,984 / 1,984, but only 177 native PHP behavior tests | 177 / 0 | 1,031 / 1,984 | 107 / 0 |
| syncthing | 475 / 658 | 3,371 / 0 | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active test/writer processes and a broad dirty tree block any
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the required pre-root gate matched active PHP harness PID
     `3500927` owned by `claude`; process sampling found `29` repo
     worker/status/test processes; the tree has `230` tracked dirty rows and
     over `109k` diff insertions. Progress still says every lane is stopped.
   - Impact: a no-argument root run now would test a moving, unaccepted tree
     while another PHP harness is active, so it would not be a stable accepted
     baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:40` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: both files still publish generated time `2026-05-23 04:57:16
     UTC` and source snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `ecebabe2ec5f`.
   - Evidence: dashboard counts disagree with current manifests/status files
     on every lane except Quadrable's mapped count. Severe examples:
     markerPDF is currently `252 / 304` with `385` PHP passes, but the dashboard
     says `159 / 78` and `264`; Gitoxide is `2573 / 2877` with `4994` PHP
     passes, but the dashboard says `1432 / 2877` and `2646`.
   - Evidence: the HTML still combines PHP pass/fail and mapped coverage into
     one `Mapped` cell and has no separate upstream-denominator and PHP
     pass/fail columns.

3. **High - `progress.md` is not a reliable supervisor coordination source.**
   - Paths: `progress.md:14`, `progress.md:15`, `progress.md:25`, and
     `progress.md:31` through `progress.md:42`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:44` require accurate
     active-lane/session state, owner/session coordination, blockers, next
     tasks, and percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two
     implementation lanes plus one auditor, and the active-lane table marks all
     lanes `stopped`; process sampling found primary agents for the lanes plus
     dashboard/evaluator/capacity/integrator/auditor loops. Current
     `lane-status.json` estimates range from `82%` to `99%`, while the progress
     table still shows `5%` to `66%`.
   - Impact: capacity decisions and acceptance sequencing cannot safely use
     `progress.md` until it is regenerated from a frozen accepted snapshot.

4. **High - dirty, unaccepted lane batches are being counted as progress.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`,
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:31`, and `goal.md:48`
     require small reviewable committed slices, precise blockers, verification,
     cleanup, progress updates, and next-task reassignment.
   - Evidence: every sampled lane records `pending`, `uncommitted`, or
     dirty-worktree prose in `latestCommit`. Recent Git history shows the
     latest 59 commits after `b75226d1` are audit-only refreshes, not accepted
     implementation commits.
   - Impact: focused lane tests may be useful evidence, but the progress bars
     should not treat these batches as accepted native-port progress until they
     are isolated, root-gated where appropriate, committed, and regenerated into
     the dashboard from the same commit.

5. **High - manifest denominator, mapped-count, and PHP-count schemas remain
   non-comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`,
     and `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: some `benchmarkDenominator.total` fields are numbers, while
     Difftastic, Dolt, esbuild, Pandoc, and Quadrable use prose strings. Dolt's
     `total` field is a running status log with the `613` denominator embedded
     inside prose. Readability reports `mapped: 1984` against `total: 1984`,
     but current status has only `177` native PHP behavior tests.
   - Impact: the portfolio average still mixes upstream files, test functions,
     fixture pages, local behavior tests, local assertions, copied fixtures,
     runner logs, and plan-only evidence. It is not auditable as one metric.

6. **High - near-complete lane percentages still understate full-parity
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` and
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` and
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` and
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` and
     `lanes/pandoc/lane-status.json:12`,
     `lanes/quadrable/lane-status.json:4` and
     `lanes/quadrable/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` and
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say passing tests are not enough, unresolved upstream runners and hard
     features must be blockers or future slices, and edge-case/error behavior
     matters.
   - Evidence: several lanes report `97%` to `99%` while also documenting
     pending root aggregate verification, unexecuted full upstream runners,
     live-provider/model/tooling gaps, or broad unported protocol/provider
     behavior.
   - Audit judgment: slice-local "no blocker" language should be separated
     from full-port blockers before these percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate before any possible root run is:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned active focused Syncthing lane harness PID `3500927`, owned by
`claude`, so no duplicate or aggregate root run was started. The stability gate
also failed because active writer/test/status processes were present, broad
Dolt BATS was active, the progress table contradicted process state, and the
worktree had `230` tracked dirty rows.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, broad upstream
runners, and focused PHP loops first. Then validate all manifests from the
frozen tree, accept or reject dirty lane batches one lane at a time, normalize
denominator/mapped/PHP fields, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit, and only then run the
no-argument root harness if the exact duplicate-root gate remains empty.
