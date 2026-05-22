# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, process-execution bridge usage, and recent Git history through `46dc1eb` (`Record integration hold status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required PHP harness is green on the current checkout, but this is not an accepted integration checkpoint. `HEAD` advanced while the audit was running, the worktree remains broad and dirty, and the visible dashboard/progress/status surfaces disagree with current manifests and each other.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale enough to mislead portfolio decisions.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:61`, `porting.html:63`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:11`, `porting-summary.json:62`, `porting-summary.json:95`, `porting-summary.json:112`, `porting-summary.json:147`, `porting-summary.json:181`, `porting-summary.json:198`.
   - Evidence: both generated files still say `Generated: 2026-05-22 15:40:20 UTC` and average progress `14.3%`. Current manifests/status files report much newer values: Difftastic `78 / 404` mapped but dashboard `15 / 404`; Dolt `95 / 613` but dashboard `5 / 613`; esbuild `91 / 2,567` but dashboard `16 / 2,567`; Gitoxide `1074 / 2877` with `1970` PHP assertions but dashboard `737 / 2877` and `1257 pass`; libsqlite `77 / 1454` but dashboard `18 / 1454`; LightningCSS `292 / 3532` but dashboard `78 / 312`; markerPDF `78 / 78` with manifest `153` behavior tests but dashboard `11 / 27`; rclone `135 / 327` but dashboard `20 / 327`; Readability `539 / 1984` but dashboard `89 / 1984`; Syncthing `116 / 264` but dashboard `27 / 264`; Quadrable now records upstream `make -r test` passing and `55 / 55` mapped but dashboard still shows the old C++ runner failure and `24 / 55`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit status, current work, blocker, and commit for every lane.
   - Audit judgment: regenerate `porting.html` and `porting-summary.json` only after accepting/rejecting the current dirty lane batches, so the public status is derived from the same tested state.

2. **Critical - the current dirty worktree is too broad and too mobile to be a reviewable integration unit.**
   - Paths: `lanes/difftastic/src/TokenDiffer.php`, `lanes/gitoxide/src/ReferenceStore.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/markerpdf/src/TableRecognizer.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/rclone/src/SyncPlan.php`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/src/BepSession.php`, `porting.html`, `porting-summary.json`, plus many untracked evidence/example files.
   - Evidence: excluding this audit/progress edit, the current tracked diff is `35 files changed, 2439 insertions(+), 206 deletions(-)`. `git status --short` has `100` entries: `35` modified tracked paths and `65` untracked paths. Recent history advanced during the audit from `be5d051` to `46dc1eb`, adding multiple lane commits while unrelated lane implementation changes remain dirty.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, and generated progress/status from accepted state.
   - Audit judgment: do not batch-commit this portfolio state. Accept or reject one lane batch at a time, rerun the root harness after each accepted batch, and then regenerate coordination outputs.

3. **High - `progress.md` still presents stale lane estimates and next tasks.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`.
   - Evidence: the Active Lanes table still shows old estimates/tasks such as Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, Dolt `5%`, and esbuild `8%`. Current lane statuses report materially different progress: Gitoxide `82%`, LightningCSS `42%`, markerPDF `35%`, libsqlite `42%`, Quadrable `43%`, rclone `42%`, Syncthing `37%`, Readability `34%`, Dolt `25%`, and esbuild `28%`.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, next task per lane, percentage estimates, owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention lines. The lane table needs a supervisor refresh from the accepted manifest/status state.

4. **High - lane status files contain stale and contradictory root-suite evidence.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/markerpdf/lane-status.json:12`, `lanes/rclone/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/pandoc/lane-status.json:12`.
   - Evidence: the current required run is `124 test files, 10849 assertions, 0 failures`. Status files still preserve older and conflicting root evidence, including Difftastic saying a final root rerun failed with `10659` assertions and `5` unrelated failures, markerPDF saying root was blocked with `9513` assertions and `24` failures, and several lanes citing older green counts such as `9537`, `10675`, `10689`, or `10673` assertions.
   - Goal requirement at risk: `goal.md` requires precise blockers and honest repo-wide test recording.
   - Audit judgment: lane status audit/blocker strings should be normalized after each accepted integration slice instead of preserving every transient root count as current truth.

5. **High - upstream evidence and native PHP counts remain inconsistently modeled.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:257`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/lightningcss/lane-status.json:6`, `lanes/gitoxide/lane-status.json:6`.
   - Evidence: manifest `total` values alternate between numeric denominators and prose strings. `runnerStatus` is sometimes a structured object, sometimes a string, and sometimes only a warning. Native counts mix units: Dolt and markerPDF expose behavior-test counts, Gitoxide and LightningCSS expose assertion counts in `phpPass`, and several manifests omit a machine-readable native pass/assertion/failure split entirely.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, and honest dashboard columns.
   - Audit judgment: split full upstream runner parity, bounded runner evidence, static inventory, native behavior tests, native assertions, and native failures into separate schema fields before percentages drive priorities.

6. **High - `mapped == total` still overstates parity for inventory-shaped denominators.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:155`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`.
   - Evidence: markerPDF reports `78 / 78` mapped, but the denominator is repository/path inventory plus two CI PDF/reference pairs and the full benchmark runner is still `not-executed`. Quadrable reports `55 / 55` and has useful upstream runner evidence, but its own warning says native PHP parity is still partial and broader LMDB/quadb behavior remains.
   - Goal requirement at risk: `goal.md` says hard features must not be silently skipped and bridge/generated/shallow evidence must not count as native implementation progress.
   - Audit judgment: these are useful evidence sets, but `mapped == total` must not render as complete port parity unless the denominator is behavior-level and remaining semantic gaps are represented separately.

7. **Medium - several priority lanes still lack full upstream runner parity despite better bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:79`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:154`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:149`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:150`.
   - Evidence: Difftastic, markerPDF, Pandoc, and Syncthing still have no full upstream runner pass. Gitoxide has bounded crate/subset evidence, not full workspace Cargo parity. rclone and Dolt have valuable bounded runner evidence, not full provider/mount or full Go/BATS parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: preserve the bounded/static/full-runner distinction in manifests, status, and dashboard copy before continuing implementation breadth.

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
124 test files, 10849 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers before integration. Accept or reject the current dirty lane batches one lane at a time, rerun `php tools/run-tests.php` after each accepted batch, and regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same accepted green state. Normalize the evidence schema before using mapped percentages for work selection.
