# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `8fcbf62` (`Stamp LightningCSS fallback commit`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required PHP harness is green, but the repository is still a broad, moving integration target. `HEAD` advanced during this audit from `35a5a23` to `8fcbf62`; treat the green run as current test evidence, not proof that dirty lane batches have been reviewed, accepted, and published into the dashboard.

## Findings

1. **Critical - the public dashboard and summary are stale against the current manifests.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`.
   - Evidence: `porting.html` and `porting-summary.json` still show `Generated: 2026-05-22 15:40:20 UTC`. Dashboard mapped counts are Difftastic `15`, Dolt `5`, esbuild `16`, Gitoxide `737`, libsqlite `18`, LightningCSS `78`, markerPDF `11`, Pandoc `19`, Quadrable `24`, rclone `20`, Readability `89`, and Syncthing `27`. Current manifests report Difftastic `63`, Dolt `84`, esbuild `76`, Gitoxide `1034`, libsqlite `62`, LightningCSS `234`, markerPDF `78`, Pandoc `132`, Quadrable `55`, rclone `104`, Readability `428`, and Syncthing `93`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current mapped upstream tests, PHP pass/fail counts, phase, audit status, current work, blocker, and commit.
   - Audit judgment: do not publish or use the dashboard for portfolio decisions until it is regenerated from an accepted green state.

2. **High - the worktree is still too broad and active to be a reviewable integration checkpoint.**
   - Paths: `audits/integration-status.md`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/libsqlite/src/SQLiteCreateIndex.php`, `lanes/quadrable/src/SyncFuzzer.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`.
   - Evidence: `git status --short` shows dirty implementation, test, fixture, manifest, note, status, dashboard, and audit files across Difftastic, Dolt, Gitoxide, libsqlite, markerPDF, Quadrable, and Readability, plus untracked examples/fixtures/audits. `git diff --stat` shows 27 tracked files changed with about 3609 insertions and 174 deletions, and recent history moved while the audit was in progress.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and visible progress generated from the accepted state.
   - Audit judgment: accept or reject dirty batches one lane at a time; rerun `php tools/run-tests.php` after each accepted batch.

3. **High - lane status files carry conflicting root-suite evidence, including false current blockers.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/esbuild/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`, `lanes/pandoc/lane-status.json:12`, `lanes/gitoxide/lane-status.json:10`, `lanes/markerpdf/lane-status.json:12`, `lanes/quadrable/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: the current root run is `113 test files, 8448 assertions, 0 failures`. Difftastic still says the root suite fails with `8336 assertions` and 9 LightningCSS failures; libsqlite and Readability still say it fails with `8290 assertions` and 7 esbuild failures; markerPDF says it fails with `8411 assertions` and 1 Pandoc failure. Other lane statuses cite older green runs such as `112/8270`, `113/8366`, `113/8391`, `113/8396`, or `113/8420`.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, PHP pass/fail counts, and visible current dashboard/progress state.
   - Audit judgment: stale red/green strings should not be used to accept or reject lane work. Normalize status evidence after the dirty batches are integrated.

4. **High - upstream evidence fields remain inconsistent and can mislead progress calculations.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:141`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `porting.html:56`.
   - Evidence: `runnerStatus` is an object in Dolt/LightningCSS/libsqlite/rclone/readability/esbuild/Syncthing/Difftastic, a string in Gitoxide/markerPDF/Quadrable, and effectively absent as a structured field for Pandoc. Dashboard PHP counts also mix behavior tests and assertions: for example Gitoxide renders `1257 pass / 0 fail`, while the current lane status says lane PHP has `1820 assertions` and the manifest maps `1017` upstream checks.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators, mapped upstream tests, PHP passing/failing counts, and precise blockers when upstream runners cannot execute.
   - Audit judgment: split the schema into full upstream pass parity, bounded upstream runner evidence, static inventory, native behavior tests, native assertions, and failures before using percentages for decisions.

5. **Medium - some `mapped == total` manifest claims read stronger than the actual native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:141`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: markerPDF reports `78` total and `78` mapped while the full benchmark runner is not executed and live pdftext/layout/OCR/table/model inference remains unported. Quadrable reports `55` total and `55` mapped against tracked paths/check.cpp scenario inventory; upstream `make -r test` is useful runner evidence, but the denominator is still not a behavior-level full native parity denominator.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, meaningful fixture parity, edge-case coverage, and honest blockers rather than counting inventory breadth as implementation progress.
   - Audit judgment: keep these as useful inventory/runner evidence, but do not let `mapped == total` imply full native parity unless the denominator is behavior-level and remaining semantic gaps are separately represented.

6. **Medium - several lanes still lack full upstream runner parity despite useful bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:72`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:141`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:133`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:131`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`.
   - Evidence: Difftastic, markerPDF, Pandoc, and Syncthing still have no full upstream runner pass. Gitoxide has bounded crate/subset evidence, not full workspace Cargo parity. Dolt and rclone have valuable bounded runner evidence, not full Go/BATS/provider/mount parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: continue implementation only with these labels preserved; do not fold bounded/static evidence into full upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

```text
Exit status: 0
113 test files, 8448 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then accept or reject the remaining dirty lane batches one at a time with a fresh root run after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that accepted green state. Then normalize the evidence schema before continuing the highest-priority parity gaps: controlled Gitoxide upstream runner expansion and markerPDF native extraction parity from the two concrete CI benchmark PDF/reference pairs.
