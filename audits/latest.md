# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `c88ee9c`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the current PHP harness is green, but the repository is still a broad dirty integration target. Treat the green run as test evidence, not proof that all dirty lane batches have been reviewed, accepted, and published into the dashboard.

## Findings

1. **Critical - the visible dashboard is stale against every current manifest.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:11`, `porting-summary.json:28`, `porting-summary.json:45`, `porting-summary.json:62`, `porting-summary.json:79`, `porting-summary.json:95`, `porting-summary.json:112`, `porting-summary.json:129`, `porting-summary.json:147`.
   - Evidence: `porting.html` and `porting-summary.json` were generated at `2026-05-22 15:40:20 UTC`. They still show old mapped counts such as Difftastic `15 / 404`, Dolt `5 / 613`, Gitoxide `737 / 2877`, libsqlite `18 / 1454`, LightningCSS `78 / 312`, markerPDF `11 / 27`, Pandoc `19 / 1979`, Quadrable `24 / 55`, rclone `20 / 327`, Readability `89 / 1984`, and Syncthing `27 / 264`. Current manifests report Difftastic `60`, Dolt `78`, esbuild `71`, Gitoxide `1014`, libsqlite `59`, LightningCSS `227 / 382`, markerPDF `78`, Pandoc `130 / 2028`, Quadrable `55`, rclone `102`, Readability `352`, and Syncthing `92`.
   - Goal requirement at risk: `goal.md` requires `progress.md` and `porting.html` to show current mapped upstream tests, PHP pass/fail counts, phase, audit status, blocker, current work, and commit.
   - Audit judgment: regenerate `porting.html`, `porting-summary.json`, lane statuses, and the progress lane table only after the remaining dirty batches are explicitly accepted or rejected from this same green baseline.

2. **High - lane status blockers and audit strings still contradict the current root test result.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/difftastic/lane-status.json:12`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/markerpdf/lane-status.json:12`, `lanes/dolt/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`, `progress.md:194`, `progress.md:199`, `progress.md:230`.
   - Evidence: the required root harness now passes with `111 test files, 8150 assertions, 0 failures`. Several lane statuses still cite stale red-root evidence (`110` files, `8017` assertions, `3` Gitoxide failures) or older green-root evidence (`108/7851`, `109/7887`, `110/7961`, `110/8021`, `110/8048`).
   - Goal requirement at risk: `goal.md` requires precise blockers, audit status, PHP pass/fail counts, and visible current dashboard/progress state.
   - Audit judgment: stale red/green result strings should not be used to accept or reject lane batches. Normalize them from one accepted root run.

3. **High - the worktree is still too broad to be a reviewable integration checkpoint.**
   - Paths: `audits/integration-status.md`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/dolt/src/CommitGraph.php`, `lanes/gitoxide/src/ReferenceName.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/TrackedNodeStore.php`, `porting.html`, `porting-summary.json`.
   - Evidence: excluding this audit/progress update, `git status --short` shows modified implementation, test, fixture, manifest, note, status, dashboard, and audit files across multiple lanes, plus untracked examples/fixtures/audits. `git diff --stat` reports 46 tracked files with 4325 insertions and 252 deletions before this audit update.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with passing tests and visible progress generated from the accepted state.
   - Audit judgment: accept or reject dirty batches one lane at a time. Rerun `php tools/run-tests.php` after each accepted batch before publishing the dashboard.

4. **High - upstream evidence fields are still inconsistent enough to mislead portfolio decisions.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:135`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `porting-summary.json:64`.
   - Evidence: `runnerStatus` is an object in several manifests, a string in Gitoxide/markerPDF/Quadrable, and absent/null for Pandoc. Dolt has `executed: true` for bounded evidence while its reason says the full upstream runners were not executed. Dashboard PHP fields also mix behavior-test counts and assertion counts, for example Gitoxide renders `1257 pass / 0 fail` while current lane evidence is assertion-based and still not full Cargo parity.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators, mapped upstream tests, PHP passing/failing counts, and precise blockers when upstream runners cannot execute.
   - Audit judgment: split the schema into full upstream pass parity, bounded upstream runner evidence, static inventory, native behavior tests, native assertions, and failures before using percentages for roadmap decisions.

5. **Medium - some manifests now count inventory breadth as if it were behavior parity.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/lane-status.json:12`, `lanes/quadrable/lane-status.json:12`.
   - Evidence: markerPDF reports `78` total and `78` mapped while also saying the full benchmark runner was not executed and live pdftext/layout/OCR/image/table/model inference remains unported. Quadrable reports `55` total and `55` mapped, but the denominator is tracked upstream paths plus `check.cpp` counts, while the heavy native `SyncFuzzer::run(500, 0)` parity probe remains unresolved.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark denominator, meaningful fixture parity, edge-case coverage, and honest blockers rather than counting inventory or generated/oracle scaffolding as native implementation progress.
   - Audit judgment: do not let `mapped == total` imply full native parity unless the denominator is behavior-level and the remaining semantic gaps are separately represented.

6. **Medium - several lanes remain static-inventory or bounded-runner evidence, not full upstream parity.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:70`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:138`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:126`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:128`.
   - Evidence: Difftastic, Pandoc, markerPDF, and Syncthing still do not have full upstream runner parity. rclone and Dolt have useful bounded runner evidence, not full provider/full BATS parity. markerPDF has concrete CI benchmark pairs, but full benchmark execution and live extraction still require heavy Python/model/runtime dependencies.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible, generated fixtures or bridge calls must not count as native implementation progress, and hard features must be marked as blockers or future slices.
   - Audit judgment: keep these lanes moving, but label static inventory, bounded runner evidence, and full parity distinctly.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match was `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

```text
Exit status: 0
111 test files, 8150 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then accept or reject the remaining dirty lane batches one at a time with a fresh root run after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that accepted green state. Then normalize the evidence schema before continuing the highest-priority parity gaps: controlled Gitoxide upstream runner expansion and markerPDF native extraction parity from the two concrete CI benchmark PDF/reference pairs.
