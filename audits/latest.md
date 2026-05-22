# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, selected lane status summaries, current dirty worktree state, bridge/shell-out usage, and recent Git history through `aaaa798` before this audit update. I did not edit lane implementation files, launch agents or tmux sessions, or push.

Audit boundary: the worktree is still a shared dirty integration target. This report anchors quality findings to the explicit root test run below and treats dashboard/status surfaces as stale unless they match the current manifests and accepted green state.

## Findings

1. **Critical - `porting.html` and `porting-summary.json` are stale across the whole portfolio.**
   - Paths: `porting.html:32`, `porting.html:53`, `porting.html:54`, `porting.html:55`, `porting.html:56`, `porting.html:57`, `porting.html:58`, `porting.html:59`, `porting.html:60`, `porting.html:61`, `porting.html:62`, `porting.html:63`, `porting.html:64`, `porting-summary.json:2`, `porting-summary.json:10`, `porting-summary.json:27`, `porting-summary.json:44`, `porting-summary.json:61`, `porting-summary.json:78`, `porting-summary.json:95`, `porting-summary.json:112`, `porting-summary.json:129`, `porting-summary.json:146`, `porting-summary.json:163`, `porting-summary.json:180`, `porting-summary.json:197`.
   - Evidence: the dashboard was generated at `2026-05-22 15:40:20 UTC`, but current manifests report materially different mapped counts: difftastic `51 vs 15`, Dolt `64 vs 5`, esbuild `56 vs 16`, Gitoxide `910 vs 737`, libsqlite `52 vs 18`, LightningCSS `166 vs 78`, markerPDF `70 vs 11`, Pandoc `112 vs 19`, Quadrable `55 vs 24`, rclone `88 vs 20`, Readability `286 vs 89`, and Syncthing `82 vs 27`. Dashboard denominators are also stale for LightningCSS (`382` current vs `312` shown), markerPDF (`78 tracked paths plus 2 actual CI benchmark pairs` current vs `27` shown), and Pandoc (`2028` current vs `1979` shown).
   - Goal requirement at risk: `goal.md` requires `porting.html` to show current suite progress, benchmark source, upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Audit judgment: do not publish or use the dashboard/summary as the source of truth until regenerated from one accepted green state.

2. **High - the worktree is green but still not a reviewable accepted state.**
   - Paths: `audits/integration-status.md`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/src/GitTag.php`, `lanes/libsqlite/src/SQLiteDatabase.php`, `lanes/lightningcss/src/TransitionPrefixer.php`, `lanes/pandoc/src/MarkdownReader.php`, `lanes/readability/src/ArticleExtractor.php`, `lanes/syncthing/src/ReceiveEncrypted.php`, `porting.html`, `porting-summary.json`, `progress.md:31`, `progress.md:42`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`, `lanes/libsqlite/lane-status.json:10`, `lanes/libsqlite/lane-status.json:12`, `lanes/readability/lane-status.json:10`, `lanes/readability/lane-status.json:12`.
   - Evidence: `git status --short --untracked-files=all` shows many modified and untracked lane implementation, test, manifest, note, dashboard, and audit files. The root suite now passes, but several lane statuses still cite older red root-suite blockers, and `progress.md` still lists all lanes as stopped with old phases/tasks.
   - Goal requirement at risk: `goal.md` requires small reviewable slices with passing tests, precise blockers, current owner/session, and progress/dashboard files reflecting the accepted state.
   - Audit judgment: freeze or explicitly coordinate writers, then accept or reject the dirty lane batches before regenerating and publishing status.

3. **High - Gitoxide progress remains over-broad relative to upstream runner proof.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/gitoxide/lane-status.json:4`, `lanes/gitoxide/lane-status.json:10`, `lanes/gitoxide/lane-status.json:12`.
   - Evidence: Gitoxide is priority 1, reports `2877` upstream files and `910` mapped checks, and lane status estimates `76%`, but upstream runner evidence is only a bounded `gix-object --lib` probe with 10 passing upstream tests. Full workspace Cargo tests and gix-object integration tests remain unexecuted, while major Git surfaces such as actual SSH authentication/channel integration, broader git-daemon runtime integration, OFS_DELTA pack writing, thin-pack repair, sparse-index writing, and more merge semantics remain unported.
   - Goal requirement at risk: `goal.md` scopes Gitoxide as a Git implementation with packfiles, refs, commits, object database, protocol v2, sparse/partial clone, push, merge, and server primitives, and requires upstream tests as source of truth where possible.
   - Audit judgment: keep Gitoxide moving, but do not treat the high percentage as upstream parity. The next supervisor intervention should force a controlled crate/integration-test runner slice or tighten the estimate/schema around bounded evidence.

4. **Medium - markerPDF now has real CI benchmark pairs, but dashboard/progress still point at the old intervention and extraction parity is still shallow.**
   - Paths: `progress.md:33`, `porting.html:59`, `porting-summary.json:108`, `porting-summary.json:120`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:16`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:88`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:93`, `lanes/markerpdf/lane-status.json:12`.
   - Evidence: the manifest records 2 actual `benchmark_data_short.zip` PDF/reference pairs with archive and pair hashes, but the dashboard still says `27` denominator / `11` mapped and recommends acquiring the first external pair. Current native work verifies scoring/report boundaries and excerpts, but full upstream conversion still depends on Poetry plus heavy model/PDF dependencies and the native lane has not yet proved document-level extraction parity on either acquired PDF/reference pair.
   - Goal requirement at risk: `goal.md` scopes markerPDF as a PDF-to-structured-content extraction pipeline and requires meaningful fixture parity, not just surrogate scoring or verifier tooling.
   - Audit judgment: use the two concrete CI pairs to drive native document extraction parity before adding more scoring breadth.

5. **Medium - upstream runner evidence is modeled inconsistently, so dashboard math can mix incompatible proof classes.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:48`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:17`, `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:18`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: `benchmarkDenominator.runnerStatus` is a structured object for some manifests, a free-form string for Gitoxide and Quadrable, absent/null for Pandoc, and `executed: true` for Dolt even though its own reason starts with "The full upstream runners were not executed." Some lanes have full upstream runner evidence, some bounded upstream evidence, and some static inventory only.
   - Goal requirement at risk: `goal.md` requires defensible upstream denominators and precise blockers when upstream runners cannot execute.
   - Audit judgment: normalize runner evidence into separate fields for full upstream pass parity, bounded upstream runner evidence, static inventory, native PHP behavior tests, assertions, and failures before percentages guide portfolio decisions.

## Bridge / Shell-Out Check

Command searched PHP sources under `lanes`, `tools`, and `scripts` for process execution calls and common process wrappers:

```text
rg -n 'shell_exec|exec\(|passthru|proc_open|system\(|popen\(|Symfony\\Component\\Process|new Process|Process\(' lanes tools scripts --glob '*.php'
```

Result: no lane implementation process-execution bridge calls found. The only match was `tools/generate-dashboard.php:183`, where coordination tooling reads Git metadata with `shell_exec`; that is not native port progress.

## Test Run

Command: `php tools/run-tests.php`

Exit status: 0

Exact result:

```text
102 test files, 7032 assertions, 0 failures
```

## Recommended Next Intervention

Freeze or explicitly coordinate writers, accept or reject the dirty lane batches, rerun `php tools/run-tests.php`, and regenerate `progress.md`, `porting.html`, `porting-summary.json`, and lane statuses from that same accepted green state. Then prioritize a controlled Gitoxide upstream runner slice and markerPDF native extraction parity against the two concrete CI benchmark pairs.
