# Independent Audit - 2026-05-23T10:14:36Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history, dirty-tree state,
active process state, and the required PHP root-test gate. Before this audit
update was committed, `HEAD` moved during the audit from `af52ff75` through
`f1b95822`, `c20307a4`, `74aa03ab`, and `8fca4c31` to `4396ea72`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md`, `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`,
     `scripts/run-team-watchdog.sh`, `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`, and
     `scripts/run-evaluator-loop.sh`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require capped supervision,
     small committed slices, current coordination, honest repo-wide tests, and
     one visible stable baseline.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`. Process sampling found the team
     watchdog, evaluator, capacity controller, dashboard updater, auditor,
     integrator, primary lane agents for Pandoc, Readability, rclone,
     markerPDF, Quadrable, Dolt, Syncthing, Gitoxide, LightningCSS, and
     esbuild, plus multiple capacity/artifact jobs.
   - Evidence: the latest sampled dirty state reported `124` tracked changed
     files and `124 files changed, 30893 insertions(+), 783 deletions(-)`.
     Implementation `HEAD` moved from `af52ff75` through `f1b95822`,
     `c20307a4`, `74aa03ab`, and `8fca4c31` to `4396ea72` during the audit.
   - Audit judgment: do not accept portfolio percentages, blockers, lane commit
     fields, or aggregate pass/fail state until writers and status publishers
     are frozen and one snapshot is validated.

2. **High - the public dashboard is stale and still misses the required column
   contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit
     columns.
   - Evidence: `porting.html:32`-`36` still publishes generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while the
     latest sampled implementation `HEAD` is `4396ea72`.
   - Evidence: the dashboard collapses benchmark source plus denominator into
     `Benchmark`, and PHP pass/fail plus mapped tests into `Mapped`
     (`porting.html:41`-`45`), so it still does not expose the exact required
     columns.
   - Evidence: dashboard mapped/denominator values disagree with current
     manifests: Difftastic `160/417` vs `231/584`, Dolt `242/613` vs
     `406/613`, esbuild `164/2567` vs `208/2567`, Gitoxide `1432/2877` vs
     `1794/2877`, libsqlite `149/1454` vs `198/1589`, LightningCSS
     `773/3532` vs `1015/3532`, markerPDF `159/78` vs `194/249`, Pandoc
     `426/2028` vs `566/2276`, rclone `291/327` vs `397/2553`, Readability
     `1031/1984` vs `1415/1984`, and Syncthing `235/658` vs `296/658`.
     Quadrable's mapped denominator still matches at `55/55`, but dashboard PHP
     count `108` is stale against lane status `128`.

3. **High - manifest, lane-status, and progress schemas still cannot produce
   trustworthy portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     all `lanes/*/lane-status.json`, and `progress.md:29`-`42`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`,
     `goal.md:44`, and `goal.md:45` require real denominators, explicit slices,
     current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose/string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and null in Pandoc.
   - Evidence: lane `latestCommit` fields are still not normalized commit IDs:
     Dolt, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF, Pandoc,
     rclone, Readability, and Syncthing include pending, uncommitted, previous
     commit plus dirty-batch, or prose states.
   - Evidence: `progress.md:31`-`42` estimates are far behind lane status for
     most lanes, for example Gitoxide `66%` vs `98%`, LightningCSS `14%` vs
     `78%`, markerPDF `10%` vs `79%`, libsqlite `12%` vs `97%`, Pandoc `10%`
     vs `91%`, rclone `9%` vs `95%`, and Syncthing `8%` vs `94%`.

4. **High - root-test evidence is diagnostic only; it is not an acceptance
   checkpoint.**
   - Paths: `tools/run-tests.php`, `progress.md`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate returned no active exact
     `php tools/run-tests.php` process at the sampled preflight, but the final
     handoff gate found active root PID `2937440 php tools/run-tests.php`,
     owned by `claude`. A later post-amend gate found transient focused-lane
     PID `2951307 php tools/run-tests.php lanes/syncthing/tests`, which exited
     before owner sampling. No duplicate root run was started.
   - Evidence: lane statuses now mix green aggregate root claims, pending
     duplicate-root claims, focused-lane-only claims, red historical root claims,
     and uncommitted batch claims. Those can help triage, but they cannot
     replace one quiesced `php tools/run-tests.php` result from an accepted
     snapshot.

5. **Medium - high progress language still over-credits bounded, supplied, or
   runner-incomplete evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`, `goal.md:37`, and
     `goal.md:40` prohibit crediting bridge/shell/generated artifacts as native
     implementation progress and require hard gaps to be explicit.
   - Evidence: Gitoxide still lacks full Cargo runner parity, Difftastic still
     lacks full Cargo runner parity, Pandoc still lacks full Haskell runner
     parity, Syncthing still lacks full `go test ./...` parity, markerPDF has
     not run the full Python benchmark/model stack, Dolt still lacks full
     Go/BATS parity, rclone still excludes live provider/mount/FUSE/Docker
     coverage, and libsqlite still only records `veryquick` parity rather than
     full `all`/release permutations.
   - Audit judgment: keep these as blockers or future slices. Bounded upstream
     probes and supplied fixture pairs are useful, but they should not drive
     near-complete portfolio percentages.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Observed results:

```text
<no rows>
2937440 php tools/run-tests.php
2951307 php tools/run-tests.php lanes/syncthing/tests
```

Active root owner evidence:

```text
2937440 claude 2851877 00:28 Rs php tools/run-tests.php
```

Transient focused-lane owner evidence for PID `2951307` was not recoverable
because the process exited before `ps` sampling.

No root run was started because the stability condition failed and a handoff
gate found an active root harness: active automation/writer/status loops were
present, `progress.md` contradicted process state, implementation `HEAD` moved
during the audit, and the latest sampled dirty tree contained `124` tracked
changed files and `124 files changed, 30893 insertions(+), 783
deletions(-)`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
```

Result: all lane upstream manifests were valid JSON at the time checked.

Recent history reviewed:

```text
4396ea72 Advance rclone provider copy metadata
8fca4c31 pandoc: record ordered list writer status
74aa03ab pandoc: map markdown ordered list writer markers
c20307a4 difftastic: stamp builtin highlight status
f1b95822 difftastic: map javascript builtin highlight captures
af52ff75 Refresh independent audit status
c06b7c59 Stamp quadrable checkout fork status
fb196906 Advance quadrable checkout fork command parity
3eaeb1ca Refresh independent audit status
43573ce4 pandoc: refresh task list root result
ed1ffb47 Add Syncthing folder scan checkpoint status
d4ff8922 difftastic: map JSON directory command output
3ab0616e pandoc: record task list writer status
```

## Next Intervention

Freeze active writers/status publishers and duplicate root/focused PHP loops
first. Then validate manifests from the frozen tree, accept or reject dirty lane
batches one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, and commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same accepted snapshot,
rerun the exact duplicate-root gate, and capture one quiesced
`php tools/run-tests.php` result.
