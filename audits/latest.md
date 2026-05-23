# Independent Audit - 2026-05-23T20:33:15Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every
`lanes/*/lane-status.json`, recent Git history, worktree state, process state,
and the required root-harness duplicate gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, copied oracle
fixtures, and shell-outs are treated as non-progress unless explicitly
temporary oracle tooling.

`jq empty` passed for every lane manifest, every lane-status file, and
`porting-summary.json`.

## Current Snapshot

`HEAD` moved while this audit was reading the tree, from sampled
`5be7f5838d00` to `cda272740108`. The latest sampled worktree had `3402`
`git status --short --untracked-files=all` rows, `250` tracked dirty rows, and
`250 files changed, 99815 insertions(+), 12696 deletions(-)`.

The required exact root-harness gate was checked before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

It returned no rows in the main samples, so there was no active PID to owner
sample. I still did not start `php tools/run-tests.php` because the tree was
not stable enough: active lane agents and status/control loops were present,
`HEAD` moved during the audit, and the dirty aggregate was broad enough that a
root result would not be comparable to the starting state.

Process sampling showed active writers/status/control loops despite
`progress.md:25` documenting a two-lane-plus-auditor target and
`progress.md:31` through `progress.md:42` reporting all lanes stopped. The
sample included all primary lane agents plus dashboard/evaluator/watchdog,
capacity controller/executor, integrator, and auditor loops; `tmux ls` reported
`95` sessions.

Current manifest/status sample versus the published dashboard:

| Lane | Current manifest mapped / denominator | Current lane PHP/status count | Published dashboard mapped / denominator |
| --- | ---: | ---: | ---: |
| difftastic | 348 / prose `696` artifacts | 346 | 160 / 417 |
| dolt | 610 / prose runner-status string | 332 PASS cases | 242 / 613 |
| esbuild | 288 / prose `2567` entry points | 288 | 164 / 2567 |
| gitoxide | 2684 / 2877 | 5306 assertions | 1432 / 2877 |
| libsqlite | 271 / 1589 | 271 | 149 / 1454 |
| LightningCSS | 1705 / 3532 | 2090 assertions | 773 / 3532 |
| markerPDF | 262 / 313 static units | 396 | 159 / 78 |
| pandoc | 912 / prose `2276` artifacts | 258 | 426 / 2028 |
| quadrable | 55 / prose `55` paths plus scenarios | 177 | 55 / 55 |
| rclone | 638 / 1601 | 638 | 291 / 327 |
| readability | 1984 / 1984 upstream Mocha tests | 187 native PHP tests | 1031 / 1984 |
| syncthing | 525 / 658 | 3799 assertions | 235 / 658 |

## Findings

1. **Critical - active writers and a moving `HEAD` still block a trustworthy
   aggregate baseline.**
   - Paths: `progress.md:25`, `progress.md:31` through `progress.md:42`,
     `scripts/run-team-watchdog.sh`, `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-capacity-executor-queue.sh`, and sampled
     `.tmux-team` lane-agent invocations.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:48`, and `goal.md:49` require capped supervision, small
     reviewable tested commits, integration cleanup, and honest repo-wide
     verification.
   - Evidence: `HEAD` moved from `5be7f5838d00` to `cda272740108` during this
     audit; the tree had `250` tracked dirty rows and almost `100k` inserted
     lines in diff; process sampling showed primary lane agents plus
     dashboard/evaluator/watchdog/capacity/integrator/auditor loops; `tmux ls`
     reported `95` sessions. A root result captured here would be a moving
     aggregate, not an accepted checkpoint.

2. **Critical - the public coordination surface is stale and contradicts the
   current manifests/status files.**
   - Paths: `porting.html:30` through `porting.html:36`,
     `porting.html:54` through `porting.html:65`,
     `porting-summary.json:2` through `porting-summary.json:8`,
     `porting-summary.json:113` through `porting-summary.json:127`, and
     `porting-summary.json:163` through `porting-summary.json:178`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:44`,
     `goal.md:45`, and `goal.md:52` require current tracking, a visible
     dashboard, and per-lane denominator/mapped/PHP/status/commit fields.
   - Evidence: `porting.html` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while sampled `HEAD` is `cda272740108`. Dashboard rows
     disagree with current manifests for nearly every lane, including rclone
     `291 / 327` versus current `638 / 1601`, markerPDF `159 / 78` versus
     current `262 / 313`, and Syncthing `235 / 658` versus current `525 / 658`
     plus `3799` PHP assertions.

3. **High - near-complete or high-confidence lane claims are tied to
   uncommitted dirty batches, not accepted implementation commits.**
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
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, committed
     handoffs, and integration cleanup before assigning more work.
   - Evidence: every sampled lane `latestCommit` is pending, uncommitted, or
     dirty-batch prose. Recent history after implementation commit `b75226d1`
     is dominated by audit/status/integration-hold commits; `git rev-list`
     reported `104` commits after that point, with no accepted lane
     implementation commit visible in the latest sampled log.

4. **High - manifest denominator and PHP-count schemas remain non-normalized,
   which makes dashboard percentages mathematically weak.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2208`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/syncthing/lane-status.json:5` through `:6`, and
     `lanes/gitoxide/lane-status.json:5` through `:6`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:37`, `goal.md:38`, and `goal.md:45` require real upstream
     denominators, upstream tests as source of truth, and comparable dashboard
     fields.
   - Evidence: `benchmarkDenominator.total` mixes integers, prose artifact
     inventories, and a Dolt latest-runner status paragraph. PHP counts mix
     behavior tests, assertions, and PASS cases: Gitoxide reports `5306`
     assertions, Syncthing reports `3799` assertions, Dolt reports `332` PASS
     cases, and Readability reports `187` native PHP tests while the manifest
     maps `1984 / 1984` upstream Mocha tests.

5. **High - markerPDF and Readability still overstate native parity from
   static, supplied, copied, or oracle evidence.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:357`,
     `lanes/markerpdf/lane-status.json:5` through `:6`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14` through `:15`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:40` through `:41`, and
     `lanes/readability/lane-status.json:5` through `:6`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40` require native standard-PHP ports,
     bridge/generated/shell-out non-progress handling, meaningful fixture
     parity, and explicit hard-feature gaps.
   - Evidence: markerPDF counts `262 / 313` static behavior/reference units
     with `runnerStatus` `not-executed` while the lane status says native PHP
     maps supplied/plan-only boundaries without running Python/model/PDF
     dependencies. Readability records the full upstream Mocha suite as mapped
     and copied all Mozilla fixtures, but current native PHP coverage is still
     `187` focused tests; that is useful fixture evidence, not full native
     parity.

6. **Medium - blocker fields still mix slice-local green checks with full-port
   parity gaps.**
   - Paths: `lanes/dolt/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:12`,
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/libsqlite/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/readability/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:31`, `goal.md:35`, and
     `goal.md:40` require precise blockers, parity beyond passing local
     tests, and explicit hard-feature gaps.
   - Evidence: blockers begin with "No ... blocker" while the same fields
     disclose pending root verification, unexecuted full upstream runners,
     excluded live-provider/model/server paths, or broad remaining parity
     gaps. Slice-local status needs to be separated from full-port blockers.

## Test Gate

I did not run `php tools/run-tests.php`.

The exact duplicate-root gate returned no rows in the main samples, but the
tree was not stable enough for a trustworthy root run because active
writer/status/control loops persisted, `HEAD` moved during sampling, and the
dirty aggregate remained broad. Running a no-argument root harness here would
not satisfy the goal's stable integration requirement.

## Next Intervention

Freeze active lane agents, dashboard/evaluator/auditor/integrator loops,
capacity jobs, broad upstream runners, and duplicate focused/root PHP
harnesses. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize denominator/mapped/PHP/runner/commit
fields, split slice-local blockers from full-port blockers, regenerate
`progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from
the same accepted commit, and only then run the no-argument root harness if the
exact duplicate-root gate is empty across two polls.
