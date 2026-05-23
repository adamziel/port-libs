# Independent Audit - 2026-05-23T11:22:00Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Initial sampling saw `HEAD` at `53588555` (`Refresh independent audit status`).
During the audit, implementation work advanced `HEAD` to `29f817eb`
(`Port Syncthing folder scan API queue`). Recent history reviewed includes
`29f817eb`, `53588555`, `ae8aadcf`, `3c042169`, `5dddc1ed`, `b529b1ee`,
`c9254a88`, `0319eb91`, `64f06d33`, `3227da76`, `ab141f82`, `873879be`,
`5f2ae4bd`, `37f77f2e`, `64e9fcf1`, `6c135b81`, `24837bc2`, `f03f1473`,
`d656fc47`, and `e9c15a9a`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:295`-`305`, `porting.html:30`-`65`,
     `porting-summary.json:1`-`120`, all `lanes/*/lane-status.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:1` requires supervised coordination
     rather than uncontrolled wrappers/bridge progress; `goal.md:20` requires a
     capped supervised lane/auditor workflow; `goal.md:44` requires current
     owner/session and estimates; `goal.md:49` requires honest repo-wide test
     recording.
   - Evidence: `progress.md:25` still documents "2 implementation lanes plus 1
     auditor", while `progress.md:31`-`42` marks all 12 lanes `stopped`.
     Process sampling instead found active team-watchdog, capacity-controller,
     dashboard-updater, evaluator, auditor, primary lane agents, a capacity
     job, and an active root harness. Examples: `2347911`
     `scripts/run-team-watchdog.sh`, `2452997`
     `scripts/run-capacity-controller-loop.sh`, `2479222`
     `scripts/run-dashboard-updater-loop.sh`, `3312347` port-auditor,
     `3312019` LightningCSS, `3312221` rclone, `3313554` markerPDF,
     `3313754` Difftastic, and `3314838 php tools/run-tests.php`.
   - Evidence: `HEAD` moved during this audit from `53588555` to `29f817eb`.
     Latest dirty samples reported `1308` default `git status --short` rows,
     `134` tracked changed files, and
     `134 files changed, 35420 insertions(+), 2496 deletions(-)`.
   - Audit judgment: do not accept current percentages, root-test anecdotes,
     blockers, or commit fields until active writers/status publishers are
     frozen and one regenerated snapshot is validated.

2. **High - the public dashboard and JSON summary are stale and still fail the
   required status contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json:1`-`120`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`5` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4865c7edddaf7a680378f347eb4e6`, while sampled `HEAD` is
     `29f817eb`.
   - Evidence: `porting.html:41`-`50` collapses benchmark source/upstream
     denominator into `Benchmark`, and PHP pass/fail/mapped tests into
     `Mapped`, instead of the separate columns required by `goal.md:45`.
   - Evidence: current manifest totals disagree with the published dashboard.
     Examples: Difftastic dashboard `160/417` vs current `247/587`
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`); Gitoxide
     `1432/2877` vs `1891/2877`; libsqlite `149/1454` vs `206/1589`;
     markerPDF `159/78` vs `205/259`; Pandoc `426/2028` vs `614/2276`;
     rclone `291/327` vs `421/2553`; Readability `1031/1984` vs
     `1488/1984`; Syncthing `235/658` vs `317/658`.

3. **High - manifest/status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     all `lanes/*/lane-status.json`, `progress.md:31`-`42`,
     `porting.html`, and `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:29`, `goal.md:31`,
     `goal.md:35`, `goal.md:44`, and `goal.md:45` require real upstream
     denominators, small committed slices with passing tests, precise blockers,
     current owner/session state, and comparable percentage estimates.
   - Evidence: `benchmarkDenominator.total` is still prose in several
     manifests instead of a normalized number, including Difftastic
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`), Dolt
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`), esbuild
     (`lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`), Pandoc
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`).
   - Evidence: runner status shape is inconsistent. Difftastic, Dolt, esbuild,
     libsqlite, LightningCSS, rclone, Readability, and Syncthing use object
     `runnerStatus`; Gitoxide, markerPDF, and Quadrable use string
     `runnerStatus`; Pandoc exposes no normalized `runnerStatus` field.
   - Evidence: PHP count units are mixed. Some manifests expose
     `nativeImplementation.phpBehaviorTests`; others rely only on
     `lane-status.json` `phpPass`, where the value may mean behavior tests,
     test files, or assertions. For example, Gitoxide lane status reports
     `phpPass: 3339` assertions (`lanes/gitoxide/lane-status.json:6`), while
     rclone reports `421` behavior tests (`lanes/rclone/lane-status.json:6`).
   - Evidence: `latestCommit` fields are not normalized accepted commit IDs.
     Current status files include `pending`, `uncommitted`, "working tree
     pending commit", "not committed", and prose such as Syncthing's "pending
     lane-local folder scan API request queue batch"
     (`lanes/syncthing/lane-status.json:13`).

4. **High - repo-wide root-test evidence remains non-comparable and should not
   be used as an accepted baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:295`-`305`,
     `lanes/*/lane-status.json`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide failures to be
     recorded honestly, and `goal.md:52` requires visible progress only after
     passing PHP tests.
   - Evidence: the required exact duplicate-root gate returned an active root
     harness:

     ```text
     3314838 php tools/run-tests.php
     ```

     Owner evidence:

     ```text
     3314838 claude 3309519 15 Rs php tools/run-tests.php
     ```

   - Evidence: lane statuses still disagree about the aggregate root state.
     Difftastic and LightningCSS report duplicate-gated pending roots
     (`lanes/difftastic/lane-status.json:10`-`13`,
     `lanes/lightningcss/lane-status.json:10`-`13`); esbuild records an
     unrelated rclone root failure (`lanes/esbuild/lane-status.json:10`-`13`);
     Gitoxide, libsqlite, markerPDF, Pandoc, rclone, and Syncthing each record
     green root runs from different moving snapshots; Quadrable records pending
     because another root harness was active
     (`lanes/quadrable/lane-status.json:10`-`13`).
   - Audit judgment: no root result observed in this run represents a single
     accepted repository snapshot. I did not start a duplicate root harness.

5. **Medium - several lanes still over-credit bounded, static, or oracle
   evidence as portfolio progress.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, and related lane statuses.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as source of truth, meaningful fixture
     parity, and explicit treatment of generated fixtures, bridge calls,
     shell-outs, hard features, and future slices.
   - Evidence: Gitoxide still lacks full Cargo workspace runner parity;
     Difftastic lacks full Cargo runner parity; Pandoc lacks the Haskell runner;
     Syncthing lacks broad `go test ./...`; rclone excludes live providers,
     FUSE/container paths, and provider integration suites; markerPDF's full
     ML/PDF benchmark runner is still not executed; Dolt mixes direct CLI
     probes, BATS/Go shards, runner-only evidence, and native mapping metadata
     in one denominator narrative.
   - Audit judgment: these are useful slice-level signals, but they should not
     inflate native PHP portfolio progress until the manifest contract separates
     static inventory, upstream runner pass evidence, temporary oracle probes,
     native PHP behavior tests, assertions, and accepted commits.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed:

```text
3314838 php tools/run-tests.php
```

Owner evidence:

```text
3314838 claude 3309519 15 Rs php tools/run-tests.php
```

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json
git status --short --untracked-files=all | wc -l
git status --short --untracked-files=no | wc -l
git diff --shortstat
pgrep -af '^php tools/run-tests\.php( |$)'
```

Results: all lane upstream manifests, lane status files, and
`porting-summary.json` parsed as valid JSON at the time checked. The latest
dirty-tree samples reported `1308` default status rows, `134` tracked changed
files, and `134 files changed, 35420 insertions(+), 2496 deletions(-)`.

Recent history reviewed:

```text
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
e9c15a9a Update readability lane status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
