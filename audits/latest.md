# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files where needed to validate status drift, current dirty worktree state, bridge/shell-out usage, and recent Git history through `5909241` (`Stamp pandoc nested table status`). I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the root PHP harness is green, but the repository is still not an accepted integration checkpoint. During this audit, `HEAD` advanced from `7e86f32` to `5909241` and the dirty set changed repeatedly, so the green run is evidence against a moving worktree, not a stable integrated state.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale against the current manifests and lane statuses.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:11`, `porting-summary.json:28`, `porting-summary.json:45`, `porting-summary.json:62`, `porting-summary.json:79`, `porting-summary.json:96`, `porting-summary.json:113`.
   - Evidence: both generated files still say `Generated: 2026-05-22 15:40:20 UTC`. Dashboard mapped counts are Difftastic `15`, Dolt `5`, esbuild `16`, Gitoxide `737`, libsqlite `18`, LightningCSS `78`, markerPDF `11`, Pandoc `19`, Quadrable `24`, rclone `20`, Readability `89`, and Syncthing `27`. Current manifests report Difftastic `70`, Dolt `90`, esbuild `81`, Gitoxide `1052`, libsqlite `69`, LightningCSS `259`, markerPDF `78`, Pandoc `138`, Quadrable `55`, rclone `115`, Readability `492`, and Syncthing `102`. Quadrable is especially misleading: the dashboard still says the C++ runner fails, while `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` and `:18` say `make -r test` passes.
   - Goal requirement at risk: `goal.md` requires a generated dashboard showing current upstream denominator, mapped tests, PHP pass/fail, phase, audit, current work, blocker, and commit for each lane.
   - Audit judgment: do not publish or use the dashboard for portfolio decisions until the supervisor accepts/rejects dirty batches and regenerates the dashboard from that same green state.

2. **Critical - the worktree is moving and too broad for a reviewable integration checkpoint.**
   - Paths: `lanes/dolt/src/CommitLogTable.php`, `lanes/gitoxide/src/LooseReferenceStore.php`, `lanes/gitoxide/src/PackedReferences.php`, `lanes/rclone/src/SyncPlan.php`, `lanes/readability/src/ArticleExtractor.php`, `porting.html`, `porting-summary.json`, `audits/integration-status.md`.
   - Evidence: the worktree changed repeatedly during this audit; I observed at least 60 porcelain status entries, including 23 tracked modified files plus many untracked audit, fixture, example, and implementation files, and it continued changing after the audit commit. The tracked diff sampled during the audit spanned Dolt, Gitoxide, markerPDF, rclone, Readability, generated dashboard files, and audit/status material with about 3,702 insertions and 167 deletions.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, cleanup of unrelated changes, and progress generated from the accepted state.
   - Audit judgment: freeze or explicitly coordinate active writers before integrating. Accept or reject one lane batch at a time, with `php tools/run-tests.php` after each accepted batch.

3. **High - `progress.md` still presents stale lane estimates, next tasks, and session state.**
   - Paths: `progress.md:31`, `progress.md:32`, `progress.md:33`, `progress.md:34`, `progress.md:35`, `progress.md:36`, `progress.md:37`, `progress.md:38`, `progress.md:39`, `progress.md:40`, `progress.md:41`, `progress.md:42`, `progress.md:194`, `progress.md:199`, `progress.md:230`.
   - Evidence: the Active Lanes table still lists older estimates such as Gitoxide `66%`, LightningCSS `14%`, libsqlite `12%`, Quadrable `8%`, Dolt `5%`, and esbuild `8%`, while committed lane statuses now report Gitoxide `80%`, LightningCSS `39%`, libsqlite `38%`, Quadrable `40%`, Dolt `24%`, and esbuild `25%`. Several next tasks are also stale relative to current manifests, and the session status still describes previously observed active sessions while the current audit did not inspect tmux.
   - Goal requirement at risk: `goal.md` requires `progress.md` to include current lanes, blockers, next task per lane, percentage estimates, current owner/session, and audit status.
   - Audit judgment: I updated only the latest audit/test/intervention text. The lane table needs a supervisor refresh after accepting or rejecting the current dirty batches.

4. **High - lane status files carry conflicting root-suite evidence, including stale red evidence.**
   - Paths: `lanes/difftastic/lane-status.json:10`, `lanes/dolt/lane-status.json:10`, `lanes/esbuild/lane-status.json:10`, `lanes/gitoxide/lane-status.json:10`, `lanes/libsqlite/lane-status.json:10`, `lanes/lightningcss/lane-status.json:10`, `lanes/markerpdf/lane-status.json:12`, `lanes/pandoc/lane-status.json:12`, `lanes/quadrable/lane-status.json:12`, `lanes/rclone/lane-status.json:10`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`, `lanes/syncthing/lane-status.json:10`.
   - Evidence: the current root run is `119 test files, 9127 assertions, 0 failures`. Lane statuses still cite many other root results: Difftastic/Pandoc `118/9081`, esbuild/libsqlite/LightningCSS/markerPDF/Syncthing `118/9065`, rclone `116/8955`, Gitoxide `117/9034`, Quadrable `117/9047`, Dolt `116/8955` and `116/8974`, while Readability still says the root suite fails with 2 unrelated failures.
   - Goal requirement at risk: `goal.md` requires precise blockers, current audit/status evidence, and honest repo-wide test recording.
   - Audit judgment: stale root-suite strings should not drive lane acceptance. Normalize lane status evidence after the dirty batches are integrated or rejected.

5. **High - upstream evidence and native PHP counts are still modeled inconsistently.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:146`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:169`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:142`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:485`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:119`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:385`.
   - Evidence: `runnerStatus` is a structured object in some manifests, but a string in Gitoxide, markerPDF, and Quadrable. Several `total` fields are narrative strings rather than machine-checkable denominators. `nativeImplementation.phpBehaviorTests` exists for only some lanes; other manifests omit native pass/fail counts and rely on lane-status prose. Dashboard PHP values also mix behavior tests, mapped semantics, and assertion-like counts; for example stale Gitoxide renders `1257 pass / 0 fail`, while current Gitoxide lane status says `1884 assertions`.
   - Goal requirement at risk: `goal.md` requires real upstream benchmark denominators, mapped upstream tests, PHP passing/failing counts, meaningful parity evidence, and clear dashboard columns.
   - Audit judgment: split full upstream pass parity, bounded runner evidence, static inventory, native behavior-test count, assertion count, and native failures into separate schema fields before using percentages for decisions.

6. **Medium - `mapped == total` overstates native parity for inventory-shaped denominators.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:146`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:15`.
   - Evidence: markerPDF reports `78 / 78` mapped, but its denominator is repository/source-path inventory plus two benchmark PDF/reference pairs, and its full benchmark runner is still `not-executed`. Quadrable reports `55 / 55` with a passing C++ runner, but the lane still records remaining native evidence gaps such as the full persisted tracked-node fuzzer outside the fast suite.
   - Goal requirement at risk: `goal.md` prohibits counting bridge/generated/shallow evidence as implementation progress and requires hard features to be marked as blockers or future slices.
   - Audit judgment: these are useful evidence sets, but `mapped == total` must not imply full native parity unless the denominator is behavior-level and remaining semantic gaps are represented separately.

7. **Medium - several lanes still lack full upstream runner parity despite useful bounded evidence.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:76`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:146`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:141`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:142`.
   - Evidence: Difftastic, markerPDF, Pandoc, and Syncthing still have no full upstream runner pass. Gitoxide has bounded crate/subset evidence, not full workspace Cargo parity. Dolt and rclone have valuable bounded runner evidence, not full Go/BATS/provider/mount parity.
   - Goal requirement at risk: `goal.md` says upstream tests are the source of truth where possible and hard features must be marked as blockers or future slices.
   - Audit judgment: continue implementation only with these labels preserved; do not fold bounded/static evidence into full upstream parity.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match is `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

```text
Exit status: 0
119 test files, 9127 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate active writers, then accept or reject dirty batches one lane at a time with `php tools/run-tests.php` after each accepted batch. Regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from the same accepted green state, then normalize the evidence schema before using dashboard percentages for priority decisions.
