# Independent Audit - 2026-05-23T10:52:32Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Current `HEAD` at this audit sample: `873879be2c6c` (`Advance libsqlite
auto-vacuum pointer maps`). Recent history includes implementation/status churn
after audit commits: `873879be`, `5f2ae4bd`, `37f77f2e`, `64e9fcf1`,
`6c135b81`, `24837bc2`, `f03f1473`, and `d656fc47`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:278`-`300`, `porting.html:30`-`65`,
     `porting-summary.json`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20` requires capped supervision, and
     `goal.md:29`, `goal.md:48`, and `goal.md:49` require small committed
     passing slices, integration cleanup, and honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, while `progress.md:31`-`42` still
     marks every lane `stopped`. Process sampling instead found active
     watchdog/capacity/dashboard/evaluator/integrator/auditor/lane-agent loops,
     including primary lane agents for Syncthing, markerPDF, libsqlite,
     Gitoxide, LightningCSS, esbuild, rclone, Pandoc, Quadrable, Difftastic,
     Readability, Dolt, and a Dolt runner.
   - Evidence: the dirty snapshot is broad: `git status --short` reported
     `1246` rows, tracked-only status reported `126` changed files, and
     `git diff --shortstat` reported `126 files changed, 30949 insertions(+),
     890 deletions(-)`.
   - Evidence: `HEAD` has advanced since the previous audit/status commit; the
     latest commit is a libsqlite implementation batch, while many lane files
     outside libsqlite remain dirty. This is not a single accepted portfolio
     snapshot.
   - Audit judgment: do not accept current percentages, blocker fields, commit
     fields, or root-test anecdotes until active writers/status publishers are
     frozen and one regenerated snapshot is validated.

2. **High - the public dashboard is stale and fails the required status
   contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while current
     `HEAD` is `873879be2c6c`.
   - Evidence: `porting.html:41`-`50` still collapses upstream denominator into
     `Benchmark` and PHP pass/fail plus mapped tests into `Mapped`, rather than
     publishing the separate denominator, mapped-test, and PHP pass/fail columns
     required by the goal.
   - Evidence: dashboard rows disagree with current manifests and statuses:
     Difftastic `160/417` vs manifest `240/586`; Dolt `242/613` vs `426/613`;
     esbuild `164/2567` vs `215/2567`; Gitoxide `1432/2877` vs `1880/2877`;
     libsqlite `149/1454` vs `203/1589`; LightningCSS `773/3532` vs
     `1112/3532`; markerPDF `159/78` vs `198/252`; Pandoc `426/2028` vs
     `585/2276`; rclone `291/327` vs `413/2553`; Readability `1031/1984` vs
     `1458/1984`; Syncthing `235/658` vs `310/658`; and Quadrable PHP
     `108` vs lane status `131`.

3. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:31`, `goal.md:35`, and
     `goal.md:44` require a real upstream denominator, precise blockers,
     meaningful coverage evidence, current owner/session, and percentage
     estimates.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`), Dolt
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`), esbuild
     (`lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`), Pandoc
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`), but numeric in other
     lanes.
   - Evidence: `runnerStatus` is an object in several manifests but a string in
     Gitoxide (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`), markerPDF
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:249`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`), and absent at the
     Pandoc manifest surface.
   - Evidence: PHP count units are mixed. Some manifests expose
     `nativeImplementation.phpBehaviorTests` (`markerPDF`, `rclone`,
     `readability`), some omit it while lane status reports `phpPass`, and some
     lane statuses use assertions or behavior checks as if they were comparable
     tests.
   - Evidence: `latestCommit` fields remain non-normalized prose or pending
     dirty-batch notes in Dolt, esbuild, Gitoxide, LightningCSS, markerPDF,
     Pandoc, Quadrable, rclone, and Syncthing lane status files, despite
     `goal.md:44` requiring current commit status.

4. **High - root-test evidence is non-comparable and a duplicate root harness
   was active during the audit.**
   - Paths: `tools/run-tests.php`, `progress.md:281`-`296`,
     `lanes/*/lane-status.json`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require passing tests for accepted slices and honest repo-wide failure
     records.
   - Evidence: the required duplicate-root gate initially returned active root
     PID `3109422 php tools/run-tests.php`; it exited before owner sampling.
     A later exact gate returned active root PID `3110747 php
     tools/run-tests.php`, with owner evidence `3110747 claude 3096285 00:07
     Rs php tools/run-tests.php`. I did not start a duplicate root run.
   - Evidence: lane statuses contradict each other about aggregate health:
     libsqlite records a root-green result (`213` files / `24527` assertions /
     `0` failures), markerPDF and Readability also claim root green, Gitoxide
     and Pandoc mark root pending, rclone says aggregate root is not green due
     unrelated Readability failures, and Syncthing records recent red root
     samples in Rclone. These cannot all describe the same accepted snapshot.
   - Audit judgment: root evidence remains unaccepted until the exact root gate
     is clear, active writers are frozen, and one full `php tools/run-tests.php`
     run is captured from the same regenerated snapshot.

5. **Medium - high progress language still over-credits bounded, static, or
   runner-incomplete evidence.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` require upstream tests as source of truth, meaningful
     fixture parity, and explicit blockers for hard unported features.
   - Evidence: Gitoxide still lacks full Cargo workspace runner parity;
     libsqlite is bounded to veryquick/focused Tcl slices, not full SQLite
     all/release permutations; rclone excludes live providers, mount/FUSE,
     Docker, and `fstest/test_all`; Syncthing still lacks `go test ./...`;
     Pandoc lacks the Haskell runner; markerPDF lacks full
     `benchmarks/overall.py` with model dependencies; Difftastic lacks full
     Cargo runner parity.
   - Audit judgment: bounded probes, supplied documents, generated fixtures,
     and oracle artifacts are useful evidence, but they should not drive
     near-complete lane status until accepted commit state, native behavior,
     runner gaps, and full denominators are normalized.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed during audit:

```text
3109422 php tools/run-tests.php
```

`3109422` exited before owner sampling. A later exact gate found:

```text
3110747 php tools/run-tests.php
```

Owner evidence:

```text
3110747 claude 3096285 00:07 Rs php tools/run-tests.php
```

No duplicate root run was started. The stability gate also failed because
active writer/status loops persisted and the dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
rg --pcre2 -n 'proc_open|shell_exec|passthru|system\(|(?<!->)exec\(' lanes -g '*.php'
```

Results: all lane upstream manifests and lane status files parsed as valid JSON
at the time checked. The shell-out scan found no PHP process shell-outs under
`lanes/`.

Recent history reviewed:

```text
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
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
