# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, current dirty worktree state, bridge/shell-out usage, and recent Git history through `570af5c`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale enough to misdirect work.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53-64`, `porting-summary.json`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC` and still reports average progress `14.3%`. Current manifests have newer mapped counts than the dashboard for every lane: difftastic `30` vs `15`, Dolt `30` vs `5`, esbuild `36` vs `16`, Gitoxide `812` vs `737`, libsqlite `36` vs `18`, LightningCSS `124` vs `78`, markerPDF `35` vs `11`, Pandoc `43` vs `19`, Quadrable `43` vs `24`, rclone `40` vs `20`, Readability `161` vs `89`, and Syncthing `55` vs `27`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard and compact JSON only after the dirty lane batches are reviewed from one committed state.

2. **Critical - the repository is still a dirty moving integration target.**
   - Paths: current `git status --short`, `progress.md:31-42`, recent history ending at `570af5c`.
   - Evidence: the worktree contains modified manifests, lane status files, notes, implementation files, tests, `porting.html`, and `porting-summary.json`, plus untracked audit/status files and lane fixtures. Recent commits continued to advance lane behavior after prior audit commits, while the Active Lanes table still marks every lane `stopped` and gives older estimates/next tasks.
   - Goal requirement at risk: `goal.md` requires small reviewable committed slices, verified finished agent work, honest progress, and cleanup of accidental unrelated changes.
   - Audit judgment: no further feature slice should count as portfolio progress until the current dirty batches are integrated or rejected and coordination files are regenerated.

3. **High - `progress.md` Active Lanes is no longer aligned with lane manifests.**
   - Paths: `progress.md:31-42`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-16`.
   - Evidence: `progress.md` still shows Dolt at `5%` and deferred, Quadrable at `8%` with old proof work, and Readability at `12%`; current manifests/status files report Dolt bounded runner evidence with 30 mapped checks, Quadrable upstream runner pass with 43 mapped checks, and Readability 161 mapped checks with upstream `npm test` evidence.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, phase, blocker, next task, and percentage estimates.
   - Audit judgment: update the Active Lanes table as part of the same integration pass as the dashboard, not piecemeal while implementation files are dirty.

4. **High - PHP pass/fail units remain mixed and sometimes absent from manifests.**
   - Paths: `porting.html:43`, `porting.html:56`, `porting.html:58`, `lanes/gitoxide/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: lane status files still use assertion counts as `phpPass` for some lanes, for example Gitoxide `1378` and LightningCSS assertion totals, while other lanes report behavior tests. Several manifests omit `nativeImplementation.phpBehaviorTests` entirely. The dashboard labels all of this as `pass / fail`.
   - Goal requirement at risk: `goal.md` requires honest per-lane PHP pass/fail counts.
   - Audit judgment: separate PHP test files, behavior tests, assertions, and failures in the status schema before publishing pass/fail columns.

5. **High - upstream runner status is inconsistently modeled and can overstate parity.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46-49`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17-20`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:63-64`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:76-78`.
   - Evidence: Dolt sets `runnerStatus.executed: true` while saying full runners were not executed; Gitoxide and markerPDF use string runner statuses; Pandoc has no explicit runner status object; Syncthing has a structured non-executed status. Bounded runner wins are useful, but the current schema lets readers confuse bounded evidence with full upstream parity.
   - Goal requirement at risk: `goal.md` requires a defensible upstream benchmark denominator and clear blocker status when upstream runners cannot execute.
   - Audit judgment: normalize runner fields as full-run executed, bounded-run executed, scope, exact commands/results, and not-run reason.

6. **High - markerPDF still has zero real upstream benchmark PDF/reference pairs.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:63-64`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:101`.
   - Evidence: markerPDF maps 35 source semantics and 4 README-linked surrogate pairs, but `mappedBenchmarkPairs` remains `0`; the real benchmark runner is still `not-executed` because external benchmark PDFs/references and heavy dependencies are absent.
   - Goal requirement at risk: `goal.md` scopes markerPDF as PDF-to-structured-content extraction and requires meaningful fixture parity, not only surrogate Markdown comparisons.
   - Audit judgment: the next markerPDF intervention should acquire and map at least one actual `benchmark_data` PDF/reference pair before adding more cleaner/OCR breadth.

7. **Medium - Gitoxide priority-lane progress is ahead of upstream evidence quality.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`, `lanes/gitoxide/lane-status.json`.
   - Evidence: Gitoxide reports 812 mapped items and high estimated progress, but the Cargo runner is still not executed and the manifest relies on a large prose static inventory rather than normalized upstream fixture IDs, native test IDs, and uncovered category accounting.
   - Goal requirement at risk: `goal.md` prioritizes Gitoxide and requires upstream tests as source of truth whenever possible.
   - Audit judgment: keep Gitoxide status conservative until it has normalized fixture/test IDs and uncovered-category accounting, or a controlled crate-level runner probe.

## Bridge / Shell-Out Check

Command searched `lanes`, `tools`, and `scripts` for PHP process execution calls:

```text
rg --pcre2 "\b(shell_exec|exec|passthru|proc_open|system|popen)\s*\(" lanes tools scripts -n
```

Only match: `lanes/readability/fixtures/mozilla/videos-2/source.html:830`, JavaScript fixture code `regex.exec(url)`. No PHP process-execution bridge matches were found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
73 test files, 4072 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active workers, then review the dirty lane batches and either commit or reject them. Regenerate `progress.md`, `porting.html`, and `porting-summary.json` from that committed state, refresh lane blockers, and split dashboard/status schema fields for full upstream runner evidence, bounded runner evidence, PHP behavior tests, assertions, and failures. After that, prioritize markerPDF real benchmark PDF/reference parity and Gitoxide normalized fixture IDs or a controlled crate-level runner probe.
