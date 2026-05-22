# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, upstream cache state, bridge/shell-out search, and recent Git history through `124acf9` (`Update markerPDF lane status`). The worktree was clean at audit start. No lane implementation files were edited. During final review, markerPDF benchmark-scoring commits landed, and separate uncommitted Gitoxide loose-reference lane changes appeared in the worktree; they were not created, edited, or staged by this audit. The final test result below includes the uncommitted Gitoxide files, so it is a working-tree result, not a clean-HEAD result.

## Findings

1. **Critical - The portfolio is still local smoke coverage plus static inventory, not upstream-quality completion.**
   - Paths: `goal.md:24-25`, `goal.md:35-38`, `progress.md:11`, `progress.md:70-82`, `porting.html:48-59`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: all 12 lane manifests still record `static-upstream-inventory`, `cloned-static-inventory`, or targeted static inventory status, and every upstream runner is marked not executed or blocked. The dashboard now correctly labels `Local PHP Pass / Fail`, but the local test files are not upstream pass parity.
   - Goal requirement at risk: real upstream benchmark denominators, upstream tests as the source of truth, meaningful fixture parity, edge-case coverage, and the warning that passing tests are not enough.
   - Audit judgment: keep the baseline milestone as "all lanes have visible native slices," but do not use it as a quality or parity milestone.

2. **High - Uncommitted Gitoxide work changed the test surface without integrated status updates.**
   - Paths: `goal.md:29`, `goal.md:44-45`, `progress.md:66`, `porting.html:51`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/lane-status.json`, `lanes/gitoxide/tests/LooseReferenceTest.php`.
   - Evidence: after the first clean-tree test run, new untracked Gitoxide loose-reference files appeared and Gitoxide manifest/status/dashboard/progress files changed. The final root harness passes 18 files and 298 assertions, while the last committed Gitoxide dashboard/status still show 15 local passes and commit `c2ee31a`.
   - Goal requirement at risk: small reviewable commits, accurate `progress.md` and `porting.html` status, and not counting unintegrated work as progress.
   - Audit judgment: the supervisor should review the Gitoxide lane changes separately, then either integrate/stamp/regenerate them or discard them; this audit commit should not include them.

3. **High - markerPDF maps zero benchmark PDF/reference pairs despite being priority 3.**
   - Paths: `goal.md:9`, `goal.md:35-37`, `progress.md:33`, `progress.md:73`, `porting.html:54`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:49-59`, `lanes/markerpdf/tests/PdfTextExtractorTest.php:7-38`, `lanes/markerpdf/tests/MarkdownPostProcessorTest.php:8-42`, `lanes/markerpdf/tests/BenchmarkScorerTest.php`.
   - Evidence: the manifest now maps 5 focused source/scoring semantics after `834a4dd`, but it still records `mappedBenchmarkPairs: 0`; the tests build tiny inline PDFs, local WordPress fixtures, or scoring examples, not a real upstream benchmark document and reference Markdown pair.
   - Goal requirement at risk: PDF-to-structured-content extraction suitable for WordPress import/Data Liberation with meaningful fixture parity.
   - Audit judgment: the next markerPDF slice should map one real upstream benchmark/reference pair, or a clearly documented upstream-derived surrogate if the Google Drive benchmark archive remains unavailable.

4. **High - Gitoxide is priority 1, and the new loose-ref slice is still unintegrated and narrow.**
   - Paths: `goal.md:7`, `progress.md:31`, `progress.md:71`, `porting.html:51`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:25-28`, `lanes/gitoxide/tests/GitObjectTest.php:8-25`, `lanes/gitoxide/tests/CommitTest.php:8-36`, `lanes/gitoxide/tests/TreeTest.php:18-78`, `lanes/gitoxide/tests/LooseReferenceTest.php`.
   - Evidence: committed status still describes loose object storage, commit parsing, and tree entry parsing/serialization. The uncommitted loose-reference slice appears to cover direct/symbolic loose refs, but it is not committed, not reflected in Gitoxide lane status, and still leaves packed refs, packfiles, object database behavior, protocol v2, sparse/partial clone, push, merge, and server primitives unmapped.
   - Goal requirement at risk: priority-ordered work on the Git implementation and preserving upstream semantics where compatibility matters.
   - Audit judgment: if the uncommitted slice is accepted, stamp it as a small Gitoxide commit and immediately follow with packed-ref fixture parsing rather than broad new inventory counting.

5. **Medium - Several lanes have exact upstream fixture/golden suites available but still use local surrogate tests.**
   - Paths: `goal.md:35-38`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:57-66`, `lanes/readability/tests/ArticleExtractorTest.php:8-76`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:39-42`, `lanes/pandoc/tests/MarkdownReaderTest.php:8-55`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:60`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:73`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:81`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:79`.
   - Evidence: Readability has 130 source/expected/metadata fixture pages counted but no mapped Mozilla fixture; Pandoc counts 252 `.native` expected artifacts but no golden case mapped; Difftastic, Rclone, Dolt, and esbuild next tasks still explicitly call for first upstream sample/provider/parser/schema mappings.
   - Goal requirement at risk: upstream tests as source of truth, meaningful fixture parity, and explicit slices when upstream suites are huge.
   - Audit judgment: future implementation commits should name the upstream fixture/test they close or document why a temporary local surrogate is being used.

6. **Medium - Four upstream caches remain inventory evidence, not runner-ready worktrees.**
   - Paths: `.upstream-cache/dolt`, `.upstream-cache/esbuild`, `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `progress.md:88`, `progress.md:93`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:46-72`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:47-70`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:32-50`.
   - Evidence: `git status --short | wc -l` reports 2,387 entries in `.upstream-cache/dolt`, 349 in `.upstream-cache/esbuild`, 2,781 in `.upstream-cache/pandoc`, and 940 in `.upstream-cache/syncthing`. `git ls-tree` and targeted `git show` reads are valid for static inventory, but broad scans or runner attempts would be unsafe until restored or recloned.
   - Goal requirement at risk: reproducible generated artifacts and precise blocker recording.
   - Audit judgment: restore or reclone these caches before the supervisor asks any worker to run upstream tests or inspect working-tree files broadly.

7. **Medium - The periodic auditor/team workflow is not currently active.**
   - Paths: `goal.md:20`, `progress.md:14`, `progress.md:95-99`.
   - Evidence: progress records the auditor and every worker session as stopped, while the goal calls for an independent auditor challenging quality every 20 minutes and active implementation lanes under a practical cap.
   - Goal requirement at risk: durable coordination and continuous quality challenge.
   - Audit judgment: this manual audit updates the latest status, but the supervisor should restart the capped auditor/worker cadence when implementation resumes.

8. **Low - `progress.md` carried stale resolved audit status after `30e4e0f`.**
   - Paths: `progress.md:90`, `porting.html:38`, `porting.html:53`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:15`, `lanes/lightningcss/lane-status.json`.
   - Evidence: commit `30e4e0f` fixed the dashboard label to `Local PHP Pass / Fail` and reconciled LightningCSS mapped/pass counts to 6, but `progress.md` still listed the old mismatch as an open audit hygiene issue at audit start.
   - Goal requirement at risk: accurate `progress.md` blocker and audit-status tracking.
   - Audit judgment: updated `progress.md` in this run to replace the stale blocker with current audit status.

## Bridge / Shell-Out Check

Searched committed lane/tool/script files for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(`. No matches were found in `lanes`, `tools`, or `scripts`, so I did not find committed bridge code or shell-outs being counted as native implementation progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
18 test files, 298 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

First reconcile the uncommitted Gitoxide lane work without letting it count as integrated progress by default. If accepted, stamp/regenerate status from that work; then prioritize upstream-anchored parity: packed/direct Git refs, one markerPDF benchmark/reference pair or documented upstream-derived surrogate, and named Readability/Pandoc upstream fixtures or golden outputs.
