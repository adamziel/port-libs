# Independent Audit - 2026-05-23T18:33:52Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, current worktree state, and
process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle fixtures,
and shell-outs are treated as non-progress unless explicitly temporary oracle
tooling.

Sampled `HEAD`: `79083d426638` (`Refresh independent audit status`). The latest
58 commits after `b75226d1` are all audit-only refresh commits; the nearest
recent implementation commit is `b75226d1` (`Port rclone OneDrive Object.Update
upload selection`).

`jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
porting-summary.json` passed at this sample.

## Current Snapshot

The tree is not quiescent. Latest samples reported `2677`
`git status --short --untracked-files=all` rows, `230` tracked dirty rows, and
`230 files changed, 108936 insertions(+), 9425 deletions(-)`.

I did not run `php tools/run-tests.php`. The required pre-root gate was clear at
the audit sample:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no rows, but the stability gate still failed. Process sampling found
`28` matching repo worker/status/test processes, including dashboard updater,
team watchdog, evaluator, capacity controller, Dolt runner, all primary lane
agents, auditor agent, and broad Dolt BATS activity. `progress.md` still reports
every lane as `stopped`.

Current manifests and the published dashboard disagree:

| Lane | Current manifest mapped / denominator | Current lane PHP | Published dashboard mapped / denominator | Published PHP |
| --- | ---: | ---: | ---: | ---: |
| difftastic | 326 / 630 prose artifacts | 324 / 0 | 160 / 417 | 160 / 0 |
| dolt | 601 / prose field with 613 embedded | 323 / 0 | 242 / 613 | 193 / 0 |
| esbuild | 273 / 2,567 | 273 / 0 | 164 / 2,567 | 164 / 0 |
| gitoxide | 2,569 / 2,877 | 4,994 / 0 | 1,432 / 2,877 | 2,646 / 0 |
| libsqlite | 261 / 1,589 | 261 / 0 | 149 / 1,454 | 149 / 0 |
| lightningcss | 1,522 / 3,532 | 1,721 / 0 | 773 / 3,532 | 906 / 0 |
| markerPDF | 252 / 304 | 384 / 0 | 159 / 78 | 264 / 0 |
| pandoc | 831 / 2,276 prose artifacts | 246 / 0 | 426 / 2,028 | 164 / 0 |
| quadrable | 55 / 55 prose paths | 169 / 0 | 55 / 55 | 108 / 0 |
| rclone | 584 / 1,601 | 584 / 0 | 291 / 327 | 291 / 0 |
| readability | 1,984 / 1,984, but only 177 native PHP behavior tests | 177 / 0 | 1,031 / 1,984 | 107 / 0 |
| syncthing | 475 / 658 | 3,371 / 0 | 235 / 658 | 235 / 0 |

## Findings

1. **Critical - live writers and broad dirty state block any trustworthy root
   baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`, and
     `lanes/*/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:20` requires supervised parallelism
     capped to VM capacity; `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require committed, verified slices and honest repo-wide test recording.
   - Evidence: the exact pre-root gate returned no rows, but the stability gate
     failed with 28 active repo worker/status/test processes, broad Dolt BATS
     work, `230` tracked dirty rows, and over `108k` diff insertions. The
     progress table still says every lane is stopped.
   - Impact: a no-argument root run from this auditor would be an aggregate test
     of a moving, unaccepted tree, not an accepted baseline.

2. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30` through `porting.html:65` and
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with separate upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit per
     lane.
   - Evidence: both files still publish generated time `2026-05-23 04:57:16
     UTC` and source snapshot `bda83c6b93d4`, while sampled `HEAD` is
     `79083d426638`.
   - Evidence: current manifests disagree with the dashboard for every lane
     except Quadrable's mapped count. Severe examples: markerPDF is now
     `252 / 304`, but the dashboard says `159 / 78`; rclone is `584 / 1601`,
     but the dashboard says `291 / 327`; Gitoxide is `2569 / 2877`, but the
     dashboard says `1432 / 2877`.
   - Evidence: the HTML table still combines PHP pass/fail and mapped coverage
     into one `Mapped` cell and lacks separate upstream-denominator and PHP
     pass/fail columns.

3. **High - `progress.md` is not a reliable supervisor coordination source.**
   - Paths: `progress.md:14`, `progress.md:15`, `progress.md:25`, and
     `progress.md:31` through `progress.md:42`.
   - Goal requirement at risk: `goal.md:44` requires accurate active lanes,
     owner/session state, blockers, next task, and percentage estimates.
   - Evidence: `progress.md:25` documents a launch target of two implementation
     lanes plus one auditor, but live process sampling found all primary lane
     agents plus dashboard/evaluator/capacity/auditor processes. The active
     lane table reports all lanes as `stopped` with estimates from `5%` to
     `66%`, while current lane-status files report `82%` to `99%`.
   - Impact: capacity and acceptance decisions cannot safely use `progress.md`
     until it is regenerated from a frozen accepted snapshot.

4. **High - dirty, unaccepted lane batches are being counted as progress.**
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:31`, and `goal.md:48`
     require small reviewable committed slices, precise blockers, test
     verification, progress updates, cleanup, and next-task reassignment.
   - Evidence: every sampled lane has `pending`, `uncommitted`, dirty-worktree,
     or stale-HEAD prose in `latestCommit`. Recent Git history confirms the
     last 58 commits after the nearest implementation commit are audit-only
     refresh commits.
   - Impact: focused lane tests are useful evidence, but they do not satisfy the
     accepted native progress bar until isolated, root-gated where appropriate,
     committed, and reflected in a regenerated dashboard from the same commit.

5. **High - manifest denominator, mapped-count, and PHP-count schemas remain
   non-comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14` and
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:30`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, and
     `lanes/readability/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:45`
     require real upstream denominators, mapped upstream tests, PHP pass/fail
     counts, meaningful fixture parity, and comparable dashboard fields.
   - Evidence: some `total` fields are numbers, while Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable use prose strings. Readability reports
     `mapped: 1984` against `total: 1984`, but current lane-status says only
     `177` native PHP behavior tests. Dolt stores the latest slice log in
     `benchmarkDenominator.total`, with the actual `613` denominator embedded
     inside prose.
   - Impact: the average progress number mixes upstream files, test functions,
     fixtures, local behavior tests, local assertions, copied fixtures,
     plan-only evidence, and runner logs. It is not auditable as one metric.

6. **High - near-complete lane percentages still understate full-parity
   blockers.**
   - Paths: `lanes/difftastic/lane-status.json:4` and
     `lanes/difftastic/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:4` and
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:4` and
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:4` and
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/pandoc/lane-status.json:4` and
     `lanes/pandoc/lane-status.json:12`,
     `lanes/rclone/lane-status.json:4` and
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:4` and
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:4` and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and `goal.md:40`
     say passing tests are not enough, unresolved upstream runners and hard
     features must be blockers or future slices, and edge-case/error behavior
     matters.
   - Evidence: several lanes report `97%` to `99%` while also documenting
     pending root aggregate verification, unexecuted full upstream runners,
     live-provider or model/tooling gaps, or broad unported protocol/provider
     behavior.
   - Audit judgment: "No local blocker" may be true for a focused slice, but
     the status model must separate slice-local blockers from full-port blockers
     before these percentages can be trusted.

## Test Gate

I did not run `php tools/run-tests.php`.

The required gate before any possible root run is:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no rows at the audit sample. I still did not start a no-argument
root harness because the tree was not stable enough: active writer/test/status
processes were present, broad Dolt BATS was active, the progress table
contradicted process state, and the worktree had `230` tracked dirty rows.

## Next Intervention

Freeze active lane agents, status publishers, capacity jobs, and broad upstream
runners first. Then validate all manifests from the frozen tree, accept or
reject dirty lane batches one lane at a time, normalize denominator/mapped/PHP
fields, regenerate `progress.md`, `porting.html`, and `porting-summary.json`
from the same accepted commit, and only then run the no-argument root harness if
the exact duplicate-root gate remains empty.
