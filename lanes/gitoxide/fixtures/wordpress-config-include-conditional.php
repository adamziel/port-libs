<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$root = sys_get_temp_dir() . '/port-libs-wordpress-config-' . bin2hex(random_bytes(6));
$repo = $root . '/sites/wp-content.git';
$gitDir = $repo . '/.git';
$installPrefix = $root . '/git-install';
$legacyByte = "\xFF";
$blankTab = "\t";
$blankVerticalTab = "\x0B";
$trailingBackslashUrl = 'https://git.example.test/wp-content/trailing\\';
$escapedTrailingBackslashUrl = str_replace('\\', '\\\\', $trailingBackslashUrl);
$escapedRepoCondition = str_replace('\\', '\\\\', $repo);
$escapedGitDirCondition = str_replace('\\', '\\\\', $gitDir);
$namedDeployUser = 'wpdeploy';
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

$write($repo . '/legacy-dot-remote.config', <<<CFG
[wordpress]
legacyDotRemote = matched
CFG);

$write($repo . '/posix-url.config', <<<CFG
[wordpress]
posixUrl = matched
CFG);

$write($repo . '/escaped-hyphen-url.config', <<<CFG
[wordpress]
escapedHyphenUrl = matched
CFG);

$write($repo . '/reversed-range-start-url.config', <<<CFG
[wordpress]
reversedRangeStartUrl = matched
CFG);

$write($repo . '/reversed-range-middle-url.config', <<<CFG
[wordpress]
reversedRangeMiddleUrl = should-not-load
CFG);

$write($repo . '/legacy-byte.config', <<<CFG
[wordpress]
legacyByte = matched
CFG);

$write($repo . '/blank-tab-url.config', <<<CFG
[wordpress]
blankTabUrl = matched
CFG);

$write($repo . '/blank-vtab-url.config', <<<CFG
[wordpress]
blankVtabUrl = should-not-load
CFG);

$write($repo . '/control-vtab-url.config', <<<CFG
[wordpress]
controlVtabUrl = matched
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

$write($repo . '/trailing-backslash-url.config', <<<CFG
[wordpress]
trailingBackslashUrl = should-not-load
CFG);

$write($repo . '/optional-prefix.config', <<<CFG
[wordpress]
optionalPrefix = matched
CFG);

$write($repo . '/tilde-alone-gitdir.config', <<<CFG
[wordpress]
tildeAloneGitdir = should-not-load
CFG);

$write($repo . '/double-slash-gitdir.config', <<<CFG
[wordpress]
doubleSlashGitdir = should-not-load
CFG);

$write($repo . '/dotdot-gitdir.config', <<<CFG
[wordpress]
dotDotGitdir = should-not-load
CFG);

$write($root . '/dot-slash-root.config', <<<CFG
[wordpress]
dotSlashRoot = matched
CFG);

$write($root . '/dot-slash-miss.config', <<<CFG
[wordpress]
dotSlashMiss = should-not-load
CFG);

$write($repo . '/absolute-worktree.config', <<<CFG
[wordpress]
absoluteWorktree = should-not-load
CFG);

$write($repo . '/absolute-gitdir.config', <<<CFG
[wordpress]
absoluteGitdir = matched
CFG);

$write($repo . '/absolute-worktree-glob.config', <<<CFG
[wordpress]
absoluteWorktreeGlob = matched
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

$write($repo . '/environment-branch.config', <<<CFG
[wordpress]
environmentBranch = matched
CFG);

$write($repo . '/environment-branch-boundary.config', <<<CFG
[wordpress]
environmentBranchBoundary = should-not-load
CFG);

$write($repo . '/environment-remote.config', <<<CFG
[wordpress]
environmentRemote = matched
CFG);

$write($root . '/named-user-path.config', <<<CFG
[wordpress]
namedUserPath = matched
CFG);

$write($repo . '/named-user-gitdir.config', <<<CFG
[wordpress]
namedUserGitdir = matched
CFG);

$write($repo . '/mixed-case-includeif.config', <<<CFG
[wordpress]
mixedCaseIncludeIf = should-not-load
CFG);

$write($root . '/environment-named-user.config', <<<CFG
[wordpress]
environmentNamedUser = matched
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
[remote "reversed-range"]
url = https://git.example.test/wp-content/site-z.git
[remote "reversed-range-middle"]
url = https://git.example.test/wp-content/range-middle-m.git
[remote "legacy-byte"]
url = https://git.example.test/wp-content/legacy-{$legacyByte}.git
[remote "blank-tab"]
url = "https://git.example.test/wp-content/blank-tab-{$blankTab}.git"
[remote "blank-vtab"]
url = "https://git.example.test/wp-content/blank-vtab-{$blankVerticalTab}.git"
[remote "backslash-url"]
url = https://windows.example.test\wp-content.git
[remote "nested-content"]
url = https://git.example.test/wp/site/content.git
[remote.legacyDot]
url = https://git.example.test/wp-content/legacy-dot.git
[remote "invalid-posix"]
url = https://git.example.test/wp-content/site-[[:word:]].git
[remote "unclosed-bracket"]
url = https://git.example.test/wp-content/site-[.git
[remote "trailing-backslash"]
url = "{$escapedTrailingBackslashUrl}"
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
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-dot.git"]
path = ../legacy-dot-remote.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:digit:]].git"]
path = ../posix-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[a\\\\-c].git"]
path = ../escaped-hyphen-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[z-a].git"]
path = ../reversed-range-start-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/range-middle-[z-a].git"]
path = ../reversed-range-middle-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-?.git"]
path = ../legacy-byte.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-tab-[[:blank:]].git"]
path = ../blank-tab-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-vtab-[[:blank:]].git"]
path = ../blank-vtab-url.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/blank-vtab-[[:cntrl:]].git"]
path = ../control-vtab-url.config
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
[includeIf "hasconfig:remote.*.url:{$escapedTrailingBackslashUrl}"]
path = ../trailing-backslash-url.config
[includeIf "gitdir::(optional)wp-content.git/"]
path = :(optional)../optional-prefix.config
[includeIf "gitdir:~"]
path = ../tilde-alone-gitdir.config
[includeIf "gitdir://wp-content.git"]
path = ../double-slash-gitdir.config
[includeIf "gitdir:../"]
path = ../dotdot-gitdir.config
[includeIf "gitdir:{$escapedRepoCondition}"]
path = ../absolute-worktree.config
[includeIf "gitdir:{$escapedGitDirCondition}"]
path = ../absolute-gitdir.config
[includeIf "gitdir:{$escapedRepoCondition}/**"]
path = ../absolute-worktree-glob.config
[include]
path = ~{$namedDeployUser}/named-user-path.config
[includeIf "gitdir:~{$namedDeployUser}/sites/wp-content.git/"]
path = ../named-user-gitdir.config
[includeif "gitdir:wp-content.git/"]
path = ../mixed-case-includeif.config
CFG);

$config = GitConfig::fromFile($gitDir . '/config', [
    'gitDir' => $gitDir,
    'homeDir' => $root,
    'userHomeDirs' => [$namedDeployUser => $root],
    'installPrefix' => $installPrefix,
    'branchName' => 'refs/heads/deploy/site-a',
]);

$backslashConfig = GitConfig::fromFile($backslashGitDir . '/config', [
    'gitDir' => $backslashGitDir,
    'homeDir' => $root,
]);

$write($root . '/.gitconfig', <<<CFG
[includeIf "gitdir:./"]
path = dot-slash-root.config
[includeIf "gitdir:./missing/.git"]
path = dot-slash-miss.config
CFG);

$dotSlashConfig = GitConfig::fromFile($root . '/.gitconfig', [
    'gitDir' => $gitDir,
    'homeDir' => $root,
]);

$environmentConfig = GitConfig::fromEnvironmentPairs([
    ['key' => 'includeIf.onbranch:deploy/*.path', 'value' => $repo . '/environment-branch.config'],
    ['key' => 'includeIf.onbranch:deploy*.path', 'value' => $repo . '/environment-branch-boundary.config'],
    ['key' => 'remote.origin.url', 'value' => 'https://git.example.test/wp-content.git'],
    [
        'key' => 'includeIf.hasconfig:remote.*.url:https://git.example.test/**.path',
        'value' => $repo . '/environment-remote.config',
    ],
    ['key' => 'includeIf.onbranch:deploy/*.path', 'value' => '~' . $namedDeployUser . '/environment-named-user.config'],
], [
    'gitDir' => $gitDir,
    'homeDir' => $root,
    'userHomeDirs' => [$namedDeployUser => $root],
    'branchName' => 'refs/heads/deploy/site-a',
]);

$drivePrefixSupported = false;
$drivePrefixGitdirPolicy = null;
if (DIRECTORY_SEPARATOR !== '\\') {
    $driveRepo = $root . '/C:/wp-content-drive.git';
    $driveGitDir = $driveRepo . '/.git';
    mkdir($driveGitDir, 0777, true);
    $write($driveRepo . '/drive-prefix.config', <<<CFG
    [wordpress]
    drivePrefixGitdir = matched
    CFG);
    $write($driveGitDir . '/config', <<<CFG
    [wordpress]
    drivePrefixBase = base
    [includeIf "gitdir:C:/wp-content-drive.git/"]
    path = ../drive-prefix.config
    CFG);
    $driveConfig = GitConfig::fromFile($driveGitDir . '/config', [
        'gitDir' => $driveGitDir,
        'homeDir' => $root,
    ]);
    $drivePrefixSupported = true;
    $drivePrefixGitdirPolicy = $driveConfig->value('wordpress', null, 'drivePrefixGitdir');
}

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
    'legacyDotRemotePolicy' => $config->value('wordpress', null, 'legacyDotRemote'),
    'posixUrlPolicy' => $config->value('wordpress', null, 'posixUrl'),
    'escapedHyphenUrlPolicy' => $config->value('wordpress', null, 'escapedHyphenUrl'),
    'reversedRangeStartUrlPolicy' => $config->value('wordpress', null, 'reversedRangeStartUrl'),
    'reversedRangeMiddleUrlPolicy' => $config->value('wordpress', null, 'reversedRangeMiddleUrl'),
    'legacyBytePolicy' => $config->value('wordpress', null, 'legacyByte'),
    'blankTabUrlPolicy' => $config->value('wordpress', null, 'blankTabUrl'),
    'blankVerticalTabUrlPolicy' => $config->value('wordpress', null, 'blankVtabUrl'),
    'controlVerticalTabUrlPolicy' => $config->value('wordpress', null, 'controlVtabUrl'),
    'literalTildePathPolicy' => $config->value('wordpress', null, 'literalTildePath'),
    'installPrefixPathPolicy' => $config->value('wordpress', null, 'installPrefixPath'),
    'literalPrefixPathPolicy' => $config->value('wordpress', null, 'literalPrefixPath'),
    'backslashUrlSlashPolicy' => $config->value('wordpress', null, 'backslashUrlSlash'),
    'backslashUrlLiteralPolicy' => $config->value('wordpress', null, 'backslashUrlLiteral'),
    'unboundedDoubleStarRejectedPolicy' => $config->value('wordpress', null, 'unboundedDoubleStar'),
    'invalidPosixPolicy' => $config->value('wordpress', null, 'invalidPosix'),
    'unclosedBracketPolicy' => $config->value('wordpress', null, 'unclosedBracket'),
    'trailingBackslashUrlPolicy' => $config->value('wordpress', null, 'trailingBackslashUrl'),
    'optionalPrefixPolicy' => $config->value('wordpress', null, 'optionalPrefix'),
    'tildeAloneGitdirPolicy' => $config->value('wordpress', null, 'tildeAloneGitdir'),
    'doubleSlashGitdirPolicy' => $config->value('wordpress', null, 'doubleSlashGitdir'),
    'dotDotGitdirPolicy' => $config->value('wordpress', null, 'dotDotGitdir'),
    'dotSlashRootPolicy' => $dotSlashConfig->value('wordpress', null, 'dotSlashRoot'),
    'dotSlashMissPolicy' => $dotSlashConfig->value('wordpress', null, 'dotSlashMiss'),
    'absoluteWorktreePolicy' => $config->value('wordpress', null, 'absoluteWorktree'),
    'absoluteGitdirPolicy' => $config->value('wordpress', null, 'absoluteGitdir'),
    'absoluteWorktreeGlobPolicy' => $config->value('wordpress', null, 'absoluteWorktreeGlob'),
    'backslashGitdirSlashPolicy' => $backslashConfig->value('wordpress', null, 'backslashGitdirSlash'),
    'backslashGitdirWildcardPolicy' => $backslashConfig->value('wordpress', null, 'backslashGitdirWildcard'),
    'drivePrefixSupported' => $drivePrefixSupported,
    'drivePrefixGitdirPolicy' => $drivePrefixGitdirPolicy,
    'symlinkGitdirSupported' => $symlinkGitdirSupported,
    'symlinkRealpathPolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkRealpath'),
    'symlinkLiteralPolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkLiteral'),
    'symlinkIcasePolicy' => $symlinkConfig?->value('wordpress', null, 'symlinkIcase'),
    'environmentBranchPolicy' => $environmentConfig->value('wordpress', null, 'environmentBranch'),
    'environmentBranchBoundaryPolicy' => $environmentConfig->value('wordpress', null, 'environmentBranchBoundary'),
    'environmentRemotePolicy' => $environmentConfig->value('wordpress', null, 'environmentRemote'),
    'environmentNamedUserPolicy' => $environmentConfig->value('wordpress', null, 'environmentNamedUser'),
    'namedUserPathPolicy' => $config->value('wordpress', null, 'namedUserPath'),
    'namedUserGitdirPolicy' => $config->value('wordpress', null, 'namedUserGitdir'),
    'mixedCaseIncludeIfPolicy' => $config->value('wordpress', null, 'mixedCaseIncludeIf'),
    'sectionsLoaded' => array_map(
        static fn (array $section): string => $section['subsection'] === null
            ? $section['name']
            : $section['name'] . '.' . $section['subsection'],
        $config->sections(),
    ),
];
