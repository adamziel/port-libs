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

    'onbranch includeIf conditions match short local branch names and upstream glob boundaries' => static function (TestRunner $t) use ($loadConditionalValue): void {
        $t->same('override-value', $loadConditionalValue('onbranch:main', ['branchName' => 'refs/heads/main']));
        $t->same('base-value', $loadConditionalValue('onbranch:refs/heads/main', ['branchName' => 'refs/heads/main']));
        $t->same('base-value', $loadConditionalValue('onbranch:good', ['branchName' => 'refs/bisect/good']));
        $t->same('override-value', $loadConditionalValue('onbranch:feature/', ['branchName' => 'refs/heads/feature/b/start']));
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

    'wordpress fixture resolves branch and remote conditional deployment config without git process' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-config-include-conditional.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-config-include-conditional.php';

        $t->same('refs/heads/deploy/site-a', $fixture['activeBranch']);
        $t->same('https://git.example.test/wp-content.git', $fixture['remoteUrl']);
        $t->same('enabled', $fixture['preview']);
        $t->same('zdiff3', $fixture['conflictStyle']);
        $t->same('X-WP-Deploy: staging', $fixture['httpExtraHeader']);
        $t->same('true', $fixture['transferFsckObjects']);
        $t->same('matched', $fixture['escapedGitdirPolicy']);
        $t->same('matched', $fixture['recursiveGitdirPolicy']);
        $t->same(null, $fixture['slashClassRejectedPolicy']);
        $t->same('matched', $fixture['bracketUrlPolicy']);
        $t->same($fixture['preview'], $summary['preview']);
        $t->same($fixture['conflictStyle'], $summary['conflictStyle']);
        $t->same($fixture['escapedGitdirPolicy'], $summary['escapedGitdirPolicy']);
        $t->same($fixture['recursiveGitdirPolicy'], $summary['recursiveGitdirPolicy']);
        $t->same($fixture['slashClassRejectedPolicy'], $summary['slashClassRejectedPolicy']);
        $t->same($fixture['bracketUrlPolicy'], $summary['bracketUrlPolicy']);
        $t->same($fixture['sectionsLoaded'], $summary['sectionsLoaded']);
    },
];
