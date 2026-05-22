# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history through at least `9f9a539` (`Stamp quadrable gc status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: this is still a moving shared checkout. `HEAD` was `f5b35e7` during the first required root test run, then advanced through at least `9f9a539` while this audit was being written. `git status --short` moved between 160 and 200 entries during the audit, with 189 entries in the latest sampled status after another root rerun. The latest root PHP harness run is green, but it is green on a moving dirty aggregate, not an accepted integration checkpoint.

## Findings

1. **Critical - the root test signal changed from red to green during a moving dirty checkout.**
   - Paths: `lanes/esbuild/tests/TypeScriptModuleLowererTest.php:1088`, `lanes/esbuild/tests/TypeScriptModuleLowererTest.php:1094`, `lanes/esbuild/lane-status.json:10`, `lanes/esbuild/lane-status.json:12`, `porting.html:55`.
   - Evidence: the first required run of `php tools/run-tests.php` exited `1` with `141 test files, 12615 assertions, 1 failures`, failing `lowers wordpress function scoped disposable asset handles without node` in `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`. A focused diagnostic reported: expected substring `const settings = {name:metadata.name, viewScript:previewAsset.url};`; actual output preserved spaces as `const settings = { name: metadata.name, viewScript: previewAsset.url };`. After additional commits landed and the dirty status count changed, a rerun of the same root harness produced `143 test files, 12738 assertions, 0 failures`.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and honest periodic repo-wide test records.
   - Audit judgment: do not treat the final green result as portfolio acceptance. Verify the esbuild function-scoped `using` slice in isolation, then accept or reject one lane batch at a time from a quiesced checkout.

2. **Critical - the dirty worktree is not an integration slice and changed during the audit.**
   - Paths: `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/esbuild/tests/TypeScriptModuleLowererTest.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/rclone/src/SyncPlan.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, `progress.md:199`, `progress.md:230`.
   - Evidence: the worktree has broad modified lane implementation files, manifests/status files, generated dashboard artifacts, and 100+ untracked evidence files. The status count moved between 160 and 200 during this audit, and `HEAD` advanced from `f5b35e7` through at least `9f9a539`.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices, cleanup of unrelated changes, accurate latest commits, and progress/status generated from accepted green state.
   - Audit judgment: freeze or explicitly coordinate writers before attempting integration. Accept or reject one lane batch at a time.

3. **High - `porting.html` and `porting-summary.json` are stale against the current lane statuses and manifests.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`.
   - Evidence: the dashboard still says `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`, while current lane-status estimates average about `47.5%`. Examples: Difftastic renders `8.0%`, `15 / 404` mapped, but status says `33%` and `93` PHP tests; Gitoxide renders `66.0%`, `1257 pass`, `737 / 2877` mapped, but status says `86%`, `2171` assertions, and the manifest maps `1140`; LightningCSS renders denominator `312` and mapped `78`, while the manifest says `3532` and `415`; Syncthing renders `264` and `27`, while the manifest/status say `658` and `151`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate dashboard artifacts only after a single accepted green checkpoint exists.

4. **High - `progress.md` still has stale lane estimates, next tasks, and status language.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:37`, `progress.md:38`, `progress.md:41`, `progress.md:42`, `lanes/gitoxide/lane-status.json:4`, `lanes/lightningcss/lane-status.json:4`, `lanes/quadrable/lane-status.json:4`, `lanes/syncthing/lane-status.json:4`.
   - Evidence: the Active Lanes table still reports Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, Quadrable `8%`, Syncthing `8%`, Dolt `5%`, and esbuild `8%`. Current lane status files report Gitoxide `86%`, LightningCSS `47%`, markerPDF `41%`, Quadrable `55%`, Syncthing `46%`, Dolt `32%`, and esbuild `31%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/blocker/intervention lines. The lane table needs a supervisor refresh from the next accepted green checkpoint.

5. **High - lane status files disagree about the root gate and latest integration state.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:13`, `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:13`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:13`, `lanes/esbuild/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`, `lanes/syncthing/lane-status.json:13`, `lanes/markerpdf/lane-status.json:13`.
   - Evidence: Difftastic still cites a root failure in LightningCSS; Gitoxide cites a root failure in Dolt; Dolt and Readability cite a root failure in Syncthing; esbuild, Syncthing, LightningCSS, markerPDF, Quadrable, rclone, and libsqlite claim green root runs from different snapshots. The latest audit rerun is green, but it superseded an earlier esbuild root failure during the same audit window. Several `latestCommit` fields are prose rather than accepted commit hashes, including `this lane batch` and `current lane commit`.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, latest commit, and repo-wide test failures recorded honestly.
   - Audit judgment: after fixing the current red root, normalize every lane status to one shared root result and accepted commit hash.

6. **Medium - evidence units remain mixed across upstream mapped cases, PHP tests, and assertion counts.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/lane-status.json:5`, `lanes/readability/lane-status.json:6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/lane-status.json:6`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/lane-status.json:6`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/rclone/lane-status.json:6`.
   - Evidence: Readability manifest maps `643` checks while lane status reports `71` PHP tests; LightningCSS manifest maps `415` upstream-aligned checks while status reports `508` assertions; Gitoxide manifest maps `1140` upstream cases while status reports `2171` assertions; rclone manifest maps `167` while status reports `164` PHP behavior tests. The dashboard collapses these units into one pass/mapped display.
   - Goal requirement at risk: `goal.md` requires real upstream denominators, mapped upstream tests, and PHP passing/failing counts.
   - Audit judgment: split the schema into upstream denominator, mapped upstream cases, PHP behavior tests, PHP assertions, failures, full runner parity, bounded runner evidence, and static inventory before using percentages for planning.

7. **Medium - full upstream parity gaps are still easy to overread as completion.**
   - Paths: `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/lane-status.json:12`, `lanes/difftastic/lane-status.json:4`, `lanes/difftastic/lane-status.json:12`, `lanes/dolt/lane-status.json:4`, `lanes/dolt/lane-status.json:12`, `lanes/markerpdf/lane-status.json:4`, `lanes/markerpdf/lane-status.json:12`, `lanes/syncthing/lane-status.json:4`, `lanes/syncthing/lane-status.json:12`.
   - Evidence: Gitoxide is at `86%` despite no full cargo workspace runner; Difftastic is at `33%` with no full upstream runner; Dolt is at `32%` with no full Go/BATS runners; markerPDF is at `41%` without the full benchmark runner; Syncthing is at `46%` without `go test ./...`. These percentages may be useful local progress signals, but they are not upstream parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: keep bounded/static evidence visible, but do not let dashboard percentages imply full native/upstream parity.

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
First full run exit status: 1
141 test files, 12615 assertions, 1 failures
FAIL lowers wordpress function scoped disposable asset handles without node (lanes/esbuild/tests/TypeScriptModuleLowererTest.php)

Latest rerun after additional commits landed:
143 test files, 12738 assertions, 0 failures
```

Focused diagnostic failure:

```text
String does not contain 'const settings = {name:metadata.name, viewScript:previewAsset.url};'
Haystack: export function registerPreviewAsset(metadata) {
  using previewAsset = acquirePreviewAsset(metadata.viewScript);
  const settings = { name: metadata.name, viewScript: previewAsset.url };
  wp.blocks.registerBlockType(settings.name, settings);
}
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Verify the esbuild function-scoped disposable asset slice that was red earlier in this audit, then accept or reject each dirty lane batch one at a time with `php tools/run-tests.php` rerun after every accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that exact accepted green checkout.
