# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the final required root PHP harness run is green in the current moving checkout, but this is not an accepted integration checkpoint. Recent commits continued landing during the audit; observed `HEAD` moved from `e10470b` (`Refresh independent audit`) through `eefccb4`, `473ec10`, and then `3087796` (`Add Syncthing receive index slice`) while tests and inspection were in progress. The latest status sample still shows broad dirty lane implementation files, manifests/status files, generated dashboard artifacts, audit evidence, and fixtures.

## Findings

1. **Critical - the root test signal is attached to a moving dirty aggregate, not a reviewed integration slice.**
   - Paths: `lanes/rclone/src/MemoryProvider.php:717`, `lanes/rclone/src/MemoryProvider.php:1205`, `lanes/rclone/tests/DeletePlanningTest.php`, `lanes/rclone/tests/FixCaseTest.php`, `lanes/rclone/tests/HashAndCheckTest.php`, `lanes/rclone/tests/MemoryProviderTest.php`, `lanes/rclone/tests/ProviderMoveFeatureTest.php`, `porting.html`, `porting-summary.json`, `progress.md`.
   - Evidence: the first required `php tools/run-tests.php` run exited `1` with `139 test files, 12271 assertions, 12 failures`, all from rclone calls to `MemoryProvider::duplicateDirectoryPaths()` while `lanes/rclone/src/MemoryProvider.php` was being changed. After the source settled enough to expose `duplicateDirectoryPaths()` at line 1205, a second run exited `0` with `140 test files, 12359 assertions, 0 failures`. `HEAD` also advanced during the audit from `e10470b` to `3087796`.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests, accurate latest commits, cleanup of unrelated changes, and progress/status generated from accepted state.
   - Audit judgment: do not publish the moving aggregate as the portfolio baseline. Freeze or explicitly coordinate writers, then accept or reject one lane batch at a time with a root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:1`.
   - Evidence: the dashboard still shows average progress `14.3%` and `Generated: 2026-05-22 15:40:20 UTC`; current lane-status estimates average about `45%`. Current manifest mapped counts are Difftastic `91`, Dolt `110`, esbuild `106`, Gitoxide `1120`, libsqlite `94`, LightningCSS `400`, markerPDF `78`, Pandoc `170`, Quadrable `55`, rclone `163`, Readability `637`, and Syncthing `150`; the dashboard still shows `15`, `5`, `16`, `737`, `18`, `78`, `11`, `19`, `24`, `20`, `89`, and `27` respectively. The dashboard also still shows Pandoc's denominator as `1979`, while the manifest now reports `2028` inspected upstream artifacts.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate `porting.html` and `porting-summary.json` only from the next accepted green checkpoint.

3. **High - `progress.md` still presents obsolete lane estimates, blockers, and next tasks.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`.
   - Evidence: the Active Lanes table still shows old estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. Current lane statuses report Gitoxide `84%`, Quadrable `53%`, libsqlite `49%`, rclone `48%`, LightningCSS/Pandoc `46%`, Syncthing `45%`, markerPDF `40%`, Readability `37%`, Difftastic `32%`, and Dolt/esbuild `31%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines. The lane table needs a supervisor refresh from the next accepted green checkpoint.

4. **High - lane status blockers/audit fields still cite obsolete root evidence and non-machine-checkable commits.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`, `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/dolt/lane-status.json:10`, `lanes/markerpdf/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`, `lanes/markerpdf/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`.
   - Evidence: this audit's final root run is `140 / 12359 / 0`, but lane statuses still cite stale totals including `138 / 12193 / 0`, `139 / 12343 / 0`, and older green/red claims. Some `latestCommit` values are prose such as `current lane commit pending...`, `pending: ...`, or `not committed this run...`, not stable accepted commit IDs.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and periodic repo-wide test failures recorded honestly.
   - Audit judgment: normalize every lane status after accepting/rejecting dirty batches. Use exact commit hashes and the same root test result as the accepted checkpoint.

5. **High - evidence units remain mixed across upstream mapped cases, PHP behavior tests, and assertion counts.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/lane-status.json:6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/lane-status.json:6`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/lane-status.json:6`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/lane-status.json:6`.
   - Evidence: Gitoxide maps `1120` upstream cases while status reports `2082` PHP assertions; LightningCSS maps `400` upstream-aligned checks while status reports `499` PHP assertions; markerPDF maps `78` repository/source semantics while status reports `173` PHP behavior tests; Readability maps `637` local assertions against a `1984` Mocha-test denominator but status reports `70` PHP behavior tests. The dashboard collapses these units into one pass/mapped presentation.
   - Goal requirement at risk: `goal.md` requires real upstream denominators, mapped upstream tests, and PHP passing/failing counts.
   - Audit judgment: split the schema into upstream denominator, mapped upstream cases, PHP behavior tests, PHP assertions, failures, full upstream runner parity, bounded runner evidence, and static inventory before using percentages for planning.

6. **Medium - manifest runner metadata is still inconsistent.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:161`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:93`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:175`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:181`.
   - Evidence: `runnerStatus` is an object for several lanes, a raw string for Gitoxide, markerPDF, and Quadrable, and absent/null for Pandoc, which embeds runner status in `warning`.
   - Goal requirement at risk: `goal.md` asks for a durable coordination system and generated dashboard backed by lane manifests.
   - Audit judgment: normalize manifest schemas before adding more dashboard automation.

7. **Medium - bounded/static evidence can still be mistaken for upstream parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:204`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:264`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:342`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:500`.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, rclone, and Syncthing still lack full upstream runner parity. Their bounded runner/static inventories are useful progress signals, but they are not the full upstream source of truth.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: keep bounded evidence visible, but prevent dashboard percentages or labels from implying full native/upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Observed results:

```text
First run exit status: 1
139 test files, 12271 assertions, 12 failures

Final rerun exit status: 0
140 test files, 12359 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Pick the current green dirty state apart lane by lane, commit or reject each batch with `php tools/run-tests.php` rerun after every accepted batch, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that exact accepted checkout. Normalize evidence fields before continuing broad implementation work.
