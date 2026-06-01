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

    private bool $preserveResolverPaths = false;

    /** @var array<string, int> */
    private array $sourceIndexes = [];

    private ?SourceMap $sourceMap = null;

    private string $sourceMapProjectRoot = '/';

    private bool $cssModules = false;

    /** @var array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} */
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
     *     cssModuleFirstComposesLoc:?array{line:int,column:int},
     *     layer:?string,
     *     supports:?string,
     *     media:string,
     *     loc:array{line:int,column:int},
     *     parentSourceIndex:int,
     *     parentDepIndex:int
     * }>
     */
    private array $stylesheets = [];

    /** @var array<int, true> */
    private array $cycleConsumedLayers = [];

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
     * @return array{code:string, sourceMap:SourceMap}
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     */
    public function bundleWithSourceMap(string $entry, array $files, ?callable $resolver = null, string $projectRoot = '/'): array
    {
        $result = $this->bundleInternal($entry, $files, $resolver, false, [], null, false, $projectRoot);

        return [
            'code' => $result['code'],
            'sourceMap' => $result['sourceMap'],
        ];
    }

    /**
     * @return array{code:string, sourceMap:SourceMap}
     *
     * @param callable(string): string $reader
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     */
    public function bundleWithReaderSourceMap(
        string $entry,
        callable $reader,
        ?callable $resolver = null,
        string $projectRoot = '/'
    ): array {
        $result = $this->bundleInternal($entry, [], $resolver, false, [], $reader, false, $projectRoot);

        return [
            'code' => $result['code'],
            'sourceMap' => $result['sourceMap'],
        ];
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $options
     */
    public function bundleCssModules(string $entry, array $files, ?callable $resolver = null, array $options = []): array
    {
        return $this->bundleInternal($entry, $files, $resolver, true, $options);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>,
     *   sourceMap:SourceMap
     * }
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $options
     */
    public function bundleCssModulesWithSourceMap(
        string $entry,
        array $files,
        ?callable $resolver = null,
        array $options = [],
        string $projectRoot = '/'
    ): array {
        return $this->bundleInternal($entry, $files, $resolver, true, $options, null, false, $projectRoot);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param callable(string): string $reader
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $options
     */
    public function bundleCssModulesWithReader(string $entry, callable $reader, ?callable $resolver = null, array $options = []): array
    {
        return $this->bundleInternal($entry, [], $resolver, true, $options, $reader);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>,
     *   sourceMap:SourceMap
     * }
     *
     * @param callable(string): string $reader
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $options
     */
    public function bundleCssModulesWithReaderSourceMap(
        string $entry,
        callable $reader,
        ?callable $resolver = null,
        array $options = [],
        string $projectRoot = '/'
    ): array {
        return $this->bundleInternal($entry, [], $resolver, true, $options, $reader, false, $projectRoot);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $options
     */
    public function bundleCssModulesFile(string $entry, ?callable $resolver = null, array $options = []): array
    {
        return $this->bundleInternal($entry, [], $resolver, true, $options, null, true);
    }

    /**
     * @return array{
     *   code:string,
     *   exports:array<string, array{name:string, composes:list<array{type:string, name:string, specifier?:string}>, isReferenced:bool}>
     * }
     *
     * @param array<string, string> $files
     * @param (callable(string, string): (string|array{external?:string,file?:string}))|null $resolver
     * @param array{hashes?:array<string,string>|callable(string):string,pattern?:string,minify?:bool,dashedIdents?:bool,dashed_idents?:bool,animation?:bool,grid?:bool,container?:bool,customIdents?:bool,custom_idents?:bool,pure?:bool,unusedSymbols?:list<string>,unused_symbols?:list<string>,pseudoClasses?:array<string,string>,pseudo_classes?:array<string,string>,projectRoot?:string,project_root?:string} $cssModuleOptions
     * @param (callable(string): string)|null $reader
     */
    private function bundleInternal(
        string $entry,
        array $files,
        ?callable $resolver,
        bool $cssModules,
        array $cssModuleOptions = [],
        ?callable $reader = null,
        bool $filesystemReads = false,
        ?string $sourceMapProjectRoot = null
    ): array
    {
        $this->files = [];
        foreach ($files as $path => $css) {
            $this->files[$this->normalizePath((string) $path)] = $css;
        }

        $this->resolver = $resolver;
        $this->reader = $reader;
        $this->filesystemReads = $filesystemReads;
        $this->preserveResolverPaths = $reader !== null || $filesystemReads;
        $this->sourceIndexes = [];
        $this->sourceMap = $sourceMapProjectRoot === null ? null : new SourceMap($sourceMapProjectRoot);
        $this->sourceMapProjectRoot = $sourceMapProjectRoot ?? '/';
        $this->stylesheets = [];
        $this->cycleConsumedLayers = [];
        $this->cssModules = $cssModules;
        $this->cssModuleOptions = $cssModuleOptions;

        $entry = $this->preserveResolverPaths ? $entry : $this->normalizePath($entry);
        $this->loadFile(
            $entry,
            [
                'layer' => null,
                'supports' => null,
                'media' => '',
                'loc' => ['line' => 0, 'column' => 0],
                'file' => $entry,
            ],
            !$this->preserveResolverPaths
        );
        $this->assertNoConditionalCssModuleComposes();
        $this->order();

        $raw = $this->licenseCommentPrefix() . $this->inline(0, []);
        $raw = (new CustomMediaTransformer())->transform($raw);

        $code = (new CssMinifier())->minify($raw, false, true);
        $exports = $cssModules ? $this->resolvedCssModuleExports(0) : [];

        return [
            'code' => $code,
            'exports' => $exports,
            ...($this->sourceMap === null ? [] : ['sourceMap' => $this->sourceMap]),
        ];
    }

    /**
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     */
    private function loadFile(string $file, array $rule, bool $normalizeFile = true): int
    {
        if ($normalizeFile) {
            $file = $this->normalizePath($file);
        }
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
            'cssModuleHasComposes' => false,
            'cssModuleFirstComposesLoc' => null,
            'layer' => $rule['layer'],
            'supports' => $rule['supports'],
            'media' => $rule['media'],
            'loc' => $rule['loc'],
            'parentSourceIndex' => 0,
            'parentDepIndex' => 0,
        ];

        $source = $this->readFile($file, $rule);
        if ($this->sourceMap !== null) {
            $this->addBundleSource($file, $source);
        }

        $cssModuleResult = null;
        $cssModuleDependencyLocations = [];
        $cssModuleHasComposes = false;
        $cssModuleFirstComposesLoc = null;
        $originalImportLocations = [];
        if ($this->cssModules) {
            $originalImportLocations = $this->importLocationsForItems($this->topLevelItems($source, $file));
            $cssModuleDependencyLocations = $this->cssModuleDependencyLocations($source);
            $cssModuleFirstComposesLoc = $this->firstCssModuleComposesLocation($source);
            $cssModuleHasComposes = $cssModuleFirstComposesLoc !== null;
            try {
                $cssModuleResult = (new CssModulesTransformer())->transform($source, $this->cssModuleTransformOptions($file));
            } catch (\InvalidArgumentException $exception) {
                if ($exception->getMessage() === 'The `composes` property cannot be used within nested rules') {
                    $this->throwFirstCssModuleDependencyDiagnostic($file, $rule, $cssModuleDependencyLocations);
                }

                throw $exception;
            }
            $source = $cssModuleResult['code'];
        }

        $cssModuleDependencies = [];
        $cssModuleDependencySources = [];
        $cssModuleExports = $cssModuleResult['exports'] ?? [];
        $cssModuleReferences = $cssModuleResult['references'] ?? [];

        $items = $this->topLevelItems($source, $file);
        if ($originalImportLocations !== []) {
            $items = $this->restoreOriginalImportLocations($items, $originalImportLocations);
        }
        $splitItems = $this->splitTopLevelBundleItems($items);
        $licenseComments = $splitItems['licenseComments'];
        $contentItems = $splitItems['contentItems'];
        $dependencies = $this->importDependenciesForItems($contentItems, $rule, $file);

        foreach ($this->cssModuleDependencySpecifiers($cssModuleExports, $cssModuleReferences) as $specifier) {
            $dependencyLocations = $cssModuleDependencyLocations[$specifier] ?? [
                'read' => ['line' => 1, 'column' => 1],
                'resolve' => ['line' => 1, 'column' => 1],
            ];
            $dependencyLoc = $dependencyLocations['read'];
            $resolveLoc = $dependencyLocations['resolve'];
            $resolved = $this->resolveImport($specifier, $file, $resolveLoc);
            if (isset($resolved['external'])) {
                throw new CssBundleException(
                    'referenced-external-module-with-css-module-from',
                    'Referenced external module with CSS module "from" clause',
                    $file,
                    $resolveLoc['line'],
                    $resolveLoc['column'],
                );
            }

            $dependencyRule = [
                'layer' => $rule['layer'],
                'supports' => $rule['supports'],
                'media' => $rule['media'],
                'loc' => $dependencyLoc,
                'file' => $file,
            ];
            $depSourceIndex = $this->loadFile(
                $resolved['file'],
                $dependencyRule,
                !$this->shouldPreserveResolvedPath($resolved)
            );
            $cssModuleDependencies[] = ['sourceIndex' => $depSourceIndex];
            $cssModuleDependencySources[$specifier] = $depSourceIndex;
        }

        $sourceChangedByReferences = false;
        foreach ($cssModuleReferences as $placeholder => $reference) {
            if (($reference['type'] ?? '') !== 'dependency') {
                continue;
            }

            $specifier = (string) ($reference['specifier'] ?? '');
            $depSourceIndex = $cssModuleDependencySources[$specifier] ?? null;
            if ($depSourceIndex === null) {
                continue;
            }

            $replaced = str_replace(
                (string) $placeholder,
                $this->cssModuleDashedNameForSource($depSourceIndex, (string) ($reference['name'] ?? '')),
                $source
            );

            if ($replaced !== $source) {
                $sourceChangedByReferences = true;
                $source = $replaced;
            }
        }

        if ($sourceChangedByReferences) {
            $items = $this->topLevelItems($source, $file);
            if ($originalImportLocations !== []) {
                $items = $this->restoreOriginalImportLocations($items, $originalImportLocations);
            }
            $splitItems = $this->splitTopLevelBundleItems($items);
            $licenseComments = $splitItems['licenseComments'];
            $contentItems = $splitItems['contentItems'];
        }

        $this->stylesheets[$sourceIndex]['items'] = $contentItems;
        $this->stylesheets[$sourceIndex]['licenseComments'] = $licenseComments;
        $this->stylesheets[$sourceIndex]['dependencies'] = $dependencies;
        $this->stylesheets[$sourceIndex]['cssModuleDependencies'] = $cssModuleDependencies;
        $this->stylesheets[$sourceIndex]['cssModuleDependencySources'] = $cssModuleDependencySources;
        $this->stylesheets[$sourceIndex]['cssModuleExports'] = $cssModuleExports;
        $this->stylesheets[$sourceIndex]['cssModuleReferences'] = $cssModuleReferences;
        $this->stylesheets[$sourceIndex]['cssModuleHasComposes'] = $cssModuleHasComposes;
        $this->stylesheets[$sourceIndex]['cssModuleFirstComposesLoc'] = $cssModuleFirstComposesLoc;

        return $sourceIndex;
    }

    private function assertNoConditionalCssModuleComposes(): void
    {
        if (!$this->cssModules) {
            return;
        }

        foreach ($this->stylesheets as $stylesheet) {
            if (!($stylesheet['cssModuleHasComposes'] ?? false)) {
                continue;
            }

            if ($this->isConditionalCssModuleImportContext($stylesheet)) {
                $loc = $stylesheet['cssModuleFirstComposesLoc'] ?? $stylesheet['loc'] ?? ['line' => 1, 'column' => 1];
                throw new CssBundleException(
                    'parser-error',
                    'The `composes` property cannot be used within nested rules',
                    (string) $stylesheet['file'],
                    (int) $loc['line'],
                    (int) $loc['column'],
                );
            }
        }
    }

    /**
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     * @param array<string, array{read:array{line:int,column:int},resolve:array{line:int,column:int}}> $dependencyLocations
     */
    private function throwFirstCssModuleDependencyDiagnostic(string $file, array $rule, array $dependencyLocations): void
    {
        foreach ($dependencyLocations as $specifier => $locations) {
            $dependencyLoc = $locations['read'];
            $resolveLoc = $locations['resolve'];
            $resolved = $this->resolveImport($specifier, $file, $resolveLoc);
            if (isset($resolved['external'])) {
                throw new CssBundleException(
                    'referenced-external-module-with-css-module-from',
                    'Referenced external module with CSS module "from" clause',
                    $file,
                    $resolveLoc['line'],
                    $resolveLoc['column'],
                );
            }

            $this->readFile(
                $resolved['file'],
                [
                    'layer' => $rule['layer'],
                    'supports' => $rule['supports'],
                    'media' => $rule['media'],
                    'loc' => $dependencyLoc,
                    'file' => $file,
                ]
            );
        }
    }

    /**
     * @param array{layer:?string,supports:?string,media:string} $rule
     */
    private function isConditionalCssModuleImportContext(array $rule): bool
    {
        return $rule['layer'] !== null
            || $rule['supports'] !== null
            || trim($rule['media']) !== '';
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

    private function addBundleSource(string $file, string $source): void
    {
        if ($this->sourceMap === null) {
            return;
        }

        $sourceMapUrl = $this->sourceMapUrl($source);
        if ($sourceMapUrl !== null && str_starts_with(strtolower($sourceMapUrl), 'data:')) {
            try {
                $this->sourceMap->addSourceMap(SourceMap::fromDataUrl($sourceMapUrl, $this->sourceMapProjectRoot));

                return;
            } catch (\Throwable) {
                // Fall back to generated CSS source collection when the inline map is malformed.
            }
        }

        $sourceMapIndex = $this->sourceMap->addSource($file);
        $this->sourceMap->setSourceContent($sourceMapIndex, $source);
    }

    private function sourceMapUrl(string $source): ?string
    {
        $lastUrl = null;
        $length = strlen($source);
        $quote = null;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];

            if ($quote !== null) {
                if ($char === '\\') {
                    $offset++;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char !== '/' || ($source[$offset + 1] ?? '') !== '*') {
                continue;
            }

            $end = strpos($source, '*/', $offset + 2);
            if ($end === false) {
                break;
            }

            $url = $this->sourceMapUrlFromComment(substr($source, $offset + 2, $end - $offset - 2));
            if ($url !== null) {
                $lastUrl = $url;
            }

            $offset = $end + 1;
        }

        return $lastUrl;
    }

    private function sourceMapUrlFromComment(string $comment): ?string
    {
        $comment = ltrim($comment);
        $marker = $comment[0] ?? '';
        if ($marker !== '#' && $marker !== '@') {
            return null;
        }

        $rest = ltrim(substr($comment, 1));
        $name = 'sourceMappingURL';
        if (strncasecmp($rest, $name, strlen($name)) !== 0) {
            return null;
        }

        $rest = ltrim(substr($rest, strlen($name)));
        if (($rest[0] ?? '') !== '=') {
            return null;
        }

        $rest = ltrim(substr($rest, 1));
        if ($rest === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $rest);
        if ($parts === false || $parts === [] || $parts[0] === '') {
            return null;
        }

        return $parts[0];
    }

    private function readerTypeName(mixed $value): string
    {
        return match (get_debug_type($value)) {
            'int', 'float' => 'Number',
            'bool' => 'Boolean',
            'null' => 'Null',
            'array' => 'Object',
            default => is_object($value) ? 'Object' : get_debug_type($value),
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
            if ($entry['supports'] !== null) {
                $entry['supports'] = $this->combineSupportsOr($entry['supports'], $rule['supports']);
            }
        } else {
            $entry['supports'] = null;
        }

        if ($rule['layer'] !== null) {
            if (
                $entry['layer'] !== null
                && (
                    $rule['layer'] === ''
                    || $entry['layer'] === ''
                    || !$this->layerNamesEquivalent($entry['layer'], $rule['layer'])
                )
            ) {
                throw new CssBundleException(
                    'unsupported-layer-combination',
                    'Unsupported layer combination in @import',
                    $rule['file'],
                    $rule['loc']['line'],
                    $rule['loc']['column'],
                );
            }

            if ($entry['layer'] === null) {
                $entry['layer'] = $rule['layer'];
            }
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
            return $this->inlineCyclePlaceholder($sourceIndex);
        }

        $stack[$sourceIndex] = true;
        $stylesheet = $this->stylesheets[$sourceIndex];
        $output = '';
        $body = '';
        $importIndex = 0;
        $depIndex = 0;
        $hasBundledImport = false;
        $bodyStarted = false;

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
                $statement = $this->rewriteLayerStatement((string) $item['raw'], $stylesheet['layer']);
                if ($bodyStarted) {
                    $body .= $statement;
                } else {
                    $output .= $statement;
                }
                continue;
            }

            $raw = (string) $item['raw'];
            $body .= $raw;
            if (!$this->startsAtKeyword($raw, 0, '@charset')) {
                $bodyStarted = true;
            }
        }

        $layer = isset($this->cycleConsumedLayers[$sourceIndex]) ? null : $stylesheet['layer'];
        $output .= $this->wrapRules($body, $layer, $stylesheet['media'], $stylesheet['supports']);

        return $output;
    }

    private function inlineCyclePlaceholder(int $sourceIndex): string
    {
        $stylesheet = $this->stylesheets[$sourceIndex] ?? null;
        if ($stylesheet === null || isset($this->cycleConsumedLayers[$sourceIndex])) {
            return '';
        }

        $layer = $stylesheet['layer'];
        if ($layer === null) {
            return '';
        }

        $this->cycleConsumedLayers[$sourceIndex] = true;

        return $layer === '' ? '@layer{}' : '@layer ' . $layer . ';';
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
            $rootKey = $sourceIndex . ':' . $name;
            $exports[$name]['composes'] = $this->resolveCssModuleReferences($sourceIndex, $export['composes'], [
                $rootKey => true,
            ]);
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

            $resolved[] = [
                'type' => 'local',
                'name' => $depExport['name'],
            ];
            $nextStack = $stack;
            $nextStack[$key] = true;
            foreach ($this->resolveCssModuleReferences($depSourceIndex, $depExport['composes'], $nextStack) as $depReference) {
                $resolved[] = $depReference;
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
     * @param list<array<string, mixed>> $items
     * @return array{licenseComments:list<string>,contentItems:list<array<string, mixed>>}
     */
    private function splitTopLevelBundleItems(array $items): array
    {
        $licenseComments = [];
        $contentItems = [];
        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'license-comment') {
                $licenseComments[] = (string) $item['raw'];
                continue;
            }

            $contentItems[] = $item;
        }

        return [
            'licenseComments' => $licenseComments,
            'contentItems' => $contentItems,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{line:int,column:int}>
     */
    private function importLocationsForItems(array $items): array
    {
        $locations = [];
        foreach ($items as $item) {
            if (($item['type'] ?? null) !== 'import') {
                continue;
            }

            /** @var array{line:int,column:int} $loc */
            $loc = $item['loc'];
            $locations[] = $loc;
        }

        return $locations;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array{line:int,column:int}> $locations
     * @return list<array<string, mixed>>
     */
    private function restoreOriginalImportLocations(array $items, array $locations): array
    {
        $index = 0;
        foreach ($items as &$item) {
            if (($item['type'] ?? null) !== 'import' || !isset($locations[$index])) {
                continue;
            }

            $loc = $locations[$index];
            $item['loc'] = $loc;
            if (isset($item['import']) && is_array($item['import'])) {
                $item['import']['loc'] = $loc;
            }
            $index++;
        }
        unset($item);

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $contentItems
     * @param array{layer:?string,supports:?string,media:string,loc:array{line:int,column:int},file:string} $rule
     * @return list<array<string, mixed>>
     */
    private function importDependenciesForItems(array $contentItems, array $rule, string $file): array
    {
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
                'sourceIndex' => $this->loadFile(
                    $resolved['file'],
                    $dependencyRule,
                    !$this->shouldPreserveResolvedPath($resolved)
                ),
            ];
        }

        return $dependencies;
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
        $namespacesAllowed = true;
        $seenImport = false;

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
                        $loc = $this->sourceLocation($css, $this->atKeywordEndOffset($css, $cursor, '@import') ?? $cursor + strlen('@import'));
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
                        'import' => $this->parseImportStatement($raw, $this->sourceLocation($css, $cursor), $file),
                    ];
                    $seenImport = true;
                } elseif ($this->startsAtKeyword($css, $cursor, '@layer')) {
                    if ($seenImport) {
                        $importsAllowed = false;
                    }

                    $items[] = [
                        'type' => 'layer-statement',
                        'raw' => $raw,
                    ];
                } elseif ($this->startsAtKeyword($css, $cursor, '@namespace')) {
                    if (!$namespacesAllowed) {
                        $loc = $this->sourceLocation($css, $this->atKeywordEndOffset($css, $cursor, '@namespace') ?? $cursor + strlen('@namespace'));
                        throw new CssBundleException(
                            'parser-error',
                            '@namespaces rules must precede all rules aside from @charset, @import, and @layer statements',
                            $file,
                            $loc['line'],
                            $loc['column'],
                        );
                    }

                    $importsAllowed = false;
                    $items[] = [
                        'type' => 'other',
                        'raw' => $raw,
                    ];
                } elseif ($this->startsAtKeyword($css, $cursor, '@charset')) {
                    $this->validateCharsetStatement($raw, $file, $this->sourceLocation($css, $cursor));
                } else {
                    if (!$this->startsAtKeyword($css, $cursor, '@charset')) {
                        $importsAllowed = false;
                        $namespacesAllowed = false;
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
                    if ($this->startsAtKeyword($css, $cursor, '@import')) {
                        if (!$importsAllowed) {
                            $loc = $this->sourceLocation($css, $this->atKeywordEndOffset($css, $cursor, '@import') ?? $cursor + strlen('@import'));
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
                            'raw' => substr($css, $cursor),
                            'loc' => $this->sourceLocation($css, $cursor),
                            'import' => $this->parseImportStatement(substr($css, $cursor), $this->sourceLocation($css, $cursor), $file),
                        ];
                        break;
                    }

                    if ($this->startsAtKeyword($css, $cursor, '@charset')) {
                        $this->validateCharsetStatement(substr($css, $cursor), $file, $this->sourceLocation($css, $cursor));
                        break;
                    }

                    if ($this->startsAtKeyword($css, $cursor, '@namespace')) {
                        if (!$namespacesAllowed) {
                            $loc = $this->sourceLocation($css, $this->atKeywordEndOffset($css, $cursor, '@namespace') ?? $cursor + strlen('@namespace'));
                            throw new CssBundleException(
                                'parser-error',
                                '@namespaces rules must precede all rules aside from @charset, @import, and @layer statements',
                                $file,
                                $loc['line'],
                                $loc['column'],
                            );
                        }

                        $items[] = [
                            'type' => 'other',
                            'raw' => substr($css, $cursor),
                        ];
                        break;
                    }

                    $loc = $this->sourceLocation($css, strlen($css));
                    throw new CssBundleException(
                        'parser-error',
                        'Unexpected end of input',
                        $file,
                        $loc['line'],
                        $loc['column'],
                    );
                }
                break;
            }

            if ($this->startsAtKeyword($css, $cursor, '@import')) {
                if (!$importsAllowed) {
                    $loc = $this->sourceLocation($css, $this->atKeywordEndOffset($css, $cursor, '@import') ?? $cursor + strlen('@import'));
                    throw new CssBundleException(
                        'parser-error',
                        '@import rules must precede all rules aside from @charset and @layer statements',
                        $file,
                        $loc['line'],
                        $loc['column'],
                    );
                }

                $loc = $this->sourceLocation($css, $cursor);
                $this->parseImportStatement(substr($css, $cursor, $blockOpen - $cursor), $loc, $file);
                $blockLoc = $this->sourceLocation($css, $blockOpen + 1);

                throw new CssBundleException(
                    'parser-error',
                    'Unexpected token CurlyBracketBlock',
                    $file,
                    $blockLoc['line'],
                    $blockLoc['column'],
                );
            }

            $close = $this->findMatchingDelimiter($css, $blockOpen, '{', '}');
            $this->assertNoNestedImportRules($css, $blockOpen + 1, $close, $file);
            $importsAllowed = false;
            $namespacesAllowed = false;
            $items[] = [
                'type' => 'other',
                'raw' => substr($css, $cursor, $close - $cursor + 1),
            ];
            $cursor = $close + 1;
        }

        return $items;
    }

    private function assertNoNestedImportRules(string $css, int $start, int $end, string $file): void
    {
        $quote = null;
        $statementStart = true;
        for ($i = $start; $i < $end; $i++) {
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
                $commentEnd = strpos($css, '*/', $i + 2);
                if ($commentEnd === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $i = $commentEnd + 1;
                continue;
            }

            if (ctype_space($char)) {
                continue;
            }

            if ($statementStart && $this->startsAtKeyword($css, $i, '@import')) {
                $loc = $this->sourceLocation($css, $i);
                throw new CssBundleException(
                    'parser-error',
                    'Unknown at rule: @import',
                    $file,
                    $loc['line'],
                    $loc['column'],
                );
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $statementStart = false;
                continue;
            }

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($css, $i);
                $statementStart = false;
                continue;
            }

            $statementStart = $char === ';' || $char === '{' || $char === '}';
        }
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function validateCharsetStatement(string $statement, string $file, array $loc): void
    {
        $statement = rtrim(trim($statement), ';');
        $rest = trim(substr($statement, $this->atKeywordEndOffset($statement, 0, '@charset') ?? strlen('@charset')));
        $offset = $this->skipWhitespaceAndComments($rest, 0);
        $quote = $rest[$offset] ?? '';
        if ($quote !== '"' && $quote !== "'") {
            throw new CssBundleException('parser-error', 'Invalid @charset rule', $file, $loc['line'], $loc['column']);
        }

        try {
            $end = $this->readQuotedTokenEnd($rest, $offset);
        } catch (CssBundleException) {
            throw new CssBundleException('parser-error', 'Invalid @charset rule', $file, $loc['line'], $loc['column']);
        }

        $after = $this->skipWhitespaceAndComments($rest, $end);
        if ($after < strlen($rest)) {
            throw new CssBundleException('parser-error', 'Invalid @charset rule', $file, $loc['line'], $loc['column']);
        }
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
    private function parseImportStatement(string $statement, array $loc, string $file): array
    {
        $statement = rtrim(trim($statement), ';');
        $restStart = $this->atKeywordEndOffset($statement, 0, '@import') ?? strlen('@import');
        $restEnd = strlen($statement);
        while ($restStart < $restEnd && ctype_space($statement[$restStart])) {
            $restStart++;
        }
        while ($restEnd > $restStart && ctype_space($statement[$restEnd - 1])) {
            $restEnd--;
        }
        $rest = substr($statement, $restStart, $restEnd - $restStart);
        $offset = $this->skipWhitespaceAndComments($rest, 0);
        $specifier = null;

        $urlOpen = $this->cssFunctionOpenOffset($rest, $offset, 'url');
        if ($urlOpen !== null) {
            $open = $urlOpen;
            try {
                $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
            } catch (CssBundleException) {
                $this->throwInvalidImportSource($file, $loc);
            }
            $specifier = $this->parseImportUrlFunctionSource(substr($rest, $open + 1, $close - $open - 1), $file, $loc);
            $offset = $close + 1;
        } elseif (($rest[$offset] ?? '') === '"' || ($rest[$offset] ?? '') === "'") {
            $end = $this->readImportSourceStringEnd($rest, $offset, $file, $loc);
            $specifier = $this->cssStringTokenValue(substr($rest, $offset, $end - $offset));
            $offset = $end;
        }

        if ($specifier === null) {
            throw new CssBundleException('parser-error', 'Invalid @import source', $file, $loc['line'], $loc['column']);
        }

        $layer = null;
        $supports = null;
        $media = '';

        $offset = $this->skipWhitespaceAndComments($rest, $offset);
        $layerOpen = $this->cssFunctionOpenOffset($rest, $offset, 'layer');
        if ($layerOpen !== null) {
            $open = $layerOpen;
            try {
                $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
            } catch (CssBundleException $exception) {
                throw new CssBundleException('parser-error', $exception->getMessage(), $file, $loc['line'], $loc['column']);
            }
            $layer = trim(substr($rest, $open + 1, $close - $open - 1));
            $this->validateImportLayerName($layer, $file, $loc);
            $offset = $close + 1;
            $offset = $this->skipWhitespaceAndComments($rest, $offset);
        } else {
            $identifier = $this->readCssIdentifierToken($rest, $offset);
            if ($identifier !== null && strcasecmp($identifier['name'], 'layer') === 0) {
                $layer = '';
                $offset = $identifier['end'];
                $offset = $this->skipWhitespaceAndComments($rest, $offset);
            }
        }

        $supportsOpen = $this->cssFunctionOpenOffset($rest, $offset, 'supports');
        if ($supportsOpen !== null) {
            $open = $supportsOpen;
            try {
                $close = $this->findMatchingDelimiter($rest, $open, '(', ')');
            } catch (CssBundleException $exception) {
                throw new CssBundleException('parser-error', $exception->getMessage(), $file, $loc['line'], $loc['column']);
            }
            $supports = $this->normalizeSupportsIdentifierEscapes(trim(substr($rest, $open + 1, $close - $open - 1)));
            $this->validateImportSupportsCondition($supports, $file, $loc);
            $offset = $close + 1;
            $offset = $this->skipWhitespaceAndComments($rest, $offset);
        }

        if ($offset < strlen($rest)) {
            $mediaTail = substr($rest, $offset);
            $this->assertNoImportMediaTopLevelFunctions($mediaTail, $statement, $restStart + $offset, $file, $loc);
            $media = trim($mediaTail);
            $this->validateImportMediaQueryList($media, $file, $loc);
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
     */
    private function parseImportUrlFunctionSource(string $source, string $file, array $loc): string
    {
        $offset = $this->skipWhitespaceAndComments($source, 0);
        if (($source[$offset] ?? '') === '"' || ($source[$offset] ?? '') === "'") {
            $end = $this->readImportSourceStringEnd($source, $offset, $file, $loc);
            $after = $this->skipWhitespaceAndComments($source, $end);
            if ($after < strlen($source)) {
                $this->throwInvalidImportSource($file, $loc);
            }

            return $this->cssStringTokenValue(substr($source, $offset, $end - $offset));
        }

        $specifier = $this->trimWhitespaceAndComments($source);
        if ($this->containsUnescapedCommentStart($specifier)) {
            $this->throwInvalidImportSource($file, $loc);
        }
        $this->validateUnquotedImportUrlSource($specifier, $file, $loc);

        return $this->decodeCssEscapes($specifier);
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function validateImportMediaQueryList(string $media, string $file, array $loc): void
    {
        if (trim($media) === '') {
            return;
        }

        try {
            (new MediaQueryParser())->minifyList($media, true);
        } catch (\InvalidArgumentException $exception) {
            throw new CssBundleException('parser-error', $exception->getMessage(), $file, $loc['line'], $loc['column']);
        }
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function assertNoImportMediaTopLevelFunctions(
        string $mediaTail,
        string $statement,
        int $tailOffsetInStatement,
        string $file,
        array $loc
    ): void {
        $function = $this->firstImportMediaTopLevelFunction($mediaTail);
        if ($function === null) {
            return;
        }

        $diagnostic = $this->sourceLocationRelativeTo($statement, $tailOffsetInStatement + $function['offset'], $loc);
        throw new CssBundleException(
            'parser-error',
            'Unexpected token Function("' . $function['name'] . '")',
            $file,
            $diagnostic['line'],
            $diagnostic['column']
        );
    }

    /**
     * @return array{name:string,offset:int}|null
     */
    private function firstImportMediaTopLevelFunction(string $mediaTail): ?array
    {
        $length = strlen($mediaTail);
        $offset = 0;
        while ($offset < $length) {
            $tokenStart = $offset;
            while ($offset < $length) {
                while ($offset < $length && ctype_space($mediaTail[$offset])) {
                    $offset++;
                }
                if ($offset >= $length) {
                    return null;
                }
                if ($mediaTail[$offset] === '/' && ($mediaTail[$offset + 1] ?? '') === '*') {
                    $end = strpos($mediaTail, '*/', $offset + 2);
                    if ($end === false) {
                        return null;
                    }
                    $offset = $end + 2;
                    continue;
                }

                break;
            }

            if ($offset >= $length) {
                return null;
            }

            $char = $mediaTail[$offset];
            if ($char === '"' || $char === "'") {
                try {
                    $offset = $this->readQuotedTokenEnd($mediaTail, $offset);
                } catch (CssBundleException) {
                    return null;
                }
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $right = $char === '(' ? ')' : ($char === '[' ? ']' : '}');
                try {
                    $offset = $this->findMatchingDelimiter($mediaTail, $offset, $char, $right) + 1;
                } catch (CssBundleException) {
                    return null;
                }
                continue;
            }

            $identifier = $this->readCssIdentifierToken($mediaTail, $offset);
            if ($identifier !== null) {
                if (($mediaTail[$identifier['end']] ?? '') === '(') {
                    return [
                        'name' => $identifier['name'],
                        'offset' => $tokenStart,
                    ];
                }

                $offset = $identifier['end'];
                continue;
            }

            $offset++;
        }

        return null;
    }

    /**
     * @param array{line:int,column:int} $baseLoc
     * @return array{line:int,column:int}
     */
    private function sourceLocationRelativeTo(string $source, int $offset, array $baseLoc): array
    {
        $relative = $this->sourceLocation($source, $offset);
        $line = $baseLoc['line'] + $relative['line'] - 1;
        $column = $relative['line'] === 1
            ? $baseLoc['column'] + $relative['column'] - 1
            : $relative['column'];

        return ['line' => $line, 'column' => $column];
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function validateImportSupportsCondition(string $condition, string $file, array $loc): void
    {
        try {
            $valid = $this->isValidImportSupportsCondition($condition);
        } catch (CssBundleException $exception) {
            throw new CssBundleException('parser-error', $exception->getMessage(), $file, $loc['line'], $loc['column']);
        }

        if (!$valid) {
            throw new CssBundleException(
                'parser-error',
                'Invalid @import supports condition',
                $file,
                $loc['line'],
                $loc['column'],
            );
        }
    }

    private function isValidImportSupportsCondition(string $condition): bool
    {
        $condition = $this->trimWhitespaceAndComments($condition);
        if ($condition === '') {
            return false;
        }

        $logical = $this->splitSupportsConditionByLogicalOperator($condition);
        if ($logical !== null) {
            if (count(array_unique($logical['operators'])) > 1) {
                return false;
            }

            foreach ($logical['parts'] as $part) {
                if (!$this->isValidSupportsInParens($part)) {
                    return false;
                }
            }

            return true;
        }

        $identifier = $this->readCssIdentifierToken($condition, 0);
        if ($identifier !== null && strcasecmp($identifier['name'], 'not') === 0) {
            $rest = $this->trimWhitespaceAndComments(substr($condition, $identifier['end']));

            return $this->isValidSupportsInParens($rest);
        }

        return $this->isValidSupportsInParens($condition)
            || $this->isSupportsDeclaration($condition);
    }

    /**
     * @return array{parts:list<string>,operators:list<string>}|null
     */
    private function splitSupportsConditionByLogicalOperator(string $condition): ?array
    {
        $parts = [];
        $operators = [];
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $start = 0;
        $length = strlen($condition);

        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
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

            if ($char === '/' && ($condition[$i + 1] ?? '') === '*') {
                $end = strpos($condition, '*/', $i + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '\\') {
                if ($parenDepth === 0 && $bracketDepth === 0) {
                    $identifier = $this->readCssIdentifierToken($condition, $i);
                    if ($identifier !== null && $this->isSupportsLogicalOperator($identifier['name'])) {
                        $parts[] = $this->trimWhitespaceAndComments(substr($condition, $start, $i - $start));
                        $operators[] = strtolower($identifier['name']);
                        $start = $identifier['end'];
                        $i = $identifier['end'] - 1;
                        continue;
                    }
                }
                $i = $this->cssEscapeEndOffset($condition, $i);
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($parenDepth !== 0 || $bracketDepth !== 0 || !$this->isIdentifierChar($char)) {
                continue;
            }

            $identifier = $this->readCssIdentifierToken($condition, $i);
            if ($identifier === null) {
                continue;
            }

            if ($this->isSupportsLogicalOperator($identifier['name'])) {
                $parts[] = $this->trimWhitespaceAndComments(substr($condition, $start, $i - $start));
                $operators[] = strtolower($identifier['name']);
                $start = $identifier['end'];
            }

            $i = $identifier['end'] - 1;
        }

        if ($operators === []) {
            return null;
        }

        $parts[] = $this->trimWhitespaceAndComments(substr($condition, $start));

        return [
            'parts' => $parts,
            'operators' => $operators,
        ];
    }

    private function isSupportsLogicalOperator(string $identifier): bool
    {
        return strcasecmp($identifier, 'and') === 0 || strcasecmp($identifier, 'or') === 0;
    }

    private function normalizeSupportsIdentifierEscapes(string $condition): string
    {
        $output = '';
        $quote = null;
        $length = strlen($condition);

        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
            if ($quote !== null) {
                $output .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $output .= $condition[++$i];
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '/' && ($condition[$i + 1] ?? '') === '*') {
                $end = strpos($condition, '*/', $i + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }

                $output .= substr($condition, $i, $end - $i + 2);
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $output .= $char;
                continue;
            }

            if ($this->isSupportsIdentifierStart($condition, $i)) {
                $identifier = $this->readCssIdentifierToken($condition, $i);
                if ($identifier !== null) {
                    $output .= $this->serializeCssIdentifier($identifier['name']);
                    $i = $identifier['end'] - 1;
                    continue;
                }
            }

            $output .= $char;
        }

        return $output;
    }

    private function isSupportsIdentifierStart(string $condition, int $offset): bool
    {
        $char = $condition[$offset] ?? '';
        if ($char === '') {
            return false;
        }

        if ($char === '\\') {
            return $this->isValidCssEscape($condition, $offset);
        }

        $byte = ord($char);
        if ($byte >= 0x80 || $char === '_' || ctype_alpha($char)) {
            return true;
        }

        if ($char !== '-') {
            return false;
        }

        $next = $condition[$offset + 1] ?? '';
        if ($next === '') {
            return false;
        }

        if ($next === '\\') {
            return $this->isValidCssEscape($condition, $offset + 1);
        }

        return $next === '-' || $next === '_' || ctype_alpha($next) || ord($next) >= 0x80;
    }

    private function isValidSupportsInParens(string $value): bool
    {
        $value = $this->trimWhitespaceAndComments($value);
        if ($value === '') {
            return false;
        }

        if (($value[0] ?? '') === '(') {
            try {
                $close = $this->findMatchingDelimiter($value, 0, '(', ')');
            } catch (CssBundleException) {
                return false;
            }

            if ($this->skipWhitespaceAndComments($value, $close + 1) < strlen($value)) {
                return false;
            }

            $inner = substr($value, 1, $close - 1);
            if ($this->trimWhitespaceAndComments($inner) === '') {
                return false;
            }

            return $this->isValidImportSupportsCondition($inner)
                || $this->isSingleSupportsUnknownToken($inner);
        }

        return $this->isValidSupportsFunctionCondition($value);
    }

    private function isValidSupportsFunctionCondition(string $value): bool
    {
        $identifier = $this->readCssIdentifierToken($value, 0);
        if ($identifier === null || ($value[$identifier['end']] ?? '') !== '(') {
            return false;
        }

        try {
            $close = $this->findMatchingDelimiter($value, $identifier['end'], '(', ')');
        } catch (CssBundleException) {
            return false;
        }

        if ($this->skipWhitespaceAndComments($value, $close + 1) < strlen($value)) {
            return false;
        }

        return $this->trimWhitespaceAndComments(substr($value, $identifier['end'] + 1, $close - $identifier['end'] - 1)) !== '';
    }

    private function isSupportsDeclaration(string $value): bool
    {
        $colon = $this->findNextTopLevel($value, ':', 0);
        if ($colon === null) {
            return false;
        }

        $property = $this->trimWhitespaceAndComments(substr($value, 0, $colon));
        $declarationValue = $this->trimWhitespaceAndComments(substr($value, $colon + 1));
        if ($property === '' || $declarationValue === '') {
            return false;
        }

        $identifier = $this->readCssIdentifierToken($property, 0);

        return $identifier !== null
            && $this->skipWhitespaceAndComments($property, $identifier['end']) === strlen($property);
    }

    private function isSingleSupportsUnknownToken(string $value): bool
    {
        $value = $this->trimWhitespaceAndComments($value);
        $identifier = $this->readCssIdentifierToken($value, 0);

        return $identifier !== null
            && $this->skipWhitespaceAndComments($value, $identifier['end']) === strlen($value);
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function validateImportLayerName(string $layer, string $file, array $loc): void
    {
        if ($layer === '' || $this->containsTopLevelDelimiter($layer, ',') || !$this->isValidLayerName($layer)) {
            throw new CssBundleException(
                'parser-error',
                "Invalid @import layer name: {$layer}",
                $file,
                $loc['line'],
                $loc['column'],
            );
        }
    }

    private function isValidLayerName(string $layer): bool
    {
        $length = strlen($layer);
        $offset = 0;
        if (!$this->consumeLayerNameSegment($layer, $offset)) {
            return false;
        }

        while ($offset < $length) {
            if ($layer[$offset] !== '.') {
                return false;
            }

            $offset++;
            if (!$this->consumeLayerNameSegment($layer, $offset)) {
                return false;
            }
        }

        return $offset === $length;
    }

    private function layerNamesEquivalent(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return $a === $b;
        }

        return $this->layerNameKey($a) === $this->layerNameKey($b);
    }

    private function layerNameKey(string $layer): string
    {
        $segments = [];
        $length = strlen($layer);
        $offset = 0;

        while ($offset < $length) {
            $start = $offset;
            if (!$this->consumeLayerNameSegment($layer, $offset)) {
                return $layer;
            }

            $segments[] = $this->decodeCssEscapes(substr($layer, $start, $offset - $start));
            if ($offset >= $length) {
                break;
            }

            if ($layer[$offset] !== '.') {
                return $layer;
            }

            $offset++;
        }

        return implode("\0", $segments);
    }

    private function consumeLayerNameSegment(string $layer, int &$offset): bool
    {
        $length = strlen($layer);
        $start = $offset;
        if ($offset >= $length) {
            return false;
        }

        if ($layer[$offset] === '-') {
            $offset++;
            if (($layer[$offset] ?? '') === '-') {
                $offset++;
            }
        }

        if ($offset >= $length) {
            return $offset - $start >= 2 && substr($layer, $start, 2) === '--';
        }

        if (!$this->isLayerNameStart($layer, $offset)) {
            return false;
        }

        $this->consumeLayerNameCodepoint($layer, $offset);
        while ($offset < $length && $this->isLayerNameContinue($layer, $offset)) {
            $this->consumeLayerNameCodepoint($layer, $offset);
        }

        return $offset > $start;
    }

    private function isLayerNameStart(string $layer, int $offset): bool
    {
        if (($layer[$offset] ?? '') === '\\') {
            return $this->isValidCssEscape($layer, $offset);
        }

        $byte = ord($layer[$offset]);

        return ($byte >= 0x80)
            || $layer[$offset] === '_'
            || ctype_alpha($layer[$offset]);
    }

    private function isLayerNameContinue(string $layer, int $offset): bool
    {
        if (($layer[$offset] ?? '') === '\\') {
            return $this->isValidCssEscape($layer, $offset);
        }

        $byte = ord($layer[$offset]);

        return ($byte >= 0x80)
            || $layer[$offset] === '_'
            || $layer[$offset] === '-'
            || ctype_alnum($layer[$offset]);
    }

    private function consumeLayerNameCodepoint(string $layer, int &$offset): void
    {
        if (($layer[$offset] ?? '') === '\\') {
            $offset = $this->cssEscapeEndOffset($layer, $offset) + 1;
            return;
        }

        $offset++;
    }

    private function isValidCssEscape(string $value, int $offset): bool
    {
        if (($value[$offset] ?? '') !== '\\' || $offset + 1 >= strlen($value)) {
            return false;
        }

        $next = $value[$offset + 1];

        return $next !== "\n" && $next !== "\r" && $next !== "\f";
    }

    /**
     * @param array{line:int,column:int} $loc
     * @return array{file:string,preservePath?:bool}|array{external:string}
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
                    if (count($result) !== 1 || !is_string($result['external'])) {
                        $this->throwUnsupportedResolveResult($originatingFile, $loc);
                    }

                    return ['external' => $result['external']];
                }
                if (array_key_exists('file', $result)) {
                    if (count($result) !== 1 || !is_string($result['file'])) {
                        $this->throwUnsupportedResolveResult($originatingFile, $loc);
                    }

                    return $this->resolvedFileResult($result['file']);
                }
            }

            if (is_string($result)) {
                return $this->resolvedFileResult($result);
            }

            $this->throwUnsupportedResolveResult($originatingFile, $loc);
        }

        if (preg_match('/^https?:/i', $specifier) === 1) {
            return ['external' => $specifier];
        }

        if (str_starts_with($specifier, '/')) {
            return $this->defaultResolvedFileResult($specifier);
        }

        $directory = dirname($originatingFile);
        $path = ($directory === '.' || $directory === '') ? $specifier : rtrim($directory, '/') . '/' . $specifier;

        return $this->defaultResolvedFileResult($path);
    }

    /**
     * @return array{file:string,preservePath?:bool}
     */
    private function defaultResolvedFileResult(string $file): array
    {
        if ($this->preserveResolverPaths) {
            return [
                'file' => $file,
                'preservePath' => true,
            ];
        }

        return ['file' => $this->normalizePath($file)];
    }

    /**
     * @return array{file:string,preservePath?:bool}
     */
    private function resolvedFileResult(string $file): array
    {
        if ($this->preserveResolverPaths) {
            return [
                'file' => $file,
                'preservePath' => true,
            ];
        }

        return ['file' => $this->normalizePath($file)];
    }

    /**
     * @param array{file?:string,external?:string,preservePath?:bool} $resolved
     */
    private function shouldPreserveResolvedPath(array $resolved): bool
    {
        return ($resolved['preservePath'] ?? false) === true;
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

        $parser = new MediaQueryParser();
        $queries = array_map(
            static fn (string $query): string => $parser->minifyList($query, true),
            $this->splitTopLevel($parent, ',')
        );

        foreach ($this->splitTopLevel($child, ',') as $childQuery) {
            $childQuery = $parser->minifyList($childQuery, true);
            if (in_array($childQuery, $queries, true)) {
                continue;
            }

            foreach ($queries as $index => $parentQuery) {
                $queries[$index] = $this->andMediaQuery($parentQuery, $childQuery, $file, $loc);
            }
        }

        return implode(', ', $queries);
    }

    private function combineMediaOr(string $a, string $b): string
    {
        $queries = array_merge($this->splitTopLevel($a, ','), $this->splitTopLevel($b, ','));
        $parser = new MediaQueryParser();
        $deduped = [];
        foreach ($queries as $query) {
            $query = $parser->minifyList($query, true);
            $deduped[$query] = $query;
        }

        return implode(', ', array_values($deduped));
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function andMediaQuery(string $a, string $b, string $file, array $loc): string
    {
        try {
            return (new MediaQueryParser())->andQuery($a, $b);
        } catch (\InvalidArgumentException) {
            throw new CssBundleException(
                'unsupported-media-boolean-logic',
                'Unsupported boolean logic in @import media query',
                $file,
                $loc['line'],
                $loc['column'],
            );
        }
    }

    private function combineSupportsAnd(?string $parent, ?string $child): ?string
    {
        if ($parent === null) {
            return $child;
        }
        if ($child === null) {
            return $parent;
        }

        return $this->supportsOperand($parent, 'and') . ' and ' . $this->supportsOperand($child, 'and');
    }

    private function combineSupportsOr(string $a, string $b): string
    {
        return $this->supportsOperand($a, 'or') . ' or ' . $this->supportsOperand($b, 'or');
    }

    private function supportsOperand(string $condition, string $parentOperator): string
    {
        $prelude = $this->supportsPrelude($condition);
        $operator = $this->supportsConditionOperator($prelude);
        $needsParens = match ($operator) {
            'not' => true,
            'and' => $parentOperator !== 'and',
            'or' => $parentOperator !== 'or',
            default => false,
        };

        return $needsParens ? '(' . $prelude . ')' : $prelude;
    }

    private function supportsConditionOperator(string $condition): string
    {
        $condition = trim($condition);
        if ($condition === '') {
            return 'leaf';
        }

        $inner = $this->unwrapSingleParenthesizedValue($condition);
        if ($inner !== null) {
            return $this->supportsConditionOperator($inner);
        }

        if (preg_match('/^not(?:\s+|\()/i', $condition) === 1) {
            return 'not';
        }

        $quote = null;
        $depth = 0;
        $length = strlen($condition);
        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];
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

            if ($char === '/' && ($condition[$i + 1] ?? '') === '*') {
                $end = strpos($condition, '*/', $i + 2);
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

            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && $this->isIdentifierChar($char)) {
                $identifier = $this->readIdentifier($condition, $i);
                $lower = strtolower($identifier);
                $previous = $condition[$i - 1] ?? '';
                $next = $condition[$i + strlen($identifier)] ?? '';
                if (($lower === 'and' || $lower === 'or')
                    && ($previous === '' || !$this->isIdentifierChar($previous))
                    && ($next === '' || !$this->isIdentifierChar($next))
                ) {
                    return $lower;
                }

                $i += strlen($identifier) - 1;
            }
        }

        return 'leaf';
    }

    private function unwrapSingleParenthesizedValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value[0] !== '(') {
            return null;
        }

        $quote = null;
        $depth = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
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

            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i === $length - 1 ? substr($value, 1, -1) : null;
                }
            }
        }

        return null;
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
        $parts = ['@import ' . $this->cssStringLiteral($url)];
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

    private function cssStringLiteral(string $value): string
    {
        $output = '"';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $byte = ord($char);
            if ($char === '"') {
                $output .= '\\"';
                continue;
            }
            if ($char === '\\') {
                $output .= '\\\\';
                continue;
            }
            if ($byte === 0) {
                $output .= "\u{FFFD}";
                continue;
            }
            if (($byte >= 1 && $byte <= 31) || $byte === 127) {
                $output .= '\\' . dechex($byte) . ' ';
                continue;
            }

            $output .= $char;
        }

        return $output . '"';
    }

    private function serializeCssIdentifier(string $identifier): string
    {
        $output = '';
        $length = strlen($identifier);

        for ($i = 0; $i < $length; $i++) {
            $char = $identifier[$i];
            $byte = ord($char);

            if ($i === 0 && ctype_digit($char)) {
                $output .= '\\' . dechex($byte) . ' ';
                continue;
            }

            if ($i === 1 && ($identifier[0] ?? '') === '-' && ctype_digit($char)) {
                $output .= '\\' . dechex($byte) . ' ';
                continue;
            }

            if ($this->isIdentifierChar($char) || $byte >= 0x80) {
                $output .= $char;
                continue;
            }

            if ($byte === 0 || ($byte >= 1 && $byte <= 31) || $byte === 127) {
                $output .= '\\' . dechex($byte) . ' ';
                continue;
            }

            $output .= '\\' . $char;
        }

        return $output;
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
        $statement = rtrim(trim($raw), ';');
        $keywordEnd = $this->atKeywordEndOffset($statement, 0, '@layer') ?? strlen('@layer');
        $names = trim(substr($statement, $keywordEnd));
        if ($parentLayer === null || $parentLayer === '') {
            return $keywordEnd === strlen('@layer') ? $raw : '@layer' . ($names === '' ? '' : ' ' . $names) . ';';
        }

        if ($names === '') {
            return $raw;
        }

        $prefixed = [];
        foreach ($this->splitTopLevel($names, ',') as $name) {
            $prefixed[] = $parentLayer . '.' . trim($name);
        }

        return '@layer ' . implode(', ', $prefixed) . ';';
    }

    /**
     * @return array{hash?:string,filename:string,projectRoot?:string,pattern:string,minify:bool,dashedIdents:bool,animation:bool,grid:bool,container:bool,customIdents:bool,pure:bool,unusedSymbols?:list<string>,pseudoClasses?:array<string,string>,preserveDependencyComposesDuplicates:bool}
     */
    private function cssModuleTransformOptions(string $file): array
    {
        $options = [
            'filename' => $file,
            'pattern' => $this->cssModulePattern(),
            'minify' => $this->cssModuleOptions['minify'] ?? true,
            'dashedIdents' => ($this->cssModuleOptions['dashedIdents'] ?? $this->cssModuleOptions['dashed_idents'] ?? false) === true,
            'animation' => ($this->cssModuleOptions['animation'] ?? true) !== false,
            'grid' => ($this->cssModuleOptions['grid'] ?? true) !== false,
            'container' => ($this->cssModuleOptions['container'] ?? true) !== false,
            'customIdents' => ($this->cssModuleOptions['customIdents'] ?? $this->cssModuleOptions['custom_idents'] ?? true) !== false,
            'pure' => ($this->cssModuleOptions['pure'] ?? false) === true,
            'preserveDependencyComposesDuplicates' => true,
        ];

        $unusedSymbols = $this->cssModuleOptions['unusedSymbols'] ?? $this->cssModuleOptions['unused_symbols'] ?? null;
        if (is_array($unusedSymbols)) {
            $options['unusedSymbols'] = array_values(array_filter($unusedSymbols, 'is_string'));
        }

        $pseudoClasses = $this->cssModuleOptions['pseudoClasses'] ?? $this->cssModuleOptions['pseudo_classes'] ?? null;
        if (is_array($pseudoClasses)) {
            $options['pseudoClasses'] = array_filter($pseudoClasses, 'is_string');
        }

        $projectRoot = $this->cssModuleProjectRoot();
        if ($projectRoot !== null) {
            $options['projectRoot'] = $projectRoot;
        }

        $hash = $this->cssModuleExplicitHashForFile($file);
        if ($hash !== null) {
            $options['hash'] = $hash;
        }

        return $options;
    }

    private function cssModuleExplicitHashForFile(string $file): ?string
    {
        $hashes = $this->cssModuleOptions['hashes'] ?? null;
        if (is_array($hashes) && isset($hashes[$file])) {
            return $hashes[$file];
        }
        if (is_array($hashes)) {
            $normalized = $this->normalizePath($file);
            if (isset($hashes[$normalized])) {
                return $hashes[$normalized];
            }
        }

        if (is_callable($hashes)) {
            return (string) $hashes($file);
        }

        return null;
    }

    private function cssModuleHashForFile(string $file): string
    {
        return $this->cssModuleExplicitHashForFile($file)
            ?? CssModulesTransformer::filenameHashForPattern($file, $this->cssModuleProjectRoot(), $this->cssModulePattern());
    }

    private function cssModuleDashedNameForSource(int $sourceIndex, string $name): string
    {
        $export = $this->stylesheets[$sourceIndex]['cssModuleExports'][$name] ?? null;
        if (is_array($export) && isset($export['name']) && is_string($export['name'])) {
            return $export['name'];
        }

        $file = $this->stylesheets[$sourceIndex]['file'];
        $local = str_starts_with($name, '--') ? substr($name, 2) : $name;
        $pattern = $this->cssModulePattern();

        return '--' . strtr($pattern, [
            '[name]' => $this->cssModulePatternFileName($file),
            '[hash]' => $this->cssModuleHashForFile($file),
            '[content-hash]' => '',
            '[local]' => $local,
        ]);
    }

    private function cssModulePattern(): string
    {
        return $this->cssModuleOptions['pattern'] ?? '[hash]_[local]';
    }

    private function cssModuleProjectRoot(): ?string
    {
        $root = $this->cssModuleOptions['projectRoot'] ?? $this->cssModuleOptions['project_root'] ?? null;
        if (!is_string($root) || $root === '') {
            return null;
        }

        return $this->normalizePath($root);
    }

    private function cssModulePatternFileName(string $file): string
    {
        $base = basename(str_replace('\\', '/', $file));
        $dot = strrpos($base, '.');
        $stem = $dot === false ? $base : substr($base, 0, $dot);

        return str_replace('.', '-', $stem);
    }

    /**
     * @return array<string, array{read:array{line:int,column:int},resolve:array{line:int,column:int}}>
     */
    private function cssModuleDependencyLocations(string $source): array
    {
        $locations = [];
        $offset = 0;
        while (($property = $this->findNextCssIdentifierInSet($source, ['composes'], $offset)) !== null) {
            $propertyOffset = $property['start'];
            $offset = $property['end'];
            $colon = $this->skipWhitespaceAndComments($source, $offset);
            if (($source[$colon] ?? '') !== ':') {
                continue;
            }

            $valueStart = $this->skipWhitespaceAndComments($source, $colon + 1);
            $valueEnd = $this->findNextTopLevel($source, ';', $valueStart)
                ?? $this->findNextTopLevel($source, '}', $valueStart)
                ?? strlen($source);
            $value = substr($source, $valueStart, $valueEnd - $valueStart);
            $readLocation = $this->sourceLocation($source, $valueStart);
            $resolveLocation = $this->cssStyleRuleLocationForOffset($source, $propertyOffset);
            foreach ($this->cssModuleDependencySpecifiersInValue($value) as $specifier) {
                $locations[$specifier] ??= [
                    'read' => $readLocation,
                    'resolve' => $resolveLocation,
                ];
            }

            $offset = $valueEnd + 1;
        }

        $offset = 0;
        while (($function = $this->findNextCssIdentifierInSet($source, ['var', 'env'], $offset)) !== null) {
            $functionOffset = $function['start'];
            $open = $function['end'];
            $offset = $open + 1;
            if (($source[$open] ?? '') !== '(') {
                continue;
            }

            try {
                $close = $this->findMatchingDelimiter($source, $open, '(', ')');
            } catch (CssBundleException) {
                continue;
            }
            $value = substr($source, $open + 1, $close - $open - 1);
            $location = $this->cssStyleRuleLocationForOffset($source, $functionOffset);
            foreach ($this->cssModuleDependencySpecifiersInValue($value) as $specifier) {
                $locations[$specifier] ??= [
                    'read' => $location,
                    'resolve' => $location,
                ];
            }

            $offset = $close + 1;
        }

        return $locations;
    }

    /**
     * @return array{line:int,column:int}|null
     */
    private function firstCssModuleComposesLocation(string $source): ?array
    {
        $offset = 0;
        while (($property = $this->findNextCssIdentifierInSet($source, ['composes'], $offset)) !== null) {
            $offset = $property['end'];
            $colon = $this->skipWhitespaceAndComments($source, $property['end']);
            if (($source[$colon] ?? null) !== ':') {
                continue;
            }

            if (!$this->isCssModuleDeclarationBoundaryBefore($source, $property['start'])) {
                continue;
            }

            return $this->sourceLocation($source, $colon + 1);
        }

        return null;
    }

    private function isCssModuleDeclarationBoundaryBefore(string $source, int $offset): bool
    {
        $previous = $this->previousNonTriviaChar($source, $offset);
        return $previous === null || $previous === '{' || $previous === ';';
    }

    private function previousNonTriviaChar(string $source, int $offset): ?string
    {
        for ($index = $offset - 1; $index >= 0; $index--) {
            $char = $source[$index];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === '/' && ($source[$index - 1] ?? null) === '*') {
                $commentStart = strrpos(substr($source, 0, max(0, $index - 1)), '/*');
                if ($commentStart === false) {
                    return $char;
                }

                $index = $commentStart;
                continue;
            }

            return $char;
        }

        return null;
    }

    /**
     * @return array{line:int,column:int}
     */
    private function cssStyleRuleLocationForOffset(string $source, int $offset): array
    {
        $stack = $this->cssBlockOpenStackBeforeOffset($source, $offset);
        if ($stack === []) {
            return $this->sourceLocation($source, $offset);
        }

        $blockOpen = $stack[array_key_last($stack)];
        $parentOpen = count($stack) > 1 ? $stack[count($stack) - 2] : -1;
        $start = $this->cssPreludeStartInParent($source, $parentOpen + 1, $blockOpen);

        return $this->sourceLocation($source, $start);
    }

    /**
     * @return list<int>
     */
    private function cssBlockOpenStackBeforeOffset(string $source, int $offset): array
    {
        $stack = [];
        $quote = null;
        $length = min($offset, strlen($source));
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
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

            if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = strpos($source, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($source, $i);
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                $stack[] = $i;
                continue;
            }

            if ($char === '}') {
                array_pop($stack);
            }
        }

        return $stack;
    }

    private function cssPreludeStartInParent(string $source, int $start, int $blockOpen): int
    {
        $boundary = $start;
        $quote = null;
        $braceDepth = 0;
        $parenDepth = 0;
        $bracketDepth = 0;
        for ($i = $start; $i < $blockOpen; $i++) {
            $char = $source[$i];
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

            if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = strpos($source, '*/', $i + 2);
                if ($end === false) {
                    break;
                }
                $i = $end + 1;
                continue;
            }

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($source, $i);
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '(') {
                $parenDepth++;
                continue;
            }
            if ($char === ')') {
                $parenDepth = max(0, $parenDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }

            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                if ($braceDepth === 0) {
                    $boundary = $i + 1;
                }
                continue;
            }

            if ($char === ';' && $braceDepth === 0 && $parenDepth === 0 && $bracketDepth === 0) {
                $boundary = $i + 1;
            }
        }

        return $this->skipWhitespaceAndComments($source, $boundary);
    }

    /**
     * @return list<string>
     */
    private function cssModuleDependencySpecifiersInValue(string $value): array
    {
        $specifiers = [];
        $offset = 0;
        while (($from = $this->findNextCssIdentifierInSet($value, ['from'], $offset)) !== null) {
            $offset = $from['end'];
            $quoteOffset = $this->skipWhitespaceAndComments($value, $offset);
            $quote = $value[$quoteOffset] ?? '';
            if ($quote !== '"' && $quote !== "'") {
                continue;
            }

            $end = $this->readQuotedTokenEnd($value, $quoteOffset);
            $specifier = $this->cssStringTokenValue(substr($value, $quoteOffset, $end - $quoteOffset));
            if ($specifier !== '' && !in_array($specifier, $specifiers, true)) {
                $specifiers[] = $specifier;
            }

            $offset = $end;
        }

        return $specifiers;
    }

    /**
     * @param list<string> $names
     * @return array{name:string,start:int,end:int}|null
     */
    private function findNextCssIdentifierInSet(string $source, array $names, int $offset): ?array
    {
        $lookup = [];
        foreach ($names as $name) {
            $lookup[strtolower($name)] = true;
        }

        $quote = null;
        $length = strlen($source);
        for ($i = $offset; $i < $length; $i++) {
            $char = $source[$i];
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

            if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = strpos($source, '*/', $i + 2);
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

            if ($char !== '\\' && !$this->isIdentifierChar($char)) {
                continue;
            }

            $identifier = $this->readCssIdentifierToken($source, $i);
            if ($identifier === null) {
                if ($char === '\\') {
                    $i = $this->cssEscapeEndOffset($source, $i);
                }
                continue;
            }

            if (isset($lookup[strtolower($identifier['name'])])) {
                return [
                    'name' => $identifier['name'],
                    'start' => $i,
                    'end' => $identifier['end'],
                ];
            }

            $i = $identifier['end'] - 1;
        }

        return null;
    }

    private function isCssOffsetInsideStringOrComment(string $source, int $offset): bool
    {
        $quote = null;
        $length = min($offset, strlen($source));
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
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

            if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = strpos($source, '*/', $i + 2);
                if ($end === false || $end + 2 > $offset) {
                    return true;
                }

                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            }
        }

        return $quote !== null;
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
                if ($parts !== [] && end($parts) !== '..') {
                    array_pop($parts);
                    continue;
                }

                if (!$absolute) {
                    $parts[] = '..';
                }
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

            if ($char === '\\') {
                $end = $this->cssEscapeEndOffset($value, $i);
                $parts[array_key_last($parts)] .= substr($value, $i, $end - $i + 1);
                $i = $end;
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

    private function containsTopLevelDelimiter(string $value, string $delimiter): bool
    {
        $quote = null;
        $parenDepth = 0;
        $bracketDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
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

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($value, $i);
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
                return true;
            }
        }

        return false;
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

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($css, $i);
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

            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($value, $i);
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

    private function cssEscapeEndOffset(string $value, int $offset): int
    {
        $length = strlen($value);
        if ($offset + 1 >= $length) {
            return $offset;
        }

        $cursor = $offset + 1;
        $next = $value[$cursor];
        if (!ctype_xdigit($next)) {
            return $next === "\r" && ($value[$cursor + 1] ?? '') === "\n" ? $cursor + 1 : $cursor;
        }

        $digits = 0;
        while ($cursor < $length && $digits < 6 && ctype_xdigit($value[$cursor])) {
            $cursor++;
            $digits++;
        }

        if ($cursor < $length && ctype_space($value[$cursor])) {
            return $value[$cursor] === "\r" && ($value[$cursor + 1] ?? '') === "\n" ? $cursor + 1 : $cursor;
        }

        return $cursor - 1;
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

    private function trimWhitespaceAndComments(string $value): string
    {
        $start = $this->skipWhitespaceAndComments($value, 0);
        $cursor = $start;
        $lastNonTriviaEnd = $start;
        $length = strlen($value);

        while ($cursor < $length) {
            if (ctype_space($value[$cursor])) {
                $cursor++;
                continue;
            }

            if ($value[$cursor] === '/' && ($value[$cursor + 1] ?? '') === '*') {
                $end = strpos($value, '*/', $cursor + 2);
                if ($end === false) {
                    throw new CssBundleException('parser-error', 'CSS contains an unbalanced comment');
                }

                $cursor = $end + 2;
                continue;
            }

            if ($value[$cursor] === '\\') {
                $cursor = $this->cssEscapeEndOffset($value, $cursor) + 1;
                $lastNonTriviaEnd = $cursor;
                continue;
            }

            $cursor++;
            $lastNonTriviaEnd = $cursor;
        }

        return substr($value, $start, $lastNonTriviaEnd - $start);
    }

    private function containsUnescapedCommentStart(string $value): bool
    {
        $cursor = 0;
        $length = strlen($value);

        while ($cursor < $length) {
            if ($value[$cursor] === '\\') {
                $cursor = $this->cssEscapeEndOffset($value, $cursor) + 1;
                continue;
            }

            if ($value[$cursor] === '/' && ($value[$cursor + 1] ?? '') === '*') {
                return true;
            }

            $cursor++;
        }

        return false;
    }

    private function startsAtKeyword(string $css, int $offset, string $keyword): bool
    {
        $token = $this->readAtKeywordToken($css, $offset);
        if ($token === null || strcasecmp($token['name'], ltrim($keyword, '@')) !== 0) {
            return false;
        }

        return true;
    }

    private function atKeywordEndOffset(string $css, int $offset, string $keyword): ?int
    {
        $token = $this->readAtKeywordToken($css, $offset);
        if ($token === null || strcasecmp($token['name'], ltrim($keyword, '@')) !== 0) {
            return null;
        }

        return $token['end'];
    }

    /**
     * @return array{name:string,end:int}|null
     */
    private function readAtKeywordToken(string $css, int $offset): ?array
    {
        if (($css[$offset] ?? '') !== '@') {
            return null;
        }

        return $this->readCssIdentifierToken($css, $offset + 1);
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

    private function cssFunctionOpenOffset(string $value, int $offset, string $name): ?int
    {
        $identifier = $this->readCssIdentifierToken($value, $offset);
        if ($identifier === null || strcasecmp($identifier['name'], $name) !== 0) {
            return null;
        }

        return ($value[$identifier['end']] ?? '') === '(' ? $identifier['end'] : null;
    }

    /**
     * @return array{name:string,end:int}|null
     */
    private function readCssIdentifierToken(string $value, int $offset): ?array
    {
        $length = strlen($value);
        if ($offset >= $length) {
            return null;
        }

        $cursor = $offset;
        $raw = '';
        while ($cursor < $length) {
            $char = $value[$cursor];
            if ($char === '\\') {
                if (!$this->isValidCssEscape($value, $cursor)) {
                    break;
                }

                $end = $this->cssEscapeEndOffset($value, $cursor);
                $raw .= substr($value, $cursor, $end - $cursor + 1);
                $cursor = $end + 1;
                continue;
            }

            if (!$this->isIdentifierChar($char)) {
                break;
            }

            $raw .= $char;
            $cursor++;
        }

        if ($raw === '') {
            return null;
        }

        return [
            'name' => $this->decodeCssEscapes($raw),
            'end' => $cursor,
        ];
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

    /**
     * @param array{line:int,column:int} $loc
     */
    private function validateUnquotedImportUrlSource(string $source, string $file, array $loc): void
    {
        $length = strlen($source);
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            $byte = ord($char);

            if ($char === '\\') {
                if ($i + 1 >= $length) {
                    $this->throwInvalidImportSource($file, $loc);
                }

                $next = $source[$i + 1];
                if ($next === "\n" || $next === "\f" || $next === "\r") {
                    $this->throwInvalidImportSource($file, $loc);
                }

                $i = $this->cssEscapeEndOffset($source, $i);
                continue;
            }

            if (
                ctype_space($char)
                || $char === '"'
                || $char === "'"
                || $char === '('
                || $byte === 0
                || ($byte >= 1 && $byte <= 8)
                || $byte === 11
                || ($byte >= 14 && $byte <= 31)
                || $byte === 127
            ) {
                $this->throwInvalidImportSource($file, $loc);
            }
        }
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function throwInvalidImportSource(string $file, array $loc): void
    {
        throw new CssBundleException('parser-error', 'Invalid @import source', $file, $loc['line'], $loc['column']);
    }

    /**
     * @param array{line:int,column:int} $loc
     */
    private function readImportSourceStringEnd(string $value, int $offset, string $file, array $loc): int
    {
        $quote = $value[$offset] ?? '';
        $length = strlen($value);
        for ($i = $offset + 1; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\') {
                $i = $this->cssEscapeEndOffset($value, $i);
                continue;
            }
            if ($char === "\n" || $char === "\r" || $char === "\f") {
                $this->throwInvalidImportSource($file, $loc);
            }
            if ($char === $quote) {
                return $i + 1;
            }
        }

        $this->throwInvalidImportSource($file, $loc);
    }

    private function cssStringTokenValue(string $token): string
    {
        $token = trim($token);
        $quote = $token[0] ?? '';
        if (($quote !== '"' && $quote !== "'") || substr($token, -1) !== $quote) {
            return $token;
        }

        $value = substr($token, 1, -1);

        return $this->decodeCssEscapes($value);
    }

    private function decodeCssEscapes(string $token): string
    {
        $output = '';
        $length = strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $char = $token[$i];
            if ($char !== '\\') {
                $output .= $char;
                continue;
            }

            if ($i + 1 >= $length) {
                $output .= '\\';
                continue;
            }

            $next = $token[$i + 1];
            if ($next === "\r") {
                $i++;
                if (($token[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                continue;
            }

            if ($next === "\n" || $next === "\f") {
                $i++;
                continue;
            }

            if (!ctype_xdigit($next)) {
                $output .= $next;
                $i++;
                continue;
            }

            $hex = '';
            $cursor = $i + 1;
            while ($cursor < $length && strlen($hex) < 6 && ctype_xdigit($token[$cursor])) {
                $hex .= $token[$cursor];
                $cursor++;
            }

            if ($cursor < $length && ctype_space($token[$cursor])) {
                if ($token[$cursor] === "\r" && ($token[$cursor + 1] ?? '') === "\n") {
                    $cursor += 2;
                } else {
                    $cursor++;
                }
            }

            $output .= $this->codepointToUtf8((int) hexdec($hex));
            $i = $cursor - 1;
        }

        return $output;
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0 || $codepoint > 0x10ffff) {
            $codepoint = 0xfffd;
        }

        if (function_exists('mb_chr')) {
            return mb_chr($codepoint, 'UTF-8');
        }

        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_NOQUOTES, 'UTF-8');
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
