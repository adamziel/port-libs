# Independent Audit - 2026-05-23T18:44:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, `audits/integration-status.md`, recent Git history,
current worktree state, and process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `37b407fe` (`Record integration hold status`). The latest 61
commits after `b75226d1` are audit/status/integration commits, not accepted
lane implementation commits. The nearest recent implementation commit remains
`b75226d1` (`Port rclone OneDrive Object.Update upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2701`
`git status --short --untracked-files=all` rows, `229` tracked dirty rows,
`2472` untracked rows, and `230 files changed, 110581 insertions(+), 9422
deletions(-)`. `HEAD` changed during this audit from `cda74d84a4b1` to
`37b407fe`, which is direct evidence that the repo was still moving while audit
evidence was being collected.

I did not run `php tools/run-tests.php`. The required exact pre-root gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

returned no rows at the initial audit sample, but the final pre-commit sample
was not clear:

```text
3521992 php tools/run-tests.php
3524980 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
3525001 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests
```

Owner evidence:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3521992 claude 3521942  01:05   R+   php tools/run-tests.php
3524980 claude 3524792  00:12   R+   php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

PID `3525001` exited before owner sampling. I did not start a duplicate root run.
The stability gate also failed: process sampling found `25` matching repo
worker/status/test-control processes, including dashboard updater, team
watchdog, evaluator, capacity controller, Dolt runner, integrator, auditor,
primary lane agents, and capacity agents. `progress.md` still reports every lane
as `stopped`, while those agents remain active.

The latest committed integration status independently reaches the same hold:
`audits/integration-status.md:3` through `audits/integration-status.md:8` says
no worker output, dashboard regeneration, push, upstream parity result, or root
aggregate result was accepted; `audits/integration-status.md:61` through
`audits/integration-status.md:75` says the tree is too active to integrate and
requires a freeze plus serialized root harness before accepting a snapshot.

Current manifest/status files and the published dashboard disagree:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 326 / 630 prose artifacts | 326 focused lane tests in prose | 160 / 417 | 160 / 0 |
| dolt | 602 / prose field with 613 embedded | manifest prose says 324 PASS; `phpBehaviorTests` says 321 | 242 / 613 | 193 / 0 |
| esbuild | 275 / 2,567 prose entry points | 275 focused tests | 164 / 2,567 | 164 / 0 |
| gitoxide | 2,573 / 2,877 | 5,057 assertions in prose | 1,432 / 2,877 | 2,646 / 0 |
| libsqlite | 262 / 1,589 | 262 focused tests in status prose | 149 / 1,454 | 149 / 0 |
| lightningcss | 1,525 / 3,532 | warning says 1,512 checks and 1,711 assertions | 773 / 3,532 | 906 / 0 |
| markerPDF | 252 / 304 | 385 focused tests | 159 / 78 | 264 / 0 |
| pandoc | 836 / 2,276 prose artifacts | 247 focused tests in status prose | 426 / 2,028 | 164 / 0 |
| quadrable | 55 / 55 prose paths | not normalized | 55 / 55 | 108 / 0 |
| rclone | 590 / 1,601 | 590 focused tests | 291 / 327 | 291 / 0 |
| readability | 1,984 / 1,984 upstream JS runner tests | 179 native PHP behavior tests | 1,031 / 1,984 | 107 / 0 |
| syncthing | 476 / 658 | 3,380 assertions in prose | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and the moving dirty tree block any trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `audits/integration-status.md:61` through
     `audits/integration-status.md:75`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small committed verified slices, cleanup, progress updates, and
     repo-wide tests/static checks recorded honestly.
   - Evidence: the exact PHP root gate was clear at one sample, then later
     matched active no-argument root PID `3521992` plus focused lane harnesses.
     The tree had `2701` status rows, `229` tracked dirty rows, and `230 files
     changed, 110581 insertions(+), 9422 deletions(-)`. Process sampling found
     `25` active repo worker/status/test-control processes, and `HEAD` advanced
     during the audit itself.
   - Impact: a root run now would test a moving, unaccepted tree and would not
     satisfy the goal's acceptance standard.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:40` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `porting-summary.json:11` through `porting-summary.json:212`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: both files still publish generated time `2026-05-23 04:57:16
     UTC` and source snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `37b407fe`. The HTML has a single `Mapped` column that combines PHP
     pass/fail and mapped coverage, with no separate upstream-denominator or PHP
     pass/fail columns.
   - Evidence: every dashboard row is stale against the current manifests. For
     example, rclone is currently `590 / 1601` but the dashboard says
     `291 / 327`; markerPDF is currently `252 / 304` with `385` PHP tests but
     the dashboard says `159 / 78` and `264`; Syncthing is currently
     `476 / 658` but the dashboard says `235 / 658`.

3. **High - manifests still mix incompatible denominator and mapped-count
   schemas, so the portfolio percentage is not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:691`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings,
     while others are numeric counts. Difftastic counts behavior artifacts,
     Pandoc counts files/artifacts, Quadrable counts tracked paths plus scenario
     prose, and Readability reports `mapped: 1984` against the upstream JS Mocha
     runner while only `179` native PHP behavior tests are recorded.
   - Impact: the dashboard average still mixes upstream test functions, files,
     fixtures, local behavior tests, assertions, copied fixtures, runner logs,
     and plan-only evidence.

4. **High - some manifests/status records are internally inconsistent, not just
   stale against the dashboard.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2169`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2175`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:2113`,
     `lanes/lightningcss/lane-status.json:13`, and
     `lanes/dolt/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:45`
     require precise blockers, current status, and reliable per-lane mapped/PHP
     fields.
   - Evidence: Dolt's manifest prose says the latest focused PHP pass is `324`
     PASS cases, but `nativeImplementation.phpBehaviorTests` remains `321` and
     the warning says `321`. LightningCSS has `mapped: 1525`, while the warning
     says `1,512` focused checks. LightningCSS status still says `HEAD
     cda74d84a4b1` even though current `HEAD` is `37b407fe`.
   - Impact: even before regenerating the public dashboard, lane-local metadata
     cannot be trusted as a normalized source of truth.

5. **High - dirty, unaccepted lane batches are being counted as progress.**
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, and `goal.md:48`
     require committed reviewable slices, no generated/bridge progress credit,
     verification, cleanup, and next-task reassignment.
   - Evidence: every sampled lane records `pending`, `uncommitted`, or dirty
     worktree prose in `latestCommit`. Recent history shows the last accepted
     lane implementation commit is still `b75226d1`; subsequent commits are
     audit/status/integration records.
   - Impact: focused lane tests and upstream probes are useful handoff evidence,
     but the portfolio should not count them as accepted native-port progress
     until they are isolated, reviewed, root-gated where appropriate, committed,
     and regenerated into the dashboard from the same commit.

6. **High - blocker language and near-complete claims still hide full-port
   parity gaps.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:574`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:575`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:988`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1188`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say blockers must be recorded precisely, passing tests are not enough, and
     hard features must not be silently skipped.
   - Evidence: lane status often says "No current blocker" for the slice while
     also listing root verification as pending and full upstream/provider/model
     runners or major feature families as unexecuted. Examples include rclone
     live provider/mount parity, markerPDF Python/model/benchmark execution,
     Syncthing full `go test ./...`, and Gitoxide full cargo workspace parity.
   - Audit judgment: slice-local blocker status should be separated from
     full-port blocker status before percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The exact pre-root gate returned no rows at the initial audit sample, but the
final pre-commit sample matched active no-argument root PID `3521992` owned by
`claude`, plus focused lane harness PID `3524980` owned by `claude` and transient
focused PID `3525001` that exited before owner sampling. No duplicate or
aggregate root run was started by this audit. The stability gate also failed:
active writer/status/agent processes remained, the worktree was broadly dirty,
lane statuses were changing, and `HEAD` advanced during the audit.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, upstream runners,
and focused PHP loops first. Then validate all manifests from the frozen tree,
accept or reject dirty lane batches one lane at a time, normalize
denominator/mapped/PHP fields, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit, and only then run the
no-argument root harness if the exact duplicate-root gate remains empty across
two polls.
