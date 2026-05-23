# Independent Audit - 2026-05-23T18:56:24Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, `audits/integration-status.md`, recent Git history,
current worktree state, and process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `8d7c5c7c961f` (`Record integration hold status`). `HEAD`
advanced during this audit from `1b2688689590` through `76bf8d3ab60b` and
`b0d21fea6f20` to `8d7c5c7c961f`; the latest 65 commits after `b75226d1` are
audit/status/integration commits, not accepted
lane implementation commits. The nearest recent implementation commit remains
`b75226d1` (`Port rclone OneDrive Object.Update upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is still not quiescent. Latest samples reported `2777`
`git status --short --untracked-files=all` rows, `230` tracked dirty rows, and
`231 files changed, 83912 insertions(+), 9535 deletions(-)`. Manifest/status
values changed while this audit was running, including rclone moving from
`590 / 1601` to `594 / 1601` and Readability moving from `179` to `180` native
PHP behavior tests.

I did not run `php tools/run-tests.php`. The required exact pre-root gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

returned active PHP harnesses during the audit:

```text
3559071 php tools/run-tests.php
```

Owner evidence:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3559071 claude 3559070  00:32   R    php tools/run-tests.php
```

The latest exact root gate returned no rows, but the tree was not stable enough
for a trustworthy aggregate run. A final pre-commit gate then found focused
rclone/syncthing PID `3576829`, which exited before owner sampling. Process
sampling found `19` matching repo
worker/status/test-control processes, including dashboard updater, team
watchdog, evaluator, capacity controller, Dolt runner, integrator, auditor,
primary lane agents, and root/focused PHP harnesses. `progress.md:25` still
documents a target of two implementation lanes plus one auditor, and
`progress.md:31` through `progress.md:42` still reports every lane as
`stopped`.

The latest committed integration hold independently reaches the same conclusion:
`audits/integration-status.md:3` through `audits/integration-status.md:8` says
no worker output, dashboard regeneration, push, upstream parity result, or root
aggregate result was accepted; `audits/integration-status.md:62` through
`audits/integration-status.md:77` says the tree is too active to integrate and
requires a freeze plus serialized root harness before accepting a snapshot.

Current manifest/status files and the published dashboard disagree:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 328 / 633 prose artifacts | no normalized manifest PHP field; lane-status says 328 pass | 160 / 417 | 160 / 0 |
| dolt | 602 / prose field with 613 embedded | manifest `phpBehaviorTests` 321; lane-status says 325 | 242 / 613 | 193 / 0 |
| esbuild | 275 / 2,567 entry points | 275 behavior tests | 164 / 2,567 | 164 / 0 |
| gitoxide | 2,578 / 2,877 | no normalized manifest PHP field; lane-status says 5,057 assertions | 1,432 / 2,877 | 2,646 / 0 |
| libsqlite | 262 / 1,589 | no normalized manifest PHP field; lane-status says 262 tests | 149 / 1,454 | 149 / 0 |
| lightningcss | 1,525 / 3,532 | warning says 1,512 checks and 1,711 assertions; lane-status says 1,846 assertions | 773 / 3,532 | 906 / 0 |
| markerPDF | 253 / 305 | 387 behavior tests | 159 / 78 | 264 / 0 |
| pandoc | 845 / 2,276 prose artifacts | no normalized manifest PHP field; lane-status says 248 tests | 426 / 2,028 | 164 / 0 |
| quadrable | 55 / 55 prose paths | no normalized manifest PHP field; lane-status says 171 pass | 55 / 55 | 108 / 0 |
| rclone | 594 / 1,601 | 594 behavior tests | 291 / 327 | 291 / 0 |
| readability | 1,984 / 1,984 upstream JS runner tests | 180 native PHP behavior tests | 1,031 / 1,984 | 107 / 0 |
| syncthing | 476 / 658 | no normalized manifest PHP field; lane-status says 3,380 assertions | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers, root harnesses, and the dirty moving tree block
   any trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `audits/integration-status.md:62` through
     `audits/integration-status.md:77`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small committed verified slices, cleanup, progress updates, and
     repo-wide tests/static checks recorded honestly.
   - Evidence: the exact PHP root gate matched active no-argument root PID
     `3559071`, owned by `claude`. The tree had `2777` status rows, `230`
     tracked dirty rows, and `231 files changed, 83912 insertions(+), 9535
     deletions(-)`. Process sampling found `19` active repo
     worker/status/test-control processes.
   - Impact: a root run now would be a duplicate or would test a moving,
     unaccepted tree. It would not satisfy the goal's acceptance standard.

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
     `8d7c5c7c961f`. The HTML has a single `Mapped` column that combines PHP
     pass/fail and mapped coverage, with no separate upstream-denominator or PHP
     pass/fail columns.
   - Evidence: every dashboard row is stale against current manifests. For
     example, rclone is currently `594 / 1601` but the dashboard says
     `291 / 327`; markerPDF is currently `253 / 305` with `387` PHP tests but
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
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:685` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:691`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: several `benchmarkDenominator.total` values are prose strings,
     while others are numeric counts. Difftastic counts behavior artifacts,
     Pandoc counts files/artifacts, Quadrable counts tracked paths plus scenario
     prose, and Readability reports `mapped: 1984` against the upstream JS Mocha
     runner while only `180` native PHP behavior tests are recorded.
   - Impact: dashboard averages still mix upstream test functions, files,
     fixtures, local behavior tests, assertions, copied fixtures, runner logs,
     and plan-only evidence.

4. **High - some manifest/status records are internally inconsistent, not just
   stale against the dashboard.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2173`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2179`,
     `lanes/dolt/lane-status.json:5` through
     `lanes/dolt/lane-status.json:7`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:2113`, and
     `lanes/lightningcss/lane-status.json:6` through
     `lanes/lightningcss/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:45`
     require precise blockers, current status, and reliable per-lane mapped/PHP
     fields.
   - Evidence: Dolt's manifest has `mapped: 602` and
     `nativeImplementation.phpBehaviorTests: 321`, while lane status says `603`
     focused mappings and `325` PHP passes. LightningCSS has `mapped: 1525`,
     while its warning says `1,512` checks; lane status says `1,846`
     assertions and still records `HEAD cda74d84a4b1` even though sampled
     `HEAD` is `8d7c5c7c961f`.
   - Impact: even before regenerating the public dashboard, lane-local metadata
     cannot be trusted as a normalized source of truth.

5. **High - dirty, unaccepted lane batches are being counted as near-complete
   progress.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4` through `lanes/dolt/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, and `goal.md:48`
     require committed reviewable slices, no generated/bridge progress credit,
     verification, cleanup, and next-task reassignment.
   - Evidence: sampled lane-status files claim high completion estimates such
     as Difftastic `99`, Dolt `95`, Gitoxide `98`, rclone `98`, Readability
     `99`, and Syncthing `99`, while the same files record `pending`,
     `uncommitted`, or dirty-worktree prose in `latestCommit`. Recent history
     shows the last accepted lane implementation commit is still `b75226d1`;
     the following 65 commits are audit/status/integration records.
   - Impact: focused lane tests and upstream probes are useful handoff evidence,
     but the portfolio should not count them as accepted native-port progress
     until they are isolated, reviewed, root-gated where appropriate, committed,
     and regenerated into the dashboard from the same commit.

6. **High - blocker language still hides full-port parity gaps behind
   slice-local "no blocker" claims.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:574`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:578`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1028`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
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

The exact pre-root gate matched active no-argument root PID `3559071` owned by
`claude` during the audit. A later exact gate returned no rows, and the final
pre-commit gate saw focused rclone/syncthing PID `3576829` before it exited. A
post-commit handoff gate then found active no-argument root PID `3581473`, owned
by `claude`:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3581473 claude 3581423  00:47   R+   php tools/run-tests.php
```

No aggregate root run was started by this audit because the stability gate still
failed: active writer/status/agent processes remained, the worktree was broadly
dirty, `HEAD` advanced during the audit, and manifest/status values changed
during audit sampling.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, upstream runners,
root harnesses, and focused PHP loops first. Then validate all manifests from
the frozen tree, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP fields, separate slice-local blockers from
full-port blockers, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit, and only then run the
no-argument root harness if the exact duplicate-root gate remains empty across
two polls.
