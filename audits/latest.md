# Independent Audit - 2026-05-23 00:40 UTC

Scope reviewed: `goal.md`, `progress.md`, current `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate dashboard drift, bridge/shell-out usage, and recent Git history through latest observed lane/status `HEAD` `e42de6d` before this audit-only commit. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Current audit boundary: the checkout moved repeatedly while this audit was running. `HEAD` advanced from initially observed `6baab7d` through `4986091`, `e334d69`, `36deb5c`, `b7a2690`, `78d4811`, `611ac0e`, `8f41b89`, `c6cfd79`, `84c0b83`, `4a91015`, `c140107`, `de5af8f`, `fd3ab9a`, and latest observed lane/status commit `e42de6d`; tracked dirty files also changed while the audit was running. Latest observed state before writing: `git status --short` reported `218` entries, `git status --short --untracked-files=no` included `41` changed tracked files, and `git diff --shortstat` reported `41 files changed, 5838 insertions(+), 191 deletions(-)`. The root PHP harness is green, but this is still a moving dirty aggregate, not an accepted integration checkpoint.

## Findings

1. **Critical - the repository is still a moving dirty aggregate, not a reviewable integration checkpoint.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/src/DiffSqlRenderer.php`, `lanes/esbuild/src/TypeScriptModuleLowerer.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/lane-status.json`, `porting.html`, `porting-summary.json`.
   - Evidence: recent history advanced during this audit through lane/status commits including `4986091`, `e334d69`, `36deb5c`, `b7a2690`, `78d4811`, `611ac0e`, `8f41b89`, `c6cfd79`, `84c0b83`, `4a91015`, `c140107`, `de5af8f`, `fd3ab9a`, and `e42de6d`. Latest observed dirty state still has `218` total status entries and `41` tracked changed files.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup before acceptance, and honest repo-wide verification (`goal.md:29`, `goal.md:48`, `goal.md:49`).
   - Audit judgment: freeze or explicitly coordinate writers before any integration claim. Accept/reject one lane batch at a time, preserving existing staged work ownership.

2. **High - `porting.html` and `porting-summary.json` are stale and materially disagree with current lane files.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:56`, `porting.html:58`, `porting.html:59`, `porting.html:61`, `porting.html:63`, `porting-summary.json:2`, `porting-summary.json:3`, `porting-summary.json:57`, `porting-summary.json:91`, `porting-summary.json:108`, `porting-summary.json:141`, `porting-summary.json:175`.
   - Evidence: the dashboard still says `Average progress: 14.3%` and `Generated: 2026-05-22 15:40:20 UTC`. It shows Gitoxide `66%`/`737 mapped`, LightningCSS `14%`/`78 mapped`, markerPDF `10%`/`11 mapped`, Quadrable `8%`/old failed-C++ blocker, Readability `12%`/`89 mapped`, and Syncthing `8%`/`27 mapped`. Current status/manifests report much newer values such as Gitoxide `90%` and manifest mapped `1230`, LightningCSS `54%` and manifest mapped `524`, markerPDF `48%` and manifest mapped `78`, Quadrable `63%` with upstream runner pass, Readability `46%` and manifest mapped `755`, and Syncthing `55%`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52` require a current browsable dashboard with lane status, denominators, mapped tests, pass/fail counts, blockers, current work, and commits.
   - Audit judgment: do not publish or rely on the dashboard until it is regenerated from one accepted green snapshot.

3. **High - `progress.md` still has a stale Active Lanes table and stale audit/root-test status.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:37`, `progress.md:38`, `progress.md:41`, `progress.md:42`, `progress.md:232`.
   - Evidence: the Active Lanes table still says Gitoxide `66%`, LightningCSS `14%`, markerPDF `10%`, libsqlite `12%`, Readability `12%`, Quadrable `8%`, Syncthing `8%`, Dolt `5%`, and esbuild `8%`. It also still described the prior latest audit as ending at `HEAD` `78c880f` with `14252` assertions until this audit status update.
   - Goal requirement at risk: `goal.md:44` requires current active lanes, blockers, next task per lane, owner/session, and percentage estimates.
   - Audit judgment: update the audit/status text now, but defer the table refresh until the supervisor accepts or rejects dirty lane batches and regenerates status from one state.

4. **High - lane status root-test and latest-commit fields are unreliable as machine-readable truth.**
   - Paths: `lanes/pandoc/lane-status.json:10`, `lanes/pandoc/lane-status.json:13`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:13`, `lanes/readability/lane-status.json:5`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/readability/lane-status.json:13`, `lanes/syncthing/lane-status.json:10`, `lanes/syncthing/lane-status.json:13`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:13`.
   - Evidence: Readability still says the required root harness currently fails outside readability with `157` files / `14201` assertions / `1` failure, but this audit's required run passes. Several lanes still cite older root counts such as `14093`, `14252`, `14270`, or `14315`, and several `latestCommit` fields are pending/prose instead of accepted commit hashes.
   - Goal requirement at risk: `goal.md:31`, `goal.md:44`, and `goal.md:45` require precise blockers, audit status, and latest commit tracking.
   - Audit judgment: normalize lane status after integration. Until then, treat these fields as narrative notes, not dashboard source of truth.

5. **High - upstream denominator and mapped-count units remain inconsistent across manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:14`.
   - Evidence: Difftastic counts inspected behavior artifacts, Dolt counts executable files plus BATS cases and fixture artifacts, esbuild counts test entry points, Gitoxide counts upstream files, LightningCSS counts helper invocations/behavior checks, markerPDF counts repository paths plus benchmark identifiers/pairs and zero Python unit tests, Pandoc counts files/artifacts, Readability counts Mocha tests, rclone counts Go test files, and Syncthing counts Go test/benchmark entry points. The `mapped` field is also overloaded with behavior tests, checks, assertions, or mapped source semantics depending on lane.
   - Goal requirement at risk: `goal.md:25`, `goal.md:35`, and `goal.md:38` require real upstream denominators, meaningful mapped tests, and honest percentages.
   - Audit judgment: split schema fields for upstream files/artifacts, executable upstream tests, upstream behavior cases, mapped upstream cases, native PHP behavior tests, assertions, failures, full-runner parity, bounded-runner evidence, and static inventory.

6. **Medium - manifest schema shape is inconsistent enough to break simple aggregation.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:16`.
   - Evidence: `benchmarkDenominator.runnerStatus` is an object in some manifests, a string in Gitoxide/markerPDF/quadrable, and absent/null in Pandoc. A simple jq aggregation failed on mixed string/boolean/object assumptions. markerPDF says `mapped: 78` while warning text says native PHP maps `122` semantics plus benchmark pairs; Readability says `mapped: 755`, which is local assertion count, while lane status says `phpPass: 81`.
   - Goal requirement at risk: `goal.md:45` requires the dashboard to show comparable upstream denominator, mapped tests, and PHP pass/fail counts.
   - Audit judgment: schema normalization is not optional if the dashboard is meant to be trusted.

7. **Medium - markerPDF still risks counting supplied model-boundary scaffolding as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:180`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:304`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the manifest explicitly reports `0 committed Python unit test files found`, no full benchmark runner, and many supplied boundaries: Surya layout/OCR, Texify, pdftext/pypdfium, tabled recognition, image preview/rendering, FastAPI/Uvicorn/requests, callback-driven conversion/server boundaries, and PIL/font rendering.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`, and `goal.md:35` require native standard-PHP implementation progress, not bridge/generated/shell-out progress.
   - Audit judgment: keep these boundaries labeled as oracle/scaffolding until document-level native extraction parity is driven by acquired benchmark pairs.

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
158 test files, 14360 assertions, 0 failures
```

Because the checkout moved repeatedly during this audit and dirty lane changes remain, this green result is diagnostic evidence for the moving aggregate, not an accepted integration checkpoint.

## Recommended Next Intervention

Freeze or explicitly coordinate active writers. Preserve ownership of in-flight lane batches, then integrate or reject dirty lane batches one at a time with a fresh root rerun and real commit hash after each accepted batch. Regenerate `porting.html`, `porting-summary.json`, `progress.md`, and lane statuses from the same accepted green snapshot. In parallel, normalize manifest/status fields so denominator units, mapped upstream cases, native behavior tests, assertions, failures, and runner parity are separate values.
