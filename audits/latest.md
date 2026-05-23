# Independent Audit - 2026-05-23T07:57:12Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check alignment, recent Git history through
`580e81a9`, dirty-tree status, active process/test state, and PHP shell-out
surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - the repository still has no stable integration snapshot to
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md:264`-`272`, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52` require a practical concurrency cap,
     accurate owner/session state, deliberate integration, cleanup, repo-wide
     verification, and visible current progress.
   - Evidence: `progress.md:25` still says the current launch target is two
     implementation lanes plus one auditor, and `progress.md:31`-`42` lists
     every lane as `stopped`. Process sampling instead found active watchdog,
     capacity, dashboard, evaluator, auditor, integrator, lane-agent,
     artifact-acceptance, and root-test processes.
   - Evidence: latest samples reported `962` default `git status --short`
     entries, `104` tracked changed files, and `104 files changed, 23308
     insertions(+), 606 deletions(-)`.
   - Evidence: the required duplicate-root gate returned active exact root
     harness PID `1939865 php tools/run-tests.php`; owner evidence was
     `1939865 claude 1858959 00:18 Rs php tools/run-tests.php`.
   - Audit judgment: freeze active writers and root loops before accepting any
     root harness, dashboard, lane-status, manifest percentage, or progress
     estimate.

2. **High - root-test state is contradictory and cannot be treated as the
   current repo result.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/esbuild/lane-status.json`, `lanes/markerpdf/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`, `lanes/dolt/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     and `lanes/pandoc/lane-status.json`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: Difftastic, markerPDF, Quadrable, Readability, and Syncthing
     record green aggregate root runs around `196` files and `21507`
     assertions. Esbuild records a red aggregate root run with one Difftastic
     failure. Rclone records a red aggregate root run with one Readability
     failure. Dolt, Gitoxide, libsqlite, and Pandoc record root verification as
     pending because duplicate-root gates found active root runners.
   - Evidence: this audit also found an active exact root process
     `1939865 php tools/run-tests.php`, so starting another root run would
     violate the requested duplicate-root gate.
   - Audit judgment: collapse root status back to one repo-level integration
     record for a frozen tree, then regenerate lane statuses from that single
     record.

3. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `580e81a9`.
   - Evidence: `porting.html:41`-`50` still has compound `Benchmark` and
     `Mapped` columns instead of separate benchmark source, upstream
     denominator, mapped tests, and PHP pass/fail columns.
   - Evidence: dashboard rows disagree with current manifests:
     Difftastic `160 / 417` versus `203 / 559`; Dolt `242 / 613` versus
     `342 / 613`; esbuild `164 / 2567` versus `190 / 2567`; Gitoxide
     `1432 / 2877` versus `1639 / 2877`; libsqlite `149 / 1454` versus
     `183 / 1454`; LightningCSS `773 / 3532` versus `886 / 3532`; markerPDF
     `159 / 78` versus `181 / 240`; Pandoc `426 / 2028` versus `516 / 2276`;
     rclone `291 / 327` versus `371 / 2553`; Readability `1031 / 1984`
     versus `1247 / 1984`; and Syncthing `235 / 658` versus `276 / 658`.
     Quadrable's mapped count still agrees at `55 / 55`, but its PHP count is
     stale in the dashboard.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`-`15`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`-`18`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`-`15`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`-`15`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators, meaningful fixture
     parity, explicit slices for huge suites, and dashboard separation of
     denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes objects, prose
     strings, and missing/null fields. Gitoxide, markerPDF, and Quadrable use
     prose strings; Pandoc has no denominator-level runner status.
   - Evidence: manifest-level PHP pass/fail fields are inconsistent or absent.
     Dolt has `mapped: 342` while `phpBehaviorTests` is `221`; markerPDF has
     `mapped: 181` while `phpBehaviorTests` is `281`; rclone has both
     `mapped: 371` and `phpBehaviorTests: 371`; Readability has `mapped: 1247`
     and `phpBehaviorTests: 121`; several other manifests omit a direct PHP
     behavior-test field and rely on lane-status prose.
   - Audit judgment: normalize manifest/status schema before publishing average
     progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed evidence remains
   too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`, `goal.md:39`, and
     `goal.md:40` require upstream tests as source of truth, reproducible
     generated artifacts, hard-feature blockers, and no credit for generated
     fixtures, bridge calls, or shell-outs as native implementation progress.
   - Evidence: Gitoxide still excludes live external driver, secret-state, and
     SSH process-spawn surfaces from mapped progress. markerPDF has no full
     upstream runner parity and leans on supplied document/model-output
     boundaries. rclone's upstream evidence is bounded and excludes live
     provider/mount parity. Syncthing has no full `go test ./...` parity.
     Quadrable has strong C++ runner evidence but still uses generated
     LMDB/raw cursor oracle fixtures and keeps full 500-trial sync-fuzzer probes
     outside the fast suite.
   - Audit judgment: keep the evidence, but separate it from native
     implementation progress and aggregate percentages.

6. **Medium - no lane PHP shell-outs were found, but the dashboard generator
   still shells out for coordination metadata.**
   - Paths: `tools/generate-dashboard.php:183`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` prohibit wrappers around
     JS/Rust/Go/C binaries as the deliverable and disallow shell-outs from
     counting as native implementation progress.
   - Evidence: `rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'`
     found only `tools/generate-dashboard.php:183`.
   - Audit judgment: no lane implementation shell-out surfaced in this audit;
     dashboard shell-out remains coordination-only and must not be counted as
     lane progress.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
1939865 php tools/run-tests.php
```

Owner evidence:

```text
1939865 claude 1858959 00:18 Rs php tools/run-tests.php
```

No duplicate root run was started. The tree was also not stable enough for a
trustworthy aggregate run because active writer/update loops were present and
the worktree remained a large dirty aggregate.

Latest dirty-tree samples:

```text
git status --short: 962 entries
git status --short --untracked-files=no: 104 entries
git diff --shortstat: 104 files changed, 23308 insertions(+), 606 deletions(-)
```

## Recent Git History

Recent commits reviewed:

```text
580e81a9 Record active root audit handoff
580a2a76 Refresh independent audit status
f4d7e836 Update syncthing lane status
6804a824 Port syncthing scanner Windows exec bits
fddbb567 difftastic map ansi syntax highlight controls
463189ff readability: map Atlas Obscura article body fixture
d91836c9 Refresh independent audit status
4f597d6f Refresh independent audit status
119d9916 Update Syncthing lane status commit pointer
25e8b8b8 Add Syncthing scanner progress events
3446f9fc pandoc record raw html list status
52ce98b3 pandoc map raw html list item slice
baddfe23 difftastic map guarded json display command
9764081e Refresh independent audit status
fcb1c75b Refresh independent audit status
68f04dbf Record syncthing normalization lane status
8a8bf56e Port syncthing scanner normalization slice
37c9d3bf readability: map additional Mozilla fixtures
9ef2cca7 Port libsqlite index leaf split insert planning
53385c27 Record lightningcss all reset status
a6721493 Record pandoc smart punctuation status
b067aab2 Port lightningcss all reset minifier slice
d50f586f Refresh independent audit status
c8d138c1 Port pandoc smart punctuation edge cases
```
