# Independent Audit - 2026-05-23T09:45:07Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane
status files needed for alignment checks, recent Git history through observed
initial `HEAD` `23794a87a463` and handoff `HEAD` `811eec9e3f98`,
dirty-tree state, active process state, and the required PHP root-test gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness. Bridge,
generated, supplied, oracle, and shell-backed evidence is treated as
non-progress unless it is explicitly temporary oracle tooling.

## Findings

1. **Critical - there is still no stable integration snapshot to accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:278`-`287`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting.html`, `porting-summary.json`,
     and active automation under `scripts/`.
   - Goal requirement at risk: `goal.md:20`, `goal.md:29`,
     `goal.md:44`, `goal.md:48`, `goal.md:49`, and `goal.md:52` require
     capped supervision, small committed slices, current coordination, and
     a visible stable baseline.
   - Evidence: `progress.md:25` still documents a two-implementation-worker
     plus one-auditor target, and `progress.md:31`-`42` still reports every
     lane session as `stopped`; current process sampling found active
     watchdog, capacity-controller, dashboard-updater, evaluator, auditor,
     integrator, artifact-acceptance, and lane-agent processes including
     agents for Dolt, markerPDF, Readability, Quadrable, Gitoxide,
     libsqlite, Dolt, rclone, Difftastic, LightningCSS, Pandoc, esbuild,
     Syncthing, and capacity jobs.
   - Evidence: dirty-tree samples moved during the audit. The latest sample
     reported `1152` default `git status --short` entries, `136` tracked
     changed files, and `136 files changed, 29538 insertions(+), 939
     deletions(-)`.
   - Evidence: all sampled lane manifests and lane statuses are dirty, plus
     `porting.html` and `porting-summary.json`; many `latestCommit` fields
     are prose such as pending/uncommitted lane batches rather than accepted
     commit IDs.
   - Audit judgment: no portfolio percentage, pass/fail, blocker, or commit
     field should be accepted until writers/status publishers are frozen and
     one snapshot is validated.

2. **High - root-test evidence is contradictory, so the root harness was not
   rerun.**
   - Paths: `tools/run-tests.php`, `lanes/difftastic/lane-status.json:10`,
     `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`,
     `lanes/libsqlite/lane-status.json:10`,
     `lanes/lightningcss/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/pandoc/lane-status.json:10`,
     `lanes/quadrable/lane-status.json:10`,
     `lanes/rclone/lane-status.json:10`,
     `lanes/readability/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:10`-`12`.
   - Goal requirement at risk: `goal.md:29` and `goal.md:49` require passing
     tests on committed slices and honest repo-wide failure records.
   - Evidence: the required duplicate-root gate
     `pgrep -af '^php tools/run-tests\.php( |$)'` returned no rows at the
     audit sample, but the tree was not stable enough for a trustworthy root
     run because active writer loops and broad dirty lane/status changes were
     present.
   - Evidence: lane statuses disagree on root state: Difftastic, Gitoxide,
     Dolt, and Quadrable record green aggregate runs; esbuild records one red
     aggregate run followed by a filtered green rerun; Syncthing records a red
     aggregate run with 3 failures; libsqlite, LightningCSS, markerPDF,
     Pandoc, rclone, and Readability record pending duplicate-root gates tied
     to stale active PIDs.
   - Audit judgment: collapse root-test state to one repo-level record from a
     frozen snapshot. Lane-local root anecdotes should not drive acceptance.

3. **High - `porting.html` and `porting-summary.json` are stale and still miss
   the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:2`-`8`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard fields for benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: `porting.html:32`-`36` and
     `porting-summary.json:2`-`5` still publish generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`; handoff
     `HEAD` is `811eec9e3f98`.
   - Evidence: the table still combines benchmark source and denominator in a
     single `Benchmark` column and combines PHP pass/fail with mapped tests in
     a single `Mapped` column (`porting.html:41`-`45`), contrary to the
     separate-column requirement in `goal.md:45`.
   - Evidence: current manifest mapped/denominator values disagree with the
     dashboard rows: Difftastic `228 / 583` vs `160 / 417`, Dolt
     `399 / 613` vs `242 / 613`, esbuild `204 / 2567` vs `164 / 2567`,
     Gitoxide `1718 / 2877` vs `1432 / 2877`, libsqlite `196 / 1589` vs
     `149 / 1454`, LightningCSS `1010 / 3532` vs `773 / 3532`,
     markerPDF `193 / 248` vs `159 / 78`, Pandoc `556 / 2276` vs
     `426 / 2028`, rclone `395 / 2553` vs `291 / 327`, Readability
     `1387 / 1984` vs `1031 / 1984`, and Syncthing `288 / 658` vs
     `235 / 658`.

4. **High - manifest/status schemas still cannot produce trustworthy
   portfolio percentages.**
   - Paths: all `lanes/*/UPSTREAM_TEST_MANIFEST.json` and
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, `goal.md:44`, and `goal.md:45` require real
     denominators, explicit slices, current coordination fields, and
     meaningful percentages.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable but numeric in other lanes. Examples:
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, and
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: `runnerStatus` is an object in many lanes but a string in
     Gitoxide, markerPDF, and Quadrable
     (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:242`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`).
   - Evidence: PHP behavior counts are not normalized: markerPDF records
     `phpBehaviorTests: 303` at
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:440` while the same
     manifest maps only `193` upstream units at line 15; Readability records
     `mapped: 1387` at `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`
     but `phpBehaviorTests: 130` at line 423.
   - Audit judgment: normalize denominator, mapped, PHP pass/fail, runner
     evidence, and commit fields before using percentages for portfolio
     decisions.

5. **Medium - high progress language still over-credits bounded, supplied, or
   shell/oracle-backed evidence.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:30`, `goal.md:35`,
     `goal.md:37`, and `goal.md:40` prohibit crediting bridge/shell/generated
     artifacts as native implementation progress and require hard gaps to be
     explicit.
   - Evidence: Gitoxide still lacks full cargo workspace runner parity,
     Difftastic lacks full Cargo runner parity, Pandoc lacks full Haskell
     runner parity, markerPDF's full benchmark runner is not executed and uses
     supplied/model-output boundary evidence, rclone excludes live provider,
     mount, FUSE, Docker, and provider-integration breadth, Syncthing full
     `go test ./...` remains unrun, and Quadrable still includes extensive
     oracle dump/load fixture evidence outside the native implementation.
   - Audit judgment: keep these as explicit blockers/future slices rather
     than treating them as near-complete native parity.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root gate before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
<no rows>
```

No root run was started because the stability condition failed: active
automation/writer loops were present and the dirty tree moved during the audit.
The latest sample contained 1152 default status entries, 136 tracked changed
files, and `136 files changed, 29538 insertions(+), 939 deletions(-)`.

Validation commands run instead:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
```

Result: all lane upstream manifests were valid JSON at the time checked.

Recent history reviewed:

```text
811eec9e Record quadrable binary proof status
b3f93896 Advance quadrable binary proof command parity
23794a87 Refresh independent audit status
106f6864 Refresh independent audit status
7a8809ea Record rclone lane implementation commit
fc1428e9 Advance rclone provider copy parity
d8c51049 Record quadrable implementation commit
316b477e Refresh independent audit status
8d345b5e Advance quadrable proof command parity
a1af8ce1 Record difftastic lane implementation commit
```

## Next Intervention

Freeze active writers/status publishers and duplicate root loops first. Then
validate all manifests from the frozen tree, accept or reject dirty lane batches
one lane at a time, normalize manifest/status denominator, mapped, PHP
pass/fail, runner, and commit fields, regenerate `progress.md`,
`porting.html`, `porting-summary.json`, and lane statuses from the same
accepted snapshot, rerun the exact duplicate-root gate, and capture one
quiesced `php tools/run-tests.php` result.
