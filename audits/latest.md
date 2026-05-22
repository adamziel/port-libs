# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history through `e6cc3bb` (`esbuild: update root verification count`) before this audit metadata commit. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required root PHP harness is green on the current checkout, but this is not a clean integration checkpoint. `HEAD` moved during the audit from `d2fb6db` to `e6cc3bb` before the audit metadata commit, and `git status --porcelain=v1` still reports `111` entries with a tracked diff of `32 files changed, 2211 insertions(+), 137 deletions(-)`, plus many untracked evidence files and fixtures.

## Findings

1. **Critical - the green root suite is attached to a moving, very dirty integration state.**
   - Paths: `audits/integration-status.md`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/CommitLogTable.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/markerpdf/src/TableRecognizer.php`, `lanes/quadrable/src/QuadbStore.php`, `lanes/rclone/src/SyncPlan.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, `progress.md`.
   - Evidence: the root run now passes, but the worktree still has `111` porcelain entries and the tracked dirty diff spans `32` files. Recent commits advanced while this audit was in progress (`d2fb6db` to `e6cc3bb`), so the result proves the current dirty checkout ran, not that any individual lane batch has been reviewed and accepted.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, accepted commits, and coordination status generated from accepted state.
   - Audit judgment: freeze or explicitly coordinate writers, then accept or reject one lane batch at a time. Do not treat the dirty aggregate as an integrated milestone.

2. **High - `porting.html` and `porting-summary.json` are stale enough to misdirect planning.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`.
   - Evidence: both generated files still say `Generated: 2026-05-22 15:40:20 UTC` and average progress `14.3%`. Current manifests/status files disagree materially: Difftastic dashboard `15 / 404` vs manifest `84 / 404`; Dolt `5 / 613` vs `101 / 613`; esbuild `16 / 2567` vs `99 / 2567`; Gitoxide `737 / 2877` vs `1077 / 2877`; libsqlite `18 / 1454` vs `85 / 1454`; LightningCSS denominator `312` vs `3532` and mapped `78` vs `363`; markerPDF `11 / 27` vs `78` mapped; Pandoc `19 / 1979` vs `156 / 2028`; Quadrable `24 / 55` vs `55 / 55`; rclone `20 / 327` vs `151 / 327`; Readability `89 / 1984` vs `613 / 1984`; Syncthing `27 / 264` vs `129 / 264`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit status, current work, blocker, and commit for every lane.
   - Audit judgment: regenerate the dashboard only after the accepted dirty batches are committed or rejected from a green state.

3. **High - `progress.md` still presents stale lane estimates and next tasks.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`.
   - Evidence: the Active Lanes table still shows older estimates such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, Dolt `5%`, and esbuild `8%`. Current lane status files report Gitoxide `84%`, LightningCSS `44%`, markerPDF `37%`, libsqlite `45%`, Quadrable `47%`, rclone `45%`, Pandoc `42%`, Syncthing `41%`, Readability `35%`, Dolt `28%`, Difftastic `27%`, and esbuild `28%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines. The lane table needs a supervisor refresh from an accepted green state.

4. **High - lane status files contradict the current root-test state and each other.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/gitoxide/lane-status.json:10`, `lanes/markerpdf/lane-status.json:10`, `lanes/quadrable/lane-status.json:10`, `lanes/rclone/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: the current root run is green with `129 test files, 11636 assertions, 0 failures`, but many lane status strings still cite older root totals such as `129 / 11605` or `128 / 11591`, and rclone still cites earlier LightningCSS failures.
   - Goal requirement at risk: `goal.md` requires precise blockers and periodic repo-wide test failures recorded honestly.
   - Audit judgment: after accepting or rejecting the dirty batches, normalize lane status audit/blocker strings to one root-suite result from the same accepted checkout.

5. **High - evidence fields still mix upstream mapped counts, PHP behavior tests, and assertion counts.**
   - Paths: `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:613`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:147`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:253`, `lanes/gitoxide/lane-status.json:6`.
   - Evidence: LightningCSS maps `363` upstream-aligned checks but reports `466` local PHP assertions; Readability maps `613` local assertions but status reports `68` behavior tests; markerPDF reports `mapped: 78`, warning text says `96` focused semantics plus benchmark pairs, and `phpBehaviorTests` is `159`; Gitoxide status `phpPass` is `2055` assertions while its manifest maps `1077` upstream entries.
   - Goal requirement at risk: `goal.md` requires real upstream denominators, mapped upstream tests, PHP passing/failing counts, and honest dashboard columns.
   - Audit judgment: split schema fields for upstream denominator, mapped upstream cases, PHP behavior tests, PHP assertions, failures, full upstream runner parity, bounded runner evidence, and static inventory before percentages drive planning.

6. **Medium - bounded/static upstream evidence still reads too close to parity in several lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:85`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:166`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:169`.
   - Evidence: Difftastic, markerPDF, Pandoc, Gitoxide, rclone, and Syncthing all have useful inventories or bounded runners, but not full upstream runner parity. rclone's bounded evidence excludes live providers/mount/FUSE/Docker; Gitoxide lacks full workspace Cargo parity; Pandoc and Syncthing remain no-checkout/static for full-runner purposes; markerPDF still depends on heavy upstream ML/PDF stacks for live conversion.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: keep bounded evidence visible, but label it separately from full upstream parity and native PHP parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

```text
Exit status: 0
129 test files, 11636 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then accept or reject the dirty lane batches one lane at a time. After each accepted batch, rerun `php tools/run-tests.php`; once the accepted state is green, regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same checkout. Then normalize the evidence schema so upstream runner parity, bounded evidence, static inventory, PHP behavior tests, assertions, and failures are separate fields.
