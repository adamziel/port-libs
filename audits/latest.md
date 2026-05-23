# Independent Audit - 2026-05-23T11:03:16Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
lane status files needed for alignment checks, recent Git history, dirty-tree
state, active process state, and the required root-test gate.

Current `HEAD` at the audit sample: `0319eb91a30a` (`Record active root
harness audit evidence`). Recent history reviewed includes `0319eb91`,
`64f06d33`, `3227da76`, `ab141f82`, `873879be`, `5f2ae4bd`, `37f77f2e`,
`64e9fcf1`, `6c135b81`, `24837bc2`, `f03f1473`, and `d656fc47`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:281`-`302`, `porting.html:30`-`65`,
     `porting-summary.json`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20` requires capped supervision,
     `goal.md:29` requires small committed passing slices, `goal.md:48`
     requires finished agent work to be verified/committed/cleaned up, and
     `goal.md:49` requires honest repo-wide test recording.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     marks all 12 lanes `stopped`. Process sampling instead found active
     team-watchdog, capacity-controller, dashboard-updater, evaluator,
     integrator, auditor, primary lane agents, and capacity agents. Active
     lane/coordination PIDs sampled included `2347911` team-watchdog,
     `2424048` evaluator, `2452997` capacity-controller, `2479222`
     dashboard-updater, `3107102` Dolt runner, `3117948` markerPDF,
     `3135191` Syncthing, `3151277` LightningCSS, `3173586` rclone,
     `3173780` esbuild, `3188486` Gitoxide, `3203146` Dolt, `3216504`
     Pandoc, `3216527` Quadrable, `3216562` Readability, `3216584` auditor,
     `3216592` Difftastic, and `3217943` integrator.
   - Evidence: the dirty snapshot widened again. Current samples reported
     `1297` default `git status --short --untracked-files=all` rows, `139`
     tracked changed files, and `139 files changed, 33548 insertions(+), 2420
     deletions(-)`.
   - Audit judgment: do not accept current percentages, root-test anecdotes,
     blockers, or commit fields until active writers/status publishers are
     frozen and one regenerated snapshot is validated.

2. **High - the public dashboard is stale and still does not meet the required
   status contract.**
   - Paths: `porting.html:30`-`65`, `porting-summary.json:2`-`214`, and all
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require
     `porting.html` to show current benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json:2`-`8` still
     publish generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while current `HEAD` is `0319eb91a30a`.
   - Evidence: `porting.html:41`-`50` still collapses upstream denominator into
     `Benchmark` and PHP pass/fail plus mapped tests into `Mapped`; it still
     lacks the separate upstream-denominator, mapped-tests, and PHP pass/fail
     columns required by `goal.md:45`.
   - Evidence: current manifest/status numbers disagree with the published
     dashboard across the portfolio: Difftastic dashboard `160/417` and PHP
     `160` vs current `242/587` and PHP `242`; Dolt `242/613` and PHP `193`
     vs `432/613` and status PHP `268`; esbuild `164/2567` vs `217/2567`;
     Gitoxide `1432/2877` and PHP `2646` vs `1880/2877` and PHP `3287`;
     libsqlite `149/1454` vs `204/1589`; LightningCSS `773/3532` and PHP
     `906` vs `1116/3532` and PHP `1240`; markerPDF `159/78` and PHP `264`
     vs `203/257` and PHP `310`; Pandoc `426/2028` and PHP `164` vs
     `606/2276` and PHP `203`; Quadrable PHP `108` vs status `132`; rclone
     `291/327` vs `413/2553`; Readability `1031/1984` and PHP `107` vs
     `1471/1984` and PHP `139`; Syncthing `235/658` vs `310/658`.

3. **High - manifest/status schemas still cannot produce trustworthy portfolio
   math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`, all
     `lanes/*/lane-status.json`, `progress.md:31`-`42`, `porting.html`, and
     `porting-summary.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:31`, `goal.md:35`, and
     `goal.md:44` require a real upstream denominator, precise blockers,
     meaningful evidence, current owner/session, and percentage estimates.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic
     (`lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`), Dolt
     (`lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`), esbuild
     (`lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`), Pandoc
     (`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`), but numeric in other
     lanes.
   - Evidence: runner-status placement/type is inconsistent. Gitoxide
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`), markerPDF
     (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:251`), and Quadrable
     (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`) use string
     `runnerStatus`; Difftastic, Dolt, esbuild, libsqlite, LightningCSS,
     rclone, Readability, and Syncthing use object-shaped runner status under
     `benchmarkDenominator`; Pandoc has no comparable runner-status surface in
     the manifest.
   - Evidence: PHP count units are mixed. Several manifests expose
     `nativeImplementation.phpBehaviorTests`, while lane statuses expose
     `phpPass`, and some status text uses assertion counts as if they were
     comparable test counts.
   - Evidence: `latestCommit` fields are not normalized commit IDs across lane
     statuses. Difftastic, Dolt, Gitoxide, libsqlite, LightningCSS, markerPDF,
     Pandoc, Quadrable, rclone, Readability, Syncthing, and esbuild include
     pending/prose/root-green text rather than accepted commit hashes.

4. **High - root-test evidence remains non-comparable and should not be used as
   an accepted baseline.**
   - Paths: `tools/run-tests.php`, `progress.md:281`-`298`,
     `lanes/*/lane-status.json`, and `audits/latest.md`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require passing tests for accepted slices and honest repo-wide failure
     records.
   - Evidence: the required exact duplicate-root gate returned no active
     `php tools/run-tests.php` process at the audit samples, but the tree was
     not stable enough to start a trustworthy root run because active writers,
     status publishers, and broad dirty lane changes persisted.
   - Evidence: lane statuses do not describe one shared snapshot. Examples:
     Gitoxide records root PHP `214` files / `24643` assertions; libsqlite says
     root pending due active PID `3188250`; Readability says aggregate root
     pending due PIDs `3187243` and `3188250`; Syncthing says a final root
     harness passed `213` files / `24572` assertions; esbuild says aggregate
     verification pending due active root PID `3121991`.
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
     `goal.md:40` require upstream tests as source of truth, meaningful fixture
     parity, and explicit blockers for hard unported features.
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

Observed during audit: no output at the exact root samples.

No duplicate root run was started. The duplicate-root gate was clear, but the
stability gate failed because active writer/status loops persisted and the
dirty tree remained broad.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json
rg --pcre2 -n 'proc_open|shell_exec|passthru|system\(|(?<!->)exec\(' lanes -g '*.php'
```

Results: all lane upstream manifests and lane status files parsed as valid JSON
at the time checked. The PHP shell-out scan returned no matches under `lanes/`.

Recent history reviewed:

```text
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
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, progress, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from that same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
