# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `6fd20ce`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is still dirty with no staged files observed at final check. Multiple lane implementation, manifest/status, dashboard, tooling, fixture, and audit/status files remain modified or untracked, so this report treats the coordination surface as unstable and anchors test evidence to the explicit root run below.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale enough to mislead reviewers across the whole portfolio.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:10`, `porting-summary.json:27`, `porting-summary.json:44`, `porting-summary.json:61`, `porting-summary.json:78`, `porting-summary.json:95`, `porting-summary.json:112`, `porting-summary.json:129`, `porting-summary.json:146`, `porting-summary.json:163`, `porting-summary.json:180`, `porting-summary.json:197`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC`, but recent history is now at `6fd20ce`. Current manifest mapped counts vs dashboard mapped counts are: difftastic `47 vs 15`, Dolt `59 vs 5`, esbuild `54 vs 16`, Gitoxide `859 vs 737`, libsqlite `50 vs 18`, LightningCSS `162 vs 78`, markerPDF `67 vs 11`, Pandoc `105 vs 19`, Quadrable `55 vs 24`, rclone `78 vs 20`, Readability `236 vs 89`, and Syncthing `80 vs 27`. Dashboard denominators are also stale for LightningCSS (`382` current vs `312` shown), markerPDF (`78 tracked paths...` current vs `27` shown), and Pandoc (`2028` current vs `1979` shown). Quadrable still renders as a failed C++ runner while its manifest says `make -r test` passes.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or use the dashboard/summary as the source of truth until regenerated from one accepted green state.

2. **High - lane status files contain incompatible root-suite claims and stale integration blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`, `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:12`, `lanes/esbuild/lane-status.json:10`, `lanes/esbuild/lane-status.json:12`, `lanes/gitoxide/lane-status.json:12`, `lanes/libsqlite/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/markerpdf/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`, `lanes/rclone/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: this audit's root run is `99 test files, 6676 assertions, 0 failures`. Lane statuses still claim conflicting results such as `98/6532/0`, `99/6616/0`, `99/6638/0`, `99/6590/0`, and `97/6486/0`, plus stale red blockers like Dolt's `99/6587/4` libsqlite failure and LightningCSS's `99/6631/1` Readability failure. These fields decide whether a lane is blocked or integrable, so stale counts are not just commentary.
   - Goal requirement at risk: `goal.md` requires precise blockers, current audit status, and verified passing slices per lane.
   - Audit judgment: refresh lane statuses after freezing writers; stale per-lane blockers should not gate current integration.

3. **High - `progress.md` remains a coordination warning, not a current roadmap.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:231`, `progress.md:235`.
   - Evidence: this audit updates the latest root-suite line and next intervention, but the Active Lanes table still reports all lanes as stopped and lists old next tasks while manifests, lane statuses, and recent commits have advanced. Some older blocker bullets also contradict current manifests, for example Gitoxide's HTTPS-through-SOCKS wording and Quadrable's old digest caveat.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, owner/session, blockers, next task per lane, audit status, and percentage estimates.
   - Audit judgment: keep the warning in place, but regenerate the whole progress surface only after the supervisor freezes or coordinates writers.

4. **High - markerPDF still lacks a real upstream benchmark PDF/reference pair.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:91`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:161`.
   - Evidence: the manifest reports `mapped: 67`, but `mappedBenchmarkPairs: 0` and `mappedBenchmarkSurrogatePairs: 4`. The runner remains `not-executed` because the external benchmark PDFs/references and heavy model dependencies are absent. The mapped cleaner, scoring, layout, OCR, table, equation, and output boundaries are useful scaffolding, but they do not prove PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not just surrogate Markdown/output scoring.
   - Audit judgment: the next markerPDF intervention should be one real `benchmark_data` PDF/reference pair before counting more breadth as strong upstream parity progress.

5. **Medium - upstream runner evidence is not normalized, so status math mixes incompatible evidence classes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:91`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:101`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`.
   - Evidence: Dolt sets `runnerStatus.executed: true` while the reason starts with "The full upstream runners were not executed"; Gitoxide, Quadrable, and markerPDF use free-form strings; Pandoc records the runner gap only as warning text; rclone/readability use structured objects. This makes it easy for dashboards to conflate full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior-test counts, assertions, and failures.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: add a normalized runner-evidence schema before dashboard percentages or blockers drive portfolio decisions.

6. **Medium - the repository remains a dirty moving integration target despite the green root suite.**
   - Paths: `.tmux-team/prompts/dashboard-updater.md`, `audits/integration-status.md`, `tools/generate-dashboard.php`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/markerpdf/src/MarkdownPostProcessor.php`, `lanes/quadrable/src/SparseTree.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, plus untracked examples/fixtures/tests under several lanes.
   - Evidence: `git status --short --untracked-files=all` shows modified implementation, tests, manifests, notes, generated dashboard files, tooling/prompt files, and untracked artifacts. Recent history advanced during the audit window from `94a66ea` to `6fd20ce`.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, and committed dashboard/progress status that reflects the accepted state.
   - Audit judgment: green root tests are necessary but not sufficient; freeze or explicitly coordinate writers before integrating or publishing the current status.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no PHP process-execution bridge calls found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
99 test files, 6676 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and every lane status from this same green root-suite state. After the coordination surface is current, prioritize markerPDF real benchmark PDF/reference parity and normalize runner evidence fields so dashboard math separates full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior tests, assertions, and failures.
