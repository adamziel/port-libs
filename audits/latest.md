# Independent Audit - 2026-05-23T19:01:32Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled
`lanes/*/lane-status.json`, `audits/integration-status.md`, recent Git history,
current worktree state, and process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `3edf1fcae884` (`Record integration hold status`). Branch state
was `main...origin/main [ahead 483, behind 68]`. The nearest recent
implementation commit found in the sampled history remains `b75226d1` (`Port
rclone OneDrive Object.Update upload selection`), 67 commits behind `HEAD`; the
intervening commits sampled are audit/status/integration records, not accepted
lane implementation commits.

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json` passed at this sample.

## Current Snapshot

The tree is still not quiescent. Latest samples reported `2804`
`git status --short --untracked-files=all` rows, `231` tracked dirty rows, and
`231 files changed, 85972 insertions(+), 10876 deletions(-)`. Process sampling
found `66` repo worker/status/test-control processes plus `67` tmux sessions,
including dashboard updater, team watchdog, evaluator, capacity controller,
integrator, Dolt runner, auditor, primary lane agents, and focused/root-test
control paths. `progress.md:25` still documents a target of two implementation
lanes plus one auditor, and `progress.md:31` through `progress.md:42` still
reports every lane as `stopped`.

I did not run `php tools/run-tests.php`. The required exact pre-root gate:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

returned no rows at the initial sampled gates. A later pre-commit validation
gate returned active no-argument root PID `3620458`, and a concurrent focused
Syncthing PID `3629657`, so no duplicate root run was started. A post-commit
handoff sample briefly returned no-argument root PID `3630255`, which exited
before owner sampling; the final exact gate returned only focused Syncthing PID
`3630018`.

Owner evidence for the active root harness:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3620458 claude 3620344  72      R+   php tools/run-tests.php
```

Final focused-harness owner evidence:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3630018 claude 3582353  56      Rs   php tools/run-tests.php lanes/syncthing/tests
```

Current manifest samples versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 329 / prose `636` artifacts | lane-status says 329 pass | 160 / 417 | 160 / 0 |
| dolt | 604 / prose run-log denominator | manifest `phpBehaviorTests` 322; lane-status says 326 pass | 242 / 613 | 193 / 0 |
| esbuild | 276 / 2,567 entry points | 276 behavior tests | 164 / 2,567 | 164 / 0 |
| gitoxide | 2,579 / 2,877 | lane-status says 5,092 assertions | 1,432 / 2,877 | 2,646 / 0 |
| libsqlite | 264 / 1,589 | lane-status says 264 pass | 149 / 1,454 | 149 / 0 |
| lightningcss | 1,550 / 3,532 | lane-status says 1,872 assertions | 773 / 3,532 | 906 / 0 |
| markerPDF | 254 / 306 | 388 behavior tests | 159 / 78 | 264 / 0 |
| pandoc | 853 / prose `2,276` artifacts | lane-status says 249 tests | 426 / 2,028 | 164 / 0 |
| quadrable | 55 / prose `55` paths/scenarios | lane-status says 171 pass | 55 / 55 | 108 / 0 |
| rclone | 600 / 1,601 | 600 behavior tests | 291 / 327 | 291 / 0 |
| readability | 1,984 / 1,984 upstream JS tests | 180 native PHP behavior tests | 1,031 / 1,984 | 107 / 0 |
| syncthing | 479 / 658 | lane-status says 3,419 assertions | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and the dirty moving tree block any trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `audits/integration-status.md:66` through
     `audits/integration-status.md:81`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small committed verified slices, cleanup, progress updates, and
     repo-wide tests/static checks recorded honestly.
   - Evidence: the sampled process surface still shows `66` active repo
     worker/status/test-control processes and `67` tmux sessions while the
     progress table says all lanes are stopped. The tree has `2804` status rows,
     `231` tracked dirty rows, and `231 files changed, 85972 insertions(+),
     10876 deletions(-)`.
   - Impact: a no-argument root run now would either duplicate the active root
     harness or test a moving, unaccepted tree. It would not satisfy the goal's
     acceptance standard.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `porting-summary.json:11` through `porting-summary.json:25`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: both dashboard files still publish generated time `2026-05-23
     04:57:16 UTC` and source snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `3edf1fcae884`. The HTML still collapses PHP pass/fail and mapped coverage
     into one `Mapped` column instead of the required separate columns.
   - Evidence: every row sampled is stale against current manifests. Examples:
     rclone is currently `600 / 1601` but the dashboard says `291 / 327`;
     markerPDF is currently `254 / 306` with `388` PHP tests but the dashboard
     says `159 / 78` and `264`; Syncthing is currently `479 / 658` but the
     dashboard says `235 / 658`.

3. **High - manifests still mix incompatible denominator and mapped-count
   schemas, so portfolio percentages are not auditable.**
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
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:697`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: Difftastic counts mixed behavior artifacts, fixtures, parser
     corpus files, and source/config boundaries; Dolt's `total` is a long
     run-log string rather than a denominator value; Pandoc counts files and
     artifacts; Quadrable counts tracked paths plus scenario prose; Readability
     maps `1984` upstream JS runner tests while recording only `180` native PHP
     behavior tests.
   - Impact: the dashboard average still mixes test functions, files, fixtures,
     assertions, copied fixtures, runner logs, and plan-only evidence.

4. **High - manifest and lane-status records are internally inconsistent before
   dashboard generation even starts.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2189`,
     `lanes/dolt/lane-status.json:5` through
     `lanes/dolt/lane-status.json:7`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:589`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:45`
     require precise blockers, current status, and reliable per-lane mapped/PHP
     fields.
   - Evidence: Dolt's manifest records `mapped: 604` and
     `nativeImplementation.phpBehaviorTests: 322`, while lane status says `604`
     mappings but `326` PHP passes. markerPDF's manifest records `mapped: 254`
     and `phpBehaviorTests: 388`, while lane status still says `253` mapped
     units and `387` PHP passes.
   - Impact: the lane-local metadata cannot yet serve as a normalized source of
     truth for `progress.md`, `porting.html`, or release decisions.

5. **High - dirty, unaccepted lane batches are being counted as near-complete
   progress.**
   - Paths: `lanes/difftastic/lane-status.json:4` through
     `lanes/difftastic/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:4` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:4` through
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, and `goal.md:48`
     require committed reviewable slices, no generated/bridge progress credit,
     verification, cleanup, and next-task reassignment.
   - Evidence: sampled lane statuses claim `98` to `99` percent progress for
     several lanes while their `latestCommit` fields say `pending`,
     `uncommitted`, or dirty-worktree handoff prose. Recent history also shows
     67 audit/status/integration commits since the nearest implementation commit
     `b75226d1`.
   - Impact: focused tests are useful handoff evidence, but the portfolio should
     not count them as accepted native-port progress until isolated, reviewed,
     root-gated where appropriate, committed, and regenerated into the dashboard
     from the same accepted commit.

6. **High - blocker language still hides full-port parity gaps behind
   slice-local "no blocker" claims.**
   - Paths: `lanes/gitoxide/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say blockers must be recorded precisely, passing tests are not enough, and
     hard features must not be silently skipped.
   - Evidence: lane status often says "No current blocker" for the latest PHP
     slice while the same text admits root verification is pending and full
     upstream/provider/model runners remain unexecuted. Examples include full
     Gitoxide cargo workspace parity, markerPDF Python/model/benchmark
     execution, rclone live provider/mount parity, and Syncthing full
     `go test ./...`.
   - Audit judgment: slice-local blocker status must be separated from full-port
     blocker status before percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The exact duplicate-root gate returned no rows at initial sampled checks, then a
later pre-commit validation gate found active no-argument root PID `3620458`
owned by `claude`:

```text
PID     USER   PPID     ELAPSED STAT COMMAND
3620458 claude 3620344  72      R+   php tools/run-tests.php
```

A post-commit handoff sample briefly returned no-argument root PID `3630255`
before it exited; the final exact gate returned only focused Syncthing PID
`3630018`, owned by `claude`.

No aggregate root run was started by this audit. The root run was also blocked
by stability: active writer/status/agent processes remained, the worktree was
broadly dirty, dashboard artifacts were stale, lane metadata was internally
inconsistent, and recent history showed only audit/status/integration commits
since the last sampled implementation commit.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, upstream runners,
root harnesses, and focused PHP loops first. Then validate all manifests from
the frozen tree, accept or reject dirty lane batches one lane at a time,
normalize denominator/mapped/PHP fields, separate slice-local blockers from
full-port blockers, regenerate `progress.md`, `porting.html`, and
`porting-summary.json` from the same accepted commit, and only then run the
no-argument root harness if the exact duplicate-root gate remains empty across
two polls.
