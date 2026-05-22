# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, representative tests/examples, upstream cache state, `tmux list-sessions`, bridge/shell-out search, and recent Git history through `7006b06` (`Mark difftastic and rclone sessions stopped`). The worktree was clean at audit start. No lane implementation files were edited.

## Findings

1. **Critical - The portfolio still has no lane with upstream runner parity, and the required baseline is not met.**
   - Paths: `goal.md:22-44`, `progress.md:11-15`, `progress.md:65-80`, `porting.html:48-59`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12-17`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-20`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:46-67`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:30-34`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:38-57`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:44-64`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:32-50`
   - Evidence: every manifest either records `runnerStatus.executed: false`, a string runner status of not executed, or a static seed. Esbuild and Dolt still have `total: pending full upstream inventory`; Dolt is deferred, but esbuild is not. The other ten lanes have useful inventories, yet they are static or cloned inventories, not upstream pass parity.
   - Goal requirement at risk: "Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator", "Use upstream tests as the source of truth whenever possible", and "Reach the required baseline for every lane."
   - Audit judgment: the dashboard percentages should remain low. No lane should be called baseline-complete until at least one upstream fixture/test ID is mapped to PHP behavior and runner parity or a clearly bounded runner blocker is recorded.

2. **High - Active coordination was stale at audit start, and current capacity is still on the lowest-priority non-deferred seed lane.**
   - Paths: `progress.md:81-94`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:3-17`, `porting.html:50`
   - Evidence: `tmux list-sessions` shows `port-auditor` and `port-esbuild`. At audit start, `progress.md` still said the auditor was stopped and there were no active workers; this audit updates `progress.md` to reflect the sessions. The unresolved issue is that the only active implementation session is priority 12 esbuild while priority 1-3 lanes remain below baseline and esbuild itself still has a static seed manifest.
   - Goal requirement at risk: "`progress.md` must include ... current owner/session" and the priority-ordered supervised lane workflow.
   - Audit judgment: if `port-esbuild` remains active, its only defensible task is denominator/license/cache reconciliation. It should not broaden lexer implementation while the manifest is still a seed.

3. **High - Esbuild has an upstream cache at the manifest commit, but the cache is dirty and the manifest still claims seed status.**
   - Paths: `.upstream-cache/esbuild`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:8-17`, `progress.md:67`, `progress.md:81`, `porting.html:50`
   - Evidence: `.upstream-cache/esbuild` is at `6a794dff68e6`, matching the manifest commit, but `git status --short` reports 349 tracked deletions. The manifest still says `license: pending verification from upstream checkout`, `status: static-seed`, and `total: pending full upstream inventory`.
   - Goal requirement at risk: "Identify the best upstream source repo, version/commit, license, architecture, and test suite" and "Keep all generated artifacts reproducible."
   - Audit judgment: restore/reclone the cache first, then replace the seed manifest with a counted upstream inventory before accepting more esbuild feature work.

4. **High - Dashboard PHP pass/fail values remain easy to misread as upstream parity.**
   - Paths: `porting.html:36-38`, `porting.html:50`, `porting.html:54-58`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`
   - Evidence: markerPDF shows `mapped` 0 and PHP `5 / 0`; esbuild shows a pending denominator but PHP `2 / 0`; Readability and Pandoc show thousands of inventoried upstream artifacts with only 7 and 5 local PHP tests. The table header says `PHP Pass / Fail`, but the rows do not make clear that these are local micro-tests, not upstream test pass counts.
   - Goal requirement at risk: "`porting.html` must show ... upstream denominator, mapped tests, PHP pass/fail" and "Passing tests are not enough."
   - Audit judgment: the dashboard should label these as local PHP tests until each count is tied to an upstream fixture/test name. Otherwise it overstates parity.

5. **High - Pandoc, Syncthing, and Esbuild upstream caches are not currently reproducible working trees.**
   - Paths: `.upstream-cache/pandoc`, `.upstream-cache/syncthing`, `.upstream-cache/esbuild`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13-17`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13-16`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:12-17`
   - Evidence: cache status counts are still dirty: `.upstream-cache/pandoc` has 2,781 tracked deletions, `.upstream-cache/syncthing` has 940 tracked deletions, and `.upstream-cache/esbuild` has 349 tracked deletions. Pandoc and Syncthing manifests cite those caches as inventory sources; esbuild has a cache but still no counted inventory.
   - Goal requirement at risk: "Keep all generated artifacts reproducible" and "If the upstream runner cannot execute, create a defensible static inventory and mark it clearly as such."
   - Audit judgment: static counts may be recoverable from Git object metadata, but workers should not rely on cache-local targeted reads or runner attempts until these worktrees are restored or recloned.

6. **Medium - markerPDF remains weak for a priority-3 lane because no benchmark PDF/reference pair is mapped.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`, `lanes/markerpdf/tests/PdfTextExtractorTest.php:7-38`, `porting.html:54`
   - Evidence: the manifest identifies six benchmark PDFs and two CI score thresholds but records `mapped: 0`. The PHP tests create tiny synthetic PDFs in memory or use a local WordPress fixture; none compare against upstream benchmark PDFs, score thresholds, or reference Markdown artifacts.
   - Goal requirement at risk: markerPDF's requested "PDF-to-structured-content extraction pipeline" and the quality bar requiring meaningful fixture parity.
   - Audit judgment: the next markerPDF slice should acquire one real benchmark/reference pair or document a narrow surrogate tied to upstream scoring before adding broader extraction behavior.

7. **Medium - Several native slices are useful bootstraps but still shallow compared with their requested port scopes.**
   - Paths: `lanes/difftastic/tests/TokenDifferTest.php:8-54`, `lanes/pandoc/tests/MarkdownReaderTest.php:8-54`, `lanes/readability/tests/ArticleExtractorTest.php:8-76`, `lanes/rclone/tests/MemoryProviderTest.php:11-110`, `lanes/esbuild/tests/JsLexerTest.php:8-18`
   - Evidence: Difftastic is still token/list normalization, not recursive syntax-tree diffing; Pandoc has hand-written Markdown snippets, not golden `.native` comparisons; Readability does not yet map a Mozilla `test-pages` source/expected/metadata fixture; Rclone tests filters and an in-memory provider but not upstream provider contract execution; Esbuild has two lexer tests while its manifest is a seed.
   - Goal requirement at risk: each lane's named native port scope plus "meaningful fixture parity, edge-case coverage, error behavior, docs/examples, and WordPress-oriented scenarios."
   - Audit judgment: future implementation commits should be accepted only when they map to a manifest item or explicitly close a named blocker.

## Bridge / Shell-Out Check

Searched `lanes`, `tools`, `scripts`, and `.tmux-team` for `shell_exec`, `exec(`, `passthru`, `proc_open`, and `system(`. No matches were found, so I did not find committed bridge code or shell-outs being counted as native progress.

## Test Run

Command: `php tools/run-tests.php`

Exact result:

```text
14 test files, 206 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

Restore or reclone `.upstream-cache/esbuild`, replace the esbuild seed manifest with a counted upstream inventory and verified license, and make any active `port-esbuild` work do only that reconciliation. Then restore/reclone `.upstream-cache/pandoc` and `.upstream-cache/syncthing` before accepting cache-local evidence from those lanes. Keep dashboard PHP pass/fail values labeled as local until tied to upstream fixture IDs.
