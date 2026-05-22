# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: this is a moving shared checkout. At the start of this audit, `HEAD` was `9a972d7` (`Port gitoxide commit body trailer helpers`); while auditing and running tests it advanced at least through `42ae9c5` (`Stamp libsqlite lane status`). The required PHP harness is green for the checkout tested during the run, but more commits landed afterward; treat the result as diagnostic evidence, not an accepted integration checkpoint.

## Findings

1. **Critical - the green root test result is attached to a moving dirty aggregate, not a reviewed integration slice.**
   - Paths: `progress.md:194`, `progress.md:199`, `porting.html:30`, `porting.html:53`, `porting-summary.json:2`, `lanes/dolt/lane-status.json:13`, `lanes/libsqlite/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`, `lanes/quadrable/lane-status.json:13`, `lanes/syncthing/lane-status.json:13`.
   - Evidence: `git status --short` reported 139 changed/untracked paths in the latest sample, including broad dirty lane implementation batches, manifests/status files, generated dashboard artifacts, evidence files, and fixtures. `HEAD` advanced from `9a972d7` to at least `42ae9c5` during this audit window.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests, accurate latest commits, cleanup of unrelated changes, and progress/status generated from accepted state.
   - Audit judgment: do not publish this aggregate as the portfolio baseline. Freeze or explicitly coordinate writers, then accept or reject one lane batch at a time with a root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale against current manifests and lane statuses.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:6`, `porting-summary.json:207`.
   - Evidence: the dashboard still shows average progress `14.3%` and `Generated: 2026-05-22 15:40:20 UTC`; current lane-status estimates average about `46.1%`. Current manifest mapped counts are Difftastic `93`, Dolt `114`, esbuild `108`, Gitoxide `1120`, libsqlite `96`, LightningCSS `400`, markerPDF `78`, Pandoc `172`, Quadrable `55`, rclone `163`, Readability `643`, and Syncthing `151`; the dashboard still shows `15`, `5`, `16`, `737`, `18`, `78`, `11`, `19`, `24`, `20`, `89`, and `27`. The dashboard also still shows LightningCSS denominator `312` vs manifest `3532`, markerPDF `27` vs `78`, Pandoc `1979` vs `2028`, and Syncthing `264` vs `658`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate `porting.html` and `porting-summary.json` only from the next accepted green checkpoint.

3. **High - `progress.md` still presents obsolete lane estimates, blockers, and next tasks.**
   - Paths: `progress.md:31`, `progress.md:42`, `lanes/gitoxide/lane-status.json:4`, `lanes/lightningcss/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`, `lanes/quadrable/lane-status.json:4`, `lanes/rclone/lane-status.json:4`.
   - Evidence: the Active Lanes table still shows Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`. Current lane statuses report Gitoxide `85%`, Quadrable `55%`, libsqlite `50%`, rclone `49%`, Pandoc/LightningCSS `47%`, Syncthing `46%`, markerPDF `41%`, Readability `38%`, Difftastic/Dolt `32%`, and esbuild `31%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines. The lane table needs a supervisor refresh from the next accepted green checkpoint.

4. **High - lane status blockers and latest-commit fields are not trustworthy enough for integration control.**
   - Paths: `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:13`, `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/markerpdf/lane-status.json:10`, `lanes/markerpdf/lane-status.json:12`, `lanes/quadrable/lane-status.json:13`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/syncthing/lane-status.json:13`.
   - Evidence: several lanes still encode prose or stale blockers instead of accepted commit IDs: Dolt says its root gate is blocked by an unrelated Syncthing failure, markerPDF says “this lane batch” instead of a commit hash, and this audit's root run was green for the tested checkout. Gitoxide and Syncthing use “current lane commit” or pending prose instead of stable accepted hashes.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and periodic repo-wide test failures recorded honestly.
   - Audit judgment: normalize every lane status after accepting/rejecting dirty batches. Use exact commit hashes and one shared root test result for the accepted checkpoint.

5. **High - evidence units remain mixed across upstream mapped cases, PHP behavior tests, and assertion counts.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/lane-status.json:6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/lane-status.json:6`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/lane-status.json:6`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/lane-status.json:6`.
   - Evidence: Gitoxide maps `1120` upstream cases while status reports `2111` PHP assertions; LightningCSS maps `400` upstream-aligned checks while status reports `508` PHP assertions; markerPDF maps `78` source/dependency semantics while status reports `173` PHP behavior tests; Readability maps `643` local assertions against a `1984` Mocha-test denominator while status reports `71` PHP behavior tests. The dashboard collapses these units into one pass/mapped presentation.
   - Goal requirement at risk: `goal.md` requires real upstream denominators, mapped upstream tests, and PHP passing/failing counts.
   - Audit judgment: split the schema into upstream denominator, mapped upstream cases, PHP behavior tests, PHP assertions, failures, full upstream runner parity, bounded runner evidence, and static inventory before using percentages for planning.

6. **Medium - manifest runner metadata is still inconsistent and can mislead dashboard automation.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:163`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:98`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:177`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:184`.
   - Evidence: `runnerStatus` is a structured object for several lanes, a raw string for Gitoxide, markerPDF, and Quadrable, and omitted from Pandoc's top-level runner fields. Full runner pass, bounded runner evidence, failed probe, and static inventory are encoded differently across lanes.
   - Goal requirement at risk: `goal.md` asks for a durable coordination system and generated dashboard backed by lane manifests.
   - Audit judgment: normalize manifest schemas before adding more dashboard automation.

7. **Medium - bounded/static evidence can still be mistaken for upstream parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:163`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`.
   - Evidence: Difftastic, Gitoxide, markerPDF, Pandoc, rclone, Dolt, and Syncthing still lack full upstream runner parity. Their bounded runner/static inventories are useful progress signals, but they are not the full upstream source of truth.
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

Observed result:

```text
Exit status: 0
140 test files, 12437 assertions, 0 failures
```

This is not an accepted integration checkpoint because `HEAD` advanced during and after the test run and the worktree remains broadly dirty.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Accept or reject each dirty lane batch one at a time, rerun `php tools/run-tests.php` after every accepted batch, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that exact accepted checkout. Normalize evidence fields before continuing broad implementation work.
