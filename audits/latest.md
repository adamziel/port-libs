# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status/dashboard state, bridge/shell-out usage, the dirty worktree, and recent Git history through `b9824d1` (`Stamp LightningCSS lane status`) as observed during this audit. I did not edit lane implementation files, launch agents, launch tmux sessions, or push.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are materially stale against the current manifests and lane statuses.**
   - Paths: `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:11`, `porting-summary.json:28`, `porting-summary.json:45`, `porting-summary.json:62`, `porting-summary.json:79`, `porting-summary.json:96`, `porting-summary.json:113`, `porting-summary.json:129`, `porting-summary.json:147`, `porting-summary.json:164`, `porting-summary.json:181`, `porting-summary.json:198`.
   - Evidence: the summary was generated at `2026-05-22 15:40:20 UTC`, but current manifests disagree across every lane: difftastic `26` mapped vs dashboard `15`, Dolt `22` vs `5`, esbuild `26` vs `16`, Gitoxide `782` vs `737`, libsqlite `28` vs `18`, LightningCSS `97` vs `78`, markerPDF `25` vs `11`, Pandoc denominator `2028`/mapped `31` vs dashboard `1979`/`19`, Quadrable `36` vs `24`, rclone `28` vs `20`, Readability `146` vs `89`, and Syncthing `42` vs `27`.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show accurate per-lane mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit.
   - Audit judgment: regenerate the dashboard only after worker output is quiesced and lane changes are integrated or rejected from one committed state.

2. **High - The audit target is still moving while `progress.md` presents a stable stopped-lane snapshot.**
   - Paths: `progress.md:31`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:229`, `progress.md:231`, `progress.md:235`, current `git status --short`, and recent Git history from `71122c6` through `b9824d1`.
   - Evidence: recent history advanced during this audit through rclone, Dolt, Quadrable, markerPDF, Syncthing, difftastic, Gitoxide, and LightningCSS commits including `ccff935`, `bc06077`, `83c358d`, `9730b2c`, `4da564c`, `63d6b9b`, `2ae3c9a`, and `b9824d1`. The worktree still contains dirty lane implementation/status/dashboard changes and untracked audit/status/source/test files. `progress.md` still lists every lane session as stopped with stale estimates and next tasks.
   - Goal requirement at risk: `goal.md` requires the supervisor to verify finished agent work, commit small passing slices, update progress, clean accidental unrelated changes, and keep the roadmap honest.
   - Audit judgment: treat the current repository as an active integration surface, not a stable release or dashboard snapshot.

3. **High - Gitoxide progress remains overstated relative to a machine-checkable upstream denominator.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:20`, `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/lane-status.json:12`, `porting.html:56`.
   - Evidence: the priority-1 lane reports about `68%` progress and `782 / 2877` mapped, but the Cargo runner is still not executed and the manifest is a long prose static inventory rather than a normalized list of upstream suites/fixtures with pass/fail state. The local PHP count is smoke coverage, not upstream pass parity.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark denominator, or a clearly marked defensible static inventory, with upstream tests as source of truth for large suites.
   - Audit judgment: Gitoxide should stay conservative until fixture IDs, uncovered areas, and native mapped checks are normalized into auditable records, or a controlled crate-level upstream runner is added.

4. **High - markerPDF still has zero real upstream benchmark PDF/reference pairs.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:57`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:58`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:85`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:120`.
   - Evidence: the manifest maps 25 focused source semantics and 4 README-linked surrogate pairs, but `mappedBenchmarkPairs` remains `0` and the real upstream benchmark runner is still blocked on missing external benchmark PDFs/references plus heavy Poetry/model dependencies.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline for WordPress import/Data Liberation and requires meaningful fixture parity, not only surrogate Markdown-output scoring.
   - Audit judgment: the next markerPDF intervention should acquire at least one real `benchmark_data` PDF/reference pair before broadening OCR/layout behavior further.

5. **Medium - PHP pass/fail counts are not consistently counting the same unit.**
   - Paths: `lanes/gitoxide/lane-status.json:6`, `lanes/gitoxide/lane-status.json:12`, `lanes/lightningcss/lane-status.json:6`, `lanes/lightningcss/lane-status.json:10`, `porting.html:56`, `porting.html:58`.
   - Evidence: Gitoxide stores `phpPass: 1329` while the blocker text says `24 files, 1329 assertions`; LightningCSS stores `phpPass: 162` while its audit text calls the same value assertion coverage. Other lanes use behavior-test counts. The dashboard renders these mixed units as `pass / fail`.
   - Goal requirement at risk: `goal.md` requires per-lane PHP pass/fail counts that are meaningful for status comparison.
   - Audit judgment: record both `phpTestFiles`/`phpBehaviorTests` and `phpAssertions`, or standardize `phpPass` to behavior tests and keep assertions separate.

6. **Medium - Stale lane blocker text contradicts the current root test result.**
   - Paths: `lanes/esbuild/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`, `progress.md:194`, `progress.md:199`.
   - Evidence: esbuild status still says a final root run fails in `lanes/quadrable/tests/SyncTest.php`, and Syncthing status says the latest root run exits 1 with an unrelated Readability failure. This audit's root run is green: `61 test files, 3433 assertions, 0 failures`.
   - Goal requirement at risk: `goal.md` requires blockers and repo-wide test failures to be recorded honestly and precisely.
   - Audit judgment: refresh status/progress from the current green run after lane changes are quiesced.

7. **Medium - Quadrable has upstream runner evidence but not digest-compatible native proof roots.**
   - Paths: `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`, `lanes/quadrable/lane-status.json:10`, `lanes/quadrable/lane-status.json:12`.
   - Evidence: upstream `make -r test` passes, but native PHP `HashTree` still uses a SHA-256 surrogate because this PHP build lacks BLAKE2s. That means roots/proof bytes are not upstream digest-compatible yet.
   - Goal requirement at risk: `goal.md` requires faithful core data model and algorithm ports, not just shape-compatible behavior.
   - Audit judgment: after status is stabilized, the next Quadrable quality intervention should address BLAKE2s compatibility or explicitly isolate this as a known non-parity boundary in the dashboard.

## Bridge / Shell-Out Check

Command searched PHP files under `lanes`, `tools`, and `scripts` for `shell_exec`, `exec`, `passthru`, `proc_open`, `system`, and `popen` using `rg --pcre2`. No PHP process-execution bridge matches were found.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
61 test files, 3433 assertions, 0 failures
```

## Recommended Next Intervention

Stop the moving target first: quiesce or explicitly coordinate workers, integrate or reject the current dirty lane changes, then regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the same committed state. After status is honest again, prioritize markerPDF real benchmark PDF/reference parity, Gitoxide normalized fixture IDs or a crate-level runner probe, and a dashboard schema that separates upstream runner evidence, native PHP behavior tests, and assertion counts.
