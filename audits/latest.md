# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, bridge/shell-out usage, the current dirty worktree, and recent Git history through `36031aa` (`Resolve libsqlite upstream runner blocker`). I did not edit lane implementation files or launch agents.

## Findings

1. **High - The dashboard and progress file currently include uncommitted lane and supervisor changes, so they are not a clean-HEAD publication state.**
   - Paths: `goal.md:29`, `goal.md:48`, `progress.md:31-42`, `progress.md:185`, `porting.html:56-62`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, `.tmux-team/prompts/integrator.md`, `scripts/run-team-watchdog.sh`.
   - Evidence: after `36031aa` committed the libsqlite runner evidence, `git status --short` still shows modified files across Difftastic, esbuild, Gitoxide, libsqlite status, LightningCSS, markerPDF, Pandoc, Quadrable, rclone, Readability, Syncthing, generated dashboard files, and `progress.md`, plus untracked supervisor prompt/watchdog files and multiple untracked lane source/fixture/test files. `porting.html:59` renders markerPDF's commit as `pending`, and `porting.html:61` renders Quadrable's commit as `uncommi`.
   - Goal requirement at risk: commit small reviewable slices with passing tests, verify/commit finished agent work before moving on, and track accurate latest commits.
   - Audit judgment: integrate or reject the current libsqlite, Quadrable, dashboard, progress, and supervisor-script changes as separate reviewable commits before treating the dashboard as published state.

2. **High - Gitoxide remains overstated relative to a machine-checkable upstream denominator.**
   - Paths: `goal.md:7`, `goal.md:24-25`, `goal.md:35-38`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-20`, `porting.html:56`, `progress.md:31`, `progress.md:190`.
   - Evidence: the priority-1 lane reports 65.0%, `1234 pass / 0 fail`, and `735 / inventory mapped`, but the manifest denominator is still a long prose inventory string and the runner remains unexecuted. The dashboard denominator is `inventory benchmark`, not a concrete upstream test count.
   - Goal requirement at risk: real upstream benchmark denominator, upstream tests as source of truth, full-denominator mapping before broad slices on huge suites, and honest blockers for unported Git behavior.
   - Audit judgment: the native Git slices are useful smoke coverage, but the percentage should remain suspect until the denominator is normalized into named upstream suites/fixtures with explicit covered and uncovered counts.

3. **High - markerPDF still has no real upstream benchmark PDF/reference pair mapped.**
   - Paths: `goal.md:9`, `goal.md:35-37`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:56-68`, `porting.html:59`, `progress.md:33`, `progress.md:192`.
   - Evidence: the manifest now maps 11 focused source/scoring semantics, but it still records `mappedBenchmarkPairs: 0` and `mappedBenchmarkSurrogatePairs: 1`. The full benchmark runner is not executed because the benchmark data and heavy ML/PDF dependencies are absent.
   - Goal requirement at risk: meaningful fixture parity for PDF-to-structured-content extraction suitable for WordPress import and Data Liberation.
   - Audit judgment: priority 3 should pin at least one actual benchmark PDF/reference pair before broadening table/OCR/layout behavior; README-derived surrogate scoring should stay explicitly temporary.

4. **Medium - Upstream runner evidence is still easy to misread as native PHP parity.**
   - Paths: `goal.md:1`, `goal.md:30`, `goal.md:35-38`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:46-127`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38-80`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:47-93`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:47-99`, `porting.html:55`, `porting.html:58`, `porting.html:62-63`, `progress.md:189`, `progress.md:193-200`.
   - Evidence: LightningCSS, Readability, esbuild, and rclone now have valuable upstream runner passes or bounded passes, but their native mappings remain small slices: LightningCSS `71 / 241`, Readability `78 / 1984`, esbuild `13 / 2,567`, and rclone `17 / 327` with provider integration and mount/docker coverage excluded.
   - Goal requirement at risk: bridge/upstream binary execution may be oracle evidence only and must not count as native implementation progress.
   - Audit judgment: keep the runner evidence, but dashboard/status wording should continue to distinguish upstream-oracle passes from native PHP coverage and avoid percentage gains driven by non-PHP runners.

5. **Medium - Several lanes still rely on static inventories or partial runner coverage, so local PHP pass counts are shallow against the original scope.**
   - Paths: `goal.md:24-40`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12-59`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12-72`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-18`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:12-20`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:12-78`, `porting.html:53-64`, `progress.md:191`, `progress.md:196-201`.
   - Evidence: Difftastic, Dolt, Pandoc, Quadrable, and Syncthing all explicitly say their upstream runners were not executed. The root PHP suite passes, but these lanes map 12/287, 5/613, 15/1979, 18/55, and 22/264 respectively, with large unported protocol, parser, proof, storage, and integration areas.
   - Goal requirement at risk: passing tests are not enough; each lane needs meaningful fixture parity, edge-case/error coverage, and real upstream-denominator alignment.
   - Audit judgment: these are acceptable early slices, not broad native ports. The next slices should keep tying PHP tests to named upstream fixtures instead of increasing local-only smoke tests.

6. **Medium - Session status in progress is wrong while active agents are mutating the tree.**
   - Paths: `goal.md:20`, `goal.md:47-48`, `progress.md:14`, `progress.md:223-227`, `.tmux-team/prompts/integrator.md`, `scripts/run-team-watchdog.sh`.
   - Evidence: `progress.md` still records the auditor and all workers as stopped, but `tmux ls` shows active `port-auditor`, implementation lane sessions, `port-evaluator`, `port-integrator`, and `port-watchdog`. The worktree continued changing during this audit. I did not launch any sessions for this audit.
   - Goal requirement at risk: durable supervision, independent auditor every 20 minutes, and clean integration of finished agent work.
   - Audit judgment: the supervisor needs to quiesce or explicitly coordinate these sessions before relying on `progress.md`/`porting.html` as a stable state snapshot.

## Bridge / Shell-Out Check

Command searched committed and untracked files under `lanes`, `tools`, and `scripts` for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(` using `rg --pcre2`. The only broad match was a copied Mozilla fixture's JavaScript `regex.exec(url)` in `lanes/readability/fixtures/mozilla/videos-2/source.html`; a PHP/shell-only search found no process-execution matches. I did not find bridge code or shell-outs being counted as native implementation progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
52 test files, 2636 assertions, 0 failures
```

Exit status: 0.

Note: this result is from the current dirty working tree.

## Recommended Next Intervention

First review and integrate or reject the current dirty lane/supervisor changes as passing, reviewable commits. Then prioritize denominator-quality work: make Gitoxide's upstream denominator machine-checkable with named fixture IDs, acquire one real markerPDF benchmark PDF/reference pair, and keep each new PHP mapping tied to upstream fixture IDs rather than upstream-runner headlines.
