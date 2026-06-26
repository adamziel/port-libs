<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitDiscover;

$tmpDir = static function (): string {
    $base = sys_get_temp_dir() . '/port-libs-gitoxide-discover-' . bin2hex(random_bytes(6));
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

$makeGitDir = static function (string $path, array $options = []) use ($write): void {
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException("Unable to create git directory {$path}");
    }
    if (!is_dir($path . '/objects') && !mkdir($path . '/objects', 0777, true) && !is_dir($path . '/objects')) {
        throw new RuntimeException("Unable to create objects directory under {$path}");
    }
    if (!is_dir($path . '/refs') && !mkdir($path . '/refs', 0777, true) && !is_dir($path . '/refs')) {
        throw new RuntimeException("Unable to create refs directory under {$path}");
    }

    $write($path . '/HEAD', $options['head'] ?? "ref: refs/heads/main\n");

    if (($options['config'] ?? null) !== false) {
        $config = $options['config'] ?? "[core]\nrepositoryformatversion = 0\n";
        $write($path . '/config', $config);
    }

    if (($options['index'] ?? false) === true) {
        $write($path . '/index', '');
    }
};

$kind = static fn (string $path): string => GitDiscover::isGit($path)['kind'];

return [
    'parse gitdir valid paths like upstream gix-discover parse valid' => static function (TestRunner $t): void {
        $t->same('a', GitDiscover::parseGitdir('gitdir: a'));
        $t->same('relative/path', GitDiscover::parseGitdir('gitdir: relative/path'));
        $t->same('./relative/path', GitDiscover::parseGitdir('gitdir: ./relative/path'));
        $t->same('/absolute/path', GitDiscover::parseGitdir("gitdir: /absolute/path\n"));
        $t->same('C:/hello/there', GitDiscover::parseGitdir("gitdir: C:/hello/there\r\n"));
    },

    'parse gitdir rejects invalid formats like upstream gix-discover parse invalid' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitDiscover::parseGitdir('gitdir:'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDiscover::parseGitdir('bogus: foo'));
        $t->throws(InvalidArgumentException::class, static fn () => GitDiscover::parseGitdir('gitdir: '));
    },

    'missing configuration file is not a dealbreaker in bare repo' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        foreach (['bare-no-config-after-init.git', 'bare-no-config.git'] as $name) {
            $makeGitDir($root . '/' . $name, ['config' => false]);
            $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($root . '/' . $name));
        }
    },

    'bare repo with index file still looks bare' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        $repo = $root . '/bare-with-index.git';
        $makeGitDir($repo, ['index' => true, 'config' => "[core]\nbare = true\n"]);

        $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($repo));
    },

    'non bare repo without workdir looks possibly bare' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        $repo = $root . '/non-bare-without-worktree';
        $makeGitDir($repo, ['config' => "[core]\nbare = false\n"]);

        $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($repo));
    },

    'non bare repo without workdir with index looks possibly bare' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        $repo = $root . '/non-bare-without-worktree-with-index';
        $makeGitDir($repo, ['index' => true, 'config' => "[core]\nbare = false\n"]);

        $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($repo));
    },

    'bare repo with index file still looks bare if renamed' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        foreach (['bare-with-index-bare', 'bare-with-index-no-config-bare'] as $name) {
            $makeGitDir($root . '/' . $name, ['index' => true, 'config' => $name === 'bare-with-index-no-config-bare' ? false : "[core]\nbare = true\n"]);
            $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($root . '/' . $name));
        }
    },

    'non bare repo without index looks like worktree' => static function (TestRunner $t) use ($tmpDir, $makeGitDir): void {
        $root = $tmpDir();
        $gitDir = $root . '/non-bare-without-index/.git';
        $makeGitDir($gitDir, ['config' => "[core]\nbare = false\n"]);

        $t->same(['kind' => GitDiscover::KIND_WORK_TREE, 'linkedGitDir' => null], GitDiscover::isGit($gitDir));
    },

    'missing configuration file is not a dealbreaker in nonbare repo' => static function (TestRunner $t) use ($tmpDir, $makeGitDir): void {
        $root = $tmpDir();
        foreach (['worktree-no-config-after-init/.git', 'worktree-no-config/.git'] as $name) {
            $gitDir = $root . '/' . $name;
            $makeGitDir($gitDir, ['config' => false]);
            $t->same(['kind' => GitDiscover::KIND_WORK_TREE, 'linkedGitDir' => null], GitDiscover::isGit($gitDir));
        }
    },

    'split worktree using configuration remains possibly bare without config reads' => static function (TestRunner $t) use ($tmpDir, $makeGitDir, $kind): void {
        $root = $tmpDir();
        $configs = [
            'repo-with-worktree-in-config' => ['index' => true, 'config' => "[core]\nworktree = ../repo-with-worktree-in-config-worktree\n"],
            'repo-with-worktree-in-config-unborn' => ['config' => "[core]\nworktree = ../repo-with-worktree-in-config-unborn-worktree\n"],
            'repo-with-worktree-in-config-unborn-no-worktreedir' => ['config' => "[core]\n"],
            'repo-with-worktree-in-config-unborn-empty-worktreedir' => ['index' => true, 'config' => "[core]\nworktree = \n"],
            'repo-with-worktree-in-config-unborn-worktreedir-missing-value' => ['index' => true, 'config' => "[core]\n    worktree\n"],
        ];

        foreach ($configs as $name => $options) {
            $repo = $root . '/' . $name;
            $makeGitDir($repo, $options);
            $t->same(GitDiscover::KIND_POSSIBLY_BARE, $kind($repo), "{$name} remains possibly bare");
        }
    },

    'linked gitdir file follows commondir to classify worktree' => static function (TestRunner $t) use ($tmpDir, $write, $makeGitDir): void {
        $root = $tmpDir();
        $worktree = $root . '/worktree';
        $commonGitDir = $root . '/main/.git';
        $privateGitDir = $commonGitDir . '/worktrees/linked';

        $makeGitDir($commonGitDir);
        $write($privateGitDir . '/HEAD', "ref: refs/heads/main\n");
        $write($privateGitDir . '/commondir', "../..\n");
        $write($worktree . '/.git', 'gitdir: ../main/.git/worktrees/linked');
        $joinedGitDir = $worktree . '/../main/.git/worktrees/linked';

        $t->same(
            ['kind' => GitDiscover::KIND_WORK_TREE, 'linkedGitDir' => $joinedGitDir],
            GitDiscover::isGit($worktree . '/.git'),
        );
        $t->same($joinedGitDir, GitDiscover::gitdirFromFile($worktree . '/.git'));
    },
];
