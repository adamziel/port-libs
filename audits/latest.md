# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `abae82b`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is dirty with many uncommitted lane implementation files, manifest/status updates, dashboard artifacts, and untracked fixtures/examples. Findings below are against the current dirty worktree after running the required root PHP harness.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` materially disagree with current manifests and the green root test result.**
   - Paths: `porting.html:30`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:9`, `porting-summary.json:26`, `porting-summary.json:43`, `porting-summary.json:60`, `porting-summary.json:77`, `porting-summary.json:94`, `porting-summary.json:111`, `porting-summary.json:128`, `porting-summary.json:145`, `porting-summary.json:162`, `porting-summary.json:179`, `porting-summary.json:196`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC`, still reports average progress `14.3%`, and does not show the current root result of `96 test files, 6403 assertions, 0 failures`. Current manifest mapped counts vs dashboard mapped counts are: difftastic `44 vs 15`, Dolt `55 vs 5`, esbuild `52 vs 16`, Gitoxide `857 vs 737`, libsqlite `48 vs 18`, LightningCSS `154 vs 78`, markerPDF `65 vs 11`, Pandoc `103 vs 19`, Quadrable `53 vs 24`, rclone `75 vs 20`, Readability `222 vs 89`, Syncthing `73 vs 27`. It also reports stale blockers, for example Quadrable still says the C++ runner fails while the manifest now records `make -r test` passing.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, upstream denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit.
   - Audit judgment: treat the dashboard as stale browsing output until regenerated from one accepted green state.

2. **High - lane status files contain mutually incompatible root-suite claims and commit gates.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`, `lanes/markerpdf/lane-status.json:10`, `lanes/pandoc/lane-status.json:10`, `lanes/quadrable/lane-status.json:10`, `lanes/rclone/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: lane statuses variously claim root results of `94/6277/0`, `95/6358/0`, `95/6355/0`, `95/6313/0`, `95/5971/58`, `94/6280/0`, `95/6292/1`, `94/6277/0`, and `93/6269/0`. The current root result is `96/6403/0`. Some blockers still say the root suite is red from unrelated Pandoc or older lane failures even though the current root run is green.
   - Goal requirement at risk: `goal.md` requires precise blockers, latest commit, audit status, and passing verified slices.
   - Audit judgment: do not use per-lane blocker text as an integration signal until lane statuses are regenerated or hand-reconciled after the current green root state is frozen.

3. **High - `progress.md` needed blocker and next-intervention correction, but its lane table remains stale.**
   - Paths: `progress.md:15`, `progress.md:31`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:231`, `progress.md:235`.
   - Evidence: at audit start, `progress.md` still recorded the previous red audit state. This run updates the root-suite blocker and next intervention to the current green result, but the Active Lanes table and next tasks remain older than the current manifests and dirty lane batches.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current blockers, audit status, current owner/session, next task per lane, and next best intervention.
   - Audit judgment: keep only blocker/next-intervention updates here; a full progress reconciliation should wait for the supervisor to accept or reject the dirty lane batches and regenerate the dashboard.

4. **High - upstream runner evidence is still not modeled consistently enough for reliable dashboard math.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:47`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:88`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`.
   - Evidence: Dolt sets `runnerStatus.executed: true` while its reason starts with "The full upstream runners were not executed"; Gitoxide uses a free-form string, markerPDF uses `"not-executed"` plus a separate blocker, Pandoc has warning text but no normalized runner-status object, and Quadrable stores a pass sentence in `runnerStatus`. These shapes cannot cleanly distinguish full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior-test counts, assertions, and failures.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize manifest runner evidence before letting the dashboard aggregate or compare lane status.

5. **High - markerPDF still has no real upstream benchmark PDF/reference pair, despite a mapped count greater than the static denominator.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:88`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:89`.
   - Evidence: the manifest reports `61 targeted upstream lane-relevant paths` plus `6 README benchmark document identifiers`, `mapped: 65`, `mappedBenchmarkPairs: 0`, and `mappedBenchmarkSurrogatePairs: 4`. The full benchmark runner remains not executed because benchmark PDFs/references and heavy dependencies are absent.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not only cleaner/scoring surrogates.
   - Audit judgment: acquire at least one real `benchmark_data` PDF/reference pair before counting additional markerPDF breadth as strong upstream parity progress.

6. **Medium - the repository is still a dirty moving integration target.**
   - Paths: `git status --short --untracked-files=all`, `git log --oneline -n 10`, `progress.md:227`, `progress.md:230`, `progress.md:231`.
   - Evidence: recent history advanced during the audit and now includes `abae82b Stamp pandoc lane status`, while `git status` still shows modified implementation, tests, manifests, notes, lane statuses, and dashboard files across multiple lanes plus many untracked fixtures/examples and untracked audit files. This audit intentionally did not inspect or launch tmux sessions, and `progress.md` still warns that workers may be active despite the Active Lanes table marking every lane stopped.
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

Exit status: 0

Exact result:

```text
96 test files, 6403 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from this same green state. After the coordination surface is current, prioritize markerPDF real benchmark PDF/reference parity and normalized dashboard fields for upstream runner evidence versus native PHP behavior-test/assertion counts.
