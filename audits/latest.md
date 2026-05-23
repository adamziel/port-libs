# Independent Audit - 2026-05-23T19:23:05Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, sampled `lanes/*/lane-status.json`,
recent Git history, current worktree state, tmux/process state, and the required
pre-root PHP harness gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, copied oracle fixtures, and
shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD` moved during this audit from `54a1600947ac` through
`d465350eb484` to `735150f1d9aa` (all `Record integration hold status`). Branch
state was previously sampled as `main...origin/main [ahead 488, behind 68]`.
The newest sampled history remains audit/status/integration dominated: the
latest `75` commits after the nearest sampled implementation commit were not
accepted lane implementation commits; the nearest sampled implementation commit
remains `b75226d1` (`Port rclone OneDrive Object.Update upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json` passed.

## Current Snapshot

The repository is still not quiescent. Latest samples reported `2877`
`git status --short --untracked-files=all` rows, `234` tracked dirty rows, and
`234 files changed, 90006 insertions(+), 12323 deletions(-)`.

Process sampling reported `69` to `72` repo worker/status/test-control matches and
`75` tmux sessions. Active primary lane agents, capacity jobs, dashboard
updater, team watchdog, evaluator, integrator, auditor, and Dolt runner activity
were visible even though `progress.md:25` still documents a launch target of
two implementation lanes plus one auditor, and `progress.md:31` through
`progress.md:42` still report every lane as `stopped`.

The required exact pre-root gate matched active PHP harnesses during the audit:

```text
3699734 claude 3639107 52s Rs php tools/run-tests.php lanes/syncthing/tests
3768795 claude 3742620 27s Rs php tools/run-tests.php
```

I did not run `php tools/run-tests.php`. A later exact gate returned no rows,
then a pre-commit gate briefly matched focused Readability PID `3818786`
(`php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php`)
before it exited ahead of owner sampling, and a final exact gate returned no
rows again. The stability gate still failed. A broad Dolt BATS runner was also
active under `claude` ownership, with parent PID `3646714` and child BATS PIDs
`3646748`, `3646749`, `3646756`, `3646757`, and `3646758`.

Current manifest sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current PHP evidence | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 332 / prose `642` artifacts | lane-status says 332 pass | 160 / 417 | 160 / 0 |
| dolt | 605 / no top-level numeric total; prose says 613 executable files | lane-status says 327 pass | 242 / 613 | 193 / 0 |
| esbuild | 278 / prose `2,567` entry points | lane-status says 278 pass | 164 / 2,567 | 164 / 0 |
| gitoxide | 2585 / 2877 | lane-status says 5105 assertions | 1432 / 2877 | 2646 / 0 |
| libsqlite | 265 / 1589 | lane-status says 265 pass | 149 / 1454 | 149 / 0 |
| lightningcss | 1576 / 3532 | lane-status says 1899 pass | 773 / 3532 | 906 / 0 |
| markerPDF | 255 / 307 | lane-status says 389 pass | 159 / 78 | 264 / 0 |
| pandoc | 859 / prose `2276` artifacts | lane-status says 250 pass | 426 / 2028 | 164 / 0 |
| quadrable | 55 / prose `55` paths/scenarios | lane-status says 172 pass | 55 / 55 | 108 / 0 |
| rclone | 605 / 1601 | lane-status says 605 pass | 291 / 327 | 291 / 0 |
| readability | 1984 / 1984 upstream JS tests | 181 native PHP behavior tests | 1031 / 1984 | 107 / 0 |
| syncthing | 483 / 658 | lane-status says 3473 assertions | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - active writers and broad dirty state still block a trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `.tmux-team/prompts/*`, `scripts/run-team-watchdog.sh`,
     `scripts/run-dashboard-updater-loop.sh`, `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, and `goal.md:49` require capped
     supervision, committed verified slices, current owner/session tracking,
     cleanup, and honest repo-wide test evidence.
   - Evidence: process sampling saw `69` to `72` active repo worker/status/test-control
     matches and `75` tmux sessions, while the active-lane table still says all
     lanes are stopped. The dirty tree has `2876` status rows and `234`
     tracked dirty files.
   - Impact: a root run in this state would test a moving, unaccepted aggregate
     rather than a stable checkpoint.

2. **Critical - `porting.html` is stale and still fails the required dashboard
   column contract.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:41` through `porting.html:50`, and `porting.html:54`
     through `porting.html:65`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: the HTML still publishes generated time `2026-05-23 04:57:16
     UTC` and snapshot `bda83c6b93d4`, while sampled `HEAD` reached
     `735150f1d9aa`. The table still has a single `Mapped` column that
     combines PHP pass/fail with mapped coverage instead of separate required
     fields.
   - Evidence: dashboard rows disagree with current manifests. Examples:
     markerPDF is now `255 / 307` while the dashboard says `159 / 78`;
     rclone is now `605 / 1601` while the dashboard says `291 / 327`;
     Syncthing is now `483 / 658` while the dashboard says `235 / 658`.

3. **High - manifests still mix incompatible denominator units, making
   portfolio percentages non-auditable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:22`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, mapped upstream tests, meaningful parity, and comparable
     dashboard fields.
   - Evidence: Difftastic counts tests, golden pairs, fixture pairs, parser
     corpora, source/config boundaries, and blob metadata as one denominator.
     Dolt has no top-level numeric `total` despite prose saying 613 executable
     files. Pandoc counts files and artifacts rather than runnable tests.
     Quadrable counts tracked paths plus scenarios. Readability marks
     `1984 / 1984` mapped while lane status records only `181` native PHP
     behavior tests.
   - Impact: the displayed average progress still mixes tests, assertions,
     files, fixtures, runner anecdotes, copied oracle material, and plan-only
     evidence.

4. **High - dirty, unaccepted lane batches are being counted as near-complete
   progress.**
   - Paths: `lanes/dolt/lane-status.json:4` through
     `lanes/dolt/lane-status.json:13`,
     `lanes/rclone/lane-status.json:4` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/readability/lane-status.json:4` through
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:4` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:30`, and
     `goal.md:48` require small committed reviewable slices, no generated or
     bridge progress credit, verification, cleanup, and next-task assignment.
   - Evidence: sampled lane statuses claim `95` to `99` percent progress while
     `latestCommit` fields say `pending`, `uncommitted`, or explicitly leave
     commit selection to the supervisor/integrator. The tree simultaneously has
     `231` tracked dirty files.
   - Impact: focused tests are useful handoff evidence, but the portfolio
     should not count these batches as accepted native-port progress until they
     are isolated, reviewed, root-gated where appropriate, committed, and
     regenerated into the dashboard from the same accepted commit.

5. **High - blocker language still hides full-port parity gaps behind
   slice-local "no blocker" claims.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:17`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local tests,
     and explicit hard-feature gaps.
   - Evidence: several lane statuses say no focused PHP blocker while the same
     text records pending root verification and full upstream/provider/model
     gaps. Examples include full Dolt Go/BATS/server/cloud suites, rclone live
     provider and mount parity, Syncthing full `go test ./...`, and Quadrable's
     full sync-fuzzer/syncBench gap.
   - Audit judgment: slice-local status must be separated from full-port
     blocker status before percentages can be trusted.

6. **Medium - root-test ownership remains ambiguous even when the exact root
   gate is clear briefly.**
   - Paths: `progress.md:25`, `lanes/dolt/lane-status.json:10` through
     `lanes/dolt/lane-status.json:12`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:20` and `goal.md:49` require capped
     coordination and periodic repo-wide tests with honest failure recording.
   - Evidence: the required exact gate first found active focused Syncthing PID
     `3699734`, then a final pre-commit gate found active no-argument root PID
     `3768795`. Broad Dolt BATS was active at the same time. Multiple lane
     statuses record that root verification is pending for someone else, while
     capacity and focused runners keep starting.
   - Impact: root evidence is hard to attribute, easy to duplicate, and not
     tied to a frozen tree.

## Test Gate

I did not run `php tools/run-tests.php`.

The required exact pre-root gate matched active PHP harnesses owned by `claude`:

```text
3699734 claude 3639107 52s Rs php tools/run-tests.php lanes/syncthing/tests
3768795 claude 3742620 27s Rs php tools/run-tests.php
```

Even when a final exact sample returned no rows, the stability gate failed:
active writer/status/test-control processes persisted, a transient focused
Readability PHP run appeared during pre-commit validation, broad Dolt BATS was
running, `75` tmux sessions were present, and the tree remained broadly dirty.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, upstream runners,
root harnesses, focused PHP loops, dashboard/evaluator/auditor/integrator loops,
and Dolt runner activity first. Then validate all manifests from the frozen
tree, accept or reject dirty lane batches one lane at a time, normalize
denominator/mapped/PHP/runner/commit fields, separate slice-local blockers from
full-port blockers, regenerate `progress.md`, `porting.html`, and any summary
artifacts from the same accepted commit, and only then run the no-argument root
harness if the exact duplicate-root gate remains empty across two polls.
