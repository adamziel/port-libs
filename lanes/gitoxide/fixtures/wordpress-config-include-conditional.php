<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$root = sys_get_temp_dir() . '/port-libs-wordpress-config-' . bin2hex(random_bytes(6));
$repo = $root . '/sites/wp-content.git';
$gitDir = $repo . '/.git';
$installPrefix = $root . '/git-install';
$legacyByte = "\xFF";
mkdir($gitDir, 0777, true);

$write = static function (string $path, string $contents): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
};

$write($repo . '/deploy-branch.config', <<<CFG
[wordpress]
preview = enabled
[merge]
conflictStyle = zdiff3
CFG);

$write($repo . '/remote-policy.config', <<<CFG
[http]
extraHeader = X-WP-Deploy: staging
[transfer]
fsckObjects = true
CFG);

$write($repo . '/escaped-gitdir.config', <<<CFG
[wordpress]
escapedGitdir = matched
CFG);

$write($repo . '/recursive-gitdir.config', <<<CFG
[wordpress]
recursiveGitdir = matched
CFG);

$write($repo . '/slash-class-rejected.config', <<<CFG
[wordpress]
slashClassRejected = should-not-load
CFG);

$write($repo . '/bracket-url.config', <<<CFG
[wordpress]
bracketUrl = matched
CFG);

$write($repo . '/posix-url.config', <<<CFG
[wordpress]
posixUrl = matched
CFG);

$write($repo . '/escaped-hyphen-url.config', <<<CFG
[wordpress]
escapedHyphenUrl = matched
CFG);

$write($repo . '/legacy-byte.config', <<<CFG
[wordpress]
legacyByte = matched
CFG);

$write($gitDir . '/~', <<<CFG
[wordpress]
literalTildePath = matched
CFG);

$write($installPrefix . '/prefix-policy.config', <<<CFG
[wordpress]
installPrefixPath = matched
CFG);

$write($gitDir . '/%(prefix)/literal-policy.config', <<<CFG
[wordpress]
literalPrefixPath = matched
CFG);

$write($repo . '/backslash-slash-url.config', <<<CFG
[wordpress]
backslashUrlSlash = should-not-load
CFG);

$write($repo . '/backslash-literal-url.config', <<<CFG
[wordpress]
backslashUrlLiteral = matched
CFG);

$write($repo . '/unbounded-double-star.config', <<<CFG
[wordpress]
unboundedDoubleStar = should-not-load
CFG);

$write($repo . '/invalid-posix.config', <<<CFG
[wordpress]
invalidPosix = should-not-load
CFG);

$write($repo . '/unclosed-bracket.config', <<<CFG
[wordpress]
unclosedBracket = should-not-load
CFG);

$write($repo . '/optional-prefix.config', <<<CFG
[wordpress]
optionalPrefix = matched
CFG);

$write($repo . '/symlink-realpath.config', <<<CFG
[wordpress]
symlinkRealpath = matched
CFG);

$write($repo . '/symlink-literal.config', <<<CFG
[wordpress]
symlinkLiteral = matched
CFG);

$write($repo . '/symlink-icase.config', <<<CFG
[wordpress]
symlinkIcase = matched
CFG);

$backslashRepo = $root . '/legacy\\checkout';
$backslashGitDir = $backslashRepo . '/.git';
mkdir($backslashGitDir, 0777, true);

$write($backslashRepo . '/slash-policy.config', <<<CFG
[wordpress]
backslashGitdirSlash = should-not-load
CFG);

$write($backslashRepo . '/wildcard-policy.config', <<<CFG
[wordpress]
backslashGitdirWildcard = matched
CFG);

$write($backslashGitDir . '/config', <<<CFG
[wordpress]
backslashGitdirBase = base
[includeIf "gitdir:legacy/checkout/"]
path = ../slash-policy.config
[includeIf "gitdir:legacy?checkout/"]
path = ../wildcard-policy.config
CFG);

$write($gitDir . '/config', <<<CFG
[core]
repositoryformatversion = 0
[merge]
conflictStyle = diff3
[remote "origin"]
url = https://git.example.test/wp-content.git
[remote "site-seven"]
url = https://git.example.test/wp-content/site-7.git
[remote "literal-hyphen"]
url = https://git.example.test/wp-content/site--.git
[remote "legacy-byte"]
url = https://git.example.test/wp-content/legacy-{$legacyByte}.git
[remote "backslash-url"]
url = https://windows.example.test\wp-content.git
[remote "nested-content"]
url = https://git.example.test/wp/site/content.git
[remote "invalid-posix"]
url = https://git.example.test/wp-content/site-[[:word:]].git
[remote "unclosed-bracket"]
url = https://git.example.test/wp-content/site-[.git
[includeIf "onbranch:deploy/"]
path = ../deploy-branch.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/**"]
path = ../remote-policy.config
[includeIf "gitdir:wp\\-content.git/"]
path = ../escaped-gitdir.config
[includeIf "gitdir:**/sites/**/wp-content.git/**"]
path = ../recursive-gitdir.config
[includeIf "hasconfig:remote.*.url:https://git.example.test[/]wp-content.git"]
path = ../slash-class-rejected.config
[includeIf "hasconfig:remote.*.url:https://git.example[.]test/**"]
path = ../bracket-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:digit:]].git"]
path = ../posix-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[a\\\\-c].git"]
path = ../escaped-hyphen-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-?.git"]
path = ../legacy-byte.config
[includeIf "gitdir:wp-content.git/"]
path = ~
[includeIf "gitdir:wp-content.git/"]
path = %(prefix)/prefix-policy.config
[includeIf "gitdir:wp-content.git/"]
path = ./%(prefix)/literal-policy.config
[includeIf "hasconfig:remote.*.url:https://windows.example.test/wp-content.git"]
path = ../backslash-slash-url.config
[includeIf "hasconfig:remote.*.url:https://windows.example.test\\\\\\\\wp-content.git"]
path = ../backslash-literal-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp/site**content.git"]
path = ../unbounded-double-star.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:word:]].git"]
path = ../invalid-posix.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[.git"]
path = ../unclosed-bracket.config
[includeIf "gitdir::(optional)wp-content.git/"]
path = :(optional)../optional-prefix.config
CFG);

$config = GitConfig::fromFile($gitDir . '/config', [
    'gitDir' => $gitDir,
    'homeDir' => $root,
    'installPrefix' => $installPrefix,
    'branchName' => 'refs/heads/deploy/site-a',
]);

$backslashConfig = GitConfig::fromFile($backslashGitDir . '/config', [
    'gitDir' => $backslashGitDir,
    'homeDir' => $root,
]);

$symlinkGitdirSupported = false;
$symlinkConfig = null;
$linkedRepo = $root . '/linked-wp-content.git';
if (DIRECTORY_SEPARATOR !== '\\' && function_exists('symlink') && @symlink($repo, $linkedRepo)) {
    $symlinkGitdirSupported = true;
    $write($root . '/symlink.gitconfig', <<<CFG
    [includeIf "gitdir:~/sites/wp-content.git/"]
    path = sites/wp-content.git/symlink-realpath.config
    [includeIf "gitdir:./linked-wp-content.git/.git"]
    path = linked-wp-content.git/symlink-literal.config
    [includeIf "gitdir/i:LINKED-WP-CONTENT.GIT/"]
    path = linked-wp-content.git/symlink-icase.config
    CFG);
    $symlinkConfig = GitConfig::fromFile($root . '/symlink.gitconfig', [
        'gitDir' => $linkedRepo . '/.git',
        'homeDir' => $root,
    ]);
}

return [
    'activeBranch' => 'refs/heads/deploy/site-a',
    'remoteUrl' => $config->value('remote', 'origin', 'url'),
    'preview' => $config->value('wordpress', null, 'preview'),
    'conflictStyle' => $config->value('merge', null, 'conflictStyle'),
    'httpExtraHeader' => $config->value('http', null, 'extraHeader'),
    'transferFsckObjects' => $config->value('transfer', null, 'fsckObjects'),
    'escapedGitdirPolicy' => $config->value('wordpress', null, 'escapedGitdir'),
    'recursiveGitdirPolicy' => $config->value('wordpress', null, 'recursiveGitdir'),
    'slashClassRejectedPolicy' => $config->value('wordpress', null, 'slashClassRejected'),
    'bracketUrlPolicy' => $config->value('wordpress', null, 'bracketUrl'),
    'posixUrlPolicy' => $config->value('wordpress', null, 'posixUrl'),
    'escapedHyphenUrlPolicy' => $config->value('wordpress', null, 'escapedHyphenUrl'),
    'legacyBytePolicy' => $config->value('wordpress', null, 'legacyByte'),
    'literalTildePathPolicy' => $config->value('wordpress', null, 'literalTildePath'),
    'installPrefixPathPolicy' => $config->value('wordpress', null, 'installPrefixPath'),
    'literalPrefixPathPolicy' => $config->value('wordpress', null, 'literalPrefixPath'),
    'backslashUrlSlashPolicy' => $config->value('wordpress', null, 'backslashUrlSlash'),
    'backslashUrlLiteralPolicy' => $config->value('wordpress', null, 'backslashUrlLiteral'),
    'unboundedDoubleStarRejectedPolicy' => $config->value('wordpress', null, 'unboundedDoubleStar'),
    'invalidPosixPolicy' => $config->value('wordpress', null, 'invalidPosix'),
    'unclosedBracketPolicy' => $config->value('wordpress', null, 'unclosedBracket'),
    'optionalPrefixPolicy' => $config->value('wordpress', null, 'optionalPrefix'),
    'backslashGitdirSlashPolicy' => $backslashConfig->value('wordpress', null, 'backslashGitdirSlash'),
    'backslashGitdirWildcardPolicy' => $backslashConfig->value('wordpress', null, 'backslashGitdirWildcard'),
    'symlinkGitdirSupported' => $symlinkGitdirSupported,
    'symlinkRealpathPolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkRealpath'),
    'symlinkLiteralPolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkLiteral'),
    'symlinkIcasePolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkIcase'),
    'sectionsLoaded' => array_map(
        static fn (array $section): string => $section['subsection'] === null
            ? $section['name']
            : $section['name'] . '.' . $section['subsection'],
        $config->sections(),
    ),
];
