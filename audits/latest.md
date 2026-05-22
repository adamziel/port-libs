# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `bdeea57`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: `HEAD` advanced during this audit from `77e9cf3` to `bdeea57`, and the worktree remained dirty with lane implementation, manifest, status, dashboard, and untracked fixture/audit changes. The root test result changed during the same audit window: the first run failed, then a later rerun passed after additional work landed. Findings below are against the latest observed dirty worktree after the final manifest/status read.

## Findings

1. **Critical - the repo is still a moving dirty integration target, so no coordination surface is a stable release signal.**
   - Paths: current `git status --short --untracked-files=all`, recent history `77e9cf3..bdeea57`, `progress.md:229-231`, `porting.html:32`, `porting-summary.json:2`.
   - Evidence: `HEAD` advanced from `77e9cf3` to `6867d11` while evidence was being gathered, then advanced again through `8ac8f39`, `f14db3f`, and `bdeea57` before the final status read. The root suite changed from `87 test files, 5876 assertions, 2 failures` to `89 test files, 6056 assertions, 0 failures` during this audit. The worktree still contains modified lane implementation files, manifests/status files, modified `porting.html`/`porting-summary.json`, plus untracked fixtures/examples/audit files.
   - Goal requirement at risk: `goal.md` requires durable coordination, verified finished agent work, cleanup of unrelated changes, passing tests, and small committed slices.
   - Audit judgment: quiesce or explicitly coordinate active writers before accepting any dashboard, lane status, progress file, or root-suite result as authoritative.

2. **High - the root suite was red during the audit before later moving green, proving the current evidence is not reproducible from one stable state.**
   - Paths: `lanes/pandoc/tests/MarkdownReaderTest.php:215`, `lanes/pandoc/tests/MarkdownReaderTest.php:821`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:15-17`, `progress.md:194`.
   - Evidence: the first required `php tools/run-tests.php` run exited 1 with Pandoc failures in `maps upstream markdown apostrophe after math` (`Expected: 'math'; Actual: 'quoted'`) and `writes wordpress math and raw tex preservation markup from import notes`. A later rerun exited 0 after the tree changed under audit. The old `progress.md:194` green result was stale before this audit update, and the final green result is still dirty/uncommitted.
   - Goal requirement at risk: `goal.md` requires honest, periodically recorded repo-wide tests and small reviewable slices with passing tests.
   - Audit judgment: do not publish or stamp the green result until the exact passing state is committed and dashboard/progress are regenerated from that same state.

3. **High - `porting.html` and `porting-summary.json` are stale against every current manifest/status count.**
   - Paths: `porting.html:32`, `porting.html:53-64`, `porting-summary.json:2-207`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Evidence: the dashboard is still generated at `2026-05-22 15:40:20 UTC`. Current manifest/status values are difftastic `42` mapped/`42` PHP vs dashboard `15`/`15`, Dolt `51`/`51` vs `5`/`5`, esbuild `47`/`47` vs `16`/`16`, Gitoxide `857`/`1459` vs `737`/`1257`, libsqlite `45`/`45` vs `18`/`18`, LightningCSS `148`/`224` vs `78`/`141`, markerPDF `61`/`81` vs `11`/`23`, Pandoc `74`/`61` vs `19`/`19`, Quadrable `52`/`52` vs `24`/`24`, rclone `67`/`67` vs `20`/`20`, Readability `218`/`30` vs `89`/`15`, and Syncthing `69`/`69` vs `27`/`27`.
   - Additional evidence: denominator/status mismatches remain visible for LightningCSS (`382` manifest vs `312` dashboard), markerPDF (`59` vs `27`), Pandoc (`2028` vs `1979`), and Quadrable's dashboard blocker still says the C++ runner failed while the manifest/status now claim `make -r test` passes.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, upstream denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate dashboard artifacts only after the dirty lane batches are accepted from one committed green state.

4. **High - `progress.md` still gives supervisors stale lane guidance even after the audit status update.**
   - Paths: `progress.md:31-42`, `progress.md:194`, `progress.md:199`, `progress.md:205-210`, `progress.md:235`.
   - Evidence: the Active Lanes table still lists old stopped phases and next tasks. Several blocker bullets remain stale: Readability still says `15` tests/`89` checks while the manifest/status now report `220` mapped/`29` PHP tests; Syncthing still says Go is unavailable while status now says `/usr/bin/go` exists; Pandoc, difftastic, Dolt, LightningCSS, rclone, and Syncthing have moved past the next-task text shown in the table.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, phase, blocker, next task, audit status, and next best intervention.
   - Audit judgment: keep the warning status, but do not hand-reconcile the whole lane table until the supervisor decides which dirty lane batches to accept.

5. **High - upstream runner status is still modeled inconsistently, making bounded evidence easy to confuse with full parity.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46-50`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17-20`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:80-81`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-18`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json` runner status.
   - Evidence: Dolt has `runnerStatus.executed: true` while the reason says full upstream runners were not executed and only bounded Go/BATS evidence passed; Gitoxide and markerPDF use string statuses; Pandoc has no explicit runner-status object; Quadrable uses a string pass note; Syncthing uses structured `executed: false` with probes. These are not comparable dashboard inputs.
   - Goal requirement at risk: `goal.md` requires defensible upstream benchmark denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize runner fields into `fullRunner.executed`, `boundedRunner.executed`, scope, exact commands/results, and not-run reason before increasing estimates based on runner evidence.

6. **High - markerPDF still lacks a real upstream benchmark PDF/reference pair.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:80-81`.
   - Evidence: the manifest reports `mappedBenchmarkPairs: 0` and `mappedBenchmarkSurrogatePairs: 4`; the upstream benchmark runner remains `not-executed` because benchmark PDFs/references and heavy dependencies are absent. The lane has useful source-semantics work, but no actual PDF/reference parity for the priority-3 PDF extraction goal.
   - Goal requirement at risk: `goal.md` scopes markerPDF as PDF-to-structured-content extraction and requires meaningful fixture parity, not only surrogate Markdown/source semantics.
   - Audit judgment: acquire and map at least one real `benchmark_data` PDF/reference pair before counting more cleaner/OCR/table breadth as high-value markerPDF progress.

7. **Medium - Gitoxide remains ahead of its upstream evidence quality for the priority-1 lane.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/gitoxide/lane-status.json:6-13`, `porting.html:56`, `porting-summary.json:57-71`.
   - Evidence: Gitoxide reports `857` mapped items and high progress, but the Cargo runner is still not executed and the manifest relies on a very large prose inventory instead of normalized upstream fixture IDs, native test IDs, and uncovered-category accounting. Dashboard/status values are also stale: dashboard `737` mapped and `1257` pass vs manifest `857` mapped and status `1459` assertions.
   - Goal requirement at risk: `goal.md` prioritizes Gitoxide and requires upstream tests as the source of truth whenever possible.
   - Audit judgment: keep Gitoxide estimates conservative until the lane has normalized fixture/test IDs with uncovered-category accounting, or a controlled crate-level upstream runner probe.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no PHP process-execution bridge calls found.

## Test Runs

First command: `php tools/run-tests.php`

Exit status: 1

Exact result:

```text
87 test files, 5876 assertions, 2 failures
```

Failing tests:

```text
FAIL maps upstream markdown apostrophe after math (lanes/pandoc/tests/MarkdownReaderTest.php)
Expected: 'math'
Actual: 'quoted'

FAIL writes wordpress math and raw tex preservation markup from import notes (lanes/pandoc/tests/MarkdownReaderTest.php)
String does not contain 'Field &amp; Value \\ \hline'
```

Final rerun command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
89 test files, 6056 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then integrate or reject the remaining dirty lane batches from one state and keep the passing root-suite result tied to that exact commit. Regenerate `progress.md`, `porting.html`, and `porting-summary.json`, refresh lane-status blockers, normalize runner/PHP count schema fields, and prioritize markerPDF real benchmark PDF/reference parity plus Gitoxide normalized fixture IDs or a controlled crate-level runner probe.
