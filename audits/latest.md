# Independent Audit - 2026-05-23T19:44:00Z

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

`HEAD` moved during this audit from `a7737548696e` through `594761e25fd8` to
the final pre-commit sample `0ad089231d86` (`Record integration hold status`).
The branch sample reported `main...origin/main [ahead 500, behind 68]`. Recent history is
still audit/status dominated: the nearest sampled non-audit/status
implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`), 84 sampled audit/status commits behind `HEAD`.

The worktree is not quiescent. Latest samples reported `2983`
`git status --short --untracked-files=all` rows, `235` tracked dirty rows, and
`235 files changed, 92626 insertions(+), 12288 deletions(-)`.

Process sampling ranged from `36` to `65` active repo worker/status/test-control
matches and from `86` to `88` tmux sessions, while `progress.md:25` still
documents a launch target of two implementation lanes plus one auditor and
`progress.md:31` through `progress.md:42` still report every lane as `stopped`.

Manifest values changed while this audit was reading them. Examples:

```text
markerPDF: 308 / 257 mapped became 309 / 258 mapped
Dolt:      606 mapped became 607 mapped
difftastic: 336 mapped became 338 mapped and the prose denominator became 657
LightningCSS: 1615 mapped became 1617 mapped
gitoxide: 2666 mapped became 2670 mapped
pandoc: 873 mapped became 881 mapped
```

The required exact pre-root gate initially returned no rows. A later required
gate matched an active no-argument root harness, which had exited by the final
sanity sample:

```text
3965438 php tools/run-tests.php
```

Owner evidence:

```text
3965438 claude 3965281 00:14 R+ php tools/run-tests.php
```

I did not run `php tools/run-tests.php`.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 338 / prose `657` artifacts | 338 pass | 160 / 417 | 160 / 0 |
| dolt | 607 / no top-level numeric total; inventory says 613 executable files | 329 pass | 242 / 613 | 193 / 0 |
| esbuild | 280 / 2,567 | 280 pass | 164 / 2,567 | 164 / 0 |
| gitoxide | 2670 / 2877 | 5239 assertions/status pass count | 1432 / 2877 | 2646 / 0 |
| libsqlite | 267 / 1589 | 267 pass | 149 / 1454 | 149 / 0 |
| LightningCSS | 1617 / 3532 | 1995 assertions/status pass count | 773 / 3532 | 906 / 0 |
| markerPDF | 258 / 309 static behavior/reference units | 391 pass | 159 / 78 | 264 / 0 |
| pandoc | 881 / prose `2276` artifacts | 253 pass | 426 / 2028 | 164 / 0 |
| quadrable | 55 / prose `55` paths plus 34 scenarios | 173 pass | 55 / 55 | 108 / 0 |
| rclone | 615 / 1601 | 620 pass in lane status; manifest still says 615 mapped | 291 / 327 | 291 / 0 |
| readability | 1984 / 1984 upstream Mocha tests | 184 native PHP behavior tests | 1031 / 1984 | 107 / 0 |
| syncthing | 500 / 658 | 3606 assertions/status pass count | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and test loops still block a trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and sampled
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped
     supervision, current owner/session tracking, committed reviewable slices,
     integration cleanup, and honest repo-wide test evidence.
   - Evidence: active process/tmux counts contradict the stopped-lane table and
     documented cap. Manifests changed during this audit, and a later exact
     pre-root gate found no-argument root PID `3965438` owned by `claude`, so
     starting another aggregate run would duplicate live test activity and test
     a moving dirty aggregate.

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
     `0ad089231d86`. The table still has one combined `Mapped` column instead
     of separate upstream denominator, mapped-test, and PHP pass/fail columns.
     Current manifest rows disagree materially, for example rclone is now
     `615 / 1601` while the dashboard says `291 / 327`, and markerPDF is now
     `258 / 309` while the dashboard says `159 / 78`.

3. **High - manifest denominator and PHP-count schemas remain non-normalized,
   so percentages are not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:31`,
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
     units, assertions, and prose strings. Dolt has no stable top-level numeric
     `total`. Difftastic and Quadrable store the denominator as long prose.
     Readability claims `1984 / 1984` mapped while lane status records only
     `183` native PHP behavior tests. Gitoxide, LightningCSS, and Syncthing PHP
     pass fields are assertion counts, while other lanes use behavior-test
     counts.

4. **High - near-complete lane estimates are tied to unaccepted dirty batches,
   not committed verified slices.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4` and
     `lanes/dolt/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` and
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:4` and
     `lanes/syncthing/lane-status.json:13`, and
     `lanes/markerpdf/lane-status.json:4` and
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
   - Evidence: markerPDF is marked `98%` even though the lane records `0`
     committed Python tests, no full upstream runner execution, and many
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

The required exact pre-root gate initially returned no rows, but the tree was
not stable enough for a trustworthy aggregate run. A later required gate found
active no-argument root PID `3965438`, owned by `claude`, running
`php tools/run-tests.php`; that process had exited by the final sanity sample.
Active repo processes/tmux sessions and broad dirty worktree movement persisted
throughout the audit.

## Next Intervention

Freeze active lane agents, status publishers, dashboard/evaluator/auditor/
integrator loops, capacity jobs, Dolt/BATS runners, and duplicate focused/root
PHP harnesses first. Then validate manifests from the frozen tree, accept or
reject dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/commit fields, separate slice-local blockers from full-port blockers,
regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane
statuses from the same accepted commit, and only then run the no-argument root
harness if the exact duplicate-root gate remains empty across two polls.
