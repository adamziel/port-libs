# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected lane statuses, current dirty worktree state, bridge/shell-out usage, and recent Git history through `d7ef780` before this audit update. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the repository is still a shared dirty integration target. This report treats lane implementation changes as producer work, not auditor work, and anchors status to the root test runs below.

## Findings

1. **Critical - the integration state changed under audit, so the final green suite is not yet an accepted baseline.**
   - Paths: `lanes/readability/tests/ArticleExtractorTest.php:151`, `lanes/readability/tests/ArticleExtractorTest.php:167`, `lanes/readability/src/ArticleExtractor.php`, `progress.md:194`, `progress.md:199`, `porting.html:32`.
   - Evidence: the first audit run of `php tools/run-tests.php` exited 1 with `104 test files, 7108 assertions, 1 failures` in the new Readability `remove-aria-hidden` fixture. A later rerun, after concurrent lane changes outside the auditor edits, exited 0 with `104 test files, 7203 assertions, 0 failures`. The test is now narrower, comparing normalized expected/actual paragraph nodes instead of full content text.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, precise blockers, and status generated from the accepted state.
   - Audit judgment: treat the final green run as useful evidence, but not as a publishable integration checkpoint until the dirty Readability batch and other concurrent lane batches are explicitly accepted or rejected.

2. **Critical - `porting.html` and `porting-summary.json` are stale against the manifests and current test state.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC`, but current manifests report different mapped counts: difftastic `51 vs 15`, Dolt `64 vs 5`, esbuild `58 vs 16`, Gitoxide `912 vs 737`, libsqlite `52 vs 18`, LightningCSS `166 vs 78`, markerPDF `72 vs 11`, Pandoc `112 vs 19`, Quadrable `55 vs 24`, rclone `88 vs 20`, Readability `286 vs 89`, and Syncthing `82 vs 27`. Denominators are stale too, including LightningCSS `382 vs 312`, markerPDF `78 tracked paths plus 2 actual CI pairs vs 27`, and Pandoc `2028 vs 1979`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard and summary only after the dirty lane acceptance problem is resolved against one final green root-suite run.

3. **High - status surfaces disagree about the same root-suite blocker.**
   - Paths: `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`.
   - Evidence: lane statuses variously claim the root suite passed with older counts, failed in Pandoc, failed in esbuild, or is blocked by an unrelated Pandoc failure. The latest audit rerun is `104 test files, 7203 assertions, 0 failures`, after an earlier same-turn red run in Readability.
   - Goal requirement at risk: `goal.md` requires precise blockers, current owner/session state, and a generated dashboard/status system that stays honest.
   - Audit judgment: stop copying per-lane root-suite anecdotes into lane blockers. Record one root integration result and link lanes to it, or every lane status will keep drifting.

4. **High - the dirty worktree is too broad to be a reviewable integration unit.**
   - Paths: `audits/integration-status.md`, `lanes/esbuild/src/TypeScriptNamespaceLowerer.php`, `lanes/gitoxide/src/GitObject.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/src/ReceiveEncrypted.php`, `porting.html`, `porting-summary.json`.
   - Evidence: `git status --short --untracked-files=all` shows many modified and untracked implementation, manifest, status, dashboard, fixture, and audit files across multiple lanes. The final suite is green, but the changes are not small, isolated, or reflected in the dashboard/progress surfaces.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and visible progress generated from the accepted state.
   - Audit judgment: quiesce or explicitly coordinate writers, then accept/reject lane batches one lane at a time with a fresh root test after each accepted batch.

5. **High - Gitoxide progress remains over-broad relative to upstream runner proof.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/lane-status.json:5`, `lanes/gitoxide/lane-status.json:12`.
   - Evidence: Gitoxide is priority 1 and reports `2877` denominator items, `912` mapped items, and `77%` estimated progress, but upstream runner evidence is bounded to `gix-object`: 10 library tests plus 2 filtered integration tests. Full workspace Cargo tests remain unexecuted and major surfaces remain unported, including broader SSH authentication/channel integration, broader git-daemon runtime coverage, OFS_DELTA pack writing, full delta reuse/search heuristics, thin-pack repair, sparse-index writing, and full merge semantics.
   - Goal requirement at risk: `goal.md` scopes Gitoxide as a Git implementation with packfiles, refs, commits, object database, protocol v2, sparse/partial clone, push, merge, and server primitives, and says upstream tests should be the source of truth where possible.
   - Audit judgment: keep the lane moving, but lower dashboard confidence unless the next slice is a controlled upstream crate/integration runner expansion or the status schema separates bounded runner evidence from native implementation breadth.

6. **Medium - upstream runner evidence is still modeled inconsistently.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:111`.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in some manifests, a string in Gitoxide/Quadrable/markerPDF, and absent/null in Pandoc. Dolt sets `executed: true` while the reason starts with "The full upstream runners were not executed." Several manifests mix full upstream pass parity, bounded upstream evidence, static inventories, native PHP behavior-test counts, and assertion counts.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize evidence into separate fields for full upstream pass parity, bounded upstream runner evidence, static inventory, native behavior tests, assertions, and failures before using percentages for portfolio decisions.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match was `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Runs

Command: `php tools/run-tests.php`

First audit run:

```text
Exit status: 1
104 test files, 7108 assertions, 1 failures
```

Failure was the Readability `maps Mozilla remove-aria-hidden fixture during extraction cleanup` case in `lanes/readability/tests/ArticleExtractorTest.php`.

Final audit rerun:

```text
Exit status: 0
104 test files, 7203 assertions, 0 failures
```

## Recommended Next Intervention

Quiesce or explicitly coordinate writers, accept or reject the dirty lane batches one at a time, rerun `php tools/run-tests.php` after each accepted batch, and regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from one accepted green state. Then resume higher-value parity work: controlled Gitoxide upstream runner expansion and markerPDF document-level extraction against the two CI benchmark pairs.
