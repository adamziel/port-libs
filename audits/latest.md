# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, current dirty worktree state, bridge/shell-out usage, and recent Git history through `0d76515`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: `HEAD` advanced during this audit from `0dbcf06` to `0d76515`, and the worktree remained dirty. Findings below are against `0d76515` plus the dirty worktree observed after the latest root test run.

## Findings

1. **Critical - the repo is still a moving dirty integration target, so coordination files are not a stable release signal.**
   - Paths: current `git status --short`, recent history through `0d76515`, `progress.md:229`, `progress.md:231`, `porting.html:30`.
   - Evidence: `HEAD` advanced repeatedly while the audit was running. The latest `php tools/run-tests.php` run is green, but it was run against a dirty worktree that still contains lane implementation files, manifests, status files, notes, `porting.html`, `porting-summary.json`, plus untracked audit/status files and lane fixtures.
   - Goal requirement at risk: `goal.md` requires small reviewable committed slices, verified finished agent work, cleanup of accidental unrelated changes, durable coordination, and honest status tracking.
   - Audit judgment: active writers need to be quiesced or explicitly coordinated before accepting any dashboard, lane status, or progress file as authoritative.

2. **Critical - `porting.html` and `porting-summary.json` are stale against current lane manifests.**
   - Paths: `porting.html:30-64`, `porting-summary.json:2-207`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: `porting.html`/`porting-summary.json` were generated at `2026-05-22 15:40:20 UTC` and show old mapped counts: difftastic `15` displayed vs `37` manifest, Dolt `5` vs `41`, esbuild `16` vs `42`, Gitoxide `737` vs `853`, libsqlite `18` vs `43`, LightningCSS `78` vs `138`, markerPDF `11` vs `46`, Pandoc `19` vs `52`, Quadrable `24` vs `50`, rclone `20` vs `57`, Readability `89` vs `187`, and Syncthing `27` vs `62`.
   - Additional evidence: the dashboard still reports Quadrable's blocker as a failed C++ runner (`porting.html:61`), while the manifest says `make -r test` passes (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-18`). It also reports old denominators for LightningCSS, markerPDF, and Pandoc.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard and compact JSON only after the dirty lane batches are accepted from one committed green state.

3. **High - `progress.md` Active Lanes and audit notes are stale enough to misdirect the supervisor.**
   - Paths: `progress.md:31-42`, `progress.md:194`, `progress.md:199`, `progress.md:235`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13-15`.
   - Evidence: the Active Lanes table still shows old stopped phases and old next tasks. For example, Readability still says the next task is to copy a lazy-image fixture even though the manifest now maps 176 checks and later cleanup slices; Pandoc still displays 19 mapped items while the manifest reports 52; Syncthing still displays 27 while the manifest reports 62.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, phase, blocker, next task, owner/session, and percentage estimates.
   - Audit judgment: keep the audit warning in `progress.md`, but do not hand-edit the whole lane table until the supervisor chooses which dirty lane batches to accept.

4. **High - lane status files and dashboard fields still mix PHP count units.**
   - Paths: `lanes/difftastic/lane-status.json:6-12`, `lanes/dolt/lane-status.json:6-12`, `lanes/esbuild/lane-status.json:6-12`, `lanes/readability/lane-status.json:6-12`, `lanes/rclone/lane-status.json:10-12`, `lanes/gitoxide/lane-status.json:6-12`, `porting.html:53-64`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: the latest root suite is green, but `phpPass` still mixes behavior-test and assertion units. Gitoxide reports `phpPass: 1421` as assertions, LightningCSS reports assertion totals, while Dolt/esbuild/rclone/readability report behavior-test counts. Several manifests still omit `nativeImplementation.phpBehaviorTests`, and the dashboard's `pass / fail` label makes these mixed units look comparable.
   - Goal requirement at risk: `goal.md` requires honest per-lane PHP pass/fail counts and precise blocker recording.
   - Audit judgment: split status fields into PHP test files, behavior tests, assertions, and failures; expire old root-suite text whenever a newer root result exists.

5. **High - upstream runner status remains inconsistently modeled, making bounded evidence easy to confuse with full parity.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:69`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17-18`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:83`.
   - Evidence: Dolt has `runnerStatus.executed: true` while saying full runners were not executed; Gitoxide, markerPDF, and Quadrable use string runner statuses; Pandoc has no explicit runner-status object; Syncthing uses a structured non-executed object. Bounded runner evidence is useful, but the schema does not force readers to distinguish bounded from full upstream parity.
   - Goal requirement at risk: `goal.md` requires a defensible upstream benchmark denominator and precise blocker status when upstream runners cannot execute.
   - Audit judgment: normalize runner fields as full-run executed, bounded-run executed, scope, exact commands/results, and not-run reason.

6. **High - markerPDF still has no real upstream benchmark PDF/reference pair mapped.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:69`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:124`.
   - Evidence: markerPDF maps source/image/equation/table-formatting semantics and surrogate outputs, but `mappedBenchmarkPairs` remains `0`; the real benchmark runner remains `not-executed` because the external benchmark PDFs/references and heavy dependencies are absent. The manifest also reports `mapped: 46` over a `total` of 38 inspected artifacts, which mixes semantic checks with artifact denominator units.
   - Goal requirement at risk: `goal.md` scopes markerPDF as PDF-to-structured-content extraction and requires meaningful fixture parity, not only surrogate Markdown comparisons.
   - Audit judgment: acquire and map at least one actual `benchmark_data` PDF/reference pair before more cleaner/OCR/image/equation/table-formatting breadth counts as high-value markerPDF progress.

7. **Medium - Gitoxide progress remains ahead of upstream evidence quality for the priority-1 lane.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/gitoxide/lane-status.json:4-12`, `porting.html:56`, `porting-summary.json:57-71`.
   - Evidence: Gitoxide reports high progress and 853 mapped items, but the Cargo runner is still not executed. The manifest uses a large prose inventory rather than normalized upstream fixture IDs, native test IDs, and uncovered category accounting. Dashboard/status counts also disagree: dashboard `737` mapped and `1257 pass`; manifest `853` mapped; lane status `1421` assertions.
   - Goal requirement at risk: `goal.md` prioritizes Gitoxide and requires upstream tests as the source of truth whenever possible.
   - Audit judgment: keep Gitoxide estimates conservative until it has normalized fixture/test IDs and uncovered-category accounting, or a controlled crate-level upstream runner probe.

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
81 test files, 5622 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then integrate or reject the remaining dirty lane batches from one state. Regenerate `progress.md`, `porting.html`, and `porting-summary.json`, refresh lane-status blockers, normalize status/dashboard schema fields, and prioritize markerPDF real benchmark PDF/reference parity plus Gitoxide normalized fixture IDs or a controlled crate-level runner probe.
