# Independent Audit - 2026-05-23T18:21:35Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle fixtures,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `2a8db938557c` (`Refresh independent audit status`). The latest
56 sampled commits are audit-only refresh commits; the nearest recent
implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2628`
`git status --short --untracked-files=all` rows, `228` tracked dirty rows, and
`228 files changed, 107267 insertions(+), 9414 deletions(-)`.

I did not run `php tools/run-tests.php`. The required pre-root gate was not
clear:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
3289994 php tools/run-tests.php lanes/markerpdf/tests
```

Owner evidence:

```text
3289994 claude 3265292 00:02 Rs php tools/run-tests.php lanes/markerpdf/tests
```

Process sampling found `59` matching repo worker/status/test processes,
including dashboard, watchdog, evaluator, capacity, integrator, auditor, Dolt,
and lane-agent processes while `progress.md` still reports every lane as
`stopped`.

| Lane | Current manifest mapped / denominator | Published dashboard mapped / denominator |
| --- | ---: | ---: |
| difftastic | 321 / 623 | 160 / 417 |
| dolt | 600 / 613 executable test files, embedded in prose | 242 / 613 |
| esbuild | 272 / 2,567 | 164 / 2,567 |
| gitoxide | 2,179 / 2,877 | 1,432 / 2,877 |
| libsqlite | 260 / 1,589 | 149 / 1,454 |
| lightningcss | 1,505 / 3,532 | 773 / 3,532 |
| markerPDF | 250 / 302 | 159 / 78 |
| pandoc | 825 / 2,276 | 426 / 2,028 |
| quadrable | 55 / 55 | 55 / 55 |
| rclone | 579 / 1,601 | 291 / 327 |
| readability | 1,984 / 1,984, but only 176 native PHP behavior tests | 1,031 / 1,984 |
| syncthing | 472 / 658 | 235 / 658 |

## Findings

1. **Critical - active harnesses and live writers still block any trustworthy
   root baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json:12` through `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the required pre-root gate matched active PHP harness PID
     `3289994` owned by `claude`. The tree had `228` tracked dirty rows and
     over `107k` diff insertions. Process sampling found `59` active
     repo-matching worker/status/test processes while the progress table still
     says every lane is stopped.
   - Impact: a root run from this auditor would duplicate active test work and
     would not produce an accepted aggregate baseline from a stable snapshot.

2. **Critical - `porting.html` and `porting-summary.json` still fail the
   dashboard freshness and column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:65`, and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with upstream denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit per lane.
   - Evidence: both published files still advertise generated time
     `2026-05-23 04:57:16 UTC` and source snapshot `bda83c6b93d4`, while
     sampled `HEAD` is `2a8db938557c`.
   - Evidence: current manifests disagree with the dashboard for every lane
     except Quadrable. Severe examples: markerPDF is now `250 / 302`, but the
     dashboard says `159 / 78`; rclone is `579 / 1601`, but the dashboard says
     `291 / 327`; LightningCSS is `1505 / 3532`, but the dashboard says `773 /
     3532`.
   - Evidence: the HTML table still combines PHP pass/fail and mapped coverage
     into one `Mapped` cell and lacks separate upstream-denominator and PHP
     pass/fail columns, despite the goal's explicit column list.

3. **High - `progress.md` is no longer usable as the supervisor coordination
   source.**
   - Paths: `progress.md:14`, `progress.md:25`, and `progress.md:31` through
     `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires accurate active lanes,
     owner/session state, blockers, next task, and percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two implementation
     lanes plus one auditor, but live process sampling found all primary lane
     agents plus dashboard/evaluator/capacity/integrator/auditor processes.
     `progress.md:31` through `progress.md:42` report all lanes as `stopped`
     with estimates from `5%` to `66%`, while lane-status files now report
     estimates from `81%` to `99%` and active pending root verification.
   - Impact: the coordination file cannot safely drive capacity or acceptance
     decisions until regenerated from a frozen, accepted snapshot.

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
     require small reviewable committed slices, precise blockers, and repo-wide
     test failures recorded honestly.
   - Evidence: every sampled lane has `pending`, `uncommitted`, dirty-worktree,
     or stale `HEAD` prose in `latestCommit`. The most recent 56 sampled
     commits are audit-only refreshes, not accepted lane implementation
     commits.
   - Impact: focused lane tests are useful local evidence, but they do not
     satisfy the goal's accepted native progress bar until isolated, root-gated,
     committed, and reflected in a regenerated dashboard from the same commit.

5. **High - manifest denominator, mapped-count, and PHP-count schemas remain
   non-comparable and sometimes internally stale.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2163`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:32`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:680`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:686`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:1179`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, and comparable dashboard fields.
   - Evidence: Dolt stores `benchmarkDenominator.total` as a rolling prose log
     whose first number is a timestamp, while its warning still says native PHP
     maps `321` focused behavior tests even though current status says `322`
     PHP passes and `600` mapped behaviors. Syncthing reports `mapped: 472`,
     but its manifest warning still says `462` focused lane checks. Readability
     reports `mapped: 1984` against `total: 1984`, but its own warning says the
     native PHP layer maps only `176` local behavior tests and `2455`
     assertions.
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
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say passing tests are not enough, hard features must not be silently
     skipped, and unresolved binary formats/runners must be blockers or future
     slices.
   - Evidence: Difftastic, Gitoxide, libsqlite, rclone, Readability, Syncthing,
     Pandoc, Quadrable, and markerPDF now report `98%` or `99%`-level lane
     status in at least one status source while documenting unexecuted full
     upstream runners, pending root aggregate verification, large remaining
     protocol/runner/provider/model gaps, or plan-only/live-service boundaries.
   - Audit judgment: "No local blocker" may be true for the current focused
     slice, but the status model must separate slice-local blockers from
     full-parity blockers before these percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate before any possible root run is:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

This gate matched active PHP harness PID `3289994` owned by `claude`:

```text
3289994 claude 3265292 00:02 Rs php tools/run-tests.php lanes/markerpdf/tests
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
