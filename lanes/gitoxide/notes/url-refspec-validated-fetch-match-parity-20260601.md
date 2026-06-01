# URL/refspec validated fetch match parity

- Worker slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T135433Z`
  on accepted base `3d3fd16a1ad2e27200a3709363c7e0cf6167b424`.
- Source truth: upstream Gitoxide
  `gix-refspec/src/match_group/validate.rs`,
  `gix-refspec/src/match_group/mod.rs`,
  `gix-refspec/src/match_group/util.rs`, and
  `gix-refspec/tests/refspec/match_group.rs` at pinned commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Upstream behavior mapped: fetch match groups first produce remote-to-local
  mappings, then validated fetch outcome reports a terminal issue when
  multiple distinct remote sources map to the same local destination. In the
  non-conflicting path, fixes remove mappings whose partial destination is not
  `HEAD` and does not start with `refs/`.
- Native PHP delta: `RefSpec::validatedFetchRemoteRefs()` now wraps the
  existing fetch matcher with upstream-style validation output: `ok`, raw or
  validated mappings, non-terminal `partial-destination-removed` fixes, and
  terminal `conflicting-destination` issues. The WordPress deployment fixture
  and example now expose both the repaired partial-destination case and the
  conflicting destination guard.
- Verification: before implementation, focused `UrlRefSpecTest.php` passed
  `1 test files, 773 assertions, 0 failures`; after implementation, focused
  `UrlRefSpecTest.php` passed `1 test files, 792 assertions, 0 failures`.
  Changed PHP lint passed; `wordpress-url-refspec-normalize.php --self-test`
  passed; `git diff --check -- lanes/gitoxide` passed.
- Upstream probe: bounded source-truth command
  `timeout 120 cargo test -p gix-refspec fetch_and_update_with -- --nocapture`
  passed `3` selected tests with `71` filtered out in the upstream cache.
  The full upstream Cargo workspace was not executed.
- Expected mapped denominator movement: `1798 / 2886` to `1799 / 2886`.
- Dependency closure: no new support component is needed. The slice reuses the
  native PHP refspec parser/matcher, existing fixture/example path, and local
  upstream source cache only; it does not require network access, credentials,
  provider config, shelling out to git, or a new support-library row.
- Non-overlap: this does not repeat prior URL parse/from-parts/from-bytes,
  empty SSH port, FTP host, path root, slash-literal raw match, one-sided push
  writer normalization, short-hex prefix expansion, or attributes/pathspec
  malformed POSIX class work.
