# Independent Audit - 2026-05-23T11:26:42Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Initial sampling saw `HEAD` at `3a42b2d8` (`Refresh independent audit status`).
Recent history reviewed includes `3a42b2d8`, `29f817eb`, `53588555`,
`ae8aadcf`, `3c042169`, `5dddc1ed`, `b529b1ee`, `c9254a88`, `0319eb91`,
`64f06d33`, `3227da76`, `ab141f82`, `873879be`, `5f2ae4bd`, `37f77f2e`,
`64e9fcf1`, `6c135b81`, `24837bc2`, `f03f1473`, and `d656fc47`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:301`-`306`, `porting.html:32`-`65`,
     `porting-summary.json:2`-`8`, all `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:1` requires a supervised tmux workflow
     for native ports; `goal.md:20` requires capped parallelism and an auditor;
     `goal.md:29` requires small reviewable slices with passing tests;
     `goal.md:44` requires current owner/session state; `goal.md:49` requires
     honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` marks
     all 12 lanes `stopped`. Process sampling instead found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator,
     integrator, auditor, primary lane agents, focused PHP capacity agents, and
     lane agents for Pandoc, Dolt, libsqlite, Readability, esbuild,
     LightningCSS, rclone, markerPDF, Difftastic, Gitoxide, Syncthing,
     Quadrable, and a Dolt runner.
   - Evidence: the dirty tree is broader than the coordination files can
     describe. Latest samples reported `1334` default `git status --short`
     rows, `143` tracked changed files, and
     `143 files changed, 36653 insertions(+), 2644 deletions(-)`.
   - Audit judgment: current percentages, blockers, latest commits, root-test
     anecdotes, and dashboard rows should not be accepted until writers and
     status publishers are frozen and one regenerated snapshot is validated.

2. **High - the public dashboard and JSON summary are stale and do not satisfy
   the required status contract.**
   - Paths: `porting.html:32`-`65`, `porting-summary.json:2`-`76`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`5` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while sampled `HEAD` is
     `3a42b2d8a0f4`.
   - Evidence: `porting.html:41`-`50` collapses benchmark source/upstream
     denominator into `Benchmark`, and PHP pass/fail/mapped tests into
     `Mapped`, instead of the separate columns required by `goal.md:45`.
   - Evidence: current manifest totals disagree with the published dashboard.
     Examples: Difftastic dashboard `160/417` at `porting.html:54` vs current
     `247/587` at `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`;
     Gitoxide `1432/2877` at `porting.html:57` vs current `1891/2877` at
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`15`; rclone `291/327`
     at `porting.html:63` vs current `426/2553` at
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`; Syncthing `235/658`
     at `porting.html:65` vs current `317/658` in the manifest.

3. **High - root-test evidence is non-comparable, and this run was blocked by
   an active root harness first, then by the unstable tree.**
   - Paths: `tools/run-tests.php`, `progress.md:301`-`306`,
     `lanes/*/lane-status.json`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:49`, and `goal.md:52`
     require passing tests to be tied to accepted slices and visible progress.
   - Evidence: the required pre-root gate returned active PHP harnesses:

     ```text
     3339320 php tools/run-tests.php lanes/lightningcss/tests/... lanes/readability/tests/ArticleExtractorTest.php
     3339417 php tools/run-tests.php
     ```

     Owner evidence for the exact aggregate root process:

     ```text
     3339417 claude 3313770 21 Rs php tools/run-tests.php
     ```

     The focused PID exited before owner sampling.
   - Evidence: lane records still contradict one another. Dolt's current
     manifest records a root failure outside Dolt with `218` files, `24980`
     assertions, and `51` LightningCSS failures at
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:27`-`28`; Syncthing's status
     records a green root run with `217` files and `25059` assertions at
     `lanes/syncthing/lane-status.json:10` and `:13`; libsqlite records root
     pending because PID `3336966` was active; several other statuses report
     pending, green, or uncommitted batches from different snapshots.
   - Evidence: a later exact duplicate-root sample was clear, but active
     writer/status processes and broad dirty state still made the tree
     unsuitable for a trustworthy root run.
   - Audit judgment: do not use any root result observed in the lane/status
     files as an accepted baseline until one quiesced root run is captured from
     a single frozen tree. I did not start a duplicate root harness.

4. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     all `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting.html`, and `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real upstream denominators,
     comparable mapped-test/PHP pass-fail counts, current session state, and
     explicit blockers.
   - Evidence: `benchmarkDenominator.total` is still prose in multiple
     manifests rather than a normalized number, including Difftastic
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`), Dolt
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`), Pandoc
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`), and Quadrable.
   - Evidence: `benchmarkDenominator.runnerStatus` is inconsistent: object in
     Difftastic, Dolt, esbuild, libsqlite, LightningCSS, rclone, Readability,
     and Syncthing; string in Gitoxide, markerPDF, and Quadrable; absent/null
     in Pandoc.
   - Evidence: lane `latestCommit` fields are not accepted commit IDs. Current
     status files include `pending`, `uncommitted`, `not committed`, "working
     tree pending commit", and prose such as Syncthing's "pending lane-local
     folder scan API request queue batch" at
     `lanes/syncthing/lane-status.json:13`.

5. **Medium - bounded runner, static inventory, and oracle/CLI evidence are
   still over-mixed with native progress.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and related lane statuses.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as the source of truth, meaningful
     parity, and generated/bridge/shell-out evidence to be excluded from native
     progress unless explicitly temporary oracle tooling.
   - Evidence: Dolt's denominator prose mixes executable file counts, BATS
     cases, Go test functions, direct CLI probes, runner-only evidence, native
     mappings, and a failing root harness in one narrative
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`28`). Gitoxide explicitly
     says full workspace Cargo was not executed
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`-`22`). Pandoc remains a
     static inventory, not Haskell runner parity
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`17`). Syncthing records
     many focused Go shards but still no `go test ./...` parity in its manifest.
   - Audit judgment: these are useful slice-level signals, but portfolio
     progress needs separate fields for static inventory, focused upstream
     runner pass, temporary oracle/CLI probe, native PHP behavior count,
     assertions, failures, and accepted commit.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed:

```text
3339320 php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php ... lanes/readability/tests/ArticleExtractorTest.php
3339417 php tools/run-tests.php
```

Owner evidence:

```text
3339417 claude 3313770 21 Rs php tools/run-tests.php
```

A later exact duplicate-root sample returned no rows, but the tree was still not
stable enough for a root run because active writer/status processes persisted
and the dirty snapshot remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
ps -o pid,user,ppid,etimes,stat,args -p 3339417,3339320
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. The latest
dirty-tree samples reported `1334` default status rows, `143` tracked changed
files, and `143 files changed, 36653 insertions(+), 2644 deletions(-)`.

Recent history reviewed:

```text
3a42b2d8 Refresh independent audit status
29f817eb Port Syncthing folder scan API queue
53588555 Refresh independent audit status
ae8aadcf Port rclone OneDrive ListP error propagation
3c042169 Advance libsqlite replacement planning
5dddc1ed Refresh independent audit status
b529b1ee Port rclone OneDrive child ListP pagination
c9254a88 Refresh independent audit status
0319eb91 Record active root harness audit evidence
64f06d33 Refresh independent audit status
3227da76 Port rclone OneDrive ListR pagination slices
ab141f82 Refresh independent audit status
873879be Advance libsqlite auto-vacuum pointer maps
5f2ae4bd Refresh independent audit status
37f77f2e readability: record tmz lane status
64e9fcf1 Refresh independent audit status
6c135b81 readability: map tmz legacy post envelope
24837bc2 difftastic: stamp highlight status
f03f1473 difftastic: map parser highlight captures
d656fc47 Refresh independent audit status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty
lane batches one lane at a time, normalize manifest/status denominator, mapped,
PHP pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
