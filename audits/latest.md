# Independent Audit - 2026-05-23T19:11:11Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled `lanes/*/lane-status.json`,
`audits/integration-status.md`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `030bb33f5d16` (`Refresh independent audit status`). Branch
state was `main...origin/main [ahead 486, behind 68]`. The newest sampled
history remains status/audit/integration dominated: the last 40 commits are not
accepted lane implementation commits, and the nearest sampled implementation
commit remains `b75226d1` (`Port rclone OneDrive Object.Update upload
selection`), 71 commits behind `HEAD`.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json` passed at this sample.

## Current Snapshot

The tree is still not quiescent. Latest samples reported `2828`
`git status --short --untracked-files=all` rows, `231` tracked dirty rows, and
`231 files changed, 88513 insertions(+), 12100 deletions(-)`. Process sampling
found `55` repo worker/status/test-control processes. Active lane agents,
dashboard updater, team watchdog, evaluator, capacity controller, Dolt runner,
auditor, and integrator paths were visible even though `progress.md:31` through
`progress.md:42` still report every lane as `stopped` and `progress.md:25`
documents a target of two implementation lanes plus one auditor.

I did not run `php tools/run-tests.php`. The required exact pre-root gate first
returned an active focused harness:

```text
3639926 php tools/run-tests.php lanes/markerpdf/tests
```

That process exited before direct owner sampling, so `ps -o pid,user,ppid,etimes,stat,args -p 3639926`
returned only the header row. Later exact gates returned no rows, but the tree
was still not stable enough for a trustworthy root run.

Current manifest sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 332 / prose `642` artifacts | lane-status says 329 pass | 160 / 417 | 160 / 0 |
| dolt | 604 / prose run-log denominator | manifest `phpBehaviorTests` 326 | 242 / 613 | 193 / 0 |
| esbuild | 277 / prose `2,567` entry points | 277 behavior tests | 164 / 2,567 | 164 / 0 |
| gitoxide | 2585 / 2877 | lane-status says 5105 assertions | 1432 / 2877 | 2646 / 0 |
| libsqlite | 265 / 1589 | lane-status says 265 pass | 149 / 1454 | 149 / 0 |
| lightningcss | 1576 / 3532 | lane-status says 1872 assertions | 773 / 3532 | 906 / 0 |
| markerPDF | 255 / 307 | 388 behavior tests | 159 / 78 | 264 / 0 |
| pandoc | 859 / prose `2276` artifacts | lane-status says 250 tests | 426 / 2028 | 164 / 0 |
| quadrable | 55 / prose `55` paths/scenarios | lane-status says 171 pass | 55 / 55 | 108 / 0 |
| rclone | 604 / 1601 | manifest `phpBehaviorTests` 604, lane-status says 600 | 291 / 327 | 291 / 0 |
| readability | 1984 / 1984 upstream JS tests | 181 native PHP behavior tests | 1031 / 1984 | 107 / 0 |
| syncthing | 483 / 658 | lane-status says 3473 assertions | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and the dirty moving tree still block any
   trustworthy aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `audits/integration-status.md:13` through
     `audits/integration-status.md:38`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped
     supervision, committed verified slices, current owners/sessions, cleanup,
     and honest repo-wide test evidence.
   - Evidence: the sampled process surface still shows active worker/status/test
     control paths while the progress table says all lanes are stopped. The
     dirty tree has `2828` status rows, `231` tracked dirty rows, and `231 files
     changed, 88513 insertions(+), 12100 deletions(-)`.
   - Impact: a no-argument root run now would either duplicate other test
     ownership or test a moving, unaccepted aggregate. It would not establish
     the stable baseline required by the goal.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`, and `porting.html:54`
     through `porting.html:65`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: the HTML still publishes generated time `2026-05-23 04:57:16
     UTC` and snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `030bb33f5d16`. The table still has one `Mapped` column that combines PHP
     pass/fail with mapped coverage instead of separate required columns.
   - Evidence: every dashboard row sampled is stale. Examples: rclone is
     currently `604 / 1601` while the dashboard says `291 / 327`; markerPDF is
     currently `255 / 307` with `388` PHP tests while the dashboard says
     `159 / 78` and `264`; Syncthing is currently `483 / 658` while the
     dashboard says `235 / 658`.

3. **High - manifests still mix incompatible denominator units, so portfolio
   percentages are not auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:695` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:699`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, mapped upstream tests, PHP pass/fail counts, meaningful
     parity, and comparable dashboard fields.
   - Evidence: Difftastic counts mixed Rust tests, golden pairs, fixture pairs,
     parser corpus files, highlight queries, source/config boundaries, and
     very large blob metadata as one denominator. Dolt's `total` is a long
     chronological runner log. Pandoc counts files and artifacts. Quadrable
     counts tracked paths plus scenario prose. Readability marks `1984 / 1984`
     upstream JS tests mapped while recording only `181` native PHP behavior
     tests.
   - Impact: the average progress number mixes tests, assertions, files,
     fixtures, runner anecdotes, copied oracles, and plan-only evidence.

4. **High - manifest and lane-status records are internally inconsistent before
   dashboard generation even starts.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:585`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:7`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and
     `goal.md:45` require precise blockers, current status, and reliable
     per-lane denominator/mapped/PHP fields.
   - Evidence: markerPDF's manifest says `total: 307`, `mapped: 255`, but the
     same manifest's `totalDescription` still says `306` units, and lane status
     still says `306` denominator with `254` mapped. rclone's manifest says
     `mapped: 604` while lane status still says native PHP maps `600` behavior
     tests and `phpPass: 600`.
   - Impact: lane-local metadata cannot yet be used as a normalized source of
     truth for `progress.md`, `porting.html`, or integration decisions.

5. **High - dirty, unaccepted lane batches are being counted as near-complete
   progress.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:4` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, and
     `goal.md:48` require committed reviewable slices, no bridge/generated
     progress credit, verification, cleanup, and next-task reassignment.
   - Evidence: sampled lane statuses claim `95` to `99` percent progress while
     their `latestCommit` fields say `pending`, `uncommitted`, or
     dirty-worktree handoff prose. Recent Git history shows 71 commits since
     the nearest sampled implementation commit, and the latest 40 sampled
     commits are status/audit/integration records.
   - Impact: focused tests are useful handoff evidence, but the portfolio
     should not count them as accepted native-port progress until isolated,
     reviewed, root-gated where appropriate, committed, and regenerated into
     the dashboard from the same accepted commit.

6. **High - blocker language still hides full-port parity gaps behind
   slice-local "no blocker" claims.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` say blockers must be recorded precisely, passing tests are
     not enough, and hard features must not be silently skipped.
   - Evidence: lane status often says "No current blocker" for the latest PHP
     slice while the same text admits root verification is pending and full
     upstream/provider/model runners remain unexecuted. Examples include full
     Gitoxide cargo workspace parity, markerPDF Python/model/benchmark
     execution, rclone live provider/mount parity, and Syncthing full
     `go test ./...`.
   - Audit judgment: slice-local blocker status must be separated from
     full-port blocker status before percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The first exact duplicate-root gate returned active focused markerPDF PID
`3639926`:

```text
3639926 php tools/run-tests.php lanes/markerpdf/tests
```

The process exited before direct owner sampling. Later exact duplicate-root
gates returned no rows, but no aggregate root run was started because active
writers/status loops persisted, the worktree was broadly dirty, dashboard
artifacts were stale, lane metadata was inconsistent, and recent history was
not an accepted implementation checkpoint.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, upstream runners,
root harnesses, focused PHP loops, dashboard/evaluator/auditor/integrator
loops, and Dolt runner activity first. Then validate all manifests from the
frozen tree, accept or reject dirty lane batches one lane at a time, normalize
denominator/mapped/PHP/runner/commit fields, separate slice-local blockers from
full-port blockers, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit, and only then run the
no-argument root harness if the exact duplicate-root gate remains empty across
two polls.
