<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$tmpDir = static function (): string {
    $base = sys_get_temp_dir() . '/port-libs-gitoxide-config-' . bin2hex(random_bytes(6));
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary directory {$base}");
    }

    return $base;
};

$write = static function (string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create directory {$dir}");
    }
    file_put_contents($path, $contents);
};

$loadConditionalValue = static function (string $condition, array $options, bool $userConfig = false) use ($tmpDir, $write): ?string {
    $root = $tmpDir();
    $worktree = $root . '/worktree';
    $gitDir = $worktree . '/.git';
    mkdir($gitDir, 0777, true);
    $includePath = $worktree . '/include.config';
    $write($includePath, "[section]\nvalue = override-value\n");

    $configPath = $userConfig ? $root . '/.gitconfig' : $gitDir . '/config';
    if (!$userConfig) {
        $write($configPath, "[section]\nvalue = base-value\n");
    }
    file_put_contents(
        $configPath,
        "\n[includeIf \"{$condition}\"]\npath = {$includePath}\n",
        FILE_APPEND,
    );

    $config = GitConfig::fromFile($configPath, array_replace([
        'gitDir' => $gitDir,
        'homeDir' => $root,
        'branchName' => 'refs/heads/main',
    ], $options));

    return $config->value('section', null, 'value');
};

return [
    'include and includeIf insertion order follows focused gix-config semantics' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $rootConfig = $root . '/root';
        $firstInclude = $root . '/first-incl';
        $secondInclude = $root . '/second-incl';
        $conditionalInclude = $root . '/incl-if';

        $write($firstInclude, "[section]\nvalue = first-incl-path\n");
        $write($secondInclude, "[section]\nvalue = second-incl-path\n");
        $write($conditionalInclude, "[section]\nvalue = incl-if-path\n");
        $write($rootConfig, <<<CFG
        [section]
        value = base
        [include]
        path = {$firstInclude}
        path = {$firstInclude}
        [section]
        value = base-past-first-include
        [includeIf "gitdir:root/"]
        path = {$conditionalInclude}
        [section]
        value = base-past-includeIf
        [include]
        path = {$secondInclude} ; paths keep inline comments out
        [section]
        value = base-past-second-include
        CFG);

        $config = GitConfig::fromFile($rootConfig, ['gitDir' => $rootConfig . '/.git', 'homeDir' => $root]);

        $t->same([
            'base',
            'first-incl-path',
            'first-incl-path',
            'base-past-first-include',
            'incl-if-path',
            'base-past-includeIf',
            'second-incl-path',
            'base-past-second-include',
        ], $config->values('section', null, 'value'));
        $t->same(11, count($config->sections()));
    },

    'conditional include max depth follows gix-config zero and truncation boundaries' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $rootConfig = $root . '/config';
        $childConfig = $root . '/child.config';
        $grandchildConfig = $root . '/grandchild.config';

        $write($childConfig, <<<CFG
        [section]
        value = child
        [include]
        path = {$grandchildConfig}
        CFG);
        $write($grandchildConfig, <<<CFG
        [section]
        value = grandchild
        CFG);
        $write($rootConfig, <<<CFG
        [section]
        value = base
        [includeIf "onbranch:deploy/"]
        path = {$childConfig}
        CFG);

        $options = ['branchName' => 'refs/heads/deploy/site'];

        $t->throws(
            RuntimeException::class,
            static fn () => GitConfig::fromFile($rootConfig, array_replace($options, ['maxDepth' => 0])),
        );

        $config = GitConfig::fromFile($rootConfig, array_replace($options, [
            'maxDepth' => 0,
            'errOnMaxDepthExceeded' => false,
        ]));
        $t->same(['base'], $config->values('section', null, 'value'));

        $t->throws(
            RuntimeException::class,
            static fn () => GitConfig::fromFile($rootConfig, array_replace($options, ['maxDepth' => 1])),
        );

        $config = GitConfig::fromFile($rootConfig, array_replace($options, [
            'maxDepth' => 1,
            'errOnMaxDepthExceeded' => false,
        ]));
        $t->same(['base', 'child'], $config->values('section', null, 'value'));

        $config = GitConfig::fromFile($rootConfig, array_replace($options, [
            'maxDepth' => 2,
            'errOnMaxDepthExceeded' => false,
        ]));
        $t->same(['base', 'child', 'grandchild'], $config->values('section', null, 'value'));
    },

    'onbranch includeIf conditions match short local branch names and upstream glob boundaries' => static function (TestRunner $t) use ($loadConditionalValue): void {
        $t->same('override-value', $loadConditionalValue('onbranch:main', ['branchName' => 'refs/heads/main']));
        $t->same('base-value', $loadConditionalValue('onbranch:refs/heads/main', ['branchName' => 'refs/heads/main']));
        $t->same('base-value', $loadConditionalValue('onbranch:good', ['branchName' => 'refs/bisect/good']));
        $t->same('override-value', $loadConditionalValue('onbranch:prefix*', ['branchName' => 'refs/heads/prefixsuffix']));
        $t->same('base-value', $loadConditionalValue('onbranch:prefix*', ['branchName' => 'refs/heads/prefix/suffix']));
        $t->same('override-value', $loadConditionalValue('onbranch:*suffix', ['branchName' => 'refs/heads/prefixsuffix']));
        $t->same('base-value', $loadConditionalValue('onbranch:*suffix', ['branchName' => 'refs/heads/prefix/suffix']));
        $t->same('override-value', $loadConditionalValue('onbranch:*/suffix', ['branchName' => 'refs/heads/prefix/suffix']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/', ['branchName' => 'refs/heads/feature/b/start']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/b/', ['branchName' => 'refs/heads/feature/b/start']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/*/start', ['branchName' => 'refs/heads/feature/a/start']));
        $t->same('base-value', $loadConditionalValue('onbranch:feature/*/start', ['branchName' => 'refs/heads/feature/a/b/start']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/**/start', ['branchName' => 'refs/heads/feature/a/b/start']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/**/start', ['branchName' => 'refs/heads/feature/start']));
    },

    'gitdir includeIf conditions follow trailing slash dot path tilde and icase parity' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('override-value', $loadConditionalValue('gitdir:worktree/', []));
        $t->same('base-value', $loadConditionalValue('gitdir:worktree', []));
        $t->same('override-value', $loadConditionalValue('gitdir:worktree/.git', []));
        $t->same('override-value', $loadConditionalValue('gitdir/i:WORKTREE/', []));
        $t->same('base-value', $loadConditionalValue('gitdir:WORKTREE/', []));
        $t->same('override-value', $loadConditionalValue('gitdir:./worktree/.git', [], true));

        $root = $tmpDir();
        $worktree = $root . '/subdir/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $includePath = $worktree . '/include.config';
        $configPath = $gitDir . '/config';
        $write($includePath, "[section]\nvalue = tilde-override\n");
        $write($configPath, "[section]\nvalue = base\n[includeIf \"gitdir:~/subdir/worktree/\"]\npath = {$includePath}\n");
        $config = GitConfig::fromFile($configPath, ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('tilde-override', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/standard/globbing/wildcards';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $includePath = $worktree . '/include.config';
        $configPath = $gitDir . '/config';
        $write($includePath, "[section]\nvalue = glob-override\n");
        $write($configPath, "[section]\nvalue = base\n[includeIf \"gitdir:stan?ard/glo*ng/[xwz]ildcards/.git\"]\npath = {$includePath}\n");
        $config = GitConfig::fromFile($configPath, ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('glob-override', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/dir/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $includePath = $worktree . '/include.config';
        $configPath = $gitDir . '/config';
        $write($includePath, "[section]\nvalue = double-star-override\n");
        $write($configPath, "[section]\nvalue = base\n[includeIf \"gitdir:**/dir/**/worktree/**\"]\npath = {$includePath}\n");
        $config = GitConfig::fromFile($configPath, ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('double-star-override', $config->value('section', null, 'value'));
    },

    'gitdir includeIf conditions preserve upstream path sentinels and absolute boundaries' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $gitDir = $root . '/.git';
        mkdir($gitDir, 0777, true);
        $write($root . '/tilde-alone.config', "[section]\ntildeAlone = should-not-load\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:~"]
        path = ../tilde-alone.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));
        $t->same(null, $config->value('section', null, 'tildeAlone'));

        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/double-slash.config', "[section]\ndoubleSlash = should-not-load\n");
        $write($worktree . '/absolute-worktree.config', "[section]\nabsoluteWorktree = should-not-load\n");
        $write($worktree . '/absolute-gitdir.config', "[section]\nabsoluteGitdir = matched\n");
        $write($worktree . '/absolute-worktree-glob.config', "[section]\nabsoluteWorktreeGlob = matched\n");
        $write($worktree . '/dotdot.config', "[section]\ndotdot = should-not-load\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir://worktree"]
        path = ../double-slash.config
        [includeIf "gitdir:{$worktree}"]
        path = ../absolute-worktree.config
        [includeIf "gitdir:{$gitDir}"]
        path = ../absolute-gitdir.config
        [includeIf "gitdir:{$worktree}/**"]
        path = ../absolute-worktree-glob.config
        [includeIf "gitdir:../"]
        path = ../dotdot.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same(null, $config->value('section', null, 'doubleSlash'));
        $t->same(null, $config->value('section', null, 'absoluteWorktree'));
        $t->same('matched', $config->value('section', null, 'absoluteGitdir'));
        $t->same('matched', $config->value('section', null, 'absoluteWorktreeGlob'));
        $t->same(null, $config->value('section', null, 'dotdot'));

        $root = $tmpDir();
        $worktree = $root . '/.hidden';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nhidden = matched\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:.hidden/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('matched', $config->value('section', null, 'hidden'));
    },

    'gitdir includeIf falls back to gix realpath normalization for missing path components' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $normalizedGitDir = $root . '/worktree/.git';
        $providedGitDir = $root . '/missing/../worktree/./.git';
        $write($root . '/canonical.config', "[section]\ncanonical = matched\n");
        $write($root . '/miss.config', "[section]\nmiss = should-not-load\n");
        $write($root . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:{$normalizedGitDir}"]
        path = canonical.config
        [includeIf "gitdir:{$root}/missing/.git"]
        path = miss.config
        CFG);

        $config = GitConfig::fromFile($root . '/config', [
            'gitDir' => $providedGitDir,
            'homeDir' => $root,
        ]);

        $t->same('base', $config->value('section', null, 'value'));
        $t->same('matched', $config->value('section', null, 'canonical'));
        $t->same(null, $config->value('section', null, 'miss'));
        $t->same(false, is_dir($normalizedGitDir));
    },

    'gitdir dot-slash includeIf conditions require an including config path like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($root . '/dot-root.config', "[section]\ndotRoot = matched\n");
        $write($root . '/miss.config', "[section]\nmiss = should-not-load\n");
        $write($root . '/.gitconfig', <<<CFG
        [includeIf "gitdir:./"]
        path = dot-root.config
        [includeIf "gitdir:./missing/.git"]
        path = miss.config
        CFG);
        $config = GitConfig::fromFile($root . '/.gitconfig', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('matched', $config->value('section', null, 'dotRoot'));
        $t->same(null, $config->value('section', null, 'miss'));

        $escapedGitDir = str_replace('\\', '\\\\', $gitDir);
        $absoluteInclude = $root . '/absolute.config';
        $write($absoluteInclude, "[section]\nabsolute = should-not-load\n");
        $t->throws(\RuntimeException::class, static fn () => GitConfig::fromString(<<<CFG
        [includeIf "gitdir:{$escapedGitDir}"]
        path = ./dot-root.config
        CFG, null, ['gitDir' => $gitDir, 'homeDir' => $root]));

        $t->throws(\RuntimeException::class, static fn () => GitConfig::fromString(<<<CFG
        [includeIf "gitdir:./worktree/.git"]
        path = {$absoluteInclude}
        CFG, null, ['gitDir' => $gitDir, 'homeDir' => $root]));

        $config = GitConfig::fromString(<<<CFG
        [includeIf "gitdir:{$escapedGitDir}"]
        path = ./dot-root.config
        [includeIf "gitdir:./worktree/.git"]
        path = {$absoluteInclude}
        CFG, null, [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'errOnMissingConfigPath' => false,
        ]);
        $t->same(null, $config->value('section', null, 'dotRoot'));
        $t->same(null, $config->value('section', null, 'absolute'));
    },

    'drive-looking paths remain relative on unix like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->same(true, true);
            return;
        }

        $root = $tmpDir();
        $write($root . '/C:/include.config', "[section]\ndriveInclude = matched\n");
        $write($root . '/config', <<<CFG
        [include]
        path = C:/include.config
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('section', null, 'driveInclude'));

        $root = $tmpDir();
        $repo = $root . '/C:/wp-content.git';
        $gitDir = $repo . '/.git';
        mkdir($gitDir, 0777, true);
        $write($repo . '/drive-gitdir.config', "[section]\nvalue = drive-gitdir\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:C:/wp-content.git/"]
        path = ../drive-gitdir.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('drive-gitdir', $config->value('section', null, 'value'));
    },

    'conditional include quoted subsections and globs use upstream backslash escape rules' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('override-value', $loadConditionalValue('gitdir:work\\tree/', []));
        $t->same('override-value', $loadConditionalValue('gitdir:\\\\work\\\\tree\\\\/', []));
        $t->same('override-value', $loadConditionalValue('gitdir:work\\\\tree/', []));

        $root = $tmpDir();
        $write($root . '/literal-star', "[user]\nstar = literal\n");
        $write($root . '/wildcard-star', "[user]\nwildcard = glob\n");
        $write($root . '/miss', "[user]\nmiss = no\n");
        $write($root . '/config', <<<CFG
        [remote "plugin"]
        url = https://git.example.test/star*repo
        [includeIf "hasconfig:remote.*.url:https://git.example.test/star\\\\*repo"]
        path = "literal-star"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/star*repo"]
        path = "wildcard-star"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/star\\\\*missing"]
        path = "miss"
        CFG);

        $config = GitConfig::fromFile($root . '/config');
        $t->same('literal', $config->value('user', null, 'star'));
        $t->same('glob', $config->value('user', null, 'wildcard'));
        $t->same(null, $config->value('user', null, 'miss'));
    },

    'gitdir includeIf preserves backslash path bytes on unix' => static function (TestRunner $t) use ($tmpDir, $write): void {
        if (DIRECTORY_SEPARATOR === '\\') {
            $t->same(true, true);
            return;
        }

        $root = $tmpDir();
        $worktree = $root . '/work\\slash';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $slashPolicy = $root . '/slash-policy.config';
        $wildcardPolicy = $root . '/wildcard-policy.config';
        $write($slashPolicy, "[section]\nslash = should-not-load\n");
        $write($wildcardPolicy, "[section]\nwildcard = matched\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:work/slash/"]
        path = {$slashPolicy}
        [includeIf "gitdir:work?slash/"]
        path = {$wildcardPolicy}
        CFG);

        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));
        $t->same(null, $config->value('section', null, 'slash'));
        $t->same('matched', $config->value('section', null, 'wildcard'));
    },

    'hasconfig includeIf preserves remote URL backslashes as URL bytes' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $write($root . '/slash-url', "[user]\nslash = should-not-load\n");
        $write($root . '/literal-backslash-url', "[user]\nliteralBackslash = matched\n");
        $write($root . '/question-backslash-url', "[user]\nquestionBackslash = matched\n");
        $write($root . '/config', <<<CFG
        [remote "windows"]
        url = https://git.example.test\\wp-content.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content.git"]
        path = "slash-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test\\\\\\\\wp-content.git"]
        path = "literal-backslash-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test?wp-content.git"]
        path = "question-backslash-url"
        CFG);

        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'slash'));
        $t->same('matched', $config->value('user', null, 'literalBackslash'));
        $t->same('matched', $config->value('user', null, 'questionBackslash'));
    },

    'hasconfig includeIf sees deprecated remote dot subsections like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $write($root . '/legacy-dot-url', "[user]\nlegacyDot = matched\n");
        $write($root . '/nested-dot-url', "[user]\nnestedDot = matched\n");
        $write($root . '/quoted-dot-url', "[user]\nquotedDot = should-not-load\n");
        $write($root . '/config', <<<CFG
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-dot.git"]
        path = "legacy-dot-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/nested-dot.git"]
        path = "nested-dot-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/quoted-dot.git"]
        path = "quoted-dot-url"
        [remote.origin]
        url = https://git.example.test/wp-content/legacy-dot.git
        [remote.origin.extra]
        url = https://git.example.test/wp-content/nested-dot.git
        [remote.origin "quoted"]
        url = https://git.example.test/wp-content/quoted-dot.git
        CFG);

        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'legacyDot'));
        $t->same('matched', $config->value('user', null, 'nestedDot'));
        $t->same(null, $config->value('user', null, 'quotedDot'));
        $t->same('https://git.example.test/wp-content/legacy-dot.git', $config->value('remote', 'origin', 'url'));
        $t->same('https://git.example.test/wp-content/nested-dot.git', $config->value('remote', 'origin.extra', 'url'));
        $t->same('https://git.example.test/wp-content/quoted-dot.git', $config->value('remote.origin', 'quoted', 'url'));
    },

    'conditional include double-star only crosses slash at path component boundaries' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/wp/site/content';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/unbounded.config', "[policy]\nunbounded = loaded\n");
        $write($worktree . '/bounded.config', "[policy]\nbounded = loaded\n");
        $write($gitDir . '/config', <<<CFG
        [includeIf "gitdir:wp**content/"]
        path = ../unbounded.config
        [includeIf "gitdir:wp/**/content/"]
        path = ../bounded.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same(null, $config->value('policy', null, 'unbounded'));
        $t->same('loaded', $config->value('policy', null, 'bounded'));

        $t->same('base-value', $loadConditionalValue('onbranch:release**candidate', [
            'branchName' => 'refs/heads/release/site/candidate',
        ]));
        $t->same('override-value', $loadConditionalValue('onbranch:release/**/candidate', [
            'branchName' => 'refs/heads/release/site/candidate',
        ]));

        $root = $tmpDir();
        $write($root . '/unbounded-url', "[user]\nunbounded = loaded\n");
        $write($root . '/bounded-url', "[user]\nbounded = loaded\n");
        $write($root . '/config', <<<CFG
        [remote "content"]
        url = https://git.example.test/wp/site/content.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp**content.git"]
        path = "unbounded-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp/**/content.git"]
        path = "bounded-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'unbounded'));
        $t->same('loaded', $config->value('user', null, 'bounded'));
    },

    'conditional include bracket classes do not match slash separators' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/work/tree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = slash-class-override\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:work[/]tree/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/work-tree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = non-slash-class-override\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:work[!/]tree/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('non-slash-class-override', $config->value('section', null, 'value'));

        $t->same('base-value', $loadConditionalValue('onbranch:feature[/]start', ['branchName' => 'refs/heads/feature/start']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature[-]start', ['branchName' => 'refs/heads/feature-start']));

        $root = $tmpDir();
        $write($root . '/slash-class', "[user]\nslash = bad\n");
        $write($root . '/dash-class', "[user]\ndash = good\n");
        $write($root . '/config', <<<CFG
        [remote "slash"]
        url = https://git.example.test/wp-content.git
        [remote "dash"]
        url = https://git.example.test-wp-content.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test[/]wp-content.git"]
        path = "slash-class"
        [includeIf "hasconfig:remote.*.url:https://git.example.test[-]wp-content.git"]
        path = "dash-class"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'slash'));
        $t->same('good', $config->value('user', null, 'dash'));
    },

    'conditional include escaped hyphen bracket classes stay literal' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('base-value', $loadConditionalValue('onbranch:release/[a\\\\-c]ite', [
            'branchName' => 'refs/heads/release/bite',
        ]));
        $t->same('override-value', $loadConditionalValue('onbranch:release/[a\\\\-c]ite', [
            'branchName' => 'refs/heads/release/-ite',
        ]));
        $t->same('override-value', $loadConditionalValue('onbranch:release/[a\\\\-c]ite', [
            'branchName' => 'refs/heads/release/cite',
        ]));

        $root = $tmpDir();
        $worktree = $root . '/deploy/site-b';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = escaped-hyphen\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy/site-[a\\\\-c]/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/deploy/site--';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = escaped-hyphen\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy/site-[a\\\\-c]/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('escaped-hyphen', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $write($root . '/range-url', "[user]\nrange = should-not-load\n");
        $write($root . '/config', <<<CFG
        [remote "range"]
        url = https://git.example.test/wp-content/site-b.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[a\\\\-c].git"]
        path = "range-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'range'));

        $root = $tmpDir();
        $write($root . '/literal-url', "[user]\nliteral = matched\n");
        $write($root . '/config', <<<CFG
        [remote "literal"]
        url = https://git.example.test/wp-content/site--.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[a\\\\-c].git"]
        path = "literal-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'literal'));
    },

    'conditional include reversed ranges stay warning-free and match gix byte ranges' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('override-value', $loadConditionalValue('onbranch:release/[z-a]ite', [
            'branchName' => 'refs/heads/release/zite',
        ]));
        $t->same('base-value', $loadConditionalValue('onbranch:release/[z-a]ite', [
            'branchName' => 'refs/heads/release/mite',
        ]));
        $t->same('override-value', $loadConditionalValue('onbranch:release/[!z-a]ite', [
            'branchName' => 'refs/heads/release/mite',
        ]));
        $t->same('base-value', $loadConditionalValue('onbranch:release/[!z-a]ite', [
            'branchName' => 'refs/heads/release/zite',
        ]));

        $root = $tmpDir();
        $worktree = $root . '/deploy/site-z';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/literal-start.config', "[section]\nvalue = reversed-start\n");
        $write($worktree . '/middle.config', "[section]\nmiddle = should-not-load\n");
        $write($worktree . '/icase.config', "[section]\nicase = reversed-icase\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy/site-[z-a]/"]
        path = ../literal-start.config
        [includeIf "gitdir:deploy/site-[!z-a]/"]
        path = ../middle.config
        [includeIf "gitdir/i:DEPLOY/SITE-[Z-A]/"]
        path = ../icase.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('reversed-start', $config->value('section', null, 'value'));
        $t->same(null, $config->value('section', null, 'middle'));
        $t->same('reversed-icase', $config->value('section', null, 'icase'));

        $root = $tmpDir();
        $write($root . '/literal-start-url', "[user]\nliteralStart = matched\n");
        $write($root . '/middle-url', "[user]\nmiddle = should-not-load\n");
        $write($root . '/config', <<<CFG
        [remote "site"]
        url = https://git.example.test/wp-content/site-z.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[z-a].git"]
        path = "literal-start-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[!z-a].git"]
        path = "middle-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'literalStart'));
        $t->same(null, $config->value('user', null, 'middle'));
    },

    'conditional include icase POSIX class names are normalized like gix wildmatch' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/wp-content/plugins';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = uppercase-posix-name\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir/i:WP-CONTENT/[[:UPPER:]]LUGINS/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('uppercase-posix-name', $config->value('section', null, 'value'));
    },

    'conditional include POSIX bracket classes match gix wildmatch classes' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('override-value', $loadConditionalValue('onbranch:deploy/[[:alpha:]]ite', ['branchName' => 'refs/heads/deploy/site']));
        $t->same('base-value', $loadConditionalValue('onbranch:deploy/[[:digit:]]ite', ['branchName' => 'refs/heads/deploy/site']));
        $t->same('override-value', $loadConditionalValue('onbranch:deploy/[![:digit:]]ite', ['branchName' => 'refs/heads/deploy/site']));
        $t->same('base-value', $loadConditionalValue('onbranch:deploy/[![:alpha:]]ite', ['branchName' => 'refs/heads/deploy/site']));

        $root = $tmpDir();
        $worktree = $root . '/wp-content/plugins';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = posix-gitdir\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:wp-content/[[:alpha:]]lugins/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('posix-gitdir', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/wp-content/plugins';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = posix-gitdir\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:wp-content/[[:digit:]]lugins/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/wp-content/plugins';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = posix-gitdir-icase\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir/i:WP-CONTENT/[[:upper:]]LUGINS/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('posix-gitdir-icase', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $write($root . '/digit-url', "[user]\ndigit = matched\n");
        $write($root . '/letter-url', "[user]\nletter = matched\n");
        $write($root . '/upper-url', "[user]\nupper = no\n");
        $write($root . '/slash-url', "[user]\nslash = no\n");
        $write($root . '/config', <<<CFG
        [remote "digit"]
        url = https://git.example.test/wp-content/site-7.git
        [remote "letter"]
        url = https://git.example.test/wp-content/site-a.git
        [remote "slash"]
        url = https://git.example.test/wp-content.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:digit:]].git"]
        path = "digit-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[![:digit:]].git"]
        path = "letter-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:upper:]].git"]
        path = "upper-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test[/[:alpha:]]wp-content.git"]
        path = "slash-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'digit'));
        $t->same('matched', $config->value('user', null, 'letter'));
        $t->same(null, $config->value('user', null, 'upper'));
        $t->same(null, $config->value('user', null, 'slash'));
    },

    'conditional include POSIX blank class follows gix ascii whitespace boundary' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $tab = "\t";
        $verticalTab = "\x0B";

        $root = $tmpDir();
        $worktree = $root . '/deploy' . $tab . 'site';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/blank.config', "[section]\nblank = matched-tab\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy[[:blank:]]site/"]
        path = ../blank.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('matched-tab', $config->value('section', null, 'blank'));

        $root = $tmpDir();
        $worktree = $root . '/deploy' . $verticalTab . 'site';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/blank.config', "[section]\nblank = should-not-load\n");
        $write($worktree . '/control.config', "[section]\ncontrol = matched-vtab\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy[[:blank:]]site/"]
        path = ../blank.config
        [includeIf "gitdir:deploy[[:cntrl:]]site/"]
        path = ../control.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same(null, $config->value('section', null, 'blank'));
        $t->same('matched-vtab', $config->value('section', null, 'control'));

        $root = $tmpDir();
        $write($root . '/tab-url', "[user]\ntab = matched\n");
        $write($root . '/vtab-blank-url', "[user]\nvtabBlank = should-not-load\n");
        $write($root . '/vtab-control-url', "[user]\nvtabControl = matched\n");
        $write($root . '/config', <<<CFG
        [remote "tab"]
        url = "https://git.example.test/wp-content/blank-tab-{$tab}.git"
        [remote "vtab"]
        url = "https://git.example.test/wp-content/blank-vtab-{$verticalTab}.git"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-tab-[[:blank:]].git"]
        path = "tab-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-vtab-[[:blank:]].git"]
        path = "vtab-blank-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-vtab-[[:cntrl:]].git"]
        path = "vtab-control-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'tab'));
        $t->same(null, $config->value('user', null, 'vtabBlank'));
        $t->same('matched', $config->value('user', null, 'vtabControl'));
    },

    'conditional include malformed bracket classes abort like gix wildmatch' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/work[[:word:]]';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = invalid-posix\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:work[[:word:]]/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/work[';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = unclosed-class\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:work[/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $t->same('base-value', $loadConditionalValue('onbranch:release/[[:word:]]', [
            'branchName' => 'refs/heads/release/[[:word:]]',
        ]));
        $t->same('base-value', $loadConditionalValue('onbranch:release/[', [
            'branchName' => 'refs/heads/release/[',
        ]));

        $root = $tmpDir();
        $write($root . '/invalid-posix-url', "[user]\ninvalid = should-not-load\n");
        $write($root . '/unclosed-url', "[user]\nunclosed = should-not-load\n");
        $write($root . '/config', <<<CFG
        [remote "invalid-posix"]
        url = https://git.example.test/wp-content/site-[[:word:]].git
        [remote "unclosed"]
        url = https://git.example.test/wp-content/site-[.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:word:]].git"]
        path = "invalid-posix-url"
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[.git"]
        path = "unclosed-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'invalid'));
        $t->same(null, $config->value('user', null, 'unclosed'));
    },

    'conditional include wildmatch stays byte safe for malformed utf8 names' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $legacyByte = "\xFF";

        $root = $tmpDir();
        $worktree = $root . '/legacy-' . $legacyByte;
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = raw-gitdir\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:legacy-?/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('raw-gitdir', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = raw-branch\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "onbranch:release-?"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'branchName' => 'refs/heads/release-' . $legacyByte,
        ]);
        $t->same('raw-branch', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $write($root . '/legacy-byte', "[user]\nlegacyByte = matched\n");
        $write($root . '/config', <<<CFG
        [remote "legacy"]
        url = https://git.example.test/wp-content/legacy-{$legacyByte}.git
        [includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-?.git"]
        path = "legacy-byte"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('matched', $config->value('user', null, 'legacyByte'));
    },

    'conditional include trailing backslash globs abort like gix wildmatch' => static function (TestRunner $t) use ($loadConditionalValue, $tmpDir, $write): void {
        $t->same('base-value', $loadConditionalValue('onbranch:release\\\\', [
            'branchName' => 'refs/heads/release\\',
        ]));

        $root = $tmpDir();
        $gitDir = $root . '/deploy\\';
        mkdir($gitDir, 0777, true);
        $write($root . '/include.config', "[section]\nvalue = should-not-load\n");
        $write($root . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:deploy\\\\"]
        path = include.config
        CFG);
        $config = GitConfig::fromFile($root . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $url = 'https://git.example.test/wp-content/trailing\\';
        $escapedUrl = str_replace('\\', '\\\\', $url);
        $write($root . '/trailing-url', "[user]\ntrailing = should-not-load\n");
        $write($root . '/config', <<<CFG
        [remote "trailing"]
        url = "{$escapedUrl}"
        [includeIf "hasconfig:remote.*.url:{$escapedUrl}"]
        path = "trailing-url"
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same(null, $config->value('user', null, 'trailing'));
    },

    'conditional include path interpolation follows gix prefix and tilde sentinels' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/literal-tilde';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($gitDir . '/~', "[section]\nvalue = tilde-literal\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:literal-tilde/"]
        path = ~
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('tilde-literal', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/home-slash';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($root . '/home-policy.config', "[section]\nvalue = home-expanded\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:home-slash/"]
        path = ~/home-policy.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('home-expanded', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/prefix-worktree';
        $gitDir = $worktree . '/.git';
        $installPrefix = $root . '/install';
        mkdir($gitDir, 0777, true);
        $write($installPrefix . '/policy.config', "[section]\nvalue = install-prefix\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:prefix-worktree/"]
        path = %(prefix)/policy.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'installPrefix' => $installPrefix,
        ]);
        $t->same('install-prefix', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/literal-prefix';
        $gitDir = $worktree . '/.git';
        $installPrefix = $root . '/install';
        mkdir($gitDir . '/%(prefix)', 0777, true);
        $write($gitDir . '/%(prefix)/policy.config', "[section]\nvalue = literal-prefix\n");
        $write($installPrefix . '/policy.config', "[section]\nvalue = should-not-load\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:literal-prefix/"]
        path = ./%(prefix)/policy.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'installPrefix' => $installPrefix,
        ]);
        $t->same('literal-prefix', $config->value('section', null, 'value'));

        $root = $tmpDir();
        $worktree = $root . '/~alice/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/include.config', "[section]\nvalue = named-user\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir:~alice/worktree/"]
        path = ../include.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', ['gitDir' => $gitDir, 'homeDir' => $root]);
        $t->same('base', $config->value('section', null, 'value'));
        $t->throws(\RuntimeException::class, static fn () => GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'errOnInterpolationFailure' => true,
        ]));
    },

    'conditional include named user interpolation uses caller supplied home dirs like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $deployHome = $root . '/deploy-user-home';
        $repo = $deployHome . '/sites/wp-content.git';
        $gitDir = $repo . '/.git';
        mkdir($gitDir, 0777, true);
        $write($deployHome . '/path-policy.config', "[section]\nnamedPath = matched\n");
        $write($repo . '/gitdir-policy.config', "[section]\nnamedGitdir = matched\n");
        $write($deployHome . '/optional-policy.config', "[section]\nnamedOptional = matched\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [include]
        path = ~deploy/path-policy.config
        [include]
        path = :(optional)~deploy/optional-policy.config
        [includeIf "gitdir:~deploy/sites/wp-content.git/"]
        path = ../gitdir-policy.config
        [includeIf "gitdir:~missing/sites/wp-content.git/"]
        path = ../missing-policy.config
        CFG);

        $config = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'userHomeDirs' => ['deploy' => $deployHome],
        ]);
        $t->same('base', $config->value('section', null, 'value'));
        $t->same('matched', $config->value('section', null, 'namedPath'));
        $t->same('matched', $config->value('section', null, 'namedGitdir'));
        $t->same('matched', $config->value('section', null, 'namedOptional'));
        $t->same(null, $config->value('section', null, 'missingPolicy'));

        $write($root . '/env-policy.config', "[env]\nnamedUser = matched\n");
        $envConfig = GitConfig::fromEnvironmentPairs([
            ['key' => 'includeIf.onbranch:deploy/*.path', 'value' => '~deploy/env-policy.config'],
        ], [
            'branchName' => 'refs/heads/deploy/site-a',
            'userHomeDirs' => ['deploy' => $root],
        ]);
        $t->same('matched', $envConfig->value('env', null, 'namedUser'));

        $missingConfig = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'errOnInterpolationFailure' => false,
        ]);
        $t->same(null, $missingConfig->value('section', null, 'namedPath'));
        $t->same(null, $missingConfig->value('section', null, 'namedGitdir'));

        $t->throws(\RuntimeException::class, static fn () => GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'errOnInterpolationFailure' => true,
        ]));
    },

    'gitdir includeIf conditions match symlinked git directories like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        if (DIRECTORY_SEPARATOR === '\\' || !function_exists('symlink')) {
            $t->same(true, true);
            return;
        }

        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $linkedWorktree = $root . '/symlink-worktree';
        $gitDir = $worktree . '/.git';
        $linkedGitDir = $linkedWorktree . '/.git';
        mkdir($gitDir, 0777, true);
        if (!@symlink($worktree, $linkedWorktree)) {
            $t->same(true, true);
            return;
        }

        $write($root . '/tilde-resolved.config', "[symlink]\ntildeResolved = matched\n");
        $write($root . '/dot-link.config', "[symlink]\ndotLink = matched\n");
        $write($root . '/relative-link.config', "[symlink]\nrelativeLink = matched\n");
        $write($root . '/icase-link.config', "[symlink]\nicaseLink = matched\n");
        $write($root . '/missing.config', "[symlink]\nmissing = should-not-load\n");
        $write($root . '/.gitconfig', <<<CFG
        [includeIf "gitdir:~/worktree/"]
        path = tilde-resolved.config
        [includeIf "gitdir:./symlink-worktree/.git"]
        path = dot-link.config
        [includeIf "gitdir:symlink-worktree/"]
        path = relative-link.config
        [includeIf "gitdir/i:SYMLINK-WORKTREE/"]
        path = icase-link.config
        [includeIf "gitdir:~/missing-worktree/"]
        path = missing.config
        CFG);

        $config = GitConfig::fromFile($root . '/.gitconfig', [
            'gitDir' => $linkedGitDir,
            'homeDir' => $root,
        ]);

        $t->same('matched', $config->value('symlink', null, 'tildeResolved'));
        $t->same('matched', $config->value('symlink', null, 'dotLink'));
        $t->same('matched', $config->value('symlink', null, 'relativeLink'));
        $t->same('matched', $config->value('symlink', null, 'icaseLink'));
        $t->same(null, $config->value('symlink', null, 'missing'));
    },

    'optional path prefix is stripped for gitdir conditions and include paths' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/optional-prefix';
        $gitDir = $worktree . '/.git';
        $installPrefix = $root . '/install';
        mkdir($gitDir, 0777, true);
        $write($worktree . '/conditional.config', "[section]\nvalue = conditional-optional\n");
        $write($worktree . '/case.config', "[section]\ncase = should-not-load\n");
        $write($installPrefix . '/install.config', "[section]\ninstall = optional-install\n");
        $write($gitDir . '/config', <<<CFG
        [section]
        value = base
        [includeIf "gitdir::(optional)optional-prefix/"]
        path = :(optional)../conditional.config
        [includeIf "gitdir::(OPTIONAL)optional-prefix/"]
        path = :(optional)../case.config
        [includeIf "gitdir::(optional)optional-prefix/"]
        path = :(optional)%(prefix)/install.config
        CFG);
        $config = GitConfig::fromFile($gitDir . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'installPrefix' => $installPrefix,
        ]);
        $t->same('conditional-optional', $config->value('section', null, 'value'));
        $t->same(null, $config->value('section', null, 'case'));
        $t->same('optional-install', $config->value('section', null, 'install'));

        $root = $tmpDir();
        $write($root . '/plain.config', "[section]\nplain = optional-plain\n");
        $write($root . '/case.config', "[section]\ncase = should-not-load\n");
        $write($root . '/config', <<<CFG
        [include]
        path = :(optional)plain.config
        [include]
        path = :(OPTIONAL)case.config
        CFG);
        $config = GitConfig::fromFile($root . '/config');
        $t->same('optional-plain', $config->value('section', null, 'plain'));
        $t->same(null, $config->value('section', null, 'case'));
    },

    'hasconfig includeIf searches parent config with gix ordering and cycle boundaries' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $write($root . '/include-this', "[user]\nthis = included\n");
        $write($root . '/dont-include-that', "[user]\nthat = not-included\n");
        $write($root . '/basic', <<<CFG
        [includeIf "hasconfig:remote.*.url:foourl"]
        path = "include-this"
        [includeIf "hasconfig:remote.*.url:barurl"]
        path = "dont-include-that"
        [remote "foo"]
        url = foourl
        CFG);
        $config = GitConfig::fromFile($root . '/basic');
        $t->same('included', $config->value('user', null, 'this'));
        $t->same(null, $config->value('user', null, 'that'));

        $root = $tmpDir();
        $write($root . '/include-two-three', "[user]\ntwo = included-config\nthree = included-config\n");
        $write($root . '/include-four', "[user]\nfour = included-config\n");
        $write($root . '/include-five', "[user]\nfive = included-config\n");
        $write($root . '/indirect', "[includeIf \"hasconfig:remote.*.url:early\"]\npath = \"include-five\"\n");
        $write($root . '/order', <<<CFG
        [remote "foo"]
        url = before
        [remote "other"]
        url = early
        [user]
        one = main-config
        [includeIf "hasconfig:remote.*.url:before"]
        path = "include-two-three"
        [includeIf "hasconfig:remote.*.url:after"]
        path = "include-four"
        [user]
        three = main-config
        five = main-config
        [remote "bar"]
        url = after
        [include]
        path = "indirect"
        CFG);
        $config = GitConfig::fromFile($root . '/order');
        $t->same('main-config', $config->value('user', null, 'one'));
        $t->same('included-config', $config->value('user', null, 'two'));
        $t->same('main-config', $config->value('user', null, 'three'));
        $t->same('included-config', $config->value('user', null, 'four'));
        $t->same('included-config', $config->value('user', null, 'five'));

        $root = $tmpDir();
        $write($root . '/double-star-start', "[user]\ndss = yes\n");
        $write($root . '/double-star-end', "[user]\ndse = yes\n");
        $write($root . '/double-star-middle', "[user]\ndsm = yes\n");
        $write($root . '/double-star-zero', "[user]\ndsz = yes\n");
        $write($root . '/single-star-middle', "[user]\nssm = yes\n");
        $write($root . '/no', "[user]\nno = no\n");
        $write($root . '/globs', <<<CFG
        [remote "foo"]
        url = https://foo/bar/baz
        [includeIf "hasconfig:remote.*.url:**/baz"]
        path = "double-star-start"
        [includeIf "hasconfig:remote.*.url:**/nomatch"]
        path = "no"
        [includeIf "hasconfig:remote.*.url:https:/**"]
        path = "double-star-end"
        [includeIf "hasconfig:remote.*.url:nomatch:/**"]
        path = "no"
        [includeIf "hasconfig:remote.*.url:https:/**/baz"]
        path = "double-star-middle"
        [includeIf "hasconfig:remote.*.url:https://foo/**/bar/baz"]
        path = "double-star-zero"
        [includeIf "hasconfig:remote.*.url:https:/**/nomatch"]
        path = "no"
        [includeIf "hasconfig:remote.*.url:https://*/bar/baz"]
        path = "single-star-middle"
        [includeIf "hasconfig:remote.*.url:https://*/baz"]
        path = "no"
        CFG);
        $config = GitConfig::fromFile($root . '/globs');
        $t->same('yes', $config->value('user', null, 'dss'));
        $t->same('yes', $config->value('user', null, 'dse'));
        $t->same('yes', $config->value('user', null, 'dsm'));
        $t->same('yes', $config->value('user', null, 'dsz'));
        $t->same('yes', $config->value('user', null, 'ssm'));
        $t->same(null, $config->value('user', null, 'no'));

        $root = $tmpDir();
        $write($root . '/include-with-url', "[remote \"bar\"]\nurl = barurl\n");
        $write($root . '/cycle', "[include]\npath = \"include-with-url\"\n[includeIf \"hasconfig:remote.*.url:foourl\"]\npath = \"include-with-url\"\n");
        $config = GitConfig::fromFile($root . '/cycle');
        $t->same(['barurl'], $config->values('remote', 'bar', 'url'));

        $root = $tmpDir();
        $write($root . '/remote', "[remote \"bar\"]\nurl = barurl\n");
        $write($root . '/include-with-value', "[user]\nname = works\n");
        $write($root . '/no-cycle', "[include]\npath = \"remote\"\n[includeIf \"hasconfig:remote.*.url:barurl\"]\npath = \"include-with-value\"\n");
        $config = GitConfig::fromFile($root . '/no-cycle');
        $t->same('works', $config->value('user', null, 'name'));
    },

    'include directive section names stay exact case like gix-config resolution' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);

        $write($root . '/mixed-include.config', "[case]\nmixedInclude = should-not-load\n");
        $write($root . '/exact-include.config', "[case]\nexactInclude = matched\n");
        $write($root . '/lower-includeif.config', "[case]\nlowerIncludeIf = should-not-load\n");
        $write($root . '/mixed-includeif.config', "[case]\nmixedIncludeIf = should-not-load\n");
        $write($root . '/exact-includeif.config', "[case]\nexactIncludeIf = matched\n");
        $write($root . '/config', <<<CFG
        [Include]
        path = mixed-include.config
        [include]
        path = exact-include.config
        [includeif "gitdir:worktree/"]
        path = lower-includeif.config
        [IncludeIf "gitdir:worktree/"]
        path = mixed-includeif.config
        [includeIf "gitdir:worktree/"]
        path = exact-includeif.config
        CFG);

        $config = GitConfig::fromFile($root . '/config', [
            'gitDir' => $gitDir,
            'homeDir' => $root,
        ]);

        $t->same(null, $config->value('case', null, 'mixedInclude'));
        $t->same('matched', $config->value('case', null, 'exactInclude'));
        $t->same(null, $config->value('case', null, 'lowerIncludeIf'));
        $t->same(null, $config->value('case', null, 'mixedIncludeIf'));
        $t->same('matched', $config->value('case', null, 'exactIncludeIf'));

        $write($root . '/env-mixed-include.config', "[envcase]\nmixedInclude = should-not-load\n");
        $write($root . '/env-lower-includeif.config', "[envcase]\nlowerIncludeIf = should-not-load\n");

        $config = GitConfig::fromEnvironmentPairs([
            ['key' => 'Include.path', 'value' => $root . '/env-mixed-include.config'],
            ['key' => 'includeif.gitdir:' . $gitDir . '.path', 'value' => $root . '/env-lower-includeif.config'],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
        ]);

        $t->same(null, $config->value('envcase', null, 'mixedInclude'));
        $t->same(null, $config->value('envcase', null, 'lowerIncludeIf'));

        $write($root . '/env-exact-include.config', "[envcase]\nexactInclude = matched\n");
        $write($root . '/env-exact-includeif.config', "[envcase]\nexactIncludeIf = matched\n");

        $config = GitConfig::fromEnvironmentPairs([
            ['key' => 'include.path', 'value' => $root . '/env-exact-include.config'],
            ['key' => 'includeIf.gitdir:' . $gitDir . '.path', 'value' => $root . '/env-exact-includeif.config'],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
        ]);

        $t->same('matched', $config->value('envcase', null, 'exactInclude'));
        $t->same('matched', $config->value('envcase', null, 'exactIncludeIf'));
    },

    'environment-style includeIf keys resolve dotted conditional subsections like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/sites/wp-content';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($root . '/gitdir.config', "[env]\ngitdir = matched\n");
        $write($root . '/branch.config', "[env]\nbranch = matched\n");
        $write($root . '/branch-boundary.config', "[env]\nbranchBoundary = should-not-load\n");
        $write($root . '/remote.config', "[env]\nremote = matched\n");

        $config = GitConfig::fromEnvironmentPairs([
            ['key' => 'includeIf.gitdir:' . $gitDir . '.path', 'value' => $root . '/gitdir.config'],
            ['key' => 'includeIf.onbranch:deploy/*.path', 'value' => $root . '/branch.config'],
            ['key' => 'includeIf.onbranch:deploy*.path', 'value' => $root . '/branch-boundary.config'],
            ['key' => 'remote.origin.url', 'value' => 'https://git.example.test/wp-content/site-a.git'],
            [
                'key' => 'includeIf.hasconfig:remote.*.url:https://git.example.test/wp-content/*.git.path',
                'value' => $root . '/remote.config',
            ],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'branchName' => 'refs/heads/deploy/site-a',
        ]);

        $t->same('matched', $config->value('env', null, 'gitdir'));
        $t->same('matched', $config->value('env', null, 'branch'));
        $t->same(null, $config->value('env', null, 'branchBoundary'));
        $t->same('matched', $config->value('env', null, 'remote'));
        $t->same('https://git.example.test/wp-content/site-a.git', $config->value('remote', 'origin', 'url'));
    },

    'environment-style includeIf keys keep missing config-path errors bounded like gix-config' => static function (TestRunner $t) use ($tmpDir, $write): void {
        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $gitDir = $worktree . '/.git';
        mkdir($gitDir, 0777, true);
        $write($root . '/absolute.config', "[env]\nabsolute = should-not-load\n");

        $t->throws(RuntimeException::class, static fn () => GitConfig::fromEnvironmentPairs([
            ['key' => 'includeIf.gitdir:' . $gitDir . '.path', 'value' => './relative.config'],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
        ]));

        $t->throws(RuntimeException::class, static fn () => GitConfig::fromEnvironmentPairs([
            ['key' => 'includeIf.gitdir:./worktree/.git.path', 'value' => $root . '/absolute.config'],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
        ]));

        $config = GitConfig::fromEnvironmentPairs([
            ['key' => 'includeIf.gitdir:' . $gitDir . '.path', 'value' => './relative.config'],
            ['key' => 'includeIf.gitdir:./worktree/.git.path', 'value' => $root . '/absolute.config'],
        ], [
            'gitDir' => $gitDir,
            'homeDir' => $root,
            'errOnMissingConfigPath' => false,
        ]);

        $t->same(null, $config->value('env', null, 'absolute'));
    },

    'wordpress fixture resolves branch and remote conditional deployment config without git process' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-config-include-conditional.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-config-include-conditional.php';

        $t->same('refs/heads/deploy/site-a', $fixture['activeBranch']);
        $t->same('https://git.example.test/wp-content.git', $fixture['remoteUrl']);
        $t->same('enabled', $fixture['preview']);
        $t->same('matched', $fixture['singleComponentBranchPolicy']);
        $t->same(null, $fixture['singleComponentSlashPolicy']);
        $t->same('zdiff3', $fixture['conflictStyle']);
        $t->same('X-WP-Deploy: staging', $fixture['httpExtraHeader']);
        $t->same('true', $fixture['transferFsckObjects']);
        $t->same('matched', $fixture['escapedGitdirPolicy']);
        $t->same('matched', $fixture['recursiveGitdirPolicy']);
        $t->same(null, $fixture['slashClassRejectedPolicy']);
        $t->same('matched', $fixture['bracketUrlPolicy']);
        $t->same('matched', $fixture['legacyDotRemotePolicy']);
        $t->same('matched', $fixture['posixUrlPolicy']);
        $t->same('matched', $fixture['escapedHyphenUrlPolicy']);
        $t->same('matched', $fixture['reversedRangeStartUrlPolicy']);
        $t->same(null, $fixture['reversedRangeMiddleUrlPolicy']);
        $t->same('matched', $fixture['legacyBytePolicy']);
        $t->same('matched', $fixture['blankTabUrlPolicy']);
        $t->same(null, $fixture['blankVerticalTabUrlPolicy']);
        $t->same('matched', $fixture['controlVerticalTabUrlPolicy']);
        $t->same('matched', $fixture['literalTildePathPolicy']);
        $t->same('matched', $fixture['installPrefixPathPolicy']);
        $t->same('matched', $fixture['literalPrefixPathPolicy']);
        $t->same(null, $fixture['backslashUrlSlashPolicy']);
        $t->same('matched', $fixture['backslashUrlLiteralPolicy']);
        $t->same(null, $fixture['unboundedDoubleStarRejectedPolicy']);
        $t->same(null, $fixture['invalidPosixPolicy']);
        $t->same(null, $fixture['unclosedBracketPolicy']);
        $t->same(null, $fixture['trailingBackslashUrlPolicy']);
        $t->same('matched', $fixture['optionalPrefixPolicy']);
        $t->same(null, $fixture['tildeAloneGitdirPolicy']);
        $t->same(null, $fixture['doubleSlashGitdirPolicy']);
        $t->same(null, $fixture['dotDotGitdirPolicy']);
        $t->same('matched', $fixture['dotSlashRootPolicy']);
        $t->same(null, $fixture['dotSlashMissPolicy']);
        $t->same('matched', $fixture['nonexistingRealpathGitdirPolicy']);
        $t->same(null, $fixture['absoluteWorktreePolicy']);
        $t->same('matched', $fixture['absoluteGitdirPolicy']);
        $t->same('matched', $fixture['absoluteWorktreeGlobPolicy']);
        $t->same(null, $fixture['backslashGitdirSlashPolicy']);
        $t->same('matched', $fixture['backslashGitdirWildcardPolicy']);
        $t->same(DIRECTORY_SEPARATOR !== '\\', $fixture['drivePrefixSupported']);
        $t->same(DIRECTORY_SEPARATOR === '\\' ? null : 'matched', $fixture['drivePrefixGitdirPolicy']);
        $t->same(true, $fixture['symlinkGitdirSupported']);
        $t->same('matched', $fixture['symlinkRealpathPolicy']);
        $t->same('matched', $fixture['symlinkLiteralPolicy']);
        $t->same('matched', $fixture['symlinkIcasePolicy']);
        $t->same('matched', $fixture['environmentBranchPolicy']);
        $t->same(null, $fixture['environmentBranchBoundaryPolicy']);
        $t->same('matched', $fixture['environmentRemotePolicy']);
        $t->same('matched', $fixture['environmentNamedUserPolicy']);
        $t->same('matched', $fixture['namedUserPathPolicy']);
        $t->same('matched', $fixture['namedUserGitdirPolicy']);
        $t->same('matched', $fixture['depthFullNestedPolicy']);
        $t->same('matched', $fixture['depthLimitedPolicy']);
        $t->same(null, $fixture['depthLimitedNestedPolicy']);
        $t->same(true, $fixture['depthZeroError']);
        $t->same(null, $fixture['mixedCaseIncludeIfPolicy']);
        $t->same($fixture['preview'], $summary['preview']);
        $t->same($fixture['singleComponentBranchPolicy'], $summary['singleComponentBranchPolicy']);
        $t->same($fixture['singleComponentSlashPolicy'], $summary['singleComponentSlashPolicy']);
        $t->same($fixture['conflictStyle'], $summary['conflictStyle']);
        $t->same($fixture['escapedGitdirPolicy'], $summary['escapedGitdirPolicy']);
        $t->same($fixture['recursiveGitdirPolicy'], $summary['recursiveGitdirPolicy']);
        $t->same($fixture['slashClassRejectedPolicy'], $summary['slashClassRejectedPolicy']);
        $t->same($fixture['bracketUrlPolicy'], $summary['bracketUrlPolicy']);
        $t->same($fixture['legacyDotRemotePolicy'], $summary['legacyDotRemotePolicy']);
        $t->same($fixture['posixUrlPolicy'], $summary['posixUrlPolicy']);
        $t->same($fixture['escapedHyphenUrlPolicy'], $summary['escapedHyphenUrlPolicy']);
        $t->same($fixture['reversedRangeStartUrlPolicy'], $summary['reversedRangeStartUrlPolicy']);
        $t->same($fixture['reversedRangeMiddleUrlPolicy'], $summary['reversedRangeMiddleUrlPolicy']);
        $t->same($fixture['legacyBytePolicy'], $summary['legacyBytePolicy']);
        $t->same($fixture['blankTabUrlPolicy'], $summary['blankTabUrlPolicy']);
        $t->same($fixture['blankVerticalTabUrlPolicy'], $summary['blankVerticalTabUrlPolicy']);
        $t->same($fixture['controlVerticalTabUrlPolicy'], $summary['controlVerticalTabUrlPolicy']);
        $t->same($fixture['literalTildePathPolicy'], $summary['literalTildePathPolicy']);
        $t->same($fixture['installPrefixPathPolicy'], $summary['installPrefixPathPolicy']);
        $t->same($fixture['literalPrefixPathPolicy'], $summary['literalPrefixPathPolicy']);
        $t->same($fixture['backslashUrlSlashPolicy'], $summary['backslashUrlSlashPolicy']);
        $t->same($fixture['backslashUrlLiteralPolicy'], $summary['backslashUrlLiteralPolicy']);
        $t->same($fixture['unboundedDoubleStarRejectedPolicy'], $summary['unboundedDoubleStarRejectedPolicy']);
        $t->same($fixture['invalidPosixPolicy'], $summary['invalidPosixPolicy']);
        $t->same($fixture['unclosedBracketPolicy'], $summary['unclosedBracketPolicy']);
        $t->same($fixture['trailingBackslashUrlPolicy'], $summary['trailingBackslashUrlPolicy']);
        $t->same($fixture['optionalPrefixPolicy'], $summary['optionalPrefixPolicy']);
        $t->same($fixture['tildeAloneGitdirPolicy'], $summary['tildeAloneGitdirPolicy']);
        $t->same($fixture['doubleSlashGitdirPolicy'], $summary['doubleSlashGitdirPolicy']);
        $t->same($fixture['dotDotGitdirPolicy'], $summary['dotDotGitdirPolicy']);
        $t->same($fixture['dotSlashRootPolicy'], $summary['dotSlashRootPolicy']);
        $t->same($fixture['dotSlashMissPolicy'], $summary['dotSlashMissPolicy']);
        $t->same($fixture['nonexistingRealpathGitdirPolicy'], $summary['nonexistingRealpathGitdirPolicy']);
        $t->same($fixture['absoluteWorktreePolicy'], $summary['absoluteWorktreePolicy']);
        $t->same($fixture['absoluteGitdirPolicy'], $summary['absoluteGitdirPolicy']);
        $t->same($fixture['absoluteWorktreeGlobPolicy'], $summary['absoluteWorktreeGlobPolicy']);
        $t->same($fixture['backslashGitdirSlashPolicy'], $summary['backslashGitdirSlashPolicy']);
        $t->same($fixture['backslashGitdirWildcardPolicy'], $summary['backslashGitdirWildcardPolicy']);
        $t->same($fixture['drivePrefixSupported'], $summary['drivePrefixSupported']);
        $t->same($fixture['drivePrefixGitdirPolicy'], $summary['drivePrefixGitdirPolicy']);
        $t->same($fixture['symlinkGitdirSupported'], $summary['symlinkGitdirSupported']);
        $t->same($fixture['symlinkRealpathPolicy'], $summary['symlinkRealpathPolicy']);
        $t->same($fixture['symlinkLiteralPolicy'], $summary['symlinkLiteralPolicy']);
        $t->same($fixture['symlinkIcasePolicy'], $summary['symlinkIcasePolicy']);
        $t->same($fixture['environmentBranchPolicy'], $summary['environmentBranchPolicy']);
        $t->same($fixture['environmentBranchBoundaryPolicy'], $summary['environmentBranchBoundaryPolicy']);
        $t->same($fixture['environmentRemotePolicy'], $summary['environmentRemotePolicy']);
        $t->same($fixture['environmentNamedUserPolicy'], $summary['environmentNamedUserPolicy']);
        $t->same($fixture['namedUserPathPolicy'], $summary['namedUserPathPolicy']);
        $t->same($fixture['namedUserGitdirPolicy'], $summary['namedUserGitdirPolicy']);
        $t->same($fixture['depthFullNestedPolicy'], $summary['depthFullNestedPolicy']);
        $t->same($fixture['depthLimitedPolicy'], $summary['depthLimitedPolicy']);
        $t->same($fixture['depthLimitedNestedPolicy'], $summary['depthLimitedNestedPolicy']);
        $t->same($fixture['depthZeroError'], $summary['depthZeroError']);
        $t->same($fixture['mixedCaseIncludeIfPolicy'], $summary['mixedCaseIncludeIfPolicy']);
        $t->same($fixture['sectionsLoaded'], $summary['sectionsLoaded']);
    },
];
