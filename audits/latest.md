# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `57015bf`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is dirty with many uncommitted lane implementation files, manifest/status updates, dashboard artifacts, and untracked fixtures/examples. Findings below are against the current dirty worktree after running the required root PHP harness.

## Findings

1. **Critical - the required root PHP suite is still red, now in the priority-1 Gitoxide lane.**
   - Paths: `lanes/gitoxide/tests/CommitTest.php:230`, `lanes/gitoxide/tests/CommitTest.php:231`, `lanes/gitoxide/src/CommitMessage.php:46`, `lanes/gitoxide/src/CommitMessage.php:410`, `lanes/gitoxide/lane-status.json:10`.
   - Evidence: `php tools/run-tests.php` exits 1 with `94 test files, 6270 assertions, 1 failures`. The failing test is `commit message parsing uses gitoxide ascii byte classes`; `CommitMessage::summaryOf()` is not preserving/trimming the control-byte boundary expected by the test fixture. The Gitoxide lane status also says the root failure is in unrelated dirty lanes, but the current failing test is Gitoxide's own commit-message slice.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, Gitoxide is the top priority lane, and upstream behavior should remain the source of truth.
   - Audit judgment: do not integrate or publish the current dirty lane batch as a stable progress point until this Gitoxide failure is fixed and the same state is rerun green.

2. **Critical - `porting.html` and `porting-summary.json` materially disagree with current manifests and test status.**
   - Paths: `porting.html:30`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:9`, `porting-summary.json:26`, `porting-summary.json:43`, `porting-summary.json:60`, `porting-summary.json:77`, `porting-summary.json:94`, `porting-summary.json:111`, `porting-summary.json:128`, `porting-summary.json:145`, `porting-summary.json:162`, `porting-summary.json:179`, `porting-summary.json:196`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC` and still reports average progress `14.3%` plus stale mapped/PHP counts. Current manifest mapped counts vs dashboard mapped counts are: difftastic `44 vs 15`, Dolt `55 vs 5`, esbuild `50 vs 16`, Gitoxide `857 vs 737`, libsqlite `46 vs 18`, LightningCSS `154 vs 78`, markerPDF `65 vs 11`, Pandoc `78 vs 19`, Quadrable `53 vs 24`, rclone `71 vs 20`, Readability `222 vs 89`, Syncthing `73 vs 27`. It also reports stale lane blockers, for example Quadrable still says the C++ runner fails while the manifest now records `make -r test` passing.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, upstream denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit.
   - Audit judgment: the dashboard should be treated as stale browsing output until regenerated from one accepted green state.

3. **High - lane status files contain mutually incompatible root-suite claims and commit gates.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`, `lanes/markerpdf/lane-status.json:10`, `lanes/pandoc/lane-status.json:10`, `lanes/quadrable/lane-status.json:10`, `lanes/rclone/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: lane statuses variously claim root results of `94/6277/0`, `93/6233/0`, `92/6178/1`, `93/6205/5`, `93/6154/9`, `91/6110/3`, `93/6257/0`, and `93/6269/0`. The current root result is `94/6270/1`. Several statuses call their own slices commit-ready or blocked by unrelated failures despite the current failure being in Gitoxide.
   - Goal requirement at risk: `goal.md` requires precise blockers, latest commit, audit status, and passing verified slices.
   - Audit judgment: stop trusting per-lane blocker text as an integration signal until statuses are regenerated or hand-reconciled after the root suite is green.

4. **High - `progress.md` is stale on the current blocker and next intervention.**
   - Paths: `progress.md:15`, `progress.md:31`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:231`, `progress.md:235`.
   - Evidence: at audit start, `progress.md` still recorded the latest audit as `9b39132` with `93 test files, 6154 assertions, and 9 failures` in Dolt/Quadrable, while recent history is at `57015bf` and the current root result is `94 test files, 6270 assertions, and 1 failure` in Gitoxide. This run updates the blocker/next-intervention lines, but the Active Lanes table and next tasks remain older than the current manifests and should not be hand-reconciled until the dirty lane batches are accepted or rejected.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current blockers, audit status, current owner/session, next task per lane, and next best intervention.
   - Audit judgment: keep only blocker/next-intervention updates here; a full progress reconciliation should wait for the supervisor to accept or reject the dirty lane batches.

5. **High - upstream runner evidence is still not modeled consistently enough for reliable dashboard math.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:47`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:88`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: Dolt sets `runnerStatus.executed: true` while its reason starts with "The full upstream runners were not executed"; Gitoxide uses a free-form string, markerPDF uses `"not-executed"` plus a separate blocker, Pandoc has warning text but no normalized runner-status object, and Quadrable stores a pass sentence in `runnerStatus`. These shapes cannot cleanly distinguish full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior-test counts, assertions, and failures.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize manifest runner evidence before letting the dashboard aggregate or compare lane status.

6. **High - markerPDF still has no real upstream benchmark PDF/reference pair, despite a mapped count greater than the static denominator.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:88`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:89`.
   - Evidence: the manifest reports `61 targeted upstream lane-relevant paths` plus `6 README benchmark document identifiers`, `mapped: 65`, `mappedBenchmarkPairs: 0`, and `mappedBenchmarkSurrogatePairs: 4`. The full benchmark runner remains not executed because benchmark PDFs/references and heavy dependencies are absent.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not only cleaner/scoring surrogates.
   - Audit judgment: acquire at least one real `benchmark_data` PDF/reference pair before counting additional markerPDF breadth as strong upstream parity progress.

7. **Medium - the repository is still a dirty moving integration target.**
   - Paths: `git status --short --untracked-files=all`, `progress.md:227`, `progress.md:230`, `progress.md:231`.
   - Evidence: recent history ends at `57015bf Record Syncthing lane status`, but `git status` shows modified implementation, tests, manifests, notes, lane statuses, and dashboard files across difftastic, Dolt, esbuild, Gitoxide, libsqlite, LightningCSS, markerPDF, Quadrable, rclone, Readability, plus many untracked fixtures/examples and untracked audit files. This audit intentionally did not inspect or launch tmux sessions, and `progress.md` still warns that workers may be active despite the Active Lanes table marking every lane stopped.
   - Goal requirement at risk: `goal.md` requires verified agent work, cleanup of unrelated changes, committed slices, and durable coordination.
   - Audit judgment: quiesce or explicitly coordinate writers before treating any audit, dashboard, or status file as authoritative.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no PHP process-execution bridge calls found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 1

Exact result:

```text
94 test files, 6270 assertions, 1 failures
```

Failing test:

```text
FAIL commit message parsing uses gitoxide ascii byte classes (lanes/gitoxide/tests/CommitTest.php)
Values are not identical
Expected: control-byte wrapped "Import WordPress export"
Actual: "Import WordPress export"
```

## Recommended Next Intervention

Stop broad lane integration. Fix the Gitoxide `CommitMessage::summaryOf()` ASCII/control-byte behavior or reject that dirty slice, rerun `php tools/run-tests.php` to green from a single state, then regenerate `porting.html`, `porting-summary.json`, lane statuses, and the relevant `progress.md` coordination lines from that same state. After the root suite is green, prioritize markerPDF real benchmark PDF/reference parity and normalized dashboard fields for upstream runner evidence versus native PHP behavior-test/assertion counts.
