# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the required root PHP harness is green in the current dirty checkout, but this is not an accepted integration checkpoint. `HEAD` is `3d01f37` (`Stamp rclone duplicate-name dedupe status`) and `git status --porcelain=v1` shows `177` entries, including lane implementation files, generated dashboard artifacts, and many untracked evidence/fixture files.

## Findings

1. **Critical - the green root suite is attached to a broad dirty aggregate, not a reviewed integration slice.**
   - Paths: `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/DiffSummaryRenderer.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/esbuild/src/TypeScriptNamespaceLowerer.php`, `lanes/gitoxide/src/SmartHttpReceivePackTransport.php`, `lanes/libsqlite/src/SQLiteCreateIndex.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/markerpdf/src/HeaderFooterCleaner.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/pandoc/src/WordPressBlockWriter.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, `progress.md`.
   - Evidence: `php tools/run-tests.php` now exits `0` with `135 test files, 12069 assertions, 0 failures`, but the status sample still shows `177` porcelain entries across implementation, manifest/status, dashboard, audit, evidence, and fixture files. Recent history has already advanced to `3d01f37`, while many lane statuses still describe uncommitted work or commits blocked by older root failures.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests, accurate latest commits, cleanup of unrelated changes, and progress/status generated from accepted state.
   - Audit judgment: do not treat the dirty aggregate as accepted just because the harness is green. Freeze or coordinate writers, then accept or reject one lane batch at a time with a rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against the current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:1`.
   - Evidence: the dashboard still shows average progress `14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. Current lane status estimates average about `43.6%`. Current manifest mapped counts are Difftastic `87`, Dolt `107`, esbuild `106`, Gitoxide `1106`, libsqlite `90`, LightningCSS `376`, markerPDF `78`, Pandoc `164`, Quadrable `55`, rclone `161`, Readability `628`, and Syncthing `141`; the dashboard still shows `15`, `5`, `16`, `737`, `18`, `78`, `11`, `19`, `24`, `20`, `89`, and `27` respectively. LightningCSS is especially misleading: dashboard `78 / 312` versus manifest `376 / 3532`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate `porting.html` and `porting-summary.json` only after a clean accepted state is selected, not from the current mixed dirty tree.

3. **High - `progress.md` still gives obsolete lane estimates, blockers, and next tasks.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:235`.
   - Evidence: the Active Lanes table still shows old estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. Current lane statuses report Gitoxide `84%`, Quadrable `49%`, libsqlite `47%`, rclone `47%`, LightningCSS `45%`, Pandoc `44%`, Syncthing `43%`, markerPDF `38%`, Readability `36%`, and Difftastic/Dolt/esbuild at `30%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines. The lane table needs a supervisor refresh from the next accepted green checkpoint.

4. **High - lane status blockers/audit fields still cite obsolete root evidence.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:10`, `lanes/esbuild/lane-status.json:12`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/markerpdf/lane-status.json:10`, `lanes/pandoc/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`, `lanes/syncthing/lane-status.json:12`.
   - Evidence: this audit's root run is green with `135 test files, 12069 assertions, 0 failures`, but lane statuses cite older results including `135 / 12012 / 2`, `135 / 11999 / 2`, `135 / 11973 / 1`, `133 / 11878 / 1`, `133 / 11871 / 2`, and `133 / 11798 / 7`. Several `latestCommit` fields still say an uncommitted batch is blocked by an older root-red state.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and periodic repo-wide test failures recorded honestly.
   - Audit judgment: normalize every lane status after accepting/rejecting dirty batches. The current root blocker is gone, but the integration blocker remains: the dirty aggregate has not been reviewed and committed lane-by-lane.

5. **High - evidence units are still mixed across upstream mapped cases, PHP behavior tests, and assertion counts.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/lane-status.json:6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/lane-status.json:6`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/lane-status.json:6`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/lane-status.json:6`.
   - Evidence: Gitoxide maps `1106` upstream cases but status reports `2070` PHP assertions; LightningCSS maps `376` upstream-aligned checks but status reports `481` PHP assertions; Readability maps `628` checks but status reports `69` PHP behavior tests; markerPDF maps `78` repository/source semantics but status reports `165` PHP behavior tests; esbuild maps `106` upstream cases while status reports `102` PHP behavior tests. The dashboard collapses these units into one pass/mapped presentation.
   - Goal requirement at risk: `goal.md` requires real upstream denominators, mapped upstream tests, and PHP passing/failing counts.
   - Audit judgment: split the schema into upstream denominator, mapped upstream cases, PHP behavior tests, PHP assertions, failures, full upstream runner parity, bounded runner evidence, and static inventory before using percentages for planning.

6. **Medium - manifest runner metadata remains inconsistent for generated tooling.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12`.
   - Evidence: `runnerStatus` is an object for several lanes, a raw string for Gitoxide and Quadrable, and absent/null for Pandoc, which instead embeds runner status in `warning`. This makes reliable dashboard generation and audit comparisons brittle.
   - Goal requirement at risk: `goal.md` asks for a durable coordination system and generated dashboard backed by lane manifests.
   - Audit judgment: normalize manifest schemas before adding more automation or dashboard fields.

7. **Medium - bounded/static evidence is still being allowed to read like parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, rclone, and Syncthing still lack full upstream runner parity. rclone and Syncthing have useful bounded runner evidence; Gitoxide has focused crate probes; markerPDF and Pandoc remain static or surrogate-heavy. These are valid progress signals, but they are not the full upstream source of truth.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: keep bounded evidence visible, but do not let percentages or dashboard labels imply full upstream/native parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Final result:

```text
Exit status: 0
135 test files, 12069 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then accept or reject the dirty lane batches one lane at a time while rerunning `php tools/run-tests.php` after each accepted batch. Once the accepted state is green and committed, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that exact checkout, then normalize the evidence schema so upstream runner parity, bounded evidence, static inventory, PHP behavior tests, assertions, and failures are separate fields.
