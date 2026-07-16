<?php

declare(strict_types=1);

namespace PortLibs\Difftastic;

final class LanguageCatalog
{
    /**
     * @return list<array{name:string, globs:list<string>}>
     */
    public function languages(): array
    {
        return [
            ['name' => 'Ada', 'globs' => ['*.ada', '*.adb', '*.ads']],
            ['name' => 'Apex', 'globs' => ['*.cls', '*.apexc', '*.trigger']],
            ['name' => 'Assembly', 'globs' => ['*.asm', '*.s', '*.S']],
            ['name' => 'Bash', 'globs' => [
                '*.bash', '*.bats', '*.cgi', '*.command', '*.env', '*.fcgi', '*.ksh', '*.sh',
                '*.sh.in', '*.tmux', '*.tool', '*.zsh', '.bash_aliases', '.bash_history',
                '.bash_logout', '.bash_profile', '.bashrc', '.cshrc', '.env', '.env.example',
                '.flaskenv', '.kshrc', '.login', '.profile', '.zlogin', '.zlogout', '.zprofile',
                '.zshenv', '.zshrc', '9fs', 'PKGBUILD', 'bash_aliases', 'bash_logout',
                'bash_profile', 'bashrc', 'cshrc', 'gradlew', 'kshrc', 'login', 'man', 'profile',
                'zlogin', 'zlogout', 'zprofile', 'zshenv', 'zshrc',
            ]],
            ['name' => 'C', 'globs' => ['*.c']],
            ['name' => 'Clojure', 'globs' => ['*.bb', '*.boot', '*.clj', '*.cljc', '*.clje', '*.cljs', '*.cljx', '*.edn', '*.joke', '*.joker']],
            ['name' => 'CMake', 'globs' => ['*.cmake', '*.cmake.in', 'CMakeLists.txt']],
            ['name' => 'Common Lisp', 'globs' => ['*.lisp', '*.lsp', '*.asd']],
            ['name' => 'C++', 'globs' => ['*.cc', '*.cpp', '*.h', '*.hh', '*.hpp', '*.ino', '*.cxx', '*.cu']],
            ['name' => 'C#', 'globs' => ['*.cs']],
            ['name' => 'CSS', 'globs' => ['*.css']],
            ['name' => 'Dart', 'globs' => ['*.dart']],
            ['name' => 'Device Tree', 'globs' => ['*.dts', '*.dtsi', '*.dtso', '*.its']],
            ['name' => 'Elixir', 'globs' => ['*.ex', '*.exs']],
            ['name' => 'Elm', 'globs' => ['*.elm']],
            ['name' => 'Elvish', 'globs' => ['*.elv']],
            ['name' => 'Emacs Lisp', 'globs' => ['*.el', '.emacs', '_emacs', 'Cask']],
            ['name' => 'Erlang', 'globs' => ['*.erl', '*.app.src', '*.es', '*.escript', '*.hrl', '*.xrl', '*.yrl', 'Emakefile', 'rebar.config', 'rebar.config.lock', 'rebar.lock']],
            ['name' => 'F#', 'globs' => ['*.fs', '*.fsx', '*.fsi']],
            ['name' => 'Fortran', 'globs' => ['*.f', '*.for', '*.f90', '*.F', '*.FOR', '*.F90']],
            ['name' => 'Gleam', 'globs' => ['*.gleam']],
            ['name' => 'Go', 'globs' => ['*.go']],
            ['name' => 'Hare', 'globs' => ['*.ha']],
            ['name' => 'Haskell', 'globs' => ['*.hs']],
            ['name' => 'HCL', 'globs' => ['*.hcl', '*.nomad', '*.tf', '*.tfvars', '*.workflow']],
            ['name' => 'HTML', 'globs' => ['*.html', '*.htm', '*.xhtml']],
            ['name' => 'Janet', 'globs' => ['*.janet', '*.jdn']],
            ['name' => 'Java', 'globs' => ['*.java']],
            ['name' => 'JavaScript', 'globs' => ['*.cjs', '*.js', '*.mjs', '*.snap']],
            ['name' => 'JavaScript JSX', 'globs' => ['*.jsx']],
            ['name' => 'JSON', 'globs' => [
                '*.json', '*.avsc', '*.geojson', '*.gltf', '*.har', '*.ice', '*.ipynb',
                '*.JSON-tmLanguage', '*.jsonl', '*.mcmeta', '*.tfstate', '*.tfstate.backup',
                '*.topojson', '*.webapp', '*.webmanifest', '.arcconfig', '.auto-changelog',
                '.c8rc', '.htmlhintrc', '.imgbotconfig', '.nycrc', '.tern-config',
                '.tern-project', '.watchmanconfig', 'Pipfile.lock', 'composer.lock',
                'mcmod.info', 'flake.lock',
            ]],
            ['name' => 'Julia', 'globs' => ['*.jl']],
            ['name' => 'Kotlin', 'globs' => ['*.kt', '*.ktm', '*.kts']],
            ['name' => 'LaTeX', 'globs' => ['*.aux', '*.cls', '*.sty', '*.tex']],
            ['name' => 'Lua', 'globs' => ['*.lua']],
            ['name' => 'Make', 'globs' => ['*.mak', '*.d', '*.make', '*.makefile', '*.mk', '*.mkfile', 'BSDmakefile', 'GNUmakefile', 'Kbuild', 'Makefile', 'Makefile.am', 'Makefile.boot', 'Makefile.frag', 'Makefile*.in', 'Makefile.inc', 'Makefile.wat', 'makefile', 'makefile.sco', 'mkfile']],
            ['name' => 'Newick', 'globs' => ['*.nhx', '*.nwk', '*.nh']],
            ['name' => 'Nix', 'globs' => ['*.nix']],
            ['name' => 'Objective-C', 'globs' => ['*.m']],
            ['name' => 'OCaml', 'globs' => ['*.ml']],
            ['name' => 'OCaml Interface', 'globs' => ['*.mli']],
            ['name' => 'Pascal', 'globs' => ['*.pas', '*.dfm', '*.dpr', '*.lpr', '*.pascal']],
            ['name' => 'Perl', 'globs' => ['*.pm', '*.pl']],
            ['name' => 'PHP', 'globs' => ['*.php', '*.phtml', '*.php3', '*.php4', '*.php5', '*.php7', '*.phps']],
            ['name' => 'Proto', 'globs' => ['*.proto']],
            ['name' => 'Python', 'globs' => ['*.py', '*.py3', '*.pyi', '*.bzl', 'TARGETS', 'BUCK', 'DEPS']],
            ['name' => 'QML', 'globs' => ['*.qml']],
            ['name' => 'R', 'globs' => ['*.R', '*.r', '*.rd', '*.rsx', '.Rprofile', 'expr-dist']],
            ['name' => 'Racket', 'globs' => ['*.rkt']],
            ['name' => 'Ruby', 'globs' => ['*.rb', '*.builder', '*.spec', '*.rake', 'Gemfile', 'Rakefile']],
            ['name' => 'Rust', 'globs' => ['*.rs']],
            ['name' => 'Scala', 'globs' => ['*.scala', '*.sbt', '*.sc']],
            ['name' => 'Scheme', 'globs' => ['*.scm', '*.sch', '*.ss']],
            ['name' => 'SCSS', 'globs' => ['*.scss']],
            ['name' => 'Smali', 'globs' => ['*.smali']],
            ['name' => 'Solidity', 'globs' => ['*.sol']],
            ['name' => 'SQL', 'globs' => ['*.sql', '*.pgsql']],
            ['name' => 'Swift', 'globs' => ['*.swift']],
            ['name' => 'TOML', 'globs' => ['*.toml', 'Cargo.lock', 'Gopkg.lock', 'Pipfile', 'pdm.lock', 'poetry.lock', 'uv.lock']],
            ['name' => 'TypeScript', 'globs' => ['*.ts']],
            ['name' => 'TypeScript TSX', 'globs' => ['*.tsx']],
            ['name' => 'Verilog', 'globs' => ['*.v', '*.sv', '*.vh']],
            ['name' => 'VHDL', 'globs' => ['*.vhdl', '*.vhd']],
            ['name' => 'XML', 'globs' => ['*.ant', '*.csproj', '*.mjml', '*.plist', '*.resx', '*.svg', '*.ui', '*.vbproj', '*.xaml', '*.xml', '*.xsd', '*.xsl', '*.xslt', '*.zcml', 'App.config', 'nuget.config', 'packages.config', '.classpath', '.cproject', '.project']],
            ['name' => 'YAML', 'globs' => ['*.yaml', '*.yml', 'yarn.lock', 'CITATION.cff']],
            ['name' => 'Zig', 'globs' => ['*.zig']],
        ];
    }

    /**
     * @param list<string> $rawOverrides
     * @return array{rows:list<array{name:string, option:string, globs:list<string>, override:bool}>, errors:list<string>}
     */
    public function parseLanguageOverrides(array $rawOverrides): array
    {
        $rows = [];
        $errors = [];

        foreach ($rawOverrides as $rawOverride) {
            $separator = strrpos($rawOverride, ':');
            if ($separator === false) {
                $errors[] = "Invalid language override syntax '{$rawOverride}'";
                $errors[] = "Language overrides are in the format 'GLOB:LANG_NAME', e.g. '*.js:JSON'.";
                continue;
            }

            $glob = substr($rawOverride, 0, $separator);
            $name = substr($rawOverride, $separator + 1);
            if ($glob === '' || !$this->isValidGlob($glob)) {
                $errors[] = "Invalid glob syntax '{$glob}'";
                $errors[] = 'Glob parsing error: ' . $this->globParsingError($glob);
                continue;
            }

            $displayName = $this->languageNameForOverride($name);
            if ($displayName === null) {
                $errors[] = "No such language '{$name}'";
                $errors[] = 'See --list-languages for the names of all languages available. Language overrides are case insensitive.';
                continue;
            }

            $rows[] = [
                'name' => $displayName,
                'option' => $this->languageOptionForDisplayName($displayName),
                'globs' => [$glob],
                'override' => true,
            ];
        }

        return ['rows' => $this->combineAdjacentOverrideRows($rows), 'errors' => $errors];
    }

    /**
     * @param list<array{name:string, globs:list<string>, override?:bool}> $overrideRows
     */
    public function renderListLanguages(array $overrideRows = [], bool $useColor = false): string
    {
        $output = '';
        foreach ($overrideRows as $row) {
            $output .= $this->formatLanguageName($row['name'], $useColor) . ' (from override)' . "\n";
            $output .= $this->formatGlobs($row['globs']) . "\n";
        }

        foreach ($this->languages() as $language) {
            $output .= $this->formatLanguageName($language['name'], $useColor) . "\n";
            $output .= $this->formatGlobs($language['globs']) . "\n";
        }

        return $output;
    }

    /**
     * Guess a display language and internal language option for a file path.
     *
     * @param list<string> $rawOverrides
     * @return array{display:string, option:string, override:bool}
     */
    public function languageForPath(string $path, string $source = '', array $rawOverrides = []): array
    {
        $fileName = basename(str_replace('\\', '/', $path));
        $parsed = $this->parseLanguageOverrides($rawOverrides);
        if ($parsed['errors'] !== []) {
            throw new \InvalidArgumentException(implode("\n", $parsed['errors']));
        }

        foreach ($parsed['rows'] as $row) {
            foreach ($row['globs'] as $glob) {
                if ($this->globMatchesFileName($glob, $fileName)) {
                    return [
                        'display' => $row['name'],
                        'option' => $row['option'],
                        'override' => true,
                    ];
                }
            }
        }

        $headerLanguage = $this->languageFromEmacsModeHeader($source);
        if ($headerLanguage !== null) {
            return [
                'display' => $headerLanguage,
                'option' => $this->languageOptionForDisplayName($headerLanguage),
                'override' => false,
            ];
        }

        $shebangLanguage = $this->languageFromShebang($source);
        if ($shebangLanguage !== null) {
            return [
                'display' => $shebangLanguage,
                'option' => $this->languageOptionForDisplayName($shebangLanguage),
                'override' => false,
            ];
        }

        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'php' && str_starts_with($source, '<?hh')) {
            return ['display' => 'Text', 'option' => 'text', 'override' => false];
        }

        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'h' && $this->looksLikeObjectiveCHeader($source)) {
            return ['display' => 'Objective-C', 'option' => 'objective-c', 'override' => false];
        }

        foreach ($this->languages() as $language) {
            foreach ($language['globs'] as $glob) {
                if ($this->globMatchesFileName($glob, $fileName)) {
                    return [
                        'display' => $language['name'],
                        'option' => $this->languageOptionForDisplayName($language['name']),
                        'override' => false,
                    ];
                }
            }
        }

        if (str_starts_with($source, '<?xml')) {
            return ['display' => 'XML', 'option' => 'xml', 'override' => false];
        }

        return ['display' => 'Text', 'option' => 'text', 'override' => false];
    }

    private function languageNameForOverride(string $name): ?string
    {
        $normalized = strtolower(trim($name));
        if ($normalized === 'text') {
            return 'Text';
        }

        foreach ($this->languages() as $language) {
            if (strtolower($language['name']) === $normalized) {
                return $language['name'];
            }
        }

        return null;
    }

    private function languageOptionForDisplayName(string $name): string
    {
        return match (strtolower($name)) {
            'assembly' => 'asm',
            'c++' => 'cpp',
            'c#' => 'csharp',
            'common lisp' => 'common-lisp',
            'emacs lisp' => 'elisp',
            'javascript jsx' => 'jsx',
            'make' => 'makefile',
            'objective-c' => 'objective-c',
            'ocaml interface' => 'ocaml-interface',
            'plain text', 'text' => 'text',
            'typescript tsx' => 'tsx',
            default => strtolower(str_replace(' ', '-', $name)),
        };
    }

    private function globMatchesFileName(string $glob, string $fileName): bool
    {
        return fnmatch($glob, $fileName);
    }

    private function languageFromEmacsModeHeader(string $source): ?string
    {
        foreach (array_slice(explode("\n", $source), 0, 2) as $line) {
            $modeName = null;
            if (preg_match('/-\*-.*mode:([^;]+?);.*-\*-/', $line, $matches) === 1) {
                $modeName = $matches[1];
            } elseif (preg_match('/-\*-(.+)-\*-/', $line, $matches) === 1) {
                $modeName = $matches[1];
            }

            if ($modeName === null) {
                continue;
            }

            $language = match (strtolower(trim($modeName))) {
                'ada' => 'Ada',
                'c' => 'C',
                'clojure' => 'Clojure',
                'csharp' => 'C#',
                'css' => 'CSS',
                'dart' => 'Dart',
                'c++' => 'C++',
                'elixir' => 'Elixir',
                'elm' => 'Elm',
                'elvish' => 'Elvish',
                'emacs-lisp' => 'Emacs Lisp',
                'fsharp' => 'F#',
                'fortran' => 'Fortran',
                'gleam' => 'Gleam',
                'go' => 'Go',
                'haskell' => 'Haskell',
                'hcl' => 'HCL',
                'html' => 'HTML',
                'janet' => 'Janet',
                'java' => 'Java',
                'js', 'js2' => 'JavaScript',
                'lisp' => 'Common Lisp',
                'nxml' => 'XML',
                'objc' => 'Objective-C',
                'perl' => 'Perl',
                'python' => 'Python',
                'racket' => 'Racket',
                'rjsx' => 'JavaScript JSX',
                'ruby' => 'Ruby',
                'rust' => 'Rust',
                'scala' => 'Scala',
                'scss' => 'SCSS',
                'sh' => 'Bash',
                'solidity' => 'Solidity',
                'sql' => 'SQL',
                'swift' => 'Swift',
                'toml' => 'TOML',
                'tuareg' => 'OCaml',
                'typescript' => 'TypeScript',
                'verilog' => 'Verilog',
                'vhdl' => 'VHDL',
                'yaml' => 'YAML',
                'zig' => 'Zig',
                default => null,
            };
            if ($language !== null) {
                return $language;
            }
        }

        return null;
    }

    private function languageFromShebang(string $source): ?string
    {
        $firstLine = explode("\n", $source, 2)[0] ?? '';
        if (preg_match('/^#! *(?:\/usr\/bin\/env )?([^ ]+)/', $firstLine, $matches) !== 1) {
            return null;
        }

        $name = basename($matches[1]);

        return match ($name) {
            'ash', 'bash', 'dash', 'ksh', 'mksh', 'pdksh', 'rc', 'sh', 'zsh' => 'Bash',
            'tcc' => 'C',
            'lisp', 'sbc', 'ccl', 'clisp', 'ecl' => 'Common Lisp',
            'elixir' => 'Elixir',
            'elvish' => 'Elvish',
            'escript' => 'Erlang',
            'runghc', 'runhaskell', 'runhugs' => 'Haskell',
            'chakra', 'd8', 'gjs', 'js', 'node', 'nodejs', 'qjs', 'rhino', 'v8', 'v8-shell' => 'JavaScript',
            'ocaml', 'ocamlrun', 'ocamlscript' => 'OCaml',
            'perl' => 'Perl',
            'python', 'python2', 'python3' => 'Python',
            'Rscript' => 'R',
            'ruby', 'macruby', 'rake', 'jruby', 'rbx' => 'Ruby',
            'swift' => 'Swift',
            'deno', 'ts-node' => 'TypeScript',
            default => null,
        };
    }

    private function looksLikeObjectiveCHeader(string $source): bool
    {
        foreach (array_slice(explode("\n", $source), 0, 100) as $line) {
            if (str_starts_with($line, '#import')
                || str_starts_with($line, '@interface')
                || str_starts_with($line, '@protocol')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isValidGlob(string $glob): bool
    {
        return substr_count($glob, '[') === substr_count($glob, ']');
    }

    private function globParsingError(string $glob): string
    {
        if (substr_count($glob, '[') !== substr_count($glob, ']')) {
            return 'unclosed character class';
        }

        return 'unsupported glob syntax';
    }

    /**
     * Upstream groups adjacent overrides for the same language so list output
     * prints one row with multiple globs while preserving first-match order.
     *
     * @param list<array{name:string, option:string, globs:list<string>, override:bool}> $rows
     * @return list<array{name:string, option:string, globs:list<string>, override:bool}>
     */
    private function combineAdjacentOverrideRows(array $rows): array
    {
        $combined = [];
        foreach ($rows as $row) {
            $lastIndex = count($combined) - 1;
            if ($lastIndex >= 0 && $combined[$lastIndex]['option'] === $row['option']) {
                $combined[$lastIndex]['globs'] = array_merge($combined[$lastIndex]['globs'], $row['globs']);
                continue;
            }

            $combined[] = $row;
        }

        return $combined;
    }

    /**
     * @param list<string> $globs
     */
    private function formatGlobs(array $globs): string
    {
        return $globs === [] ? '' : ' ' . implode(' ', $globs);
    }

    private function formatLanguageName(string $name, bool $useColor): string
    {
        return $useColor ? "\033[1m" . $name . "\033[0m" : $name;
    }
}
