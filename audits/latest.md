# Independent Audit - 2026-05-23T19:34:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, recent Git history, current worktree state, process
state, tmux session count, and the required pre-root PHP harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` was `e627a6731490` (`Record integration hold status`). The branch sample
reported `main...origin/main [ahead 496, behind 68]`. Recent history is still
audit/status/integration dominated: the nearest sampled implementation commit
is `b75226d1` (`Port rclone OneDrive Object.Update upload selection`), 79
sampled commits behind `HEAD`.

The worktree is not quiescent. Latest samples reported `2917`
`git status --short --untracked-files=all` rows, `235` tracked dirty rows, and
`235 files changed, 90838 insertions(+), 11480 deletions(-)`.

Process sampling found `24` active repo worker/status/test-control matches and
`83` tmux sessions, while `progress.md:25` still documents a launch target of
two implementation lanes plus one auditor and `progress.md:31` through
`progress.md:42` still report every lane as `stopped`.

The required exact pre-root gate first matched a focused PHP harness, later
matched an active no-argument root harness, and the final validation gate still
matched active focused harnesses:

```text
3867414 php tools/run-tests.php lanes/syncthing/tests
3872835 php tools/run-tests.php
3875321 php tools/run-tests.php lanes/syncthing/tests
3888797 php tools/run-tests.php lanes/pandoc/tests lanes/readability/tests
3888821 php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests
3888822 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

Owner evidence:

```text
3872835 claude 3872802 00:11 R php tools/run-tests.php
3875321 claude 3865036 00:55 Rs php tools/run-tests.php lanes/syncthing/tests
3888797 claude 3888591 00:05 R+ php tools/run-tests.php lanes/pandoc/tests lanes/readability/tests
3888821 claude 3888562 00:05 R+ php tools/run-tests.php lanes/libsqlite/tests lanes/lightningcss/tests lanes/quadrable/tests
3888822 claude 3888614 00:05 R+ php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
```

I did not run `php tools/run-tests.php`.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 336 / prose `651` artifacts | 336 pass | 160 / 417 | 160 / 0 |
| dolt | 606 / no top-level numeric total; inventory says 613 executable files | 328 pass | 242 / 613 | 193 / 0 |
| esbuild | 279 / prose `2,567` entry points | 279 pass | 164 / 2,567 | 164 / 0 |
| markerPDF | 257 / 308 static behavior/reference units | 391 pass | 159 / 78 | 264 / 0 |
| rclone | 609 / 1601 | 615 pass in lane status; manifest still says 609 mapped | 291 / 327 | 291 / 0 |
| readability | 1984 / 1984 upstream Mocha tests | 183 native PHP behavior tests | 1031 / 1984 | 107 / 0 |
| syncthing | 494 / 658 | 3573 assertions/status pass count | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and test loops still block a trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and sampled `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped
     supervision, current owner/session tracking, committed reviewable slices,
     cleanup, and honest repo-wide test evidence.
   - Evidence: active process/tmux counts contradict the stopped-lane table and
     documented cap. The required pre-root gate matched active focused PID
     `3867414`, later no-argument root PID `3872835`, and final focused PIDs
     `3875321`, `3888797`, `3888821`, and `3888822`, all owned by `claude`, so
     a no-argument root run would duplicate live test activity and test a
     moving dirty aggregate.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`, `porting.html:54` through
     `porting.html:65`, and `porting-summary.json:2` through
     `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4` while current `HEAD` is
     `e627a6731490`. The table still has one combined `Mapped` column instead
     of separate denominator, mapped-test, and PHP pass/fail columns. Current
     manifest rows disagree materially, for example rclone is now `609 / 1601`
     while the dashboard says `291 / 327`, and markerPDF is now `257 / 308`
     while the dashboard says `159 / 78`.

3. **High - manifest denominator and PHP-count schemas remain
   non-normalized, so percentages are not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:35`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`, and sampled
     `lanes/*/lane-status.json:4` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, meaningful fixture parity, upstream-test source of truth,
     and comparable dashboard fields.
   - Evidence: denominators mix runnable tests, executable files, function
     counts, fixture/golden pairs, source/config boundaries, static behavior
     units, assertions, and prose strings. Dolt has no top-level numeric
     `total`. Difftastic stores the total as a long prose string. Readability
     claims `1984 / 1984` mapped while lane status records only `183` native
     PHP behavior tests. Syncthing and gitoxide PHP pass fields are assertion
     counts, while other lanes use behavior-test counts.

4. **High - near-complete lane estimates are tied to unaccepted dirty batches,
   not committed verified slices.**
   - Paths: `lanes/dolt/lane-status.json:4` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`,
     `lanes/libsqlite/lane-status.json:4` through
     `lanes/libsqlite/lane-status.json:13`, and
     `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small reviewable commits with passing verification
     before moving on.
   - Evidence: sampled lane statuses claim `95` to `99` percent progress while
     `latestCommit` says `pending`, `uncommitted`, or leaves commit selection
     to the supervisor/integrator. Focused lane tests are useful handoff
     evidence, but they are not accepted repo progress until isolated,
     reviewed, root-gated where appropriate, and committed.

5. **High - plan-only and supplied-boundary evidence is still overweighted as
   native implementation progress.**
   - Paths: `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native ports, no bridge/generated
     progress credit, meaningful fixture parity, and explicit hard-feature
     gaps.
   - Evidence: markerPDF is marked `98%` even though the lane itself records
     `0 committed Python tests`, no full upstream runner execution, and many
     Python/model/service workflows as plan-only or supplied-boundary native
     behavior. That is useful inventory, but it should not carry the same
     progress weight as native parser/converter parity against executable
     upstream behavior.

6. **Medium - blocker language still mixes slice-local green checks with
   full-port parity gaps.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: statuses begin with "No ... blocker" while the same field
     records pending root verification, unexecuted full upstream runners, live
     provider/model requirements, or broad parity gaps. Slice-local health and
     full-port blockers need separate fields before estimates can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate first matched active focused PHP harness PID
`3867414`, then a later gate matched active no-argument root harness PID
`3872835`, owned by `claude`, running `php tools/run-tests.php`. The final
validation gate still matched focused PHP harnesses `3875321`, `3888797`,
`3888821`, and `3888822`, all owned by `claude`. The tree also failed the
stability gate because active repo processes/tmux sessions and the broad dirty
worktree persisted.

## Next Intervention

Freeze active lane agents, status publishers, dashboard/evaluator/auditor/
integrator loops, capacity jobs, Dolt/BATS runners, and duplicate focused/root
PHP harnesses first. Then validate manifests from the frozen tree, accept or
reject dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/commit fields, separate slice-local blockers from full-port blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from the same accepted commit, and only then run the no-argument root
harness if the exact duplicate-root gate remains empty across two polls.
