# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `f6b8349`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is still dirty with uncommitted lane implementation files, manifest/status updates, dashboard artifacts, fixtures, examples, and unrelated audit/status files. Recent commits also advanced during this audit window, so this report treats the coordination surface as a moving target and anchors the test evidence to the explicit root run below.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale enough to mislead reviewers about every active lane.**
   - Paths: `porting.html:30`, `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:11`, `porting-summary.json:28`, `porting-summary.json:45`, `porting-summary.json:62`, `porting-summary.json:79`, `porting-summary.json:96`, `porting-summary.json:113`, `porting-summary.json:130`, `porting-summary.json:147`, `porting-summary.json:164`, `porting-summary.json:181`, `porting-summary.json:198`.
   - Evidence: dashboard generation is still `2026-05-22 15:40:20 UTC` while recent history is now at `f6b8349`. Current manifest mapped counts vs dashboard mapped counts are: difftastic `46 vs 15`, Dolt `55 vs 5`, esbuild `52 vs 16`, Gitoxide `859 vs 737`, libsqlite `48 vs 18`, LightningCSS `159 vs 78`, markerPDF `66 vs 11`, Pandoc `103 vs 19`, Quadrable `54 vs 24`, rclone `78 vs 20`, Readability `236 vs 89`, Syncthing `77 vs 27`. Several dashboard blockers are also obsolete, for example Quadrable still says the C++ runner failed while the manifest says `make -r test` passes.
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not use the dashboard or summary JSON as the browsing source of truth until regenerated from one accepted green state.

2. **High - lane status files still contain incompatible root-suite claims, including stale red blockers.**
   - Paths: `lanes/difftastic/lane-status.json:12`, `lanes/dolt/lane-status.json:10`, `lanes/dolt/lane-status.json:12`, `lanes/esbuild/lane-status.json:10`, `lanes/esbuild/lane-status.json:12`, `lanes/lightningcss/lane-status.json:10`, `lanes/lightningcss/lane-status.json:12`, `lanes/markerpdf/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/quadrable/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: this audit's root run is `98 test files, 6532 assertions, 0 failures`. Lane statuses still claim conflicting root results such as `95/6358/0`, `97/6486/0`, `94/6280/0`, a Difftastic blocker with `96/6440/1`, a Dolt blocker citing an unrelated esbuild failure, and a Quadrable blocker with `98/6531/1`. These are not harmless annotations: they decide whether a lane can be integrated or blocked.
   - Goal requirement at risk: `goal.md` requires precise blockers, verified passing slices, current audit status, and latest commit per lane.
   - Audit judgment: refresh or hand-reconcile all lane statuses after freezing writers; stale per-lane blockers should not gate current integration.

3. **High - `progress.md` remains only partially current, even after this audit correction.**
   - Paths: `progress.md:31`, `progress.md:34`, `progress.md:194`, `progress.md:199`, `progress.md:230`, `progress.md:231`, `progress.md:235`.
   - Evidence: this run updates the latest audit/root-suite line and the next intervention, but the Active Lanes table still shows stopped sessions and older next tasks while manifests and recent commits have advanced. `progress.md` also cannot be fully reconciled safely while uncommitted lane batches and generated dashboard files remain in flux.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current active lanes, blockers, owner/session, next task per lane, percentage estimates, audit status, and next best intervention.
   - Audit judgment: the file is acceptable only as a warning/coordination note until the supervisor freezes writers and regenerates the full state.

4. **High - markerPDF still has no real upstream benchmark PDF/reference pair.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:91`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:92`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:161`.
   - Evidence: the manifest reports `mapped: 66`, but `mappedBenchmarkPairs: 0` and `mappedBenchmarkSurrogatePairs: 4`. The runner is still `not-executed` because the benchmark PDFs/references and heavy model dependencies are absent. Surrogate Markdown pairs are useful scaffolding, but they do not prove PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not just cleaner/scoring surrogate coverage.
   - Audit judgment: the next markerPDF intervention should be one real `benchmark_data` PDF/reference pair before any more breadth is counted as strong upstream parity progress.

5. **Medium - upstream runner evidence is normalized poorly across manifests, so dashboard math cannot distinguish full parity, bounded evidence, and static inventory.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:91`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:92`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:101`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38`.
   - Evidence: Dolt sets `runnerStatus.executed: true` while the reason starts with "The full upstream runners were not executed"; Gitoxide and Quadrable use free-form strings; markerPDF uses a string plus `runnerBlocker`; Pandoc has only warning text for the unexecuted runner; rclone/readability use structured objects. This explains why the dashboard mixes local PHP tests, assertions, bounded upstream runner evidence, and full upstream runner pass evidence.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: add a normalized runner-evidence schema before letting dashboard percentages or blockers drive portfolio decisions.

6. **Medium - the repository is still a dirty moving integration target despite the green root suite.**
   - Paths: `audits/integration-status.md`, `lanes/difftastic/src/JsonDiffRenderer.php`, `lanes/difftastic/src/TokenDiffer.php`, `lanes/esbuild/src/TypeScriptNamespaceLowerer.php`, `lanes/lightningcss/src/CssMinifier.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/quadrable/src/SparseTree.php`, `porting.html`, `porting-summary.json`, plus untracked examples/fixtures/tests under several lanes.
   - Evidence: `git status --short --untracked-files=all` shows modified implementation, tests, manifests, notes, lane statuses, audit status, and generated dashboard files across multiple lanes, plus untracked Dolt, Difftastic, esbuild, LightningCSS, and Quadrable artifacts. Recent commits advanced during this audit window through `f6b8349`.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, and committed dashboard/progress status that reflects the accepted state.
   - Audit judgment: green root tests are necessary but not sufficient; freeze or coordinate writers before integrating or publishing the current status.

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
98 test files, 6532 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and every lane status from this same green root-suite state. After the coordination surface is current, prioritize markerPDF real benchmark PDF/reference parity and normalize runner evidence fields so dashboard math separates full upstream runner parity, bounded runner evidence, static inventory, native PHP behavior tests, assertions, and failures.
