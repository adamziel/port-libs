# Independent Audit - 2026-05-23T10:02:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history, dirty-tree state,
active process state, and the required PHP root-test gate. `HEAD` moved during
the audit from `3eaeb1ca0f1e` through `fb196906b8cd` to `c06b7c59988d`.

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
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, `goal.md:49`, and `goal.md:52` require
     capped supervision, small committed slices, current coordination, honest
     repo-wide tests, and one visible stable baseline.
   - Evidence: `progress.md:25` still says the launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` still
     reports every lane session as `stopped`. Process sampling found the team
     watchdog, capacity controller, dashboard updater, evaluator, integrator,
     auditor, all primary lane agents, and multiple capacity jobs active.
   - Evidence: the latest sampled dirty state reported `126` tracked changed
     files and `126 files changed, 29645 insertions(+), 746 deletions(-)`.
   - Audit judgment: do not accept portfolio percentages, blockers, lane commit
     fields, or aggregate pass/fail state until writers and status publishers
     are frozen and one snapshot is validated.

2. **High - the public dashboard remains stale and still misses the required
   column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     benchmark source, upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit
     columns.
   - Evidence: `porting.html:32`-`36` and `porting-summary.json` still publish
     generated time `2026-05-23 04:57:16 UTC` and source commit
     `bda83c6b93d4`, while the latest sampled `HEAD` is `c06b7c59988d`.
   - Evidence: the table still collapses benchmark source plus denominator
     into `Benchmark`, and PHP pass/fail plus mapped tests into `Mapped`
     (`porting.html:41`-`45`).
   - Evidence: dashboard mapped/denominator values disagree with current
     manifests: Difftastic `160/417` vs `228/583`, Dolt `242/613` vs
     `406/613`, esbuild `164/2567` vs `206/2567`, Gitoxide `1432/2877` vs
     `1781/2877`, libsqlite `149/1454` vs `197/1589`, LightningCSS
     `773/3532` vs `1010/3532`, markerPDF `159/78` vs `194/249`, Pandoc
     `426/2028` vs `558/2276`, rclone `291/327` vs `396/2553`, Readability
     `1031/1984` vs `1402/1984`, and Syncthing `235/658` vs `291/658`.
     Quadrable's mapped denominator still matches at `55/55`, but the
     dashboard PHP count is stale against the current lane status.

3. **High - manifest and lane-status schemas still cannot produce trustworthy
   portfolio math.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json` and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, `goal.md:44`, and `goal.md:45` require real denominators,
     explicit slices, current coordination fields, and meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose/string in Difftastic,
     Dolt, esbuild, Pandoc, and Quadrable, but numeric in Gitoxide,
     libsqlite, LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in many lanes,
     a string in Gitoxide, markerPDF, and Quadrable, and absent/null in
     Pandoc. The status surface also mixes mapped upstream units with native
     behavior-test counts, for example markerPDF `194` mapped units vs `303`
     behavior tests and Readability `1402` mapped units vs `132` behavior
     tests in lane status.
   - Evidence: sampled `latestCommit` fields are still not normalized commit
     IDs across many lanes, including prose/pending/uncommitted states in
     Difftastic, Dolt, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF,
     rclone, Readability, and Syncthing.

4. **High - root-test evidence is diagnostic only; it is not an acceptance
   checkpoint.**
   - Paths: `tools/run-tests.php`, `progress.md`, and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate returned no active exact root
     process at the sampled preflight, but the tree was not stable enough for a
     root run because active writers/status loops were present and the dirty
     surface is broad. A handoff gate then found active root PID
     `2840369 php tools/run-tests.php`, owned by `claude`.
   - Evidence: lane statuses now contain a mix of green aggregate root claims,
     pending duplicate-root claims, focused-lane-only claims, and uncommitted
     batch claims. Those can help triage, but they cannot replace one
     quiesced `php tools/run-tests.php` result from an accepted snapshot.

5. **Medium - high progress language still over-credits bounded, supplied, or
   runner-incomplete evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide still lacks full Cargo runner parity, Difftastic still
     lacks full Cargo runner parity, Pandoc still lacks full Haskell runner
     parity, Syncthing still lacks full `go test ./...` parity, markerPDF has
     not run the full Python benchmark/model stack, Dolt still lacks full
     Go/BATS parity, and rclone still excludes live provider/mount/FUSE/Docker
     coverage.
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
2840369 php tools/run-tests.php
```

Active root owner evidence:

```text
2840369 claude 2818618 00:07 Rs php tools/run-tests.php
```

No root run was started because the stability condition failed: active
automation/writer/status loops were present, the Active Lanes table contradicts
the process state, a root harness appeared during handoff, and the latest
sampled dirty tree contained `126` tracked changed files and `126 files
changed, 29645 insertions(+), 746 deletions(-)`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
```

Result: all lane upstream manifests were valid JSON at the time checked.

Recent history reviewed:

```text
c06b7c59 Stamp quadrable checkout fork status
fb196906 Advance quadrable checkout fork command parity
3eaeb1ca Refresh independent audit status
43573ce4 pandoc: refresh task list root result
ed1ffb47 Add Syncthing folder scan checkpoint status
d4ff8922 difftastic: map JSON directory command output
3ab0616e pandoc: record task list writer status
7a4ddb31 pandoc: map task list writer slices
43ea985c Refresh independent audit status
811eec9e Record quadrable binary proof status
b3f93896 Advance quadrable binary proof command parity
23794a87 Refresh independent audit status
106f6864 Refresh independent audit status
7a8809ea Record rclone lane implementation commit
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
validate manifests from the frozen tree, accept or reject dirty lane batches one
lane at a time, normalize manifest/status denominator, mapped, PHP pass/fail,
runner, and commit fields, regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from that same accepted snapshot,
rerun the exact duplicate-root gate, and capture one quiesced
`php tools/run-tests.php` result.
