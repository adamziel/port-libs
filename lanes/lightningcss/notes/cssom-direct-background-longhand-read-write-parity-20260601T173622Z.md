# CSSOM direct background longhand read/write parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T173622Z`

## Source truth

- Pinned upstream LightningCSS cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream `tests/test_cssom.rs` keeps DeclarationBlock get/set/remove behavior as the CSSOM source of truth.
- Native pinned NAPI minifier evidence for direct background longhands:
  - `background-size: 0PX AUTO` -> `.x{background-size:0}`
  - `background-repeat: repeat no-repeat` -> `.x{background-repeat:repeat-x}`
  - `background-image: URL("hero.jpg")` -> `.x{background-image:url(hero.jpg)}`
  - `background-attachment: Fixed` -> `.x{background-attachment:fixed}`
  - `background-origin: Content-Box` -> `.x{background-origin:content-box}`
  - `background-position: Left 0PX Top 50%` -> `.x{background-position:0 50%}`
  - `background-position-x: Left 0PX` -> `.x{background-position-x:left 0}`
  - `background-position-y: Top 50%` -> `.x{background-position-y:top 50%}`

## Red-first behavior

Before this patch, direct CSSOM declarations preserved raw values such as `0PX AUTO`, `repeat no-repeat`, `URL("hero.jpg")`, `Fixed`, `Content-Box`, and `Left 0PX Top 50%` through `DeclarationBlock::parse()` and `getProperty()`. That diverged from upstream read/write canonicalization even though related background shorthand decomposition and direct `background-clip` canonicalization were already covered.

## Change

- Added direct background longhand value normalization for:
  - `background-image`
  - `background-position`
  - `background-position-x`
  - `background-position-y`
  - `background-size`
  - `background-repeat`
  - `background-attachment`
  - `background-origin`
- Preserved custom-property behavior, so `--Background-Size: 0PX AUTO` remains untouched.
- Extended the WordPress background CSSOM smoke with direct background longhand canonicalization.
- `phpPass` moves `8873 -> 8888` from 15 new focused DeclarationBlock assertions. Mapped coverage remains `2399 / 3532`.

## Non-overlap

This patch does not repeat prior accepted background CSSOM clusters for shorthand longhand reads, attachment/origin/clip reads from shorthands, background-position shorthand composition, background-size slash token boundaries, or direct background-clip read/write canonicalization. It is limited to direct non-clip background longhand values before CSSOM read/write serialization.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `1 test files, 1359 assertions, 0 failures`
- `php -l lanes/lightningcss/src/DeclarationBlock.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-background-cssom.php`
  - `No syntax errors detected`
- `php lanes/lightningcss/examples/wordpress-background-cssom.php --self-test`
  - `OK`
- `php -r '$json=json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json OK\n";'`
  - `lane-status json OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Dependency closure

No new support component is needed. The slice reuses existing DeclarationBlock token splitting, URL normalization, numeric length normalization, and CSSOM background shorthand helpers.

## Next task

Continue with remaining LightningCSS CSSOM/property parity gaps that can be bounded to one upstream-backed cluster, especially direct longhand canonicalization not yet covered by generic keyword/color/minifier helpers.
