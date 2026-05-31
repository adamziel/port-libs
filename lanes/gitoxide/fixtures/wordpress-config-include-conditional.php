<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfig;

$root = sys_get_temp_dir() . '/port-libs-wordpress-config-' . bin2hex(random_bytes(6));
$repo = $root . '/sites/wp-content.git';
$gitDir = $repo . '/.git';
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

$write($repo . '/legacy-byte.config', <<<CFG
[wordpress]
legacyByte = matched
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

$write($gitDir . '/config', <<<CFG
[core]
repositoryformatversion = 0
[merge]
conflictStyle = diff3
[remote "origin"]
url = https://git.example.test/wp-content.git
[remote "site-seven"]
url = https://git.example.test/wp-content/site-7.git
[remote "legacy-byte"]
url = https://git.example.test/wp-content/legacy-{$legacyByte}.git
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
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/legacy-?.git"]
path = ../legacy-byte.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp/site**content.git"]
path = ../unbounded-double-star.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[[:word:]].git"]
path = ../invalid-posix.config
[includeIf "hasconfig:remote.*.url:https://git.example.test/wp-content/site-[.git"]
path = ../unclosed-bracket.config
CFG);

$config = GitConfig::fromFile($gitDir . '/config', [
    'gitDir' => $gitDir,
    'homeDir' => $root,
    'branchName' => 'refs/heads/deploy/site-a',
]);

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
    'legacyBytePolicy' => $config->value('wordpress', null, 'legacyByte'),
    'unboundedDoubleStarRejectedPolicy' => $config->value('wordpress', null, 'unboundedDoubleStar'),
    'invalidPosixPolicy' => $config->value('wordpress', null, 'invalidPosix'),
    'unclosedBracketPolicy' => $config->value('wordpress', null, 'unclosedBracket'),
    'sectionsLoaded' => array_map(
        static fn (array $section): string => $section['subsection'] === null
            ? $section['name']
            : $section['name'] . '.' . $section['subsection'],
        $config->sections(),
    ),
];
