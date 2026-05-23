# Independent Audit - 2026-05-23T07:51:45Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
lane-status summaries needed to check status alignment, recent Git history
through `f4d7e836`, dirty-tree status, active process/test state, and the PHP
shell-out surface.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, or start a root harness.
Bridge/generated/oracle tooling is treated as non-progress unless a lane marks
it as temporary fixture/oracle evidence.

## Findings

1. **Critical - there is still no stable integration snapshot to audit or
   accept.**
   - Paths: `progress.md:25`, `progress.md:31`-`42`,
     `progress.md` current-owner section, `.tmux-team/tmp/*`,
     `.tmux-team/prompts/*`, `.tmux-team/logs/*`,
     `scripts/run-team-watchdog.sh`,
     `scripts/run-capacity-controller-loop.sh`,
     `scripts/run-dashboard-updater-loop.sh`,
     `scripts/run-evaluator-loop.sh`, and the current dirty `lanes/*` files.
   - Requirement at risk: `goal.md:20`, `goal.md:44`,
     `goal.md:48`, `goal.md:49`, and `goal.md:52` require a practical
     concurrency cap, accurate owner/session state, deliberate integration,
     cleanup, repo-wide verification, and visible current progress.
   - Evidence: `progress.md:25` still declares a two-implementation-worker
     plus one-auditor target, and `progress.md:31`-`42` still lists every lane
     as `stopped`, but process sampling showed active watchdog, capacity,
     dashboard, evaluator, auditor, artifact-acceptance, integrator, lane-agent,
     capacity-worker, and root-test activity.
   - Evidence: `HEAD` moved during this audit from `d91836c9` through
     `463189ff`, `fddbb567`, and `6804a824` to `f4d7e836`. Latest samples
     report `936` default `git status --short` entries, `94` tracked entries,
     and `94 files changed, 22042 insertions(+), 614 deletions(-)`.
   - Evidence: the required duplicate-root gate earlier returned active exact
     root harness PID `1817316 php tools/run-tests.php`, with owner evidence
     `1817316 claude 1604004 00:16 Rs php tools/run-tests.php`. A later exact
     sample was clear, but active writer/update loops and new commits were
     still landing.
   - Audit judgment: freeze active writers and root loops before accepting any
     root harness, dashboard, lane-status, manifest percentage, or progress
     estimate.

2. **High - root-test state is contradictory across lane records and cannot be
   treated as a current repo result.**
   - Paths: `lanes/*/lane-status.json`, especially
     `lanes/difftastic/lane-status.json:10`-`12`,
     `lanes/esbuild/lane-status.json:10`-`12`,
     `lanes/markerpdf/lane-status.json:10`-`12`,
     `lanes/quadrable/lane-status.json:10`-`12`,
     `lanes/rclone/lane-status.json:10`-`12`,
     `lanes/readability/lane-status.json:10`-`12`,
     `lanes/syncthing/lane-status.json:10`-`12`, and
     `lanes/dolt/lane-status.json:10`-`12`.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices with passing tests, verified integration,
     cleanup, and honest failure records.
   - Evidence: Difftastic, markerPDF, Quadrable, Readability, and Syncthing
     record aggregate root runs as green around `196` test files and
     `21368`-`21507` assertions. Esbuild and rclone record later aggregate root
     runs as red with `1` unrelated Difftastic failure. Dolt, Gitoxide,
     libsqlite, and Pandoc record root verification as pending because a
     duplicate-root gate found an active root runner.
   - Evidence: process sampling during the audit found active exact root
     process PID `1817316`, and the later no-root sample coincided with active
     writer/update loops plus new `HEAD` movement, so none of the lane-local
     root anecdotes can be the single accepted current result.
   - Audit judgment: collapse root status back to one repo-level integration
     record for a frozen tree, then regenerate lane statuses from that single
     record.

3. **High - `porting.html` and `porting-summary.json` remain stale and still
   fail the dashboard column contract.**
   - Paths: `porting.html:30`-`36`, `porting.html:41`-`65`,
     `porting-summary.json:1`-`215`, and every
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require
     current dashboard fields for benchmark source, upstream denominator,
     mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current
     work, blocker, and commit.
   - Evidence: `porting.html:32`-`36` advertises generated time
     `2026-05-23 04:57:16 UTC` and source commit `bda83c6b93d4`, while
     reviewed `HEAD` is `f4d7e836`.
   - Evidence: `porting.html:41`-`50` still uses compound `Benchmark` and
     `Mapped` columns instead of separate `benchmark source`, `upstream
     denominator`, `mapped tests`, and `PHP pass/fail` columns.
   - Evidence: dashboard rows disagree with current manifests:
     Difftastic `160 / 417` versus `203 / 559`,
     Dolt `242 / 613` versus `336 / 613`,
     esbuild `164 / 2567` versus `188 / 2567`,
     Gitoxide `1432 / 2877` versus `1631 / 2877`,
     libsqlite `149 / 1454` versus `183 / 1454`,
     LightningCSS `773 / 3532` versus `871 / 3532`,
     markerPDF `159 / 78` versus `181 / 240`,
     Pandoc `426 / 2028` versus `515 / 2028`,
     rclone `291 / 327` versus `371 / 2553`,
     Readability `1031 / 1984` versus `1247 / 1984`, and
     Syncthing `235 / 658` versus `276 / 658`. Quadrable's mapped count still
     agrees at `55 / 55`, but the dashboard PHP count is stale at `108` while
     lane status records `119`.
   - Audit judgment: the public dashboard is an old publish snapshot, not the
     current coordination surface.

4. **High - manifest denominator, runner-status, and PHP-count schemas still
   cannot support trustworthy portfolio percentages.**
   - Paths: every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, especially
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12`-`17`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12`-`16`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`-`16`, and
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12`-`16`.
   - Requirement at risk: `goal.md:25`, `goal.md:35`,
     `goal.md:38`, and `goal.md:45` require real upstream denominators,
     meaningful fixture parity, explicit slices for huge suites, and dashboard
     separation of denominator, mapped tests, and PHP pass/fail.
   - Evidence: `benchmarkDenominator.total` is prose in Difftastic, Dolt,
     esbuild, Pandoc, and Quadrable, but numeric in Gitoxide, libsqlite,
     LightningCSS, markerPDF, rclone, Readability, and Syncthing.
   - Evidence: `benchmarkDenominator.runnerStatus` mixes object, string, and
     absent/null shapes. Gitoxide, markerPDF, and Quadrable use prose strings;
     Pandoc has no denominator-level runner status.
   - Evidence: manifest-level PHP pass/fail fields remain inconsistent or
     absent. Dolt has `mapped: 336` while `phpBehaviorTests` is `221`;
     markerPDF has `mapped: 181` while `phpBehaviorTests` is `281`; rclone has
     both `mapped: 371` and `phpBehaviorTests: 371`; Readability has
     `mapped: 1247` and `phpBehaviorTests: 121`; several other manifests omit
     a direct PHP behavior-test field and rely on lane-status prose.
   - Evidence: denominator units remain mixed: rclone's manifest denominator
     is `2553` repository files while the stale dashboard still uses `327` Go
     test files; markerPDF uses `240` static behavior/reference units;
     Difftastic mixes Rust test attributes, golden pairs, sample pairs, parser
     corpus files, and parser example files in one denominator.
   - Audit judgment: normalize manifest/status schema before publishing average
     progress or comparing lane percentages.

5. **Medium - bounded, supplied, generated, or oracle-backed evidence remains
   too easy to misread as native parity.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:30`, `goal.md:37`,
     `goal.md:39`, and `goal.md:40` require upstream tests as source of truth,
     reproducible generated artifacts, hard-feature blockers, and no credit for
     generated fixtures, bridge calls, or shell-outs as native implementation
     progress.
   - Evidence: Gitoxide still excludes live external driver, secret-state, and
     SSH process-spawn surfaces from mapped progress. markerPDF has no full
     upstream runner parity and still leans on supplied document/model-output
     boundaries. rclone's upstream evidence is bounded and excludes live
     provider/mount parity. Syncthing has no full `go test ./...` parity.
     Quadrable has strong C++ runner evidence but still uses generated
     LMDB/raw cursor oracle fixtures and keeps full 500-trial sync-fuzzer probes
     outside the fast suite.
   - Audit judgment: keep the evidence, but separate it from native
     implementation progress and aggregate percentages.

## Bridge / Shell-Out Check

Command:

```text
rg -n 'proc_open|shell_exec|passthru|system\(|popen\(|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Earlier result:

```text
tools/generate-dashboard.php:183:    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
```

No lane PHP shell-out was found. The only PHP shell-out match is dashboard
coordination tooling.

## Test Gate

I did not run `php tools/run-tests.php`.

Required duplicate-root check before any possible root run:

```text
pgrep -af '^php tools/run-tests\.php( |$)'
```

Result:

```text
1817316 php tools/run-tests.php
```

Owner evidence:

```text
1817316 claude 1604004 00:16 Rs php tools/run-tests.php
```

No root run was started because an exact root harness was already active and
active writer/update loops made the broad dirty worktree non-quiescent. A later
exact duplicate-root sample was clear, but the tree was still not stable enough:
active worker/update loops persisted and `HEAD` had advanced to `f4d7e836`.

Latest dirty-tree samples before this update:

```text
git status --short: 936 entries
git status --short --untracked-files=no: 94 entries
git diff --shortstat: 94 files changed, 22042 insertions(+), 614 deletions(-)
```

## Recent Git History

Recent commits reviewed:

```text
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
```
