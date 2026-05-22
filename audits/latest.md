# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `9b39132`. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is dirty with many uncommitted lane implementation files, manifest/status updates, dashboard artifacts, and untracked fixtures/examples. Findings below are against the current dirty worktree after running the required root PHP harness.

## Findings

1. **Critical - the required root PHP suite is red in the current tree.**
   - Paths: `lanes/dolt/tests/SchemaHistoryTableTest.php:149`, `lanes/dolt/tests/SchemaHistoryTableTest.php:174`, `lanes/quadrable/tests/ProofMergeTest.php:33`, `lanes/quadrable/tests/ProofMergeTest.php:52`, `lanes/quadrable/tests/ProofMergeTest.php:94`, `lanes/quadrable/tests/SyncTest.php:66`, `lanes/quadrable/tests/SyncTest.php:105`, `lanes/quadrable/tests/SyncTest.php:154`, `lanes/quadrable/tests/SyncTest.php:216`, `lanes/quadrable/src/SparseTree.php:1224`, `lanes/quadrable/src/SparseTree.php:1345`.
   - Evidence: `php tools/run-tests.php` exited 1 with `93 test files, 6154 assertions, 9 failures`. Dolt has two schema-history failures: JSON `extra` key ordering is treated as a modification, and WordPress schema diff row ordering is not the expected added/added/modified/removed sequence. Quadrable has seven proof/sync failures: three `mergeProof` cases call `SparseTree::nodeIdFromPartialNode()` unsuccessfully and four sync cases call `SparseTree::allocatePartialNodeId()` unsuccessfully.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests and honest repo-wide test recording.
   - Audit judgment: do not integrate or publish the dirty lane batches as progress until Dolt and Quadrable are fixed or rolled out of the integration set and the root suite is rerun from the same state.

2. **Critical - dashboard, progress, and lane status files disagree with the current manifests and test result.**
   - Paths: `porting.html:32`, `porting.html:53-64`, `porting-summary.json:2-207`, `progress.md:31-42`, `progress.md:194`, `progress.md:199`, `lanes/*/lane-status.json`.
   - Evidence: `porting.html` and `porting-summary.json` are still generated at `2026-05-22 15:40:20 UTC`. Current manifest/dashboard mapped counts disagree across all lanes: difftastic `42` vs `15`, Dolt `51` vs `5`, esbuild `50` vs `16`, Gitoxide `857` vs `737`, libsqlite `45` vs `18`, LightningCSS `148` vs `78`, markerPDF `61` vs `11`, Pandoc `78` vs `19`, Quadrable `52` vs `24`, rclone `71` vs `20`, Readability `218` vs `89`, and Syncthing `69` vs `27`. Several lane statuses also record incompatible root-suite claims, ranging from old Pandoc/Difftastic/Gitoxide failures to green `90/6101` and `93/6233` results, none matching the current `93/6154/9` run.
   - Goal requirement at risk: `goal.md` requires `progress.md` and `porting.html` to show current suite progress, mapped tests, PHP pass/fail, phase, audit status, current work, blocker, and commit.
   - Audit judgment: regenerate dashboard artifacts and lane statuses only after accepting or rejecting the dirty lane batches from one green state.

3. **High - the repository is still a dirty integration target, so recent status cannot be treated as a stable release signal.**
   - Paths: `git status --short --untracked-files=all`, `git log --oneline -n 25`, `progress.md:229-235`.
   - Evidence: recent history ends at `9b39132 Refresh independent audit`, while `git status` shows modified implementation files in difftastic, esbuild, gitoxide, libsqlite, lightningcss, pandoc, quadrable, syncthing, plus modified manifests/status notes/dashboard files and many untracked lane source/test/example fixtures. `progress.md` still says worker sessions are active despite the Active Lanes table marking every lane stopped.
   - Goal requirement at risk: `goal.md` requires verified agent work, cleanup of unrelated changes, committed slices, and durable coordination.
   - Audit judgment: freeze or explicitly coordinate writers before treating any audit, dashboard, or test result as authoritative.

4. **High - `progress.md` continues to overstate the current baseline and leaves stale supervisor guidance in place.**
   - Paths: `progress.md:15`, `progress.md:31-42`, `progress.md:194`, `progress.md:201-211`, `progress.md:233-235`.
   - Evidence: the roadmap still marks the required all-lane baseline complete even though the root suite is red and the dashboard is stale. The Active Lanes table points to old next tasks, including Quadrable proof/update work that now has failing proof/sync tests and Dolt being deferred while untracked Dolt schema-history tests are already in the root harness. Several blocker bullets still cite older counts or obsolete tool availability.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current blockers, active lanes, next task per lane, audit status, and next best intervention.
   - Audit judgment: keep the warning status; do not hand-reconcile the whole lane table until the supervisor decides which dirty lane batches survive.

5. **High - upstream runner evidence is not modeled consistently enough for dashboard or estimate decisions.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46-50`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:83-84`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-18`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:97-108`.
   - Evidence: Dolt uses `runnerStatus.executed: true` while its reason says full upstream runners were not executed and only bounded evidence passed. Gitoxide and markerPDF use string statuses, Quadrable uses a pass sentence, Pandoc has no comparable runner-status object, and Syncthing uses a structured not-executed object. These cannot drive a reliable dashboard distinction between full upstream parity, bounded upstream evidence, static inventory, and local PHP smoke coverage.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize manifests before raising estimates based on runner evidence.

6. **High - markerPDF still has no real upstream benchmark PDF/reference pair.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:83-84`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:99-104`.
   - Evidence: the manifest reports `mappedBenchmarkPairs: 0` and `mappedBenchmarkSurrogatePairs: 4`; the upstream runner remains `not-executed` because benchmark PDFs/references and heavy dependencies are absent. The current surrogate Markdown examples are useful, but they do not prove PDF-to-structured-content extraction parity.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF extraction pipeline and requires meaningful fixture parity.
   - Audit judgment: acquire at least one real `benchmark_data` PDF/reference pair before counting more cleaner/OCR/table breadth as high-value markerPDF progress.

7. **Medium - Gitoxide remains ahead of its upstream evidence quality for the priority-1 lane.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-21`, `lanes/gitoxide/lane-status.json:9-12`, `porting.html:56`, `porting-summary.json:57-71`.
   - Evidence: Gitoxide reports `857` mapped items and a high progress estimate, but the Cargo runner is still not executed and the manifest is a long prose inventory rather than normalized upstream fixture IDs, native test IDs, and uncovered-category accounting. The dashboard is stale at `737` mapped and `1257` pass.
   - Goal requirement at risk: `goal.md` prioritizes Gitoxide and says upstream tests should be the source of truth whenever possible.
   - Audit judgment: keep Gitoxide estimates conservative until a controlled crate-level runner probe or normalized fixture/test accounting exists.

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
93 test files, 6154 assertions, 9 failures
```

Failing tests:

```text
FAIL dolt schemas diff keys by type and name case-insensitively (lanes/dolt/tests/SchemaHistoryTableTest.php)
Expected: array ()
Actual: one modified VIEW/Object_View row

FAIL wordpress schema history fixture surfaces migration views triggers and events (lanes/dolt/tests/SchemaHistoryTableTest.php)
Expected diff types: added, added, modified, removed
Actual diff types: added, modified, added, removed

FAIL maps upstream mergeProof expansion between separately imported proofs (lanes/quadrable/tests/ProofMergeTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::nodeIdFromPartialNode()

FAIL maps upstream mergeProof witness leaf upgrade (lanes/quadrable/tests/ProofMergeTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::nodeIdFromPartialNode()

FAIL wordpress snapshot proofs can be merged for authenticated partial reads (lanes/quadrable/tests/ProofMergeTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::nodeIdFromPartialNode()

FAIL maps upstream sync proof fragments through bounded witness expansion (lanes/quadrable/tests/SyncTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::allocatePartialNodeId()

FAIL wordpress sync diffs reconstruct a changed authenticated snapshot (lanes/quadrable/tests/SyncTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::allocatePartialNodeId()

FAIL wordpress sync scan callback matches final authenticated diff (lanes/quadrable/tests/SyncTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::allocatePartialNodeId()

FAIL deterministic upstream shaped sync fuzz converges with scan diff equivalence (lanes/quadrable/tests/SyncTest.php)
Call to undefined method PortLibs\Quadrable\SparseTree::allocatePartialNodeId()
```

## Recommended Next Intervention

Stop integrating new lane breadth. Fix or reject the current Dolt schema-history and Quadrable proof/sync batches, rerun `php tools/run-tests.php` to green from a single state, then regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same state. After the root suite is green, prioritize markerPDF real benchmark PDF/reference parity and Gitoxide normalized upstream fixture/test accounting or a controlled crate-level runner probe.
