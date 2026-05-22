# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, upstream cache state, bridge/shell-out search, and recent Git history through `f409148` (`Mark portfolio baseline reached`). The worktree was clean at audit start. No lane implementation files were edited. During final verification, untracked markerPDF lane files appeared in the worktree; they were not edited or committed by this audit, and the final test run below includes their untracked test file.

## Findings

1. **Critical - The portfolio baseline marker is a coordination milestone, not upstream-quality completion.**
   - Paths: `goal.md:22-40`, `goal.md:52`, `progress.md:11-15`, `progress.md:67-90`, `porting.html:48-59`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Evidence: all 12 manifests still report static or cloned-static inventories, and no full upstream runner has executed. The latest commit `f409148` marks the required baseline reached, but `progress.md:11` still correctly leaves "Replace seed manifests with full cloned/tested upstream benchmark denominators" open. Root PHP success is local smoke coverage only.
   - Goal requirement at risk: real upstream benchmark denominators, upstream tests as source of truth, meaningful fixture parity, edge-case coverage, and not treating passing tests as enough.
   - Audit judgment: keep `f409148` as "all lanes have visible native slices," but do not treat it as upstream parity or quality completion.

2. **High - markerPDF remains the weakest high-priority lane because it maps zero upstream benchmark pairs.**
   - Paths: `goal.md:9`, `goal.md:24-27`, `goal.md:35`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/markerpdf/tests/PdfTextExtractorTest.php`, `porting.html:54`.
   - Evidence: the manifest identifies 6 upstream benchmark documents and 2 CI score thresholds but records `mapped: 0`; the dashboard likewise says 0 benchmark documents mapped. Current PHP tests cover small synthetic/local PDFs, not a real upstream PDF/reference Markdown pair or score threshold.
   - Goal requirement at risk: PDF-to-structured-content extraction suitable for WordPress import/Data Liberation with meaningful fixture parity.
   - Audit judgment: the next markerPDF intervention should map one real upstream benchmark/reference pair or a documented upstream-derived surrogate before broadening extraction behavior.

3. **High - The dashboard and lane status can still mislead reviewers about what passed.**
   - Paths: `goal.md:44-45`, `porting.html:36-38`, `porting.html:48-59`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/lightningcss/lane-status.json`, `tools/generate-dashboard.php:25-29`.
   - Evidence: `porting.html` labels the column `PHP Pass / Fail` without saying these are local lane tests, while all upstream runners are unexecuted. LightningCSS has `mapped: 7` in the manifest but the dashboard/lane status report `6 / 0` local PHP passes. Every lane's dashboard audit cell still says it needs auditor review after the audit run.
   - Goal requirement at risk: `porting.html` must honestly show mapped tests, PHP pass/fail, audit status, and suite progress without implying that passing local tests are upstream parity.
   - Audit judgment: label dashboard pass/fail as local PHP, reconcile LightningCSS mapped/pass counts, and stamp the audit status from this run.

4. **Medium - Gitoxide is priority 1 but still has only a tree inventory and commit/object smoke slice.**
   - Paths: `goal.md:7`, `goal.md:24-25`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`, `lanes/gitoxide/lane-status.json`, `porting.html:51`.
   - Evidence: the manifest counts repository files, Rust test/bench sources, and fixtures, but warns this is not a full upstream test denominator. The PHP slice covers loose objects and canonical commit parsing; refs, tree parsing, packfiles, object database semantics, protocol v2, merge, push, sparse/partial clone, and server primitives remain unmapped.
   - Goal requirement at risk: priority-ordered work and a real upstream denominator for the Git implementation lane.
   - Audit judgment: target a controlled object/ref crate denominator next, not more broad inventory counting.

5. **Medium - Recent slices are useful but mostly not tied to upstream fixture IDs or golden outputs.**
   - Paths: `goal.md:33-40`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13-60`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13-81`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13-79`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-42`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-66`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13-73`.
   - Evidence: Difftastic is still token/comment normalization rather than recursive syntax-tree diffing; Dolt maps 5 local tests against 613 executable upstream test files and 3,808 BATS cases; esbuild is lexer-only against 2,567 counted upstream entries; Pandoc has no mapped golden `.native` cases; Readability has not mapped a Mozilla `test-pages` source/expected/metadata fixture; Rclone has filters and an in-memory provider but not upstream provider contract/check/copy parity.
   - Goal requirement at risk: meaningful fixture parity, edge-case coverage, error behavior, and small correct slices anchored to upstream behavior.
   - Audit judgment: future implementation commits should name the upstream fixture/test they close or explicitly record why a temporary local surrogate is being used.

6. **Medium - Four upstream caches remain object-store/no-checkout evidence, not runner-ready worktrees.**
   - Paths: `.upstream-cache/dolt`, `.upstream-cache/esbuild`, `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `progress.md:84-90`.
   - Evidence: `git status --short | wc -l` reports 2,387 deletions in `.upstream-cache/dolt`, 349 in `.upstream-cache/esbuild`, 2,781 in `.upstream-cache/pandoc`, and 940 in `.upstream-cache/syncthing`.
   - Goal requirement at risk: reproducible generated artifacts and precise blocker recording.
   - Audit judgment: `git ls-tree` and targeted `git show` counts can support static inventory claims, but these caches must be restored/recloned before any runner attempt or broad working-tree scan.

## Bridge / Shell-Out Check

Searched committed lane/tool/script files for `shell_exec`, `exec(`, `passthru`, `proc_open`, `system(`, and `popen(`. No matches were found in `lanes`, `tools`, or `scripts`, so I did not find committed bridge code or shell-outs being counted as native implementation progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
15 test files, 241 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

First fix coordination truthfulness: label dashboard pass/fail as local PHP, reconcile the LightningCSS mapped/pass mismatch, and stamp audit status from this run. Then spend implementation capacity on the highest-priority gaps: Gitoxide object/ref denominator work and one real markerPDF benchmark/reference mapping.
