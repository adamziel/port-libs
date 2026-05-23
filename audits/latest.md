# Independent Audit - 2026-05-23 00:10 UTC

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate dashboard drift, bridge/shell-out usage, and recent Git history through latest observed `HEAD` `78c880f`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the checkout moved repeatedly while this audit was running. `HEAD` advanced from `cca2021` through `96f76f8`, `914c579`, `9c1ee34`, `0791fda`, and `78c880f`; tracked dirty files changed as concurrent lane batches were committed. Latest observed state before writing: `git status --short` reported `203` entries, `git status --short --untracked-files=no` reported `37` tracked modified entries, and `git diff --shortstat` reported `37 files changed, 5541 insertions(+), 241 deletions(-)`. The latest completed root PHP harness run during this audit was green, but it occurred before the final observed `HEAD` movement, so this is still a moving dirty aggregate, not an accepted integration checkpoint.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not a reviewable integration checkpoint.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/pandoc/tests/MarkdownReaderTest.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`.
   - Evidence: recent history advanced through `26c9fd1`, `c102047`, `a0d65d8`, `96f76f8`, `914c579`, `9c1ee34`, `0791fda`, and `78c880f` during/around this audit. The current worktree still has `37` tracked modified files and `203` total status entries.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49` require small reviewable slices, verification/cleanup before acceptance, and honest repo-wide test recording.
   - Audit judgment: freeze or explicitly coordinate writers, then accept/reject one lane batch at a time with a fresh root rerun after each accepted batch.

2. **High - `porting.html` and `porting-summary.json` are stale and disagree with current manifests/status files.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:61`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`, `porting-summary.json:91`, `porting-summary.json:142`, `porting-summary.json:193`.
   - Evidence: the dashboard still reports `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. It shows Gitoxide at `66%`/`737 mapped`, LightningCSS at `14%`/`78 mapped`, rclone at `9%`/old commit `01ab0eb`, Readability at `12%`/`89 mapped`, Quadrable at `8%`/old C++ blocker text, and Syncthing at `8%`/`27 mapped`; current lane status/manifest files report much newer values such as Gitoxide `90%` and manifest mapped `1212`, LightningCSS `52%` and manifest mapped `524`, rclone `57%`, Readability `46%` and manifest mapped `755`, Quadrable upstream runner pass, and Syncthing `54%`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require visible current status in the browsing dashboard.
   - Audit judgment: do not publish or trust the dashboard until it is regenerated from one accepted green snapshot.

3. **High - `progress.md` still has a stale Active Lanes table.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`.
   - Evidence: the table still says Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Quadrable `8%`, Syncthing `8%`, rclone `9%`, Dolt `5%`, and esbuild `8%`, while current lane statuses range much higher and several next tasks have already been superseded.
   - Goal requirement at risk: `goal.md:44` requires current active lanes, blockers, next task per lane, owner/session, and percentage estimates.
   - Audit judgment: I updated only the latest audit/status and next intervention text. The lane table should be refreshed by the supervisor only after dirty lane batches are accepted or rejected.

4. **High - lane status audit/blocker/latest-commit fields are unreliable as machine-readable truth.**
   - Paths: `lanes/dolt/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:13`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:13`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/readability/lane-status.json:13`, `lanes/rclone/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: LightningCSS and Readability still say the required root harness is red, Dolt records an older red current-HEAD sample, and several lanes report older green root counts (`14074`, `14093`, `14149`, `14165`) while the exact latest run in this audit is `157` files / `14252` assertions / `0` failures. Several `latestCommit` fields are prose (`pending current batch`, root-result narratives, or non-hash descriptions) instead of accepted commit hashes.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:45` require precise blockers, current audit status, and latest commit tracking.
   - Audit judgment: normalize lane status files after integration; until then, treat them as narrative notes.

5. **High - upstream denominator units remain inconsistent, so percentages and mapped counts are not comparable.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: Difftastic counts inspected behavior artifacts; Dolt counts executable files plus BATS cases and fixture/data artifacts; Gitoxide counts upstream files; LightningCSS counts helper invocations and tests as behavior checks; markerPDF counts repository paths, benchmark identifiers, actual pairs, and 0 Python unit tests; Pandoc counts files/artifacts; Quadrable counts tracked paths plus scenarios/checks; Readability counts Mocha tests; rclone counts Go test files; Syncthing counts Go test/benchmark entry points.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:38` require a real upstream denominator, meaningful mapped tests, and honest percentage estimates.
   - Audit judgment: split schema fields for upstream files/artifacts, upstream executable tests, upstream behavior cases, mapped cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - some manifest mapped counts contradict local PHP counts or current root status.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:301`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:194`, `lanes/readability/lane-status.json:6`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:815`, `lanes/lightningcss/lane-status.json:6`.
   - Evidence: markerPDF says `mapped: 78` while warning says native PHP maps `121` source/dependency semantics plus benchmark pairs. Readability manifest uses `mapped: 755`, which is local assertion count, while lane status says `phpPass: 81`, and its warning says root currently fails even though the latest root run in this audit passes. LightningCSS manifest uses `mapped: 524`, while lane status says `phpPass: 581` assertions and the manifest warning says the root harness still needs rerun.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45` require clear mapped upstream tests and PHP pass/fail counts.
   - Audit judgment: stop overloading `mapped` with assertions, semantics, or inventory paths.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:178`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:301`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the manifest explicitly says there are `0` committed Python unit test files and the full benchmark runner is not executed. The warning lists many supplied boundaries: Surya layout/OCR, Texify, pdftext/pypdfium, tabled recognition, image preview/rendering, FastAPI/Uvicorn/requests, and callback-driven conversion/server boundaries.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`, and `goal.md:35` require native standard-PHP progress and a PDF-to-structured-content extraction pipeline, not bridge/generated/shell-out progress.
   - Audit judgment: keep these as oracle/boundary scaffolding unless driven by native document-level extraction against acquired benchmark pairs.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Required command: `php tools/run-tests.php`

Exact latest completed result for this audit run:

```text
Exit status: 0
157 test files, 14252 assertions, 0 failures
```

Because the checkout moved again after this run, this green result is diagnostic evidence for the moving dirty aggregate, not an accepted integration checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. First regenerate `porting.html`, `porting-summary.json`, lane statuses, and the Active Lanes table from the same accepted green snapshot, or explicitly mark them stale. Then integrate or reject dirty lane batches one at a time, with a fresh root rerun and a real commit hash after each accepted batch. In parallel, normalize the manifest/status schema so denominator units, mapped upstream cases, native PHP behavior tests, assertions, failures, and runner parity are separate fields.
