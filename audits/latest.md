# Independent Audit - 2026-05-23T11:38:28Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Initial sampling saw `HEAD` at `bdce6ef25bd5` (`Port rclone OneDrive ListP
cache slice`). Before commit handoff, `HEAD` moved through `613b4eff` to
`8b0f5af1edb5` (`Update pandoc lane status commit marker`), then to
`a1ba6d6bb3cd` (`libsqlite: add secure delete page free planning`). Recent
history reviewed includes `a1ba6d6b`, `8b0f5af1`, `613b4eff`, `bdce6ef2`, `fabad4ea`,
`2514e22e`, `3a42b2d8`, `29f817eb`, `53588555`, `ae8aadcf`, `3c042169`,
`5dddc1ed`, `b529b1ee`, `c9254a88`, `0319eb91`, `64f06d33`, `3227da76`,
`ab141f82`, `873879be`, `5f2ae4bd`, `37f77f2e`, `64e9fcf1`, `6c135b81`,
`24837bc2`, `f03f1473`, and `d656fc47`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:301`-`307`, `porting.html:32`-`65`,
     `porting-summary.json:2`-`213`, all `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:20` requires a capped supervised tmux
     workflow with one auditor; `goal.md:29` requires small reviewable slices
     with passing tests; `goal.md:44` requires current owner/session state;
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is 2
     implementation lanes plus 1 auditor, and `progress.md:31`-`42` marks all
     12 lanes `stopped`. Process sampling instead found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator,
     primary lane agents for Pandoc, Difftastic, Syncthing, Quadrable,
     libsqlite, Readability, Gitoxide, LightningCSS, Dolt, esbuild, rclone,
     plus the auditor, integrator, Dolt runner, and capacity jobs. A broader
     sample matched 75 active repo worker/Codex processes.
   - Evidence: the dirty tree is broader than the coordination files can
     describe. Latest samples reported `1364` default `git status --short`
     rows, `141` tracked changed files, and
     `141 files changed, 37251 insertions(+), 2757 deletions(-)`.
   - Evidence: `HEAD` moved during this audit from `bdce6ef2` through
     `613b4eff` and `8b0f5af1` to `a1ba6d6b`, while the latest committed
     audit and progress entries only described earlier commits and stale
     dirty-tree counts.
   - Audit judgment: percentages, blockers, latest commits, root-test
     anecdotes, and dashboard rows should not be accepted until writers and
     status publishers are frozen and one regenerated snapshot is validated.

2. **High - the public dashboard and JSON summary are stale and do not satisfy
   the required status contract.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:2`-`213`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while latest sampled `HEAD`
     is `a1ba6d6bb3cd`.
   - Evidence: `porting.html:41`-`50` still collapses benchmark
     source/upstream denominator into `Benchmark`, and PHP pass/fail/mapped
     tests into `Mapped`, instead of separate columns required by
     `goal.md:45`.
   - Evidence: current manifest/status counts disagree with the published
     dashboard. Difftastic dashboard shows `160 / 417` at `porting.html:54`,
     current manifest shows `250 / 587` at
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and lane status
     shows `247` PHP passes at `lanes/difftastic/lane-status.json:6`. Rclone
     dashboard shows `291 / 327` at `porting.html:63`, while current manifest
     shows `426 / 2553` at `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
     Syncthing dashboard shows `235 / 658` at `porting.html:65`, while current
     manifest/status show `320 / 658` and `320` PHP passes at
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`-`15` and
     `lanes/syncthing/lane-status.json:6`.

3. **High - root-test evidence is still non-comparable, and this run did not
   create a valid root baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:301`-`307`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/libsqlite/lane-status.json:10`-`13`,
     `lanes/pandoc/lane-status.json:10`-`13`,
     `lanes/syncthing/lane-status.json:10`-`13`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, `goal.md:49`, and
     `goal.md:52` require passing tests to be tied to accepted native slices
     and visible progress.
   - Evidence: the required pre-root gate initially returned:

     ```text
     3356750 php tools/run-tests.php
     ```

     A direct owner sample raced the process exit and returned only the `ps`
     header. Current Syncthing status records the same PID with owner evidence
     at `lanes/syncthing/lane-status.json:10` and repeats the active-PID
     blocker at `lanes/syncthing/lane-status.json:12`-`13`.
   - Evidence: a later exact duplicate-root sample returned no rows, but I did
     not start a root run because the stability gate failed: active writer and
     status processes persisted and the dirty tree remained broad. During
     handoff validation, a new exact root harness appeared as PID `3366300`
     owned by `claude`; a later exact gate cleared after that PID exited, then
     another exact root harness appeared as PID `3374447` owned by `claude`.
   - Evidence: lane records still contradict each other. Gitoxide's manifest
     says a root PHP run passed `218` files and `25196` assertions at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`; Pandoc says a different
     root run passed `216` files and `24927` assertions at
     `lanes/pandoc/lane-status.json:10`-`12`; libsqlite records root pending
     due to PID `3336966` at `lanes/libsqlite/lane-status.json:10`-`13`; and
     Syncthing records root pending due to PID `3356750` at
     `lanes/syncthing/lane-status.json:10`-`13`.
   - Audit judgment: none of these root anecdotes should be used as an
     accepted baseline until one quiesced root run is captured from a single
     frozen tree.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real upstream denominators,
     comparable mapped-test/PHP pass-fail counts, current session state, and
     explicit blockers.
   - Evidence: `benchmarkDenominator.total` remains prose in multiple
     manifests rather than a normalized number, including Difftastic
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`), Dolt
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`), esbuild
     (`lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`), Pandoc
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`).
   - Evidence: `runnerStatus` is not normalized: object in Difftastic/Dolt and
     several others, string in Gitoxide and Quadrable
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`), and simple
     `not-executed` in markerPDF
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:256`).
   - Evidence: lane `latestCommit` fields are not accepted commit IDs.
     Examples include `pending`, `uncommitted`, `not committed`,
     `working tree pending commit`, and stale prose such as
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - bounded runner, static inventory, and oracle/CLI evidence remain
   over-mixed with native implementation progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and related lane statuses.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as source of truth, meaningful parity,
     and generated/bridge/shell-out evidence to be excluded from native
     progress unless explicitly temporary oracle tooling.
   - Evidence: Dolt's denominator prose mixes executable file counts, BATS
     cases, Go test functions, runner-only slices, direct CLI probes, native
     mappings, and skipped full parity in one field
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`). The current Dolt status
     then says the newest schema-tag/copy-tags entry is runner-only and no PHP
     implementation files changed (`lanes/dolt/lane-status.json:10`-`13`).
   - Evidence: Gitoxide's `runnerStatus` simultaneously records bounded
     runner evidence, a root PHP pass, and a full Cargo non-run in one prose
     field (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`). Pandoc remains
     static inventory with no Haskell runner parity
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`), and markerPDF's
     full upstream runner is still `not-executed`
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:256`).
   - Audit judgment: these are useful slice signals, but portfolio progress
     needs separate fields for static inventory, focused upstream runner pass,
     temporary oracle/CLI probe, native PHP behavior count, assertions,
     failures, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Initial observation:

```text
3356750 php tools/run-tests.php
```

Direct owner sampling with `ps -o pid,user,lstart,etime,args -p 3356750`
returned no process rows because the harness exited before sampling. Current
Syncthing lane status independently records the same PID as owned by `claude`
at `lanes/syncthing/lane-status.json:10` and `:12`. Later exact duplicate-root
samples briefly returned no rows, but the tree was still not stable enough for
a root run. A final handoff sample then found a new active exact root harness:

```text
3366300 php tools/run-tests.php
```

Owner evidence:

```text
3366300 claude 3366299 00:36 R php tools/run-tests.php
```

A later handoff sample found another active exact root harness:

```text
3374447 php tools/run-tests.php
```

Owner evidence:

```text
3374447 claude 3357793 00:19 Rs php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
pgrep -af 'run-(team-watchdog|capacity-controller|dashboard-updater|evaluator|integrator|auditor)|scripts/run-tmux-agent\.sh port-|tools/run-tests\.php'
git log --oneline --decorate -n 30
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. Latest
dirty-tree samples reported `1364` default status rows, `141` tracked changed
files, and `141 files changed, 37251 insertions(+), 2757 deletions(-)`. A
handoff exact duplicate-root sample found active root PID `3366300` owned by
`claude`; a later exact gate cleared after that PID exited, but active
writer/status processes persisted. A later handoff sample found active root
PID `3374447` owned by `claude`.

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
