# Independent Audit - 2026-05-23T19:27:39Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
sampled `lanes/*/lane-status.json`, recent Git history, current worktree state,
tmux/process state, and the required pre-root PHP harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` was `f7af2933330c` (`Record integration hold status`). The branch sample
reported `main...origin/main [ahead 493, behind 68]`. Recent history remains
audit/status/integration dominated: the latest sampled commit changed only
`audits/integration-status.md`, and the nearest recent implementation commit
found by subject remains `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`), more than 75 commits behind `HEAD`.

The worktree is still not quiescent. Latest samples reported `2896`
`git status --short --untracked-files=all` rows, `232` tracked dirty rows, and
`232 files changed, 90244 insertions(+), 12165 deletions(-)`.

Process sampling found `23` active repo worker/status/test-control matches and
`82` tmux sessions, while `progress.md:25` still documents a launch target of
two implementation lanes plus one auditor and `progress.md:31` through
`progress.md:42` still report every lane as `stopped`.

The required exact pre-root gate matched active PHP harnesses:

```text
3859772 php tools/run-tests.php lanes/syncthing/tests
3860362 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
3860986 php tools/run-tests.php lanes/markerpdf/tests
```

Owner evidence was captured before two exited:

```text
3859772 claude 3765172 55 Rs php tools/run-tests.php lanes/syncthing/tests
```

I did not run `php tools/run-tests.php`.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 334 / prose `645` artifacts | 334 pass | 160 / 417 | 160 / 0 |
| dolt | 606 / no top-level numeric total; inventory says 613 executable files | 327 pass | 242 / 613 | 193 / 0 |
| esbuild | 279 / prose `2,567` entry points | 279 pass | 164 / 2,567 | 164 / 0 |
| gitoxide | 2588 / 2877 | 5117 assertions/status pass count | 1432 / 2877 | 2646 / 0 |
| libsqlite | 266 / 1589 | 266 pass | 149 / 1454 | 149 / 0 |
| lightningcss | 1602 / 3532 | 1977 pass | 773 / 3532 | 906 / 0 |
| markerPDF | 257 / 308 | 389 pass | 159 / 78 | 264 / 0 |
| pandoc | 865 / prose `2276` artifacts | 251 pass | 426 / 2028 | 164 / 0 |
| quadrable | 55 / prose `55` paths/scenarios | 172 pass | 55 / 55 | 108 / 0 |
| rclone | 609 / 1601 | 609 pass | 291 / 327 | 291 / 0 |
| readability | 1984 / 1984 upstream JS tests | 182 native PHP behavior tests | 1031 / 1984 | 107 / 0 |
| syncthing | 488 / 658 | 3538 assertions/status pass count | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers/test loops still block any trustworthy aggregate
   baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, `scripts/run-capacity-controller-loop.sh`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped
     supervision, committed verified slices, current owner/session tracking,
     cleanup, and honest repo-wide test evidence.
   - Evidence: the active process/tmux sample contradicts the stopped-lane
     table and documented cap. The exact duplicate-root gate also matched active
     `php tools/run-tests.php` processes, so starting a root run would duplicate
     live test activity and test a moving dirty aggregate.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the required dashboard contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`, `porting.html:54` through
     `porting.html:65`, and `porting-summary.json:2` through
     `porting-summary.json:18`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes generated time `2026-05-23
     04:57:16 UTC` and source commit `bda83c6b93d4` while current `HEAD` is
     `f7af2933330c`. The table has one combined `Mapped` column instead of the
     separate required denominator, mapped-test, and PHP pass/fail fields. Rows
     disagree sharply with current manifests, for example rclone now reports
     `609 / 1601` mapped while the dashboard says `291 / 327`.

3. **High - manifest denominator and status schemas remain non-normalized, so
   portfolio percentages are not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:23`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:39`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:18`, and
     `lanes/*/lane-status.json:4` through `lanes/*/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, mapped upstream tests, meaningful parity, and comparable
     dashboard fields.
   - Evidence: denominators mix runnable tests, files, fixture pairs, parser
     corpora, source/config boundaries, assertions, and prose strings. Dolt has
     no top-level numeric `total`. Difftastic's `total` is prose. Readability
     marks `1984 / 1984` mapped while lane status records only `182` native PHP
     behavior tests. Some lane `phpPass` fields are tests and others are
     assertions.

4. **High - near-complete percentages are being assigned to unaccepted dirty
   lane batches.**
   - Paths: `lanes/dolt/lane-status.json:4` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`,
     and `lanes/libsqlite/lane-status.json:4` through
     `lanes/libsqlite/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small reviewable commits, passing verification, and
     cleanup before assigning the next task.
   - Evidence: sampled lane statuses claim `95` to `99` percent progress while
     `latestCommit` says `pending`, `uncommitted`, or explicitly leaves commit
     selection to the supervisor/integrator. Focused lane tests are useful
     handoff evidence, but they are not accepted repo progress until isolated,
     reviewed, root-gated where appropriate, and committed.

5. **High - plan-only or supplied-boundary evidence is still being counted too
   aggressively as native implementation progress.**
   - Paths: `lanes/markerpdf/lane-status.json:4` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, `goal.md:35`, and
     `goal.md:40` require native ports, no bridge/generated progress credit,
     meaningful fixture parity, and explicit hard-feature gaps.
   - Evidence: markerPDF is marked `97%` even though the lane itself records
     `0 committed Python tests`, no full upstream runner execution, and many
     model/Python/service workflows as plan-only or supplied-boundary native
     behavior. That can be valuable inventory, but it should not carry the same
     progress weight as native parser/converter parity.

6. **Medium - blocker language still mixes slice-local "green" status with
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
   - Evidence: statuses often start with "No focused blocker" or "No PHP
     blocker" while the same field records pending root verification and major
     full-suite/provider/model gaps. Slice-local health and full-port blockers
     need separate fields before progress estimates can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate matched active PHP harnesses, including owner
evidence for PID `3859772` as user `claude`. The tree also failed the stability
gate because active repo processes/tmux sessions persisted and the dirty
worktree remained broad.

## Next Intervention

Freeze active lane agents, status publishers, dashboard/evaluator/auditor/
integrator loops, capacity jobs, Dolt/BATS runners, and duplicate focused/root
PHP harnesses first. Then validate manifests from the frozen tree, accept or
reject dirty lane batches one lane at a time, normalize denominator/mapped/PHP/
runner/commit fields, separate slice-local blockers from full-port blockers,
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate remains empty across two polls.
