# Independent Audit - 2026-05-23T18:27:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle fixtures,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `e90a82ee20a4` (`Refresh independent audit status`). The latest
57 commits after `b75226d1` are audit-only refresh commits; the nearest recent
implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2648`
`git status --short --untracked-files=all` rows, `228` tracked dirty rows, and
`228 files changed, 107743 insertions(+), 9398 deletions(-)`.

I did not run `php tools/run-tests.php`. The required pre-root gate was active:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3344860 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

Owner evidence:

```text
3344860 claude 3344622 00:47 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

A post-commit sanity gate at `2026-05-23T18:30:00Z` again matched active
focused PHP harness PID `3378080`; a second transient PID `3393405` exited
before owner sampling:

```text
3378080 php tools/run-tests.php lanes/syncthing/tests
```

Owner evidence:

```text
3378080 claude 3316888 01:05 Rs php tools/run-tests.php lanes/syncthing/tests
```

A later final gate at `2026-05-23T18:30:37Z` matched active no-argument root
harness PID `3397989`:

```text
3397989 php tools/run-tests.php
```

Owner evidence:

```text
3397989 claude 3397939 00:09 R+ php tools/run-tests.php
```

Process sampling found `65` matching repo worker/status/test processes,
including dashboard, watchdog, evaluator, capacity, integrator, auditor, Dolt,
all primary lane-agent processes, broad Dolt BATS activity, and the focused PHP
harness above while `progress.md` still reports every lane as `stopped`.

Current manifests and the published dashboard disagree:

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 324 / 627 | 160 / 417 |
| dolt | 601 / 613 executable test files, embedded in prose | 242 / 613 |
| esbuild | 272 / 2,567 | 164 / 2,567 |
| gitoxide | 2,569 / 2,877 | 1,432 / 2,877 |
| libsqlite | 260 / 1,589 | 149 / 1,454 |
| lightningcss | 1,512 / 3,532 | 773 / 3,532 |
| markerPDF | 251 / 303 | 159 / 78 |
| pandoc | 825 / 2,276 | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 579 / 1,601 | 291 / 327 |
| readability | 1,984 / 1,984, but only 177 native PHP behavior tests | 1,031 / 1,984 |
| syncthing | 472 / 658 | 235 / 658 |

## Findings

1. **Critical - active harnesses and live writers block any trustworthy root
   baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the required duplicate/root-test gate matched PID `3344860`
     owned by `claude` running `php tools/run-tests.php lanes/rclone/tests
     lanes/syncthing/tests`. The worktree had `228` tracked dirty rows and over
     `107k` diff insertions. Process sampling found `65` active repo-matching
     worker/status/test processes while the progress table still says every
     lane is stopped.
   - Impact: a no-argument root run from this auditor would duplicate active
     test work and would not produce an accepted aggregate baseline from a
     stable snapshot.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:32` through `porting.html:33`,
     `porting.html:41` through `porting.html:50`, `porting.html:57`,
     `porting.html:60`, `porting.html:63`, `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: both published files still advertise generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `e90a82ee20a4`.
   - Evidence: current manifests disagree with the dashboard for every lane
     except Quadrable. Severe examples: markerPDF is now `251 / 303`, but the
     dashboard says `159 / 78`; rclone is `579 / 1601`, but the dashboard says
     `291 / 327`; Gitoxide is `2569 / 2877`, but the dashboard says `1432 /
     2877`.
   - Evidence: the HTML table still combines PHP pass/fail and mapped coverage
     into one `Mapped` cell and lacks separate upstream-denominator and PHP
     pass/fail columns, despite the explicit column list in `goal.md:45`.

3. **High - `progress.md` is not a reliable supervisor coordination source.**
   - Paths: `progress.md:14`, `progress.md:15`, `progress.md:25`, and
     `progress.md:31` through `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires accurate active lanes,
     owner/session state, blockers, next task, and percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two implementation
     lanes plus one auditor, but live process sampling found all primary lane
     agents plus dashboard/evaluator/capacity/integrator/auditor processes.
     `progress.md:31` through `progress.md:42` report all lanes as `stopped`
     with estimates from `5%` to `66%`, while current lane-status files report
     estimates from `81%` to `99%` and pending aggregate verification.
   - Impact: the coordination file cannot safely drive capacity, acceptance, or
     next-lane decisions until regenerated from a frozen, accepted snapshot.

4. **High - dirty, unaccepted lane batches are being counted as progress.**
   - Paths: `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`, `lanes/pandoc/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:13`, `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:31`, and `goal.md:48`
     require small reviewable committed slices, precise blockers, test
     verification, progress updates, cleanup, and next-task reassignment.
   - Evidence: every sampled lane has `pending`, `uncommitted`, dirty-worktree,
     or stale-HEAD prose in `latestCommit`. The most recent 57 commits before
     the nearest implementation commit are audit refresh commits, not accepted
     lane implementation commits.
   - Impact: focused lane tests are useful local evidence, but they do not
     satisfy the goal's accepted native progress bar until isolated, root-gated
     where appropriate, committed, and reflected in a regenerated dashboard from
     the same commit.

5. **High - manifest denominator, mapped-count, and PHP-count schemas remain
   non-comparable and internally stale.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:610`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2166`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:680`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1179`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: Difftastic reports `mapped: 324` over a `627` artifact prose
     denominator, while its warning still says `321` focused lane tests over
     `623` artifacts. Dolt stores `benchmarkDenominator.total` as a rolling
     prose log whose first number is a timestamp, while its warning still says
     native PHP maps `321` focused behavior tests even though current status
     says `323` PHP passes and the manifest says `601` mapped. Readability
     reports `mapped: 1984` against `total: 1984`, but current status says only
     `177` PHP behavior tests. Syncthing reports `mapped: 472`, but its warning
     still says `462` focused lane checks.
   - Impact: the published percentages mix upstream files, test functions,
     fixtures, local behavior tests, local assertions, copied fixtures,
     plan-only evidence, and runner logs. The average progress number is not
     auditable.

6. **High - near-complete lane percentages still understate full-parity
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` through
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` through
     `lanes/pandoc/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say passing tests are not enough, unresolved upstream runners and hard
     features must be blockers or future slices, and edge-case/error behavior
     matters.
   - Evidence: Difftastic, Gitoxide, libsqlite, markerPDF, Pandoc, Readability,
     Syncthing, Quadrable, and rclone now report `97%` to `99%` in lane-status
     sources while also documenting unexecuted full upstream runners, pending
     root aggregate verification, large remaining protocol/provider/model
     gaps, or copied-fixture/API mapping boundaries.
   - Audit judgment: "No local blocker" may be true for a focused slice, but the
     status model must separate slice-local blockers from full-port blockers
     before these percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate before any possible root run is:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

This gate matched active PHP harness PID `3344860` owned by `claude`:

```text
3344860 claude 3344622 00:47 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

The final post-commit sanity gate still matched active focused Syncthing PID
`3378080` owned by `claude`:

```text
3378080 claude 3316888 01:05 Rs php tools/run-tests.php lanes/syncthing/tests
```

A later final gate also matched active no-argument root PID `3397989` owned by
`claude`:

```text
3397989 claude 3397939 00:09 R+ php tools/run-tests.php
```

The tree was also not stable enough for an accepted aggregate run: live writers
were active, the progress table contradicted process state, and the worktree had
`228` tracked dirty rows.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops.
Then validate every manifest from the frozen tree, normalize manifest/status
denominator, mapped, PHP pass/fail, runner, blocker, and commit fields, accept
or reject dirty lane batches one lane at a time, regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the same accepted snapshot, and
only then capture one quiesced `php tools/run-tests.php` root run if the exact
pre-root gate remains empty.
