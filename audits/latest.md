# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, current dirty worktree state, bridge/shell-out usage, and recent Git history through `a26e045`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: `HEAD` advanced during this audit from `f225568` through `a26e045`; the worktree remained dirty. Findings below are against the latest dirty worktree observed after the final manifest/status read. The root test command recorded below ran during the same moving audit window and exited green.

## Findings

1. **Critical - the repo is still a moving dirty integration target, so coordination files are not a stable release signal.**
   - Paths: current `git status --short`, recent history through `a26e045`, `progress.md:229`, `progress.md:231`, `porting.html:32`.
   - Evidence: `HEAD` advanced while audit evidence was being gathered, and the worktree still contains modified lane implementation files, manifests, status files, notes, `porting.html`, `porting-summary.json`, plus untracked audit/status files and lane fixtures. The latest root test run is green, but it was run against this dirty moving state.
   - Goal requirement at risk: `goal.md` requires small reviewable committed slices, verified finished agent work, cleanup of accidental unrelated changes, durable coordination, and honest status tracking.
   - Audit judgment: active writers need to be quiesced or explicitly coordinated before accepting any dashboard, lane status, or progress file as authoritative.

2. **Critical - `porting.html` and `porting-summary.json` are stale against current lane manifests.**
   - Paths: `porting.html:32`, `porting.html:53-64`, `porting-summary.json:2-207`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC` and still shows old mapped counts. Current manifest mapped counts are: difftastic `39` vs dashboard `15`, Dolt `46` vs `5`, esbuild `44` vs `16`, Gitoxide `856` vs `737`, libsqlite `44` vs `18`, LightningCSS `143` vs `78`, markerPDF `49` vs `11`, Pandoc `60` vs `19`, Quadrable `51` vs `24`, rclone `64` vs `20`, Readability `220` vs `89`, and Syncthing `67` vs `27`.
   - Additional evidence: the dashboard still reports Quadrable's blocker as a failed C++ runner (`porting.html:61`), while the manifest says `make -r test` passes (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-18`). It also carries old denominators for LightningCSS (`312` dashboard vs `382` manifest), markerPDF (`27` vs `39`), and Pandoc (`1979` vs `2028`).
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard and compact JSON only after the dirty lane batches are accepted from one committed green state.

3. **High - `progress.md` Active Lanes and audit notes are stale enough to misdirect the supervisor.**
   - Paths: `progress.md:31-42`, `progress.md:194`, `progress.md:199`, `progress.md:205-206`, `progress.md:235`.
   - Evidence: the Active Lanes table still shows old stopped phases and old next tasks. `progress.md:194` still records the previous audit test result as `81 test files, 5622 assertions`, while the current audit result is `85 test files, 5757 assertions`. `progress.md:205` says Readability maps `15` local behavior tests and `89` checks, but the current manifest maps `27` behavior tests and `187` checks (`lanes/readability/UPSTREAM_TEST_MANIFEST.json:95-101`). `progress.md:206` still says `go` is not on PATH for Syncthing, while the lane status now says `/usr/bin/go` is available (`lanes/syncthing/lane-status.json:12`).
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, phase, blocker, next task, owner/session, and percentage estimates.
   - Audit judgment: keep the audit warning in `progress.md`, but do not hand-edit the whole lane table until the supervisor chooses which dirty lane batches to accept.

4. **High - lane status files and dashboard fields still mix PHP count units.**
   - Paths: `lanes/difftastic/lane-status.json:6-12`, `lanes/dolt/lane-status.json:6-12`, `lanes/esbuild/lane-status.json:6-12`, `lanes/gitoxide/lane-status.json:6-12`, `lanes/lightningcss/lane-status.json:6-12`, `lanes/readability/lane-status.json:6-12`, `porting.html:53-64`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: `phpPass` still mixes behavior-test and assertion units. Gitoxide reports `phpPass: 1457` as assertions and LightningCSS reports `218` assertions, while Dolt/esbuild/rclone/readability report behavior-test counts. Several manifests still omit `nativeImplementation.phpBehaviorTests`, and the dashboard's `pass / fail` label makes these mixed units look comparable.
   - Goal requirement at risk: `goal.md` requires honest per-lane PHP pass/fail counts and precise blocker recording.
   - Audit judgment: split status fields into PHP test files, behavior tests, assertions, and failures; expire old root-suite text whenever a newer root result exists.

5. **High - upstream runner status remains inconsistently modeled, making bounded evidence easy to confuse with full parity.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:75`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:91`.
   - Evidence: Dolt has `runnerStatus.executed: true` while the warning says it is bounded evidence and not full parity; Gitoxide, markerPDF, and Quadrable use string runner statuses; Pandoc has no explicit runner-status object; Syncthing uses a structured non-executed object. Bounded runner evidence is useful, but the schema does not force readers to distinguish bounded from full upstream parity.
   - Goal requirement at risk: `goal.md` requires a defensible upstream benchmark denominator and precise blocker status when upstream runners cannot execute.
   - Audit judgment: normalize runner fields as full-run executed, bounded-run executed, scope, exact commands/results, and not-run reason.

6. **High - markerPDF still has no real upstream benchmark PDF/reference pair mapped.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:75`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:128`.
   - Evidence: markerPDF maps source, OCR/image/equation/table-formatting/table-utility semantics and surrogate outputs, but `mappedBenchmarkPairs` remains `0`; the real benchmark runner remains `not-executed` because the external benchmark PDFs/references and heavy dependencies are absent. The manifest also reports `mapped: 49` over a `total` of `39` inspected artifacts, which mixes semantic checks with artifact denominator units.
   - Goal requirement at risk: `goal.md` scopes markerPDF as PDF-to-structured-content extraction and requires meaningful fixture parity, not only surrogate Markdown comparisons.
   - Audit judgment: acquire and map at least one actual `benchmark_data` PDF/reference pair before more cleaner/OCR/image/equation/table-formatting breadth counts as high-value markerPDF progress.

7. **Medium - Gitoxide progress remains ahead of upstream evidence quality for the priority-1 lane.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/gitoxide/lane-status.json:6-13`, `porting.html:56`, `porting-summary.json:57-71`.
   - Evidence: Gitoxide reports high progress and `856` mapped items, but the Cargo runner is still not executed. The manifest uses a large prose inventory rather than normalized upstream fixture IDs, native test IDs, and uncovered-category accounting. Dashboard/status counts also disagree: dashboard `737` mapped and `1257 pass`; manifest `856` mapped; lane status `1457` assertions.
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
86 test files, 5790 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then integrate or reject the remaining dirty lane batches from one state. Regenerate `progress.md`, `porting.html`, and `porting-summary.json`, refresh lane-status blockers, normalize status/dashboard schema fields, and prioritize markerPDF real benchmark PDF/reference parity plus Gitoxide normalized fixture IDs or a controlled crate-level runner probe.
