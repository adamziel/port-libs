# Independent Audit - 2026-05-22

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, lane status files, current source/test surface, upstream cache state, `tmux list-sessions`, and recent Git history through `1f15a1d` (`Allow targeted tmux lane launches`). The worktree was clean at audit start. No lane implementation files were edited.

## Findings

1. **Critical - The portfolio baseline is still blocked by missing upstream denominators.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:13`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:13`, `porting.html:48-59`, `progress.md:11`
   - Evidence: 8 of 12 manifests still say `static-seed` with `total` set to `pending full upstream inventory`. The four stronger lanes are still not runner parity: Gitoxide is a tree inventory with `runnerStatus` not executed (`lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:13-20`), LightningCSS is a static grep-counted inventory with failed runner probes (`lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-66`), markerPDF has 6 benchmark names but 0 mapped benchmark documents (`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-37`), and Quadrable has a static `check.cpp` count with `make test` not executed (`lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13-20`).
   - Goal requirement at risk: "Build an `UPSTREAM_TEST_MANIFEST.json` that maps the real upstream benchmark denominator" and "Reach the required baseline for every lane."
   - Audit judgment: the dashboard is useful scaffolding, but it still cannot support parity claims. Denominator work should outrank additional native feature breadth.

2. **High - Dashboard pass/fail counts are seed-local and are displayed beside upstream counts without provenance.**
   - Paths: `porting.html:38`, `porting.html:53-54`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13-15`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13-16`, `progress.md:56`, `progress.md:65`
   - Evidence: the root harness passes `14 test files, 87 assertions, 0 failures`, but those are local PHP micro-tests. markerPDF shows upstream denominator `6`, mapped tests `0`, and dashboard PHP pass/fail `5 / 0`; LightningCSS shows denominator `241`, mapped `7`, and PHP pass/fail `6 / 0`. There is no per-test upstream fixture ID or result mapping in the manifests.
   - Goal requirement at risk: "`porting.html` must show ... upstream denominator, mapped tests, PHP pass/fail" and "Passing tests are not enough."
   - Audit judgment: the counts should be explicitly labeled seed-local until the PHP cases are tied to upstream fixtures or upstream test IDs.

3. **High - Current native slices remain much shallower than the requested ports.**
   - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:25`, `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:71`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:42`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json:22`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:22`, `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:22`
   - Evidence: the implemented work is currently loose Git object/commit parsing, CSS comment/value minification, PDF text-line extraction, SQLite header/varint parsing, a JS lexer, and token-level diffing. Those are valid bootstrap slices, but they do not yet exercise packfiles/refs/protocol, CSS parser/transformer/prefixer/bundler semantics, structured PDF layout/OCR/table behavior, SQLite b-trees/records/writing, JS/TS bundling, or structural syntax trees.
   - Goal requirement at risk: each named lane's requested native port scope and the quality bar requiring meaningful fixture parity, edge cases, and error behavior.
   - Audit judgment: do not expand more toy surfaces across lanes. Pick denominator-backed slices in priority order.

4. **Medium - Readability has an upstream clone available but still reports a seed manifest.**
   - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json:8-17`, `porting.html:58`, `progress.md:35`, `.upstream-cache/readability/test/test-pages/`
   - Evidence: `.upstream-cache/readability` is checked out at `08be6b4bdb204dd333c9b7a0cfbc0e730b257252`, matching the manifest commit, and contains 130 `test/test-pages/*` fixture directories with 130 `source.html`, 130 `expected.html`, and 130 `expected-metadata.json` files. The committed manifest and dashboard still say `static-seed` and `pending full upstream inventory`.
   - Goal requirement at risk: "Use upstream tests as the source of truth whenever possible" and "Replace seed manifests with full cloned/tested upstream benchmark denominators."
   - Audit judgment: this is a low-friction correction. Count and commit the Readability fixture denominator before doing more extractor work.

5. **Medium - Coordination status is stale against actual tmux state.**
   - Paths: `progress.md:31-42`, `progress.md:70-74`, `porting.html:41`, `porting.html:48-59`
   - Evidence: `progress.md` says `port-lightningcss`, `port-markerpdf`, `port-pandoc`, `port-quadrable`, `port-syncthing`, `port-difftastic`, `port-rclone`, and `port-esbuild` sessions are present, and says the active cap is Gitoxide/LightningCSS/markerPDF. `tmux list-sessions` only showed `port-auditor`, `port-libsqlite`, and `port-readability`. The dashboard audit column also still says `needs independent auditor review` for most lanes even after this audit run.
   - Goal requirement at risk: "`progress.md` must include ... current owner/session" and dashboard audit status.
   - Audit judgment: the supervisor needs to reconcile session/status fields before assigning more work; otherwise the coordination files stop being reliable.

6. **Medium - License verification is still incomplete in seven manifests.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/readability/UPSTREAM_TEST_MANIFEST.json:9`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json:9`
   - Evidence: these still say `license: pending verification from upstream checkout`.
   - Goal requirement at risk: "Identify the best upstream source repo, version/commit, license, architecture, and test suite."
   - Audit judgment: license verification should travel with denominator work; it is cheap and removes ambiguity before deeper porting.

7. **Low - WordPress scenarios are still unevenly executable.**
   - Paths: `lanes/*/notes/wordpress-scenarios.md`, `lanes/lightningcss/examples/wordpress-block-theme.php`, `lanes/markerpdf/examples/wordpress-import.php`, `lanes/*/tests/*Test.php`
   - Evidence: LightningCSS and markerPDF now have fixture/example files, and several tests contain WordPress-flavored strings or block output assertions. Most lanes still rely on notes or synthetic micro-tests rather than a focused executable scenario tied to the lane's upstream behavior.
   - Goal requirement at risk: "Add focused WordPress scenarios that explain why the port matters for Playground, Data Liberation, SQLite, Git-backed workflows, migration tools, block editing, local-first sync, document import, or shared-hosting constraints."
   - Audit judgment: explanatory notes are acceptable early, but the baseline is not met until each lane has at least one executable scenario with real behavior.

## Bridge / Shell-Out Check

Searched lane, tool, and script files for `shell_exec`, `exec(`, `passthru`, `proc_open`, and `system(`. No matches were found, so I did not find bridge code or shell-outs being counted as progress in the committed lane code.

## Test Run

Command: `php tools/run-tests.php`

Result:

```text
14 test files, 87 assertions, 0 failures
```

Exit status: 0.

## Recommended Next Intervention

Redirect current work toward upstream denominators before adding feature breadth: replace the Readability seed manifest with the 130-page fixture denominator already present in `.upstream-cache/readability`, count/verify the libsqlite upstream test inventory, and relabel dashboard PHP pass/fail values as seed-local until they are mapped to upstream fixture IDs.
