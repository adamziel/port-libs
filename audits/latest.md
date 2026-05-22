# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `bc6e754` (`Update LightningCSS lane status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the required PHP harness is green, but the repository is still a broad dirty integration target. Treat the green run as current test evidence, not proof that the dirty lane batches have been reviewed, accepted, and published into the dashboard.

## Findings

1. **Critical - the public dashboard and summary are stale against the current manifests.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`.
   - Evidence: `porting.html` and `porting-summary.json` still show `Generated: 2026-05-22 15:40:20 UTC`. Dashboard mapped counts are Difftastic `15`, Dolt `5`, esbuild `16`, Gitoxide `737`, libsqlite `18`, LightningCSS `78`, markerPDF `11`, Pandoc `19`, Quadrable `24`, rclone `20`, Readability `89`, and Syncthing `27`. Current manifests report Difftastic `61`, Dolt `84`, esbuild `71`, Gitoxide `1017`, libsqlite `60`, LightningCSS `230`, markerPDF `78`, Pandoc `131`, Quadrable `55`, rclone `102`, Readability `381`, and Syncthing `92`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current mapped upstream tests, PHP pass/fail counts, phase, audit status, current work, blocker, and commit.
   - Audit judgment: do not publish or use the dashboard for portfolio decisions until it is regenerated from an accepted green state.

2. **High - the worktree is still too broad to be a reviewable integration checkpoint.**
   - Paths: `audits/integration-status.md`, `lanes/gitoxide/src/PackBuilder.php`, `lanes/gitoxide/tests/PackBuilderTest.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/TrackedNodeStore.php`, `lanes/syncthing/src/ReceiveEncrypted.php`, `porting.html`, `porting-summary.json`.
   - Evidence: before this audit update, `git status --short` showed dirty implementation, test, fixture, manifest, note, status, dashboard, and audit files across Dolt, Gitoxide, Pandoc, Quadrable, Syncthing, plus generated dashboard files and untracked examples/audits. `git diff --stat` showed 32 tracked files changed with about 2791 insertions and 180 deletions.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and visible progress generated from the accepted state.
   - Audit judgment: accept or reject dirty batches one lane at a time; rerun `php tools/run-tests.php` after each accepted batch.

3. **High - status files and progress still carry conflicting root-suite evidence.**
   - Paths: `progress.md:194`, `progress.md:199`, `progress.md:230`, `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/markerpdf/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`, `lanes/difftastic/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/rclone/lane-status.json:10`.
   - Evidence: the current root run is `112 test files, 8254 assertions, 0 failures`. Several lane statuses still cite older root runs such as `110/8021`, `111/8175`, `111/8178`, `111/8162`, `112/8231`, or the previous audit's `111/8150`.
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, PHP pass/fail counts, and visible current dashboard/progress state.
   - Audit judgment: stale red/green strings should not be used to accept or reject lane work. Normalize status evidence after the dirty batches are integrated.

4. **High - upstream evidence fields remain inconsistent and can mislead progress calculations.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:138`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `porting.html:56`.
   - Evidence: `runnerStatus` is an object in Dolt/LightningCSS/libsqlite/rclone/readability/esbuild/Syncthing/Difftastic, a string in Gitoxide/markerPDF/Quadrable, and effectively missing as a structured field for Pandoc. Dashboard PHP counts also mix behavior tests and assertions: for example Gitoxide renders `1257 pass / 0 fail`, while the current lane status says lane PHP has `1820 assertions` and the manifest maps `1017` upstream checks.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators, mapped upstream tests, PHP passing/failing counts, and precise blockers when upstream runners cannot execute.
   - Audit judgment: split the schema into full upstream pass parity, bounded upstream runner evidence, static inventory, native behavior tests, native assertions, and failures before using percentages for decisions.

5. **Medium - some `mapped == total` manifest claims read stronger than the actual native parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/lane-status.json:12`, `lanes/quadrable/lane-status.json:12`.
   - Evidence: markerPDF reports `78` total and `78` mapped while the full benchmark runner is not executed and live pdftext/layout/OCR/table/model inference remains unported. Quadrable reports `55` total and `55` mapped even though the denominator is tracked upstream paths plus `check.cpp` scenario counts, not a behavior-level full native parity denominator.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, meaningful fixture parity, edge-case coverage, and honest blockers rather than counting inventory breadth as implementation progress.
   - Audit judgment: keep these as useful inventory/runner evidence, but do not let `mapped == total` imply full native parity unless the denominator is behavior-level and remaining semantic gaps are separately represented.

6. **Medium - several lanes still lack full upstream runner parity despite useful bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:71`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:138`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:130`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:128`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`.
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
112 test files, 8254 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then accept or reject the remaining dirty lane batches one at a time with a fresh root run after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that accepted green state. Then normalize the evidence schema before continuing the highest-priority parity gaps: controlled Gitoxide upstream runner expansion and markerPDF native extraction parity from the two concrete CI benchmark PDF/reference pairs.
