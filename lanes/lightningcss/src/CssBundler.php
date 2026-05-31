<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssBundler
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var (callable(string, string): (string|array{external?:string,file?:string}))|null */
    private $resolver = null;

    /** @var (callable(string): string)|null */
    private $reader = null;

    private bool $filesystemReads = false;

    /** @var array<string, int> */
    private array $sourceIndexes = [];

    private bool $cssModules = false;

    /** @var array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool} */
    private array $cssModuleOptions = [];

    /**
     * @var list<array{
     *     file:string,
     *     items:list<array<string, mixed>>,
     *     licenseComments:list<string>,
     *     dependencies:list<array<string, mixed>>,
     *     cssModuleDependencies:list<array{sourceIndex:int}>,
     *     cssModuleDependencySources:array<string,int>,
     *     cssModuleExports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>,
     *     cssModuleReferences:array<string, array{type:string, name:string, specifier:string}>,
     *     layer:?string,
     *     supports:?string,
     *     media:string,
     *     loc:array{line:int,column:int},
     *     parentSourceIndex:int,
     *     parentDepIndex:int
     * }>
     */
    private array $stylesheets = [];

    /**
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     */
    public function bundle(string $entry, array $files, ?callable $resolver = null): string
    {
        return $this->bundleInternal($entry, $files, $resolver, false)['code'];
    }

    /**
     * @param callable(string): string $reader
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     */
    public function bundleWithReader(string $entry, callable $reader, ?callable $resolver = null): string
    {
        return $this->bundleInternal($entry, [], $resolver, false, [], $reader)['code'];
    }

    /**
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     */
    public function bundleFile(string $entry, ?callable $resolver = null): string
    {
        return $this->bundleInternal($entry, [], $resolver, false, [], null, true)['code'];
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool} $options
     */
    public function bundleCssModules(string $entry, array $files, ?callable $resolver = null, array $options = []): array
    {
        return $this->bundleInternal($entry, $files, $resolver, true, $options);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param callable(string): string $reader
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool} $options
     */
    public function bundleCssModulesWithReader(string $entry, callable $reader, ?callable $resolver = null, array $options = []): array
    {
        return $this->bundleInternal($entry, [], $resolver, true, $options, $reader);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool} $cssModuleOptions
     * @param (callable(string): string)|null $reader
     */
    private function bundleInternal(
        string $entry,
        array $files,
        ?callable $resolver,
        bool $cssModules,
        array $cssModuleOptions = [],
        ?callable $reader = null,
        bool $filesystemReads = false
    ): array
    {
        $this->files = [];
        foreach ($files as $path => $css) {
            $this->files[$this->normalizePath((string) $path)] = $css;
        }

        $this->resolver = $resolver;
        $this->reader = $reader;
        $this->filesystemReads = $filesystemReads;
        $this->sourceIndexes = [];
        $this->stylesheets = [];
        $this->cssModules = $cssModules;
        $this->cssModuleOptions = $cssModuleOptions;

        $entry = $this->normalizePath($entry);
        $this->loadFile($entry, [
            'layer' => null,
            'supports' => null,
            'media' => '',
            'loc' => ['line' => 0, 'column' => 0],
            'file' => $entry,
        ]);
        $this->order();

        $raw = $this->licenseCommentPrefix() . $this->inline(0, []);
        $raw = (new CustomMediaTransformer())->transform($raw);

        $code = (new CssMinifier())->minify($raw);
        $exports = $cssModules ? $this->resolvedCssModuleExports(0) : [];

        return [
            'code' => $code,
            'exports' => $exports,
        ];
    }

    /**
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     */
    private function loadFile(string $file, array $rule): int
    {
        $file = $this->normalizePath($file);
        if (isset($this->sourceIndexes[$file])) {
            $sourceIndex = $this->sourceIndexes[$file];
            $this->mergeImportRule($sourceIndex, $rule);

            return $sourceIndex;
        }

        $sourceIndex = count($this->stylesheets);
        $this->sourceIndexes[$file] = $sourceIndex;
        $this->stylesheets[] = [
            'file' => $file,
            'items' => [],
            'licenseComments' => [],
            'dependencies' => [],
            'cssModuleDependencies' => [],
            'cssModuleDependencySources' => [],
            'cssModuleExports' => [],
            'cssModuleReferences' => [],
            'layer' => $rule['layer'],
            'supports' => $rule['supports'],
            'media' => $rule['media'],
            'loc' => $rule['loc'],
            'parentSourceIndex' => 0,
            'parentDepIndex' => 0,
        ];

        $source = $this->readFile($file, $rule);
        $cssModuleResult = null;
        if ($this->cssModules) {
            $cssModuleResult = (new CssModulesTransformer())->transform($source, [
                'hash' => $this->cssModuleHashForFile($file),
                'pattern' => $this->cssModuleOptions['pattern'] ?? '[hash]_[local]',
                'minify' => $this->cssModuleOptions['minify'] ?? true,
                'dashedIdents' => ($this->cssModuleOptions['dashedIdents'] ?? $this->cssModuleOptions['dashed_idents'] ?? false) === true,
            ]);
            $source = $cssModuleResult['code'];
        }

        $cssModuleDependencies = [];
        $cssModuleDependencySources = [];
        $cssModuleExports = $cssModuleResult['exports'] ?? [];
        $cssModuleReferences = $cssModuleResult['references'] ?? [];
        foreach ($this->cssModuleDependencySpecifiers($cssModuleExports, $cssModuleReferences) as $specifier) {
            $resolved = $this->resolveImport($specifier, $file, ['line' => 1, 'column' => 1]);
            if (isset($resolved['external'])) {
                throw new CssBundleException(
                    'referenced-external-module-with-css-module-from',
                    'Referenced external module with CSS module "from" clause',
                    $file,
                    1,
                    1,
                );
            }

            $dependencyRule = [
                'layer' => $rule['layer'],
                'supports' => $rule['supports'],
                'media' => $rule['media'],
                'loc' => ['line' => 1, 'column' => 1],
                'file' => $file,
            ];
            $depSourceIndex = $this->loadFile($resolved['file'], $dependencyRule);
            $cssModuleDependencies[] = ['sourceIndex' => $depSourceIndex];
            $cssModuleDependencySources[$specifier] = $depSourceIndex;
        }

        foreach ($cssModuleReferences as $placeholder => $reference) {
            if (($reference['type'] ?? '') !== 'dependency') {
                continue;
            }

            $specifier = (string) ($reference['specifier'] ?? '');
            $depSourceIndex = $cssModuleDependencySources[$specifier] ?? null;
            if ($depSourceIndex === null) {
                continue;
            }

            $source = str_replace(
                (string) $placeholder,
                $this->cssModuleDashedNameForSource($depSourceIndex, (string) ($reference['name'] ?? '')),
                $source
            );
        }

        $items = $this->topLevelItems($source, $file);
        $licenseComments = [];
        $contentItems = [];
        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'license-comment') {
                $licenseComments[] = (string) $item['raw'];
                continue;
            }

            $contentItems[] = $item;
        }

        $dependencies = [];
        foreach ($contentItems as $item) {
            if (($item['type'] ?? null) !== 'import') {
                continue;
            }

            /** @var array{specifier:string,layer:?string,supports:?string,media:string,loc:array{line:int,column:int}} $import */
            $import = $item['import'];
            $layer = $this->combineLayer($rule['layer'], $import['layer'], $file, $import['loc']);
            $media = $this->combineMediaAnd($rule['media'], $import['media'], $file, $import['loc']);
            $supports = $this->combineSupportsAnd($rule['supports'], $import['supports']);
            $resolved = $this->resolveImport($import['specifier'], $file, $import['loc']);

            if (isset($resolved['external'])) {
                $dependencies[] = [
                    'type' => 'external',
                    'url' => $resolved['external'],
                ];
                continue;
            }

            $dependencyRule = [
                'layer' => $layer,
                'supports' => $supports,
                'media' => $media,
                'loc' => $import['loc'],
                'file' => $file,
            ];
            $dependencies[] = [
                'type' => 'file',
                'sourceIndex' => $this->loadFile($resolved['file'], $dependencyRule),
            ];
        }

        $this->stylesheets[$sourceIndex]['items'] = $contentItems;
        $this->stylesheets[$sourceIndex]['licenseComments'] = $licenseComments;
        $this->stylesheets[$sourceIndex]['dependencies'] = $dependencies;
        $this->stylesheets[$sourceIndex]['cssModuleDependencies'] = $cssModuleDependencies;
        $this->stylesheets[$sourceIndex]['cssModuleDependencySources'] = $cssModuleDependencySources;
        $this->stylesheets[$sourceIndex]['cssModuleExports'] = $cssModuleExports;
        $this->stylesheets[$sourceIndex]['cssModuleReferences'] = $cssModuleReferences;

        return $sourceIndex;
    }

    /**
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     */
    private function readFile(string $file, array $rule): string
    {
        if ($this->reader !== null) {
            try {
                $source = ($this->reader)($file);
            } catch (\Throwable $throwable) {
                throw new CssBundleException(
                    'resolver-error',
                    $throwable->getMessage(),
                    $rule['loc']['column'] === 0 ? null : $rule['file'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['line'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['column'],
                );
            }

            if (!is_string($source)) {
                throw new CssBundleException(
                    'resolver-error',
                    'expect String, got: ' . $this->readerTypeName($source),
                    $rule['loc']['column'] === 0 ? null : $rule['file'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['line'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['column'],
                );
            }

            return $source;
        }

        if ($this->filesystemReads) {
            $source = @file_get_contents($file);
            if ($source === false) {
                throw new CssBundleException(
                    'resolver-error',
                    "Could not read `{$file}`.",
                    $rule['loc']['column'] === 0 ? null : $rule['file'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['line'],
                    $rule['loc']['column'] === 0 ? null : $rule['loc']['column'],
                );
            }

            return $source;
        }

        if (!array_key_exists($file, $this->files)) {
            throw new CssBundleException(
                'resolver-error',
                "Could not read `{$file}`.",
                $rule['loc']['column'] === 0 ? null : $rule['file'],
                $rule['loc']['column'] === 0 ? null : $rule['loc']['line'],
                $rule['loc']['column'] === 0 ? null : $rule['loc']['column'],
            );
        }

        return $this->files[$file];
    }

    private function readerTypeName(mixed $value): string
    {
        return match (get_debug_type($value)) {
            'int', 'float' => 'Number',
            'bool' => 'Boolean',
            'null' => 'Null',
            'array' => 'Object',
            default => get_debug_type($value),
        };
    }

    /**
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     */
    private function mergeImportRule(int $sourceIndex, array $rule): void
    {
        $entry = &$this->stylesheets[$sourceIndex];
        if (($rule['media'] !== '' && $entry['supports'] !== null) || ($entry['media'] !== '' && $rule['supports'] !== null)) {
            throw new CssBundleException(
                'unsupported-import-condition',
                'Unsupported import condition',
                $rule['file'],
                $rule['loc']['line'],
                $rule['loc']['column'],
            );
        }

        if ($rule['media'] === '') {
            $entry['media'] = '';
        } elseif ($entry['media'] !== '') {
            $entry['media'] = $this->combineMediaOr($entry['media'], $rule['media']);
        }

        if ($rule['supports'] !== null) {
            $entry['supports'] = $entry['supports'] === null
                ? $rule['supports']
                : $this->combineSupportsOr($entry['supports'], $rule['supports']);
        } else {
            $entry['supports'] = null;
        }

        if ($rule['layer'] !== null) {
            if ($entry['layer'] !== null && ($entry['layer'] !== $rule['layer'] || $rule['layer'] === '')) {
                throw new CssBundleException(
                    'unsupported-layer-combination',
                    'Unsupported layer combination in @import',
                    $rule['file'],
                    $rule['loc']['line'],
                    $rule['loc']['column'],
                );
            }

            $entry['layer'] = $rule['layer'];
        }
    }

    private function order(): void
    {
        $visited = [];
        $this->orderStylesheet(0, $visited);
    }

    private function licenseCommentPrefix(): string
    {
        $comments = '';
        foreach ($this->stylesheets as $stylesheet) {
            foreach ($stylesheet['licenseComments'] as $comment) {
                $comments .= $comment;
            }
        }

        return $comments;
    }

    /**
     * @param array<int, true> $visited
     */
    private function orderStylesheet(int $sourceIndex, array &$visited): void
    {
        if (isset($visited[$sourceIndex])) {
            return;
        }

        $visited[$sourceIndex] = true;
        $depIndex = 0;
        foreach ($this->stylesheets[$sourceIndex]['cssModuleDependencies'] as $dependency) {
            $depSourceIndex = (int) $dependency['sourceIndex'];
            if (!isset($visited[$depSourceIndex])) {
                $this->stylesheets[$depSourceIndex]['parentSourceIndex'] = $sourceIndex;
                $this->stylesheets[$depSourceIndex]['parentDepIndex'] = $depIndex;
                $this->orderStylesheet($depSourceIndex, $visited);
            }
            $depIndex++;
        }

        foreach ($this->stylesheets[$sourceIndex]['dependencies'] as $dependency) {
            if (($dependency['type'] ?? null) !== 'file') {
                continue;
            }

            $depSourceIndex = (int) $dependency['sourceIndex'];
            $this->stylesheets[$depSourceIndex]['parentSourceIndex'] = $sourceIndex;
            $this->stylesheets[$depSourceIndex]['parentDepIndex'] = $depIndex;
            $this->orderStylesheet($depSourceIndex, $visited);
            $depIndex++;
        }
    }

    /**
     * @param array<int, true> $stack
     */
    private function inline(int $sourceIndex, array $stack): string
    {
        if (isset($stack[$sourceIndex])) {
            return '';
        }

        $stack[$sourceIndex] = true;
        $stylesheet = $this->stylesheets[$sourceIndex];
        $output = '';
        $body = '';
        $importIndex = 0;
        $depIndex = 0;
        $hasBundledImport = false;

        foreach ($stylesheet['cssModuleDependencies'] as $dependency) {
            $depSourceIndex = (int) $dependency['sourceIndex'];
            $resolved = $this->stylesheets[$depSourceIndex];
            if ($resolved['parentSourceIndex'] === $sourceIndex && $resolved['parentDepIndex'] === $depIndex) {
                $output .= $this->inline($depSourceIndex, $stack);
            }
            $depIndex++;
        }

        foreach ($stylesheet['items'] as $item) {
            if (($item['type'] ?? null) === 'import') {
                $dependency = $stylesheet['dependencies'][$importIndex] ?? null;
                $importIndex++;

                if ($dependency === null) {
                    continue;
                }

                if (($dependency['type'] ?? null) === 'file') {
                    $depSourceIndex = (int) $dependency['sourceIndex'];
                    $resolved = $this->stylesheets[$depSourceIndex];
                    if ($resolved['parentSourceIndex'] === $sourceIndex && $resolved['parentDepIndex'] === $depIndex) {
                        $hasBundledImport = true;
                        $output .= $this->inline($depSourceIndex, $stack);
                    }
                    $depIndex++;
                    continue;
                }

                if ($hasBundledImport) {
                    /** @var array{line:int,column:int} $loc */
                    $loc = $item['loc'];
                    throw new CssBundleException(
                        'external-import-after-bundled-import',
                        'An external `@import` was found after a bundled `@import`. This may result in unintended selector order.',
                        $stylesheet['file'],
                        $loc['line'],
                        $loc['column'],
                    );
                }

                /** @var array{specifier:string,layer:?string,supports:?string,media:string,loc:array{line:int,column:int}} $import */
                $import = $item['import'];
                $output .= $this->externalImportStatement((string) $dependency['url'], $import);
                continue;
            }

            if (($item['type'] ?? null) === 'layer-statement') {
                $output .= $this->rewriteLayerStatement((string) $item['raw'], $stylesheet['layer']);
                continue;
            }

            $body .= (string) $item['raw'];
        }

        $output .= $this->wrapRules($body, $stylesheet['layer'], $stylesheet['media'], $stylesheet['supports']);

        return $output;
    }

    /**
     * @param array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}> $exports
     * @param array<string, array{type:string, name:string, specifier:string}> $references
     * @return list<string>
     */
    private function cssModuleDependencySpecifiers(array $exports, array $references = []): array
    {
        $specifiers = [];
        foreach ($exports as $export) {
            foreach ($export['composes'] as $reference) {
                if (($reference['type'] ?? '') !== 'dependency') {
                    continue;
                }

                $specifier = (string) ($reference['specifier'] ?? '');
                if ($specifier !== '' && !in_array($specifier, $specifiers, true)) {
                    $specifiers[] = $specifier;
                }
            }
        }
        foreach ($references as $reference) {
            if (($reference['type'] ?? '') !== 'dependency') {
                continue;
            }

            $specifier = (string) ($reference['specifier'] ?? '');
            if ($specifier !== '' && !in_array($specifier, $specifiers, true)) {
                $specifiers[] = $specifier;
            }
        }

        return $specifiers;
    }

    /**
     * @return array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     */
    private function resolvedCssModuleExports(int $sourceIndex): array
    {
        $exports = $this->stylesheets[$sourceIndex]['cssModuleExports'];
        foreach ($exports as $name => $export) {
            $exports[$name]['composes'] = $this->resolveCssModuleReferences($sourceIndex, $export['composes'], []);
        }

        return $exports;
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $references
     * @param array<string, true> $stack
     * @return list<array{type:string, name:string, specifier?:string}>
     */
    private function resolveCssModuleReferences(int $sourceIndex, array $references, array $stack): array
    {
        $resolved = [];
        foreach ($references as $reference) {
            if (($reference['type'] ?? '') !== 'dependency') {
                $this->appendCssModuleReference($resolved, $reference);
                continue;
            }

            $specifier = (string) ($reference['specifier'] ?? '');
            $depSourceIndex = $this->stylesheets[$sourceIndex]['cssModuleDependencySources'][$specifier] ?? null;
            if ($depSourceIndex === null) {
                $this->appendCssModuleReference($resolved, $reference);
                continue;
            }

            $depExport = $this->stylesheets[$depSourceIndex]['cssModuleExports'][$reference['name']] ?? null;
            if ($depExport === null) {
                continue;
            }

            $key = $depSourceIndex . ':' . $reference['name'];
            if (isset($stack[$key])) {
                continue;
            }

            $this->appendCssModuleReference($resolved, [
                'type' => 'local',
                'name' => $depExport['name'],
            ]);
            $stack[$key] = true;
            foreach ($this->resolveCssModuleReferences($depSourceIndex, $depExport['composes'], $stack) as $depReference) {
                $this->appendCssModuleReference($resolved, $depReference);
            }
        }

        return $resolved;
    }

    /**
     * @param list<array{type:string, name:string, specifier?:string}> $references
     * @param array{type:string, name:string, specifier?:string} $reference
     */
    private function appendCssModuleReference(array &$references, array $reference): void
    {
        if (in_array($reference, $references, true)) {
            return;
        }

        $references[] = $reference;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topLevelItems(string $css, string $file): array
    {
        $items = [];
        $length = strlen($css);
        $cursor = 0;
        $importsAllowed = true;

        while (true) {
            $cursor = $this->collectTopLevelTrivia($css, $cursor, $items);
            if ($cursor >= $length) {
                break;
            }

            $statementEnd = $this->findNextTopLevel($css, ';', $cursor);
            $blockOpen = $this->findNextTopLevel($css, '{', $cursor);
            if ($statementEnd !== null && ($blockOpen === null || $statementEnd < $blockOpen)) {
                $raw = substr($css, $cursor, $statementEnd - $cursor + 1);
                if ($this->startsAtKeyword($css, $cursor, '@import')) {
                    if (!$importsAllowed) {
                        $loc = $this->sourceLocation($css, $cursor + strlen('@import'));
                        throw new CssBundleException(
                            'parser-error',
                            '@import rules must precede all rules aside from @charset and @layer statements',
                            $file,
                            $loc['line'],
                            $loc['column'],
                        );
                    }

                    $items[] = [
                        'type' => 'import',
                        'raw' => $raw,
                        'loc' => $this->sourceLocation($css, $cursor),
                        'import' => $this->parseImportStatement($raw, $this->sourceLocation($css, $cursor)),
                    ];
                } elseif ($this->startsAtKeyword($css, $cursor, '@layer')) {
                    $items[] = [
                        'type' => 'layer-statement',
                        'raw' => $raw,
                    ];
                } else {
                    if (!$this->startsAtKeyword($css, $cursor, '@charset')) {
                        $importsAllowed = false;
                    }

                    $items[] = [
                        'type' => 'other',
                        'raw' => $raw,
                    ];
                }
                $cursor = $statementEnd + 1;
                continue;
            }

            if ($blockOpen === null) {
                $trailing = trim(substr($css, $cursor));
                if ($trailing !== '') {
                    $importsAllowed = false;
                    $items[] = [
                        'type' => 'other',
                        'raw' => $trailing,
                    ];
                }
                break;
            }

            $close = $this->findMatchingDelimiter($css, $blockOpen, '{', '}');
            $importsAllowed = false;
            $items[] = [
                'type' => 'other',
                'raw' => substr($css, $cursor, $close - $cursor + 1),
            ];
            $cursor = $close + 1;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function collectTopLevelTrivia(string $css, int $offset, array &$items): int
    {
        $length = strlen($css);
        while ($offset < $length) {
            if (ctype_space($css[$offset])) {
                $offset++;
                continue;
            }

            if ($css[$offset] === '/' && ($css[$offset + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $offset + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }

                $comment = substr($css, $offset, $end - $offset + 2);
                if (($css[$offset + 2] ?? '') === '!') {
                    $items[] = [
                        'type' => 'license-comment',
                        'raw' => trim($comment),
                    ];
                }

                $offset = $end + 2;
                continue;
            }

            break;
        }

        return $offset;
    }

    /**
     * @param array{line:int,column:int} $loc
     * @return array{specifier:string,layer:?string,supports:?string,media:string,loc:array{line:int,column:int}}
     */
    private function parseImportStatement(string $statement, array $loc): array
    {
        $rest = trim(substr(rtrim(trim($statement), ';'), strlen('@import')));
        $offset = $this->skipWhitespaceAndComments($rest, 0);
        $specifier = null;

        if ($this->startsFunction($rest, $offset, 'url')) {
            $open = $offset + strlen($this->readIdentifier($rest, $offset));
            $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
            $specifier = trim(substr($rest, $open + 1, $close - $open - 1));
            if (($specifier[0] ?? '') === '"' || ($specifier[0] ?? '') === "'") {
                $specifier = $this->cssStringTokenValue($specifier);
            }
            $offset = $close + 1;
        } elseif (($rest[$offset] ?? '') === '"' || ($rest[$offset] ?? '') === "'") {
            $end = $this->readQuotedTokenEnd($rest, $offset);
            $specifier = $this->cssStringTokenValue(substr($rest, $offset, $end - $offset));
            $offset = $end;
        }

        if ($specifier === null || $specifier === '') {
            throw new CssBundleException('parser-error', 'Invalid @import source', null, $loc['line'], $loc['column']);
        }

        $layer = null;
        $supports = null;
        $media = '';

        while (true) {
            $offset = $this->skipWhitespaceAndComments($rest, $offset);
            if ($offset >= strlen($rest)) {
                break;
            }

            if ($this->startsFunction($rest, $offset, 'supports')) {
                $open = $offset + strlen($this->readIdentifier($rest, $offset));
                $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
                $supports = trim(substr($rest, $open + 1, $close - $open - 1));
                $offset = $close + 1;
                continue;
            }

            if ($this->startsFunction($rest, $offset, 'layer')) {
                $open = $offset + strlen($this->readIdentifier($rest, $offset));
                $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
                $layer = trim(substr($rest, $open + 1, $close - $open - 1));
                $offset = $close + 1;
                continue;
            }

            if (strncasecmp(substr($rest, $offset, strlen('layer')), 'layer', strlen('layer')) === 0) {
                $next = $rest[$offset + strlen('layer')] ?? '';
                if ($next === '' || !$this->isIdentifierChar($next)) {
                    $layer = '';
                    $offset += strlen('layer');
                    continue;
                }
            }

            $media = trim(substr($rest, $offset));
            break;
        }

        return [
            'specifier' => $specifier,
            'layer' => $layer,
            'supports' => $supports,
            'media' => $media,
            'loc' => $loc,
        ];
    }

    /**
     * @param array{line:int,column:int} $loc
     * @return array{file:string}|array{external:string}
     */
    private function resolveImport(string $specifier, string $originatingFile, array $loc): array
    {
        if ($this->resolver !== null) {
            try {
                $result = ($this->resolver)($specifier, $originatingFile);
            } catch (\Throwable $throwable) {
                throw new CssBundleException(
                    'resolver-error',
                    $throwable->getMessage(),
                    $originatingFile,
                    $loc['line'],
                    $loc['column'],
                );
            }

            if (is_array($result)) {
                if (array_key_exists('external', $result)) {
                    if (!is_string($result['external'])) {
                        $this->throwUnsupportedResolveResult($originatingFile, $loc);
                    }

                    return ['external' => $result['external']];
                }
                if (array_key_exists('file', $result)) {
                    if (!is_string($result['file'])) {
                        $this->throwUnsupportedResolveResult($originatingFile, $loc);
                    }

                    return ['file' => $this->normalizePath($result['file'])];
                }
            }

            if (is_string($result)) {
                return ['file' => $this->normalizePath($result)];
            }

            $this->throwUnsupportedResolveResult($originatingFile, $loc);
        }

        if (preg_match('/^https?:/i', $specifier) === 1) {
            return ['external' => $specifier];
        }

        if (str_starts_with($specifier, '/')) {
            return ['file' => $this->normalizePath($specifier)];
        }

        $directory = dirname($originatingFile);
        $path = ($directory === '.' || $directory === '') ? $specifier : rtrim($directory, '/') . '/' . $specifier;

        return ['file' => $this->normalizePath($path)];
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function throwUnsupportedResolveResult(string $originatingFile, array $loc): void
    {
        throw new CssBundleException(
            'resolver-error',
            'data did not match any variant of untagged enum ResolveResult',
            $originatingFile,
            $loc['line'],
            $loc['column'],
        );
    }

    private function combineLayer(?string $parent, ?string $child, string $file, array $loc): ?string
    {
        if (($parent === '' && $child !== null) || ($child === '' && $parent !== null)) {
            throw new CssBundleException(
                'unsupported-layer-combination',
                'Unsupported layer combination in @import',
                $file,
                $loc['line'],
                $loc['column'],
            );
        }

        if ($parent !== null && $parent !== '' && $child !== null && $child !== '') {
            return $parent . '.' . $child;
        }

        return $parent ?? $child;
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function combineMediaAnd(string $parent, string $child, string $file, array $loc): string
    {
        $parent = trim($parent);
        $child = trim($child);
        if ($parent === '') {
            return $child;
        }
        if ($child === '') {
            return $parent;
        }

        $queries = [];
        foreach ($this->splitTopLevel($parent, ',') as $parentQuery) {
            foreach ($this->splitTopLevel($child, ',') as $childQuery) {
                if ($this->isNegatedMediaQuery($parentQuery) && $this->isNegatedMediaQuery($childQuery)) {
                    throw new CssBundleException(
                        'unsupported-media-boolean-logic',
                        'Unsupported boolean logic in @import media query',
                        $file,
                        $loc['line'],
                        $loc['column'],
                    );
                }

                $queries[] = $this->andMediaQuery($parentQuery, $childQuery);
            }
        }

        return implode(', ', $queries);
    }

    private function combineMediaOr(string $a, string $b): string
    {
        $queries = array_merge($this->splitTopLevel($a, ','), $this->splitTopLevel($b, ','));
        $deduped = [];
        foreach ($queries as $query) {
            $deduped[trim($query)] = trim($query);
        }

        return implode(', ', array_values($deduped));
    }

    private function andMediaQuery(string $a, string $b): string
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || strcasecmp($a, 'all') === 0) {
            return $b;
        }
        if ($b === '' || strcasecmp($b, 'all') === 0 || strcasecmp($a, $b) === 0) {
            return $a;
        }

        return $a . ' and ' . $b;
    }

    private function isNegatedMediaQuery(string $query): bool
    {
        return preg_match('/^\s*not\s+/i', $query) === 1;
    }

    private function combineSupportsAnd(?string $parent, ?string $child): ?string
    {
        if ($parent === null) {
            return $child;
        }
        if ($child === null) {
            return $parent;
        }

        return $this->supportsPrelude($parent) . ' and ' . $this->supportsPrelude($child);
    }

    private function combineSupportsOr(string $a, string $b): string
    {
        return $this->supportsPrelude($a) . ' or ' . $this->supportsPrelude($b);
    }

    private function wrapRules(string $css, ?string $layer, string $media, ?string $supports): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        if ($layer !== null) {
            $css = $layer === '' ? '@layer{' . $css . '}' : '@layer ' . $layer . '{' . $css . '}';
        }

        if (trim($media) !== '') {
            $css = '@media ' . trim($media) . '{' . $css . '}';
        }

        if ($supports !== null) {
            $css = '@supports ' . $this->supportsPrelude($supports) . '{' . $css . '}';
        }

        return $css;
    }

    /**
     * @param array{specifier:string,layer:?string,supports:?string,media:string,loc:array{line:int,column:int}} $import
     */
    private function externalImportStatement(string $url, array $import): string
    {
        $parts = ['@import "' . str_replace('"', '\\"', $url) . '"'];
        if ($import['layer'] !== null) {
            $parts[] = $import['layer'] === '' ? 'layer' : 'layer(' . $import['layer'] . ')';
        }
        if ($import['supports'] !== null) {
            $parts[] = 'supports(' . $this->supportsPrelude($import['supports']) . ')';
        }
        if (trim($import['media']) !== '') {
            $parts[] = trim($import['media']);
        }

        return implode(' ', $parts) . ';';
    }

    private function supportsPrelude(string $condition): string
    {
        $condition = trim($condition);
        if ($condition === '') {
            return '()';
        }

        if (
            ($condition[0] ?? '') === '('
            || preg_match('/^(not\b|[a-z-]+\()/i', $condition) === 1
            || preg_match('/\s(?:and|or)\s/i', $condition) === 1
        ) {
            return $condition;
        }

        return '(' . $condition . ')';
    }

    private function rewriteLayerStatement(string $raw, ?string $parentLayer): string
    {
        if ($parentLayer === null || $parentLayer === '') {
            return $raw;
        }

        $statement = rtrim(trim($raw), ';');
        $names = trim(substr($statement, strlen('@layer')));
        if ($names === '') {
            return $raw;
        }

        $prefixed = [];
        foreach ($this->splitTopLevel($names, ',') as $name) {
            $prefixed[] = $parentLayer . '.' . trim($name);
        }

        return '@layer ' . implode(', ', $prefixed) . ';';
    }

    private function cssModuleHashForFile(string $file): string
    {
        $hashes = $this->cssModuleOptions['hashes'] ?? null;
        if (is_array($hashes) && isset($hashes[$file])) {
            return $hashes[$file];
        }

        if (is_callable($hashes)) {
            return (string) $hashes($file);
        }

        $hash = rtrim(strtr(base64_encode(hash('sha1', $file, true)), '+/', '_-'), '=');
        $hash = substr($hash, 0, 6);

        return preg_match('/^[A-Za-z_]/', $hash) === 1 ? $hash : '_' . substr($hash, 0, 5);
    }

    private function cssModuleDashedNameForSource(int $sourceIndex, string $name): string
    {
        $file = $this->stylesheets[$sourceIndex]['file'];
        $local = str_starts_with($name, '--') ? substr($name, 2) : $name;
        $pattern = $this->cssModuleOptions['pattern'] ?? '[hash]_[local]';

        return '--' . strtr($pattern, [
            '[hash]' => $this->cssModuleHashForFile($file),
            '[local]' => $local,
        ]);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return ($absolute ? '/' : '') . implode('/', $parts);
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [''];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $parts[array_key_last($parts)] .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $parts[array_key_last($parts)] .= $value[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && ($value[$i + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $i + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $parts[array_key_last($parts)] .= substr($value, $i, $end - $i + 2);
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $delimiter && $parenDepth === 0 && $bracketDepth === 0) {
                $parts[] = '';
                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }

    private function findNextTopLevel(string $css, string $needle, int $start): ?int
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($css);

        for ($i = $start; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
            } elseif ($char === $needle && $parenDepth === 0 && $bracketDepth === 0) {
                return $i;
            }
        }

        return null;
    }

    private function findMatchingDelimiter(string $value, int $open, string $left, string $right): int
    {
        if (($value[$open] ?? '') !== $left) {
            throw new CssBundleException('parser-error', "Expected {$left}");
        }

        $quote = null;
        $depth = 0;
        $length = strlen($value);
        for ($i = $open; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && ($value[$i + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $i + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === $left) {
                $depth++;
                continue;
            }
            if ($char === $right) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new CssBundleException('parser-error', "CSS contains an unbalanced {$left}{$right} pair");
    }

    private function skipWhitespaceAndComments(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length) {
            if (ctype_space($value[$offset])) {
                $offset++;
                continue;
            }

            if ($value[$offset] === '/' && ($value[$offset + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $offset + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $offset = $end + 2;
                continue;
            }

            break;
        }

        return $offset;
    }

    private function startsAtKeyword(string $css, int $offset, string $keyword): bool
    {
        $length = strlen($keyword);
        if (strncasecmp(substr($css, $offset, $length), $keyword, $length) !== 0) {
            return false;
        }

        $next = $css[$offset + $length] ?? '';

        return $next === '' || !$this->isIdentifierChar($next);
    }

    private function startsFunction(string $value, int $offset, string $name): bool
    {
        $length = strlen($name);
        if (strncasecmp(substr($value, $offset, $length), $name, $length) !== 0) {
            return false;
        }

        $previous = $offset > 0 ? $value[$offset - 1] : '';
        $next = $value[$offset + $length] ?? '';

        return ($previous === '' || !$this->isIdentifierChar($previous)) && $next === '(';
    }

    private function readIdentifier(string $value, int $offset): string
    {
        $start = $offset;
        $length = strlen($value);
        while ($offset < $length && $this->isIdentifierChar($value[$offset])) {
            $offset++;
        }

        return substr($value, $start, $offset - $start);
    }

    private function readQuotedTokenEnd(string $value, int $offset): int
    {
        $quote = $value[$offset];
        $length = strlen($value);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($value[$i] === '\\') {
                $i++;
                continue;
            }
            if ($value[$i] === $quote) {
                return $i + 1;
            }
        }

        throw new CssBundleException('parser-error', 'CSS contains an unbalanced string');
    }

    private function cssStringTokenValue(string $token): string
    {
        $token = trim($token);
        $quote = $token[0] ?? '';
        if (($quote !== '"' && $quote !== "'") || substr($token, -1) !== $quote) {
            return $token;
        }

        $value = substr($token, 1, -1);

        return preg_replace('/\\\\(["\'\\\\])/', '$1', $value) ?? $value;
    }

    private function isIdentifierChar(string $char): bool
    {
        return preg_match('/[-_a-zA-Z0-9]/', $char) === 1;
    }

    /**
     * @return array{line:int,column:int}
     */
    private function sourceLocation(string $source, int $offset): array
    {
        $prefix = substr($source, 0, $offset);
        $line = substr_count($prefix, "\n") + 1;
        $lastNewline = strrpos($prefix, "\n");
        $column = $lastNewline === false ? $offset + 1 : $offset - $lastNewline;

        return ['line' => $line, 'column' => $column];
    }
}
