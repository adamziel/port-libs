# Native PHP Ports Progress

## Roadmap

- [x] Inspect local workspace and tooling.
- [x] Create lane directories for all priority ports.
- [x] Add root PHP test harness and dashboard generator.
- [x] Create defensible seed upstream benchmark manifests for every lane.
- [x] Publish progress through a generated `porting.html` dashboard.
- [x] Create GitHub repository and enable GitHub Pages for the dashboard.
- [ ] Replace seed manifests with full cloned/tested upstream benchmark denominators.
- [ ] Keep each lane moving through native PHP implementation slices with mapped upstream behavior.
- [x] Add capped tmux worker/auditor launch scripts.
- [ ] Run an independent auditor loop that challenges shallow progress and updates lane blockers.
- [x] Reach the required baseline for every lane: upstream manifest, native PHP slice, passing PHP tests, WordPress scenarios, and visible `porting.html` status.

## Environment

- Workspace: `/home/claude/port-libs`
- PHP: 8.2.29 CLI
- Composer: 2.8.12
- tmux: 3.5a
- CPU: 6 logical cores
- Memory at bootstrap: about 6.2 GiB available
- Current launch target: 2 implementation lanes plus 1 auditor under the current VM load.

## Active Lanes

| Priority | Lane | Session | Phase | Estimate | Next Task |
| --- | --- | --- | --- | ---: | --- |
| 1 | gitoxide | stopped | targeted object/ref inventory plus tree object slice | 7% | Add loose ref direct/symbolic parsing and storage from gix-ref, then map packed-ref fixture parsing. |
| 2 | lightningcss | stopped | static upstream inventory + native value minifier slice | 4% | Port a small selector/tokenizer parser slice so PHP can distinguish selectors, declarations, at-rules, and nested rules before adding more transformer semantics. |
| 3 | markerPDF | stopped | cloned inventory + native markdown postprocess slice | 6% | Acquire or sample one upstream benchmark PDF/reference pair and map it to a native PHP parity fixture. |
| 4 | libsqlite | stopped | upstream inventory plus first b-tree page-header slice | 7% | Parse table leaf cells on the schema root page and decode `sqlite_schema` records needed to locate WordPress tables such as `wp_options`. |
| 5 | readability | stopped | cloned static inventory + readerable preflight slice | 5% | Map the first Mozilla test-page source/expected/metadata fixture into PHP parity, then improve metadata/byline/media handling. |
| 6 | pandoc | stopped | cloned inventory plus inline/list slice | 5% | Map a small subset of Pandoc `Tests.Readers.Markdown` golden cases into PHP fixtures, then add nested list item blocks. |
| 7 | quadrable | stopped | upstream inventory plus key primitive slice | 4% | Port the in-memory sparse tree update/get model for basic put/get, empty heads, batch insert, and deletion scenarios. |
| 8 | syncthing | stopped | cloned static upstream inventory + scanner block parity slice | 4% | Port protocol vector update/merge/compare semantics and add concurrent WordPress edit conflict fixtures. |
| 9 | difftastic | stopped | cloned inventory plus comment/delimiter slice | 5% | Port a small recursive syntax-list diff for bracketed PHP/JS/CSS structures, then map one upstream `sample_files` pair into a fixture parity test. |
| 10 | rclone | stopped | cloned static inventory plus native filter slice | 4% | Map filesystem provider contract tests, hash set behavior, and rclone check/copy semantics. |
| 11 | dolt | stopped | cloned static upstream inventory + Dolt diff table row slice | 5% | Map schema/tag-aware row conversion and begin porting Dolt table delta matching for renamed tables and primary key set changes. |
| 12 | esbuild | stopped | cloned static upstream inventory + lexer numeric/hashbang slice | 4% | Map upstream parser/printer tests for import/export syntax and add enough AST structure to distinguish WordPress package imports from relative asset imports. |

## Completed Milestones

- Created the initial lane layout required by the goal.
- Added a zero-dependency PHP test runner so native slices can be verified without waiting on PHPUnit.
- Added generated `porting.html` dashboard support backed by lane manifests/status files.
- Added seed native PHP slices and WordPress scenarios for all 12 lanes.
- Verified the seed suite: `php tools/run-tests.php` passes 13 test files, 75 assertions, 0 failures.
- Added `scripts/start-tmux-team.sh`, `scripts/check-tmux-team.sh`, and durable worker/auditor prompts under `.tmux-team/`.
- Replaced Quadrable's seed denominator with a static upstream `check.cpp` inventory: 34 top-level scenarios, 29 `equivHeads` subcases, 136 `verify` checks, and 20 `verifyThrow` checks.
- Gitoxide: replaced the seed denominator with a safe upstream tree inventory at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: 93 Cargo manifests, 472 Rust test/bench source files, and 605 fixture files counted; added native commit parsing tests.
- Gitoxide: strengthened the denominator with targeted `gix-object`/`gix-ref` static inventory at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: 205 object/ref crate paths, 114 test/fixture paths, 37 Rust integration test files, 77 fixture paths, 296 targeted `#[test]` attributes, and 25 tree behavior tests counted. Added native Git tree entry parsing/serialization plus a WordPress deploy-tree fixture/example. Lane PHP: 15 passing tests, 0 failing tests. Implementation commit: `c2ee31a`.
- markerPDF: replaced the seed denominator with a shallow cloned upstream inventory at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`: 18 inspected benchmark/test artifacts counted (workflow, benchmark runner, scoring/verifier scripts, 6 benchmark documents, and 8 committed markdown examples); added native text-line extraction plus Marker markdown post-processing for hyphen dewrapping, sentence paragraph breaks, heading/list/text wrapping, and WordPress block import examples. Lane PHP: 9 passing tests, 0 failing tests. Implementation commit: `ca0d8e0`.
- LightningCSS: replaced the seed denominator with a shallow sparse upstream inventory at `22bdda3d190f1cd321d98026225cfc964af64ad9`: 241 behavior checks counted (160 Rust `#[test]`, 81 Node `uvu` tests) plus 8 CSS fixtures; added value-level color minification, calc operator spacing, and a WordPress block-theme fixture/example.
- libsqlite: replaced the seed denominator with a shallow blobless upstream inventory at Git mirror `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7` / official manifest UUID `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`: 1,454 upstream test-related files/scripts counted plus 58 declared permutation suites; added native b-tree page header parsing and a WordPress SQLite root-page inspection example.
- Readability: replaced the seed denominator with a shallow sparse Mozilla Readability inventory at `08be6b4bdb204dd333c9b7a0cfbc0e730b257252`: 1,984 Mocha tests counted over 130 fixture pages; added native `isProbablyReaderable` thresholds, unlikely-candidate cleanup, semantic article scoring, and a WordPress page-builder migration fixture/example.
- Syncthing: replaced the seed denominator with a shallow blob-filtered upstream inventory at `3962a237232473c20a44945a6c8ce8c930375360`: 211 unique test-related paths counted, including 141 Go test files; added scanner-style block hashing, empty-file blocks, block-list hashes, upstream block size selection, and a WordPress media-resume example. Lane PHP: 6 passing tests, 0 failing tests.
- Pandoc: replaced the seed denominator with a blob-filtered shallow upstream inventory at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: 1,979 upstream test files/artifacts counted, including 1,974 under `test/`, 62 Haskell test modules, 1,064 command markdown fixtures, 252 `.native` expected artifacts, and 5 Lua engine test modules.
- Pandoc: added native Markdown inline emphasis/strong/link/code parsing, grouped bullet/ordered list AST nodes, escaped WordPress block output, and a WordPress Markdown import fixture/example. Lane PHP: 5 passing tests, 0 failing tests.
- Rclone: replaced the seed denominator with a shallow blob-filtered upstream inventory at `28d6b0b7b906da70afdc036ba5bb21f3c86613b8`: 327 Go test files counted, including 124 backend, 91 fs, 47 cmd, 43 lib, 19 vfs, 2 cmdtest, and 1 fstest test file, plus 836 testdata paths. Added native rclone-style path glob filters, first-match include/exclude rules, ignore-case matching, filtered sync planning, and a WordPress backup fixture/example. Lane PHP: 7 passing tests, 0 failing tests. Implementation commit: `8564ff9`.
- Difftastic: replaced the seed denominator with a shallow sparse blob-filtered upstream inventory at `7ccfcb315f7e46fd015809416c7d7dffa5be7078`: 287 inspected behavior artifacts counted, including 144 Rust `#[test]` functions, 112 paired sample fixture bases, 30 vendored parser corpus files, and `sample_files/compare.expected`. Added native comment classification, delimiter anchors, `ignoreComments`, trailing-comma normalization, and a WordPress render-callback fixture/example. Lane PHP: 6 passing tests, 0 failing tests.
- esbuild: replaced the seed denominator with a shallow blob-filtered upstream inventory at `6a794dff68e6a43539f6da671e3080efdf11ca70`: 2,567 counted upstream test entry points, including 1,391 Go `func Test*` functions, 331 JS API cases, 97 plugin cases, and 748 end-to-end CLI cases. Added native hashbang tokens, unterminated block comment errors, base-prefixed/decimal numeric literal values, and a WordPress block view asset fixture/example. Lane PHP: 6 passing tests, 0 failing tests. Implementation commit: `e8f8ce0b9272`.
- Dolt: replaced the seed denominator with a shallow blob-filtered upstream inventory at `b2274926e0dcd84aab000ee242df5b5e75689eef`: 613 executable upstream test files counted, including 399 Go `_test.go` files and 214 BATS files, plus 3,808 BATS `@test` cases and 256 fixture/data artifact paths. Added native Dolt-style `DOLT_DIFF_*` row projection, structural composite primary-key indexing, and a WordPress `wp_posts` migration fixture/example. Lane PHP: 5 passing tests, 0 failing tests. Implementation commit: `e47acb6152ce`.
- Latest root suite: `php tools/run-tests.php` passes 16 test files, 268 assertions, 0 failures.

## Open Blockers

- All lanes now have at least a stronger cloned or static upstream inventory replacing the original seed denominators. None of the large upstream runners should be treated as upstream pass parity until each lane runs or maps focused upstream fixtures.
- Gitoxide now has a targeted object/ref static inventory and native tree parsing, but no upstream Cargo runner parity. Full Cargo was not executed because `.upstream-cache/gitoxide` is sparse/no-checkout and a runner attempt would require materializing crate source plus building Rust dependencies; full workspace `cargo test` would hydrate/build beyond the current VM cap.
- Quadrable now has a counted static upstream denominator, but the C++ runner has not executed and most tree/proof/sync behavior remains unported.
- markerPDF now has a cloned static upstream inventory and 4 focused upstream source semantics mapped, but its full benchmark runner was not executed because the upstream workflow downloads external Google Drive benchmark data and installs heavy Poetry/ML/PDF dependencies; no benchmark PDF/reference pair is mapped yet.
- LightningCSS full upstream runners were not executed: `npm test` fails before tests because `node_modules`/`uvu` is absent, and an offline Cargo no-run probe cannot resolve `napi-derive` for the Node workspace member.
- libsqlite full upstream runner was not executed: SQLite testing requires configuring/building `testfixture`/`sqlite3` with Tcl development libraries and then running `testrunner.tcl` suites/permutations. The current denominator is a cloned static inventory of 1,454 upstream test-related files/scripts, not upstream pass parity.
- Readability full upstream runner was not executed: `npm test` reaches `mocha test/test-*.js` but fails before tests because the sparse upstream cache has no `node_modules` and `mocha` is not installed. The current denominator is a cloned static inventory of 1,984 Mocha tests, not upstream pass parity.
- Syncthing full upstream runner was not executed: `go test ./...` would require hydrating the full blob-filtered checkout, downloading/building Go dependencies, and running 141 Go test files plus integration test paths under `test/`. The current denominator is a cloned static inventory of 211 unique test-related paths, not upstream pass parity.
- Pandoc full upstream runner was not executed: `ghc`, `cabal`, and `stack` are unavailable in this environment, and upstream `test-pandoc` must be built as a Haskell Tasty executable from a full checkout. The current denominator is a cloned static inventory of 1,979 upstream test files/artifacts, not upstream pass parity.
- rclone full upstream runner was not executed: `go test ./...` and `fstest/test_all` require Go module builds and may exercise backend/provider integration remotes. The current denominator is a cloned static inventory of 327 Go test files plus 836 testdata paths, not upstream pass parity.
- Difftastic full upstream runner was not executed: the sparse cache would need broad checkout hydration plus online Cargo dependency downloads and compilation of difftastic plus many tree-sitter parser/native parser crates. A limited `cargo test --no-run --locked --offline` probe failed before compilation because the local Cargo cache lacks `humansize`. The current denominator is a cloned static inventory of 287 inspected behavior artifacts, not upstream pass parity.
- esbuild full upstream runner was not executed: `make test` requires Go unit tests/vet plus Node source-map, end-to-end, JS API, plugin, register, node-unref, and decorator tests; `make test-all` adds Deno, WASM, typecheck, and Yarn PnP coverage. This environment has no `go` binary and no `deno` binary, and a full run would also require hydrating the checkout and installing Node dependencies. The current denominator is a cloned static inventory of 2,567 counted upstream test entry points, not upstream pass parity.
- Dolt full upstream runners were not executed: `go version` and `bats --version` are unavailable in this environment, and upstream BATS additionally requires building `dolt`, `noms`, and `remotesrv` plus Python/parquet dependencies. The current denominator is a cloned static inventory of 613 executable upstream test files plus counted BATS cases/artifacts, not upstream pass parity.
- Independent audit on 2026-05-22 found the current PHP pass/fail counts are seed-local and must not be treated as upstream parity.
- Independent audit on 2026-05-22 found `port-difftastic` and `port-rclone` active while their manifests remained static seeds; rclone and difftastic are now reconciled with cloned static inventories, but neither has upstream runner parity.
- Independent audit on 2026-05-22 found `.upstream-cache/pandoc` and `.upstream-cache/syncthing` still report mass tracked deletions even though the lane manifests now use cloned static inventories; restore or reclone those caches before relying on cache-local targeted reads or runner attempts.
- Independent audit on 2026-05-22 found `port-esbuild` and `port-auditor` sessions active while this file still reported no active sessions; `.upstream-cache/esbuild` was recloned as a no-checkout blob-filtered cache and the esbuild manifest is now reconciled with a counted static inventory.
- Independent audit on 2026-05-22 found `port-dolt` and `port-auditor` tmux sessions active while the current-session section still reported stopped/none; Dolt was later explicitly reauthorized for this lane run and now has a reconciled cloned inventory plus native PHP slice.
- Independent audit on 2026-05-22 found `.upstream-cache/dolt`, `.upstream-cache/esbuild`, `.upstream-cache/pandoc`, and `.upstream-cache/syncthing` all report mass tracked deletions/no-checkout working-tree states. Inventories based on `git ls-tree` and targeted `git show` reads remain useful, but these caches are not runner-ready or safe for broad working-tree scans until restored or recloned.
- Independent audit on 2026-05-22 after `f409148` found the portfolio baseline marker is only a coordination milestone: all lanes still lack upstream runner parity, markerPDF maps 0 upstream benchmark pairs, Gitoxide remains tree-inventory-only, and dashboard PHP pass/fail values are local smoke tests.
- Independent audit on 2026-05-22 found dashboard/status hygiene issues to fix before more status stamping: LightningCSS maps 7 manifest behaviors while the dashboard reports 6 local PHP passes, and every lane audit cell still says it needs independent auditor review after this run.
- GitHub Pages may take a few minutes to finish its first build after each push.
- The tmux team should stay capped at two implementation workers plus an auditor under the current background load.
- `.upstream-cache/dolt` is a blob-filtered no-checkout cache. Its `git ls-tree` inventory and targeted `git show` reads are valid, but it is not runner-ready until checked out/restored and the missing Go/BATS dependencies are installed.

## Current Owner / Session

- Supervisor: main Codex session.
- Auditor: stopped; latest independent audit is recorded in `audits/latest.md`.
- Worker sessions: none active after integrating the latest markerPDF slice.

## Next Best Step

Fix coordination truthfulness first: label dashboard pass/fail as local PHP, reconcile the LightningCSS mapped/pass mismatch, and stamp audit status from the latest independent audit. Then return capacity to the highest-priority gaps: add Gitoxide loose direct/symbolic ref parsing from `gix-ref` and map one markerPDF benchmark/reference pair or documented surrogate.
