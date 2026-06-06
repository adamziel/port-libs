<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocTemplate
{
    private const MAX_PARTIAL_DEPTH = 50;
    private const BREAKABLE_SPACE_MARKER = "\x1F";

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     */
    public function render(string $template, array $context, array $partials = []): string
    {
        return $this->renderTemplate($template, $context, $this->normalizePartialMap($partials), [], false);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     */
    public function renderWrapped(string $template, array $context, int $lineLength, array $partials = []): string
    {
        $this->validateLineLength($lineLength);

        return $this->wrapBreakableSpaces(
            $this->renderTemplate($template, $context, $this->normalizePartialMap($partials), [], true),
            $lineLength,
        );
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $context
     */
    public function renderResource(string $templatePath, array $resources, array $context, ?string $userDataDirectory = null, ?string $format = null): string
    {
        $templatePath = $this->normalizeTemplateResourcePath($templatePath);
        $resources = $this->normalizeTemplateResourceMap($resources);
        $templatePath = $this->resolveTemplateResourcePath($templatePath, $resources, $format);
        $resources = $this->withDefaultTemplateResource($templatePath, $resources);
        if (!array_key_exists($templatePath, $resources)) {
            throw new \UnexpectedValueException("Missing doctemplate resource {$templatePath}");
        }

        return $this->render(
            $resources[$templatePath],
            $context,
            $this->partialsForTemplateResource($templatePath, $resources, $userDataDirectory),
        );
    }

    /**
     * @param array<string, string> $resources
     * @param array<string, mixed> $context
     */
    public function renderResourceWrapped(string $templatePath, array $resources, array $context, int $lineLength, ?string $userDataDirectory = null, ?string $format = null): string
    {
        $this->validateLineLength($lineLength);
        $templatePath = $this->normalizeTemplateResourcePath($templatePath);
        $resources = $this->normalizeTemplateResourceMap($resources);
        $templatePath = $this->resolveTemplateResourcePath($templatePath, $resources, $format);
        $resources = $this->withDefaultTemplateResource($templatePath, $resources);
        if (!array_key_exists($templatePath, $resources)) {
            throw new \UnexpectedValueException("Missing doctemplate resource {$templatePath}");
        }

        return $this->renderWrapped(
            $resources[$templatePath],
            $context,
            $lineLength,
            $this->partialsForTemplateResource($templatePath, $resources, $userDataDirectory),
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderTemplate(string $template, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $tokens = $this->tokenize($template);

        return $this->renderRange($tokens, 0, count($tokens), $context, $partials, $partialStack, $preserveBreakableSpaces);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function normalizeTemplateResourceMap(array $resources): array
    {
        $normalized = [];
        foreach ($resources as $path => $source) {
            if (!is_string($path)) {
                throw new \InvalidArgumentException('Doctemplate resource paths must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate resource {$path} must be a string");
            }

            $normalized[$this->normalizeTemplateResourcePath($path)] = $source;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $resources
     */
    private function resolveTemplateResourcePath(string $templatePath, array $resources, ?string $format): string
    {
        if (array_key_exists($templatePath, $resources)) {
            return $templatePath;
        }

        if ($format === null || $format === '' || $this->templateResourceExtension($this->templateResourceBasename($templatePath)) !== '') {
            return $templatePath;
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $format)) {
            throw new \InvalidArgumentException('Invalid doctemplate output format');
        }

        $candidate = $templatePath . '.' . $format;
        if (array_key_exists($candidate, $resources) || $this->defaultTemplateResource($candidate) !== null) {
            return $candidate;
        }

        $defaultCandidate = $this->defaultTemplateResourcePathFor($templatePath, $format);
        if ($defaultCandidate !== null && (array_key_exists($defaultCandidate, $resources) || $this->defaultTemplateResource($defaultCandidate) !== null)) {
            return $defaultCandidate;
        }

        return $templatePath;
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function withDefaultTemplateResource(string $templatePath, array $resources): array
    {
        if (!array_key_exists($templatePath, $resources)) {
            $default = $this->defaultTemplateResource($templatePath);
            if ($default !== null) {
                $resources[$templatePath] = $default;
            }
        }

        foreach ($this->defaultPartialResourcesFor($templatePath) as $path => $source) {
            if (!array_key_exists($path, $resources)) {
                $resources[$path] = $source;
            }
        }

        return $resources;
    }

    /**
     * @return array<string, string>
     */
    private function defaultPartialResourcesFor(string $templatePath): array
    {
        if ($templatePath !== 'templates/default.html5') {
            return [];
        }

        return [
            'templates/styles.html' => $this->defaultHtmlStylesTemplate(),
            'templates/styles.citations.html' => $this->defaultHtmlCitationStylesTemplate(),
        ];
    }

    private function defaultTemplateResourcePathFor(string $templatePath, string $format): ?string
    {
        if ($templatePath !== 'templates/default') {
            return null;
        }

        $format = $this->canonicalDefaultTemplateFormat($format);
        if ($format === null || $format === '') {
            return null;
        }

        return 'templates/default.' . $format;
    }

    private function canonicalDefaultTemplateFormat(string $format): ?string
    {
        return match ($format) {
            'html' => 'html5',
            'markdown_strict', 'multimarkdown', 'markdown_github', 'markdown_mmd', 'markdown_phpextra' => 'markdown',
            'gfm', 'commonmark_x' => 'commonmark',
            'native', 'csljson', 'json', 'xml', 'fb2', 'pptx', 'ipynb' => '',
            default => $format,
        };
    }

    private function defaultTemplateResource(string $path): ?string
    {
        return match ($path) {
            'templates/default.html5' => $this->defaultHtml5Template(),
            'templates/default.markdown', 'templates/default.commonmark' => $this->defaultMarkdownTemplate(),
            default => null,
        };
    }

    private function defaultMarkdownTemplate(): string
    {
        return <<<'MD'
$if(titleblock)$
$titleblock$

$endif$
$for(header-includes)$
$header-includes$

$endfor$
$for(include-before)$
$include-before$

$endfor$
$if(toc)$
$table-of-contents$

$endif$
$body$
$for(include-after)$

$include-after$
$endfor$
MD;
    }

    private function defaultHtml5Template(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"$if(lang)$ lang="$lang$" xml:lang="$lang$"$endif$$if(dir)$ dir="$dir$"$endif$>
<head>
<meta charset="utf-8" />
<meta name="generator" content="pandoc $pandoc-version$" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
$for(author-meta)$<meta name="author" content="$it$" />
$endfor$$if(date-meta)$<meta name="dcterms.date" content="$date-meta$" />
$endif$$if(keywords)$<meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$$if(description-meta)$<meta name="description" content="$description-meta$" />
$endif$<title>$if(title-prefix)$$title-prefix$ &ndash; $endif$$if(pagetitle)$$pagetitle$$elseif(title)$$title$$endif$</title>
<style>
    $styles.html()$
</style>
$for(css)$<link rel="stylesheet" href="$it$" />
$endfor$$for(header-includes)$$it$
$endfor$$if(math)$$math$
$endif$</head>
<body>
$for(include-before)$$it$
$endfor$$if(title)$<header id="title-block-header">
<h1 class="title">$title$</h1>
$if(subtitle)$<p class="subtitle">$subtitle$</p>
$endif$$for(author)$<p class="author">$it$</p>
$endfor$$if(date)$<p class="date">$date$</p>
$endif$$if(abstract)$<div class="abstract">
<div class="abstract-title">$abstract-title$</div>
$abstract$
</div>
$endif$</header>
$endif$$if(toc)$<nav id="$idprefix$TOC" role="doc-toc">
$if(toc-title)$<h2 id="$idprefix$toc-title">$toc-title$</h2>
$endif$$table-of-contents$
</nav>
$endif$$body$
$for(include-after)$
$it$$endfor$
</body>
</html>
HTML;
    }

    private function defaultHtmlStylesTemplate(): string
    {
        return <<<'CSS'
/* Default styles provided by pandoc.
** See https://pandoc.org/MANUAL.html#variables-for-html for config info.
*/
$if(document-css)$
html {
$if(mainfont)$
  font-family: $mainfont$;
$endif$
$if(fontsize)$
  font-size: $fontsize$;
$endif$
$if(linestretch)$
  line-height: $linestretch$;
$endif$
  color: $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  background-color: $if(backgroundcolor)$$backgroundcolor$$else$#fdfdfd$endif$;
}
body {
  margin: 0 auto;
  max-width: $if(maxwidth)$$maxwidth$$else$36em$endif$;
  padding-left: $if(margin-left)$$margin-left$$else$50px$endif$;
  padding-right: $if(margin-right)$$margin-right$$else$50px$endif$;
  padding-top: $if(margin-top)$$margin-top$$else$50px$endif$;
  padding-bottom: $if(margin-bottom)$$margin-bottom$$else$50px$endif$;
  hyphens: auto;
  overflow-wrap: break-word;
  text-rendering: optimizeLegibility;
  font-kerning: normal;
}
@media (max-width: 600px) {
  body {
    font-size: 0.9em;
    padding: 12px;
  }
  h1 {
    font-size: 1.8em;
  }
}
@media print {
  html {
    background-color: $if(backgroundcolor)$$backgroundcolor$$else$white$endif$;
  }
  body {
    background-color: transparent;
    color: black;
    font-size: 12pt;
  }
  p, h2, h3 {
    orphans: 3;
    widows: 3;
  }
  h2, h3, h4 {
    page-break-after: avoid;
  }
}
p {
  margin: 1em 0;
}
a {
  color: $if(linkcolor)$$linkcolor$$else$#1a1a1a$endif$;
}
a:visited {
  color: $if(linkcolor)$$linkcolor$$else$#1a1a1a$endif$;
}
img {
  max-width: 100%;
}
svg {
  height: auto;
  max-width: 100%;
}
h1, h2, h3, h4, h5, h6 {
  margin-top: 1.4em;
}
h5, h6 {
  font-size: 1em;
  font-style: italic;
}
h6 {
  font-weight: normal;
}
ol, ul {
  padding-left: 1.7em;
  margin-top: 1em;
}
li > ol, li > ul {
  margin-top: 0;
}
blockquote {
  margin: 1em 0 1em 1.7em;
  padding-left: 1em;
  border-left: 2px solid #e6e6e6;
  color: #606060;
}
$if(abstract)$
div.abstract {
  margin: 2em 2em 2em 2em;
  text-align: left;
  font-size: 85%;
}
div.abstract-title {
  font-weight: bold;
  text-align: center;
  padding: 0;
  margin-bottom: 0.5em;
}
$endif$
code {
  white-space: pre-wrap;
  font-family: $if(monofont)$$monofont$$else$Menlo, Monaco, Consolas, 'Lucida Console', monospace$endif$;
$if(monobackgroundcolor)$
  background-color: $monobackgroundcolor$;
  padding: .2em .4em;
$endif$
  font-size: 85%;
  margin: 0;
  hyphens: manual;
}
pre {
  margin: 1em 0;
$if(monobackgroundcolor)$
  background-color: $monobackgroundcolor$;
  padding: 1em;
$endif$
  overflow: auto;
}
pre code {
  padding: 0;
  overflow: visible;
  overflow-wrap: normal;
}
.sourceCode {
 background-color: transparent;
 overflow: visible;
}
hr {
  border: none;
  border-top: 1px solid #1a1a1a;
  height: 1px;
  margin: 1em 0;
}
table {
  margin: 1em 0;
  border-collapse: collapse;
  width: 100%;
  overflow-x: auto;
  display: block;
  font-variant-numeric: lining-nums tabular-nums;
}
table caption {
$if(table-caption-below)$
  caption-side: bottom;
  margin-top: 0.75em;
$else$
  margin-bottom: 0.75em;
$endif$
}
tbody {
  margin-top: 0.5em;
  border-top: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  border-bottom: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
}
th {
  border-top: 1px solid $if(fontcolor)$$fontcolor$$else$#1a1a1a$endif$;
  padding: 0.25em 0.5em 0.25em 0.5em;
}
td {
  padding: 0.125em 0.5em 0.25em 0.5em;
}
header {
  margin-bottom: 4em;
  text-align: center;
}
#TOC li {
  list-style: none;
}
#TOC ul {
  padding-left: 1.3em;
}
#TOC > ul {
  padding-left: 0;
}
#TOC a:not(:hover) {
  text-decoration: none;
}
$endif$
span.smallcaps{font-variant: small-caps;}
div.columns{display: flex; gap: 1.5em;}
div.column{flex: auto;}
@media screen {
div.columns{gap: min(4vw, 1.5em);}
div.column{overflow-x: auto;}
}
div.hanging-indent{margin-left: 1.5em; text-indent: -1.5em;}
/* The extra [class] is a hack that increases specificity enough to
   override a similar rule in reveal.js */
ul.task-list[class]{list-style: none;}
ul.task-list li input[type="checkbox"] {
  font-size: inherit;
  width: 0.8em;
  margin: 0 0.8em 0.2em -1.6em;
  vertical-align: middle;
}
$if(quotes)$
q { quotes: "\201C" "\201D" "\2018" "\2019"; }
$endif$
$if(displaymath-css)$
.display.math{display: block; text-align: center; margin: 0.5rem auto;}
$endif$
$if(highlighting-css)$
/* CSS for syntax highlighting */
$highlighting-css$
$endif$
$if(csl-css)$
$styles.citations.html()$
$endif$
CSS;
    }

    private function defaultHtmlCitationStylesTemplate(): string
    {
        return <<<'CSS'
/* CSS for citations */
div.csl-bib-body { }
div.csl-entry {
  clear: both;
$if(csl-entry-spacing)$
  margin-bottom: $csl-entry-spacing$;
$endif$
}
.hanging-indent div.csl-entry {
  margin-left:2em;
  text-indent:-2em;
}
div.csl-left-margin {
  min-width:2em;
  float:left;
}
div.csl-right-inline {
  margin-left:2em;
  padding-left:1em;
}
div.csl-indent {
  margin-left: 2em;
}
CSS;
    }

    /**
     * @param array<string, string> $partials
     * @return array<string, string>
     */
    private function normalizePartialMap(array $partials): array
    {
        $normalized = [];
        foreach ($partials as $name => $source) {
            if (!is_string($name)) {
                throw new \InvalidArgumentException('Doctemplate partial names must be strings');
            }

            if (!is_string($source)) {
                throw new \InvalidArgumentException("Doctemplate partial {$name} must be a string");
            }

            $normalized[$this->normalizePartialName($name)] = $source;
        }

        return $normalized;
    }

    private function normalizeTemplateResourcePath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \InvalidArgumentException('Doctemplate resource paths must not contain parent-directory segments');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new \InvalidArgumentException('Invalid doctemplate resource path');
        }

        return ($absolute ? '/' : '') . implode('/', $segments);
    }

    private function normalizePartialName(string $name): string
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new \InvalidArgumentException('Invalid doctemplate partial name');
        }

        $name = str_replace('\\', '/', $name);
        if (str_starts_with($name, '/')) {
            throw new \InvalidArgumentException('Doctemplate partial names must be relative paths');
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Doctemplate partial names must not contain empty, current-directory, or parent-directory segments');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @param array<string, string> $resources
     * @return array<string, string>
     */
    private function partialsForTemplateResource(string $templatePath, array $resources, ?string $userDataDirectory): array
    {
        $mainDirectory = $this->templateResourceDirectory($templatePath);
        $mainExtension = $this->templateResourceExtension($this->templateResourceBasename($templatePath));
        $searchDirectories = [$mainDirectory];
        if ($userDataDirectory !== null && !$this->isAbsoluteTemplateResourcePath($templatePath)) {
            $searchDirectories[] = $this->joinTemplateResourcePath(
                $this->normalizeTemplateResourcePath($userDataDirectory),
                'templates',
            );
        }

        $partials = [];
        foreach ($searchDirectories as $directory) {
            foreach ($resources as $resourcePath => $source) {
                if ($resourcePath === $templatePath) {
                    continue;
                }

                $relativePath = $this->relativeTemplateResourceChild($resourcePath, $directory);
                if ($relativePath === null) {
                    continue;
                }

                foreach ($this->partialAliasesForResourcePath($relativePath, $mainExtension) as $alias) {
                    if (!array_key_exists($alias, $partials)) {
                        $partials[$alias] = $source;
                    }
                }
            }
        }

        return $partials;
    }

    private function isAbsoluteTemplateResourcePath(string $path): bool
    {
        return str_starts_with($path, '/');
    }

    private function templateResourceDirectory(string $path): string
    {
        $slash = strrpos($path, '/');
        if ($slash === false) {
            return '';
        }

        if ($slash === 0) {
            return '/';
        }

        return substr($path, 0, $slash);
    }

    private function templateResourceBasename(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }

    private function templateResourceExtension(string $basename): string
    {
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === 0) {
            return '';
        }

        return substr($basename, $dot);
    }

    private function joinTemplateResourcePath(string $directory, string $basename): string
    {
        if ($directory === '') {
            return $basename;
        }

        if ($directory === '/') {
            return '/' . $basename;
        }

        return $directory . '/' . $basename;
    }

    private function relativeTemplateResourceChild(string $path, string $directory): ?string
    {
        if ($directory === '') {
            return $this->isAbsoluteTemplateResourcePath($path) ? null : $path;
        }

        if ($directory === '/') {
            if (!str_starts_with($path, '/')) {
                return null;
            }

            $relative = substr($path, 1);

            return $relative !== '' ? $relative : null;
        }

        $prefix = $directory . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));

        return $relative !== '' ? $relative : null;
    }

    /**
     * @return list<string>
     */
    private function partialAliasesForResourcePath(string $relativePath, string $mainExtension): array
    {
        $basename = $this->templateResourceBasename($relativePath);
        $extension = $this->templateResourceExtension($basename);
        if ($extension === '') {
            return $mainExtension === '' ? [$relativePath] : [];
        }

        $aliases = [$relativePath];
        if ($extension === $mainExtension) {
            $aliases[] = substr($relativePath, 0, -strlen($extension));
        }

        return $aliases;
    }

    /**
     * @return list<array{type:string, value:string}>
     */
    private function tokenize(string $template): array
    {
        $tokens = [];
        $buffer = '';
        $breakableSpaces = false;
        $length = strlen($template);

        for ($index = 0; $index < $length; $index++) {
            $char = $template[$index];
            if ($char !== '$') {
                $buffer .= $char;
                continue;
            }

            if (substr($template, $index, 3) === '$--') {
                $lineEnding = $this->findCommentLineEnding($template, $index + 3);
                if ($lineEnding === null) {
                    break;
                }

                if ($this->commentStartsInFirstColumn($buffer)) {
                    $buffer = $this->dropStandaloneCommentLinePrefix($buffer);
                } else {
                    $buffer .= $lineEnding['value'];
                }
                $index = $lineEnding['start'] + $lineEnding['length'] - 1;
                continue;
            }

            if (($template[$index + 1] ?? '') === '$') {
                $buffer .= '$';
                $index++;
                continue;
            }

            if (($template[$index + 1] ?? '') === '{') {
                $closing = $this->findBracedDirectiveClosing($template, $index + 2);
                if ($closing === null) {
                    throw new \UnexpectedValueException('Unclosed doctemplate ${...} directive');
                }

                $this->appendTextToken($tokens, $buffer, $breakableSpaces);
                $buffer = '';
                $directive = trim(substr($template, $index + 2, $closing - $index - 2), " \t");
                if ($directive === '~') {
                    $breakableSpaces = !$breakableSpaces;
                    $index = $closing;
                    continue;
                }

                $tokens[] = [
                    'type' => 'directive',
                    'value' => $directive,
                ];
                $index = $closing;
                continue;
            }

            $closing = strpos($template, '$', $index + 1);
            if ($closing === false) {
                throw new \UnexpectedValueException('Unclosed doctemplate $...$ directive');
            }

            $this->appendTextToken($tokens, $buffer, $breakableSpaces);
            $buffer = '';
            $directive = trim(substr($template, $index + 1, $closing - $index - 1), " \t");
            if ($directive === '~') {
                $breakableSpaces = !$breakableSpaces;
                $index = $closing;
                continue;
            }

            $tokens[] = [
                'type' => 'directive',
                'value' => $directive,
            ];
            $index = $closing;
        }

        if ($breakableSpaces) {
            throw new \UnexpectedValueException('Unclosed doctemplate breakable-space region');
        }

        $this->appendTextToken($tokens, $buffer, $breakableSpaces);

        return $tokens;
    }

    private function findBracedDirectiveClosing(string $template, int $start): ?int
    {
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($template);

        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $bracketDepth === 0 && $char === '}') {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{start:int, length:int, value:string}|null
     */
    private function findCommentLineEnding(string $template, int $start): ?array
    {
        $length = strlen($template);
        for ($index = $start; $index < $length; $index++) {
            $char = $template[$index];
            if ($char === "\n") {
                return ['start' => $index, 'length' => 1, 'value' => "\n"];
            }

            if ($char === "\r") {
                if (($template[$index + 1] ?? '') === "\n") {
                    return ['start' => $index, 'length' => 2, 'value' => "\r\n"];
                }

                return ['start' => $index, 'length' => 1, 'value' => "\r"];
            }
        }

        return null;
    }

    private function commentStartsInFirstColumn(string $buffer): bool
    {
        $lineStart = $this->lastLineEndingByteOffset($buffer);
        $linePrefix = $lineStart === null ? $buffer : substr($buffer, $lineStart + 1);

        return $linePrefix === '';
    }

    private function dropStandaloneCommentLinePrefix(string $buffer): string
    {
        $lineStart = $this->lastLineEndingByteOffset($buffer);

        return $lineStart === null ? '' : substr($buffer, 0, $lineStart + 1);
    }

    private function lastLineEndingByteOffset(string $buffer): ?int
    {
        $lastLf = strrpos($buffer, "\n");
        $lastCr = strrpos($buffer, "\r");
        if ($lastLf === false && $lastCr === false) {
            return null;
        }

        if ($lastLf === false) {
            return $lastCr;
        }

        if ($lastCr === false) {
            return $lastLf;
        }

        return max($lastLf, $lastCr);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderRange(array $tokens, int $start, int $end, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $output = '';
        $pendingNestColumn = null;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] === 'text') {
                $text = $token['value'];
                if (($token['breakable'] ?? false) === true) {
                    $text = $this->normalizeBreakableSpaces(
                        $text,
                        $preserveBreakableSpaces ? self::BREAKABLE_SPACE_MARKER : ' ',
                    );
                }

                $this->appendRenderedChunk($output, $text, $pendingNestColumn, true);
                continue;
            }

            $directive = $token['value'];
            if ($directive === '~') {
                continue;
            }

            if ($directive === '^') {
                $pendingNestColumn = $this->currentColumn($output);
                continue;
            }

            $ifVariable = $this->controlVariable($directive, 'if');
            if ($ifVariable !== null) {
                [$rendered, $nextIndex, $skipFollowingLineEnding] = $this->renderIf($tokens, $index + 1, $end, $ifVariable, $context, $partials, $partialStack, $preserveBreakableSpaces);
                $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
                if ($skipFollowingLineEnding) {
                    $this->dropLeadingLineEndingAt($tokens, $nextIndex, $end);
                }
                $index = $nextIndex - 1;
                continue;
            }

            $forVariable = $this->controlVariable($directive, 'for');
            if ($forVariable !== null) {
                [$rendered, $nextIndex, $skipFollowingLineEnding] = $this->renderFor($tokens, $index + 1, $end, $forVariable, $context, $partials, $partialStack, $preserveBreakableSpaces);
                $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
                if ($skipFollowingLineEnding) {
                    $this->dropLeadingLineEndingAt($tokens, $nextIndex, $end);
                }
                $index = $nextIndex - 1;
                continue;
            }

            if (in_array($directive, ['elseif', 'else', 'endif', 'sep', 'endfor'], true) || $this->controlVariable($directive, 'elseif') !== null) {
                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            $isBarePartial = $this->parsePartialDirective($directive) !== null;
            $rendered = $this->renderDirective($directive, $context, $partials, $partialStack, $preserveBreakableSpaces);
            if ($pendingNestColumn === null) {
                $autoNestPrefix = $this->automaticNestPrefix($tokens, $index, $end, $output);
                if ($autoNestPrefix !== null) {
                    if ($isBarePartial && $rendered === '') {
                        $this->dropStandaloneDirectiveLine($tokens, $index + 1, $end, $output, $autoNestPrefix);
                        continue;
                    }

                    $rendered = $this->nestMultiline($rendered, $autoNestPrefix);
                }
            }

            $this->appendRenderedChunk($output, $rendered, $pendingNestColumn);
        }

        return $output;
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     * @return array{0:string, 1:int, 2:bool}
     */
    private function renderIf(array $tokens, int $start, int $end, string $firstVariable, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): array
    {
        [$branches, $nextIndex, $blockMultiline] = $this->collectIfBranches($tokens, $start, $end, $firstVariable);

        foreach ($branches as $branch) {
            if ($branch['variable'] === null || $this->isTruthy($this->resolveExpression($branch['variable'], $context)['value'])) {
                return [
                    $this->renderRangeDroppingLeadingLineEnding(
                        $tokens,
                        $branch['start'],
                        $branch['end'],
                        $context,
                        $partials,
                        $partialStack,
                        $branch['trimLeadingLineEnding'],
                        $preserveBreakableSpaces,
                    ),
                    $nextIndex,
                    $blockMultiline,
                ];
            }
        }

        return ['', $nextIndex, $blockMultiline];
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @return array{0:list<array{variable:?string, start:int, end:int, trimLeadingLineEnding:bool}>, 1:int, 2:bool}
     */
    private function collectIfBranches(array $tokens, int $start, int $end, string $firstVariable): array
    {
        $branches = [];
        $branchVariable = $firstVariable;
        $branchStart = $start;
        $blockMultiline = $this->tokenStartsWithLineEnding($tokens, $start, $end);
        $branchTrimLeadingLineEnding = $blockMultiline;
        $currentControlMultiline = $blockMultiline;
        $depth = 0;

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endif') {
                    $branches[] = [
                        'variable' => $branchVariable,
                        'start' => $branchStart,
                        'end' => $index,
                        'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                    ];

                    return [$branches, $index + 1, $blockMultiline];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth !== 0) {
                continue;
            }

            $elseifVariable = $this->controlVariable($directive, 'elseif');
            if ($elseifVariable !== null) {
                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                    'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                ];
                $branchVariable = $elseifVariable;
                $branchStart = $index + 1;
                $branchTrimLeadingLineEnding = $this->tokenStartsWithLineEnding($tokens, $branchStart, $end);
                $currentControlMultiline = $branchTrimLeadingLineEnding;
                continue;
            }

            if ($directive === 'else') {
                $branches[] = [
                    'variable' => $branchVariable,
                    'start' => $branchStart,
                    'end' => $index,
                    'trimLeadingLineEnding' => $branchTrimLeadingLineEnding,
                ];
                $branchVariable = null;
                $branchStart = $index + 1;
                $branchTrimLeadingLineEnding = $currentControlMultiline;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate if block');
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     * @return array{0:string, 1:int, 2:bool}
     */
    private function renderFor(array $tokens, int $start, int $end, string $variable, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): array
    {
        [$bodyStart, $bodyEnd, $separatorStart, $separatorEnd, $nextIndex, $blockMultiline] = $this->collectForSlices($tokens, $start, $end);
        $expression = $this->parseVariableExpression($variable);
        $baseExists = $this->resolve($expression['name'], $context)['exists'];
        $resolved = $this->resolveParsedExpression($expression, $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        $rendered = [];

        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $expression['name'], $item, $baseExists);
            $rendered[] = $this->renderRangeDroppingLeadingLineEnding(
                $tokens,
                $bodyStart,
                $bodyEnd,
                $iterationContext,
                $partials,
                $partialStack,
                $blockMultiline,
                $preserveBreakableSpaces,
            );
        }

        if ($rendered === []) {
            return ['', $nextIndex, $blockMultiline];
        }

        $separator = $separatorStart === null
            ? ''
            : $this->renderRangeDroppingLeadingLineEnding(
                $tokens,
                $separatorStart,
                (int) $separatorEnd,
                $context,
                $partials,
                $partialStack,
                $blockMultiline,
                $preserveBreakableSpaces,
            );

        return [implode($separator, $rendered), $nextIndex, $blockMultiline];
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @return array{0:int, 1:int, 2:?int, 3:?int, 4:int, 5:bool}
     */
    private function collectForSlices(array $tokens, int $start, int $end): array
    {
        $depth = 0;
        $separatorStart = null;
        $separatorEnd = null;
        $blockMultiline = $this->tokenStartsWithLineEnding($tokens, $start, $end);

        for ($index = $start; $index < $end; $index++) {
            $token = $tokens[$index];
            if ($token['type'] !== 'directive') {
                continue;
            }

            $directive = $token['value'];
            if ($this->startsControlBlock($directive)) {
                $depth++;
                continue;
            }

            if ($this->endsControlBlock($directive)) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }

                if ($directive === 'endfor') {
                    $bodyEnd = $separatorStart === null ? $index : $separatorStart - 1;
                    if ($separatorStart !== null) {
                        $separatorEnd = $index;
                    }

                    return [$start, $bodyEnd, $separatorStart, $separatorEnd, $index + 1, $blockMultiline];
                }

                throw new \UnexpectedValueException("Unexpected doctemplate control directive {$directive}");
            }

            if ($depth === 0 && $directive === 'sep' && $separatorStart === null) {
                $separatorStart = $index + 1;
            }
        }

        throw new \UnexpectedValueException('Unclosed doctemplate for block');
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderRangeDroppingLeadingLineEnding(
        array $tokens,
        int $start,
        int $end,
        array $context,
        array $partials,
        array $partialStack,
        bool $dropLeadingLineEnding,
        bool $preserveBreakableSpaces,
    ): string {
        if ($dropLeadingLineEnding) {
            $this->dropLeadingLineEndingAt($tokens, $start, $end);
        }

        return $this->renderRange($tokens, $start, $end, $context, $partials, $partialStack, $preserveBreakableSpaces);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function tokenStartsWithLineEnding(array $tokens, int $index, int $end): bool
    {
        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return false;
        }

        return $this->leadingLineEndingLength($tokens[$index]['value']) !== null;
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function dropLeadingLineEndingAt(array &$tokens, int $index, int $end): void
    {
        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return;
        }

        $length = $this->leadingLineEndingLength($tokens[$index]['value']);
        if ($length === null) {
            return;
        }

        $tokens[$index]['value'] = substr($tokens[$index]['value'], $length);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function dropStandaloneDirectiveLine(array &$tokens, int $index, int $end, string &$output, string $prefix): void
    {
        if ($prefix !== '' && str_ends_with($output, $prefix)) {
            $output = substr($output, 0, -strlen($prefix));
        }

        if ($index >= $end || !isset($tokens[$index]) || $tokens[$index]['type'] !== 'text') {
            return;
        }

        if (preg_match('/^[ \t]*(?:\r\n|\n|\r)/', $tokens[$index]['value'], $matches) !== 1) {
            return;
        }

        $tokens[$index]['value'] = substr($tokens[$index]['value'], strlen($matches[0]));
    }

    private function leadingLineEndingLength(string $value): ?int
    {
        if (str_starts_with($value, "\r\n")) {
            return 2;
        }

        if (str_starts_with($value, "\n") || str_starts_with($value, "\r")) {
            return 1;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderDirective(string $directive, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $partial = $this->parsePartialDirective($directive);
        if ($partial !== null) {
            return $this->renderPartialDirective($partial, $context, $partials, $partialStack, $preserveBreakableSpaces);
        }

        $appliedPartial = $this->parseAppliedPartialDirective($directive);
        if ($appliedPartial !== null) {
            return $this->renderAppliedPartialDirective($appliedPartial, $context, $partials, $partialStack, $preserveBreakableSpaces);
        }

        return $this->renderVariableDirective($directive, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderVariableDirective(string $directive, array $context): string
    {
        $expression = $this->parseVariableExpression($directive);
        $name = $expression['name'];
        if (in_array($name, ['if', 'else', 'elseif', 'endif', 'for', 'sep', 'endfor'], true)) {
            throw new \UnexpectedValueException("Reserved doctemplate keyword {$name} cannot be rendered as a variable");
        }

        $resolved = $this->resolveParsedExpression($expression, $context);
        if (!$resolved['exists']) {
            return '';
        }

        return $this->renderValue($resolved['value'], $expression['separator']);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $partial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderPartialDirective(array $partial, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $value = $this->renderPartial($partial['name'], $context, $partials, $partialStack, $preserveBreakableSpaces);
        foreach ($partial['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return $this->renderPartialValue($value, $partial['separator']);
    }

    /**
     * @param array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}} $appliedPartial
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderAppliedPartialDirective(array $appliedPartial, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        $resolved = $this->resolveParsedExpression($appliedPartial['variable'], $context);
        $iterations = $this->loopIterations($resolved['exists'], $resolved['value']);
        if ($iterations === []) {
            return '';
        }

        $baseExists = $this->resolve($appliedPartial['variable']['name'], $context)['exists'];
        $rendered = [];
        foreach ($iterations as $item) {
            $iterationContext = $this->contextForLoopIteration($context, $appliedPartial['variable']['name'], $item, $baseExists);
            $value = $this->renderPartial($appliedPartial['partial']['name'], $iterationContext, $partials, $partialStack, $preserveBreakableSpaces);
            foreach ($appliedPartial['partial']['pipes'] as $pipe) {
                $value = $this->applyPipe($pipe, $value);
            }

            $rendered[] = $this->renderPartialValue($value, null);
        }

        return implode($appliedPartial['partial']['separator'] ?? '', $rendered);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $partials
     * @param list<string> $partialStack
     */
    private function renderPartial(string $name, array $context, array $partials, array $partialStack, bool $preserveBreakableSpaces): string
    {
        if (!array_key_exists($name, $partials) || !is_string($partials[$name])) {
            throw new \UnexpectedValueException("Missing doctemplate partial {$name}");
        }

        if (count($partialStack) >= self::MAX_PARTIAL_DEPTH) {
            return '(loop)';
        }

        $rendered = $this->renderTemplate($partials[$name], $context, $partials, [...$partialStack, $name], $preserveBreakableSpaces);

        return $this->stripIncludedPartialFinalNewline($rendered);
    }

    private function stripIncludedPartialFinalNewline(string $value): string
    {
        return $this->stripSingleFinalNewline($value);
    }

    /**
     * @return ?string
     */
    private function controlVariable(string $directive, string $name): ?string
    {
        if (!preg_match('/^' . preg_quote($name, '/') . '\\((.+)\\)$/s', $directive, $matches)) {
            return null;
        }

        $expression = trim($matches[1], " \t");

        return $expression === '' ? null : $expression;
    }

    private function startsControlBlock(string $directive): bool
    {
        return $this->controlVariable($directive, 'if') !== null || $this->controlVariable($directive, 'for') !== null;
    }

    private function endsControlBlock(string $directive): bool
    {
        return $directive === 'endif' || $directive === 'endfor';
    }

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolve(string $path, array $context): array
    {
        $segments = explode('.', $path);
        $value = $context;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return ['exists' => false, 'value' => null];
        }

        return ['exists' => true, 'value' => $value];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveExpression(string $expression, array $context): array
    {
        return $this->resolveParsedExpression($this->parseVariableExpression($expression), $context);
    }

    /**
     * @param array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>} $expression
     * @param array<string, mixed> $context
     * @return array{exists:bool, value:mixed}
     */
    private function resolveParsedExpression(array $expression, array $context): array
    {
        $resolved = $this->resolve($expression['name'], $context);
        if (!$resolved['exists'] && $expression['pipes'] === []) {
            return $resolved;
        }

        $value = $resolved['exists'] ? $resolved['value'] : null;
        foreach ($expression['pipes'] as $pipe) {
            $value = $this->applyPipe($pipe, $value);
        }

        return ['exists' => $resolved['exists'] || $expression['pipes'] !== [], 'value' => $value];
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialDirective(string $expression): ?array
    {
        $partial = $this->parsePartialCallExpression($expression);
        if ($partial === null) {
            return null;
        }

        return $partial;
    }

    /**
     * @return array{variable:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}, partial:array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}}|null
     */
    private function parseAppliedPartialDirective(string $expression): ?array
    {
        $colon = $this->findAppliedPartialColon($expression);
        if ($colon === null) {
            return null;
        }

        $variableSource = trim(substr($expression, 0, $colon), " \t");
        $partialSource = trim(substr($expression, $colon + 1), " \t");
        if ($variableSource === '' || $partialSource === '') {
            return null;
        }

        $partial = $this->parsePartialCallExpression($partialSource);
        if ($partial === null) {
            return null;
        }

        return [
            'variable' => $this->parseVariableExpression($variableSource),
            'partial' => $partial,
        ];
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>>|null
     */
    private function parsePartialCallExpression(string $expression): ?array
    {
        if (!preg_match('/^([\\p{L}\\p{N}_.\\/\\\\-]+)\\(\\)(?:\\[(.*)\\])?(?:\\/(.*))?$/su', $expression, $matches)) {
            return null;
        }

        return [
            'name' => $this->normalizePartialName($matches[1]),
            'separator' => array_key_exists(2, $matches) ? $matches[2] : null,
            'pipes' => $this->parsePipeSuffix($matches[3] ?? '', $expression),
        ];
    }

    /**
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSuffix(string $pipeSource, string $expression): array
    {
        if ($pipeSource === '') {
            return [];
        }

        return $this->parsePipeSpecs($this->splitPipeExpression($pipeSource), $expression);
    }

    private function findAppliedPartialColon(string $expression): ?int
    {
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                continue;
            }

            if (!$inQuote && $char === ':' && $bracketDepth === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{name:string, separator:?string, pipes:list<array{name:string, args:list<int|string>}>}
     */
    private function parseVariableExpression(string $expression): array
    {
        [$expressionWithoutTrailingSeparator, $trailingSeparator] = $this->extractTrailingSeparator($expression);
        $parts = $this->splitPipeExpression($expressionWithoutTrailingSeparator);
        $base = array_shift($parts);
        if ($base === null || !preg_match('/^(.+?)(?:\\[(.*)\\])?$/s', $base, $matches)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
        }

        $name = $matches[1];
        $this->validateVariableName($name, $expression);

        return [
            'name' => $name,
            'separator' => $trailingSeparator ?? (array_key_exists(2, $matches) ? $matches[2] : null),
            'pipes' => $this->parsePipeSpecs($parts, $expression),
        ];
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private function extractTrailingSeparator(string $expression): array
    {
        $source = rtrim($expression, " \t");
        if ($source === '' || !str_ends_with($source, ']')) {
            return [$expression, null];
        }

        $inQuote = false;
        $escape = false;
        $separatorStart = null;
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            $char = $source[$index];
            if ($escape) {
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $separatorStart = $index;
                break;
            }
        }

        if ($separatorStart === null) {
            return [$expression, null];
        }

        $separator = substr($source, $separatorStart + 1, $length - $separatorStart - 2);
        if (str_contains($separator, ']')) {
            return [$expression, null];
        }

        return [rtrim(substr($source, 0, $separatorStart), " \t"), $separator];
    }

    private function validateVariableName(string $name, string $expression): void
    {
        $segments = explode('.', $name);
        if ($segments === [] || in_array('', $segments, true)) {
            throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
        }

        foreach ($segments as $offset => $segment) {
            if ($offset === 0 && $segment === 'it') {
                continue;
            }

            if (!$this->isVariableIdentifierPart($segment)) {
                throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
            }
        }
    }

    private function isVariableIdentifierPart(string $part): bool
    {
        if ($part === '') {
            return false;
        }

        if (in_array($part, ['if', 'else', 'endif', 'elseif', 'for', 'endfor', 'sep', 'it'], true)) {
            return false;
        }

        return preg_match('/^\\p{L}[\\p{L}\\p{N}_-]*$/u', $part) === 1;
    }

    /**
     * @param list<string> $pipeSpecs
     * @return list<array{name:string, args:list<int|string>}>
     */
    private function parsePipeSpecs(array $pipeSpecs, string $expression): array
    {
        $pipes = [];
        foreach ($pipeSpecs as $pipeSpec) {
            $pipeSpec = trim($pipeSpec, " \t");
            if ($pipeSpec === '') {
                throw new \UnexpectedValueException("Unsupported doctemplate directive {$expression}");
            }

            if (!preg_match('/^([A-Za-z][A-Za-z0-9_-]*)(?:\\s+(.+))?$/s', $pipeSpec, $pipeMatches)) {
                throw new \UnexpectedValueException("Unsupported doctemplate pipe {$pipeSpec}");
            }

            $pipeName = $pipeMatches[1];
            $argumentSource = isset($pipeMatches[2]) ? trim($pipeMatches[2]) : '';
            if (in_array($pipeName, ['left', 'right', 'center'], true)) {
                $pipes[] = [
                    'name' => $pipeName,
                    'args' => $this->parseBlockPipeArguments($pipeName, $argumentSource),
                ];
                continue;
            }

            if ($argumentSource !== '') {
                throw new \UnexpectedValueException("Unsupported parameterized doctemplate pipe {$pipeName}");
            }

            $pipes[] = [
                'name' => $pipeName,
                'args' => [],
            ];
        }

        return $pipes;
    }

    /**
     * @return list<int|string>
     */
    private function parseBlockPipeArguments(string $pipeName, string $source): array
    {
        if ($source === '') {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$pipeName}");
        }

        if (!preg_match('/^([0-9]+)(.*)$/s', $source, $matches)) {
            throw new \UnexpectedValueException("Expected integer parameter for doctemplate pipe {$pipeName}");
        }

        $width = (int) $matches[1];
        if ($width < 1) {
            throw new \UnexpectedValueException("Expected positive integer parameter for doctemplate pipe {$pipeName}");
        }

        $offset = 0;
        $remaining = ltrim($matches[2], " \t\r\n");
        $borders = [];
        while ($remaining !== '') {
            if ($remaining[0] !== '"') {
                throw new \UnexpectedValueException("Expected quoted border parameter for doctemplate pipe {$pipeName}");
            }

            $borders[] = $this->parseQuotedPipeString($remaining, $offset);
            $remaining = ltrim(substr($remaining, $offset), " \t\r\n");
            $offset = 0;
            if (count($borders) > 2) {
                throw new \UnexpectedValueException("Too many border parameters for doctemplate pipe {$pipeName}");
            }
        }

        return [$width, $borders[0] ?? '', $borders[1] ?? ''];
    }

    private function parseQuotedPipeString(string $source, int &$offset): string
    {
        $offset = 1;
        $value = '';
        $length = strlen($source);

        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '"') {
                $offset++;

                return $value;
            }

            if ($char === '\\') {
                $offset++;
                if ($offset >= $length) {
                    throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
                }

                $value .= $source[$offset];
                $offset++;
                continue;
            }

            $value .= $char;
            $offset++;
        }

        throw new \UnexpectedValueException('Unclosed doctemplate pipe quoted string');
    }

    /**
     * @return list<string>
     */
    private function splitPipeExpression(string $expression): array
    {
        $parts = [];
        $buffer = '';
        $bracketDepth = 0;
        $inQuote = false;
        $escape = false;
        $length = strlen($expression);

        for ($index = 0; $index < $length; $index++) {
            $char = $expression[$index];
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '[') {
                $bracketDepth++;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                $buffer .= $char;
                continue;
            }

            if (!$inQuote && $char === '/' && $bracketDepth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * @param array{name:string, args:list<int|string>} $pipe
     */
    private function applyPipe(array $pipe, mixed $value): mixed
    {
        return match ($pipe['name']) {
            'pairs' => $this->pipePairs($value),
            'uppercase' => $this->mapTextualValue($value, fn (string $text): string => $this->uppercase($text)),
            'lowercase' => $this->mapTextualValue($value, fn (string $text): string => $this->lowercase($text)),
            'length' => $this->pipeLength($value),
            'reverse' => $this->pipeReverse($value),
            'first' => is_array($value) && array_is_list($value) && $value !== [] ? $value[0] : $value,
            'last' => is_array($value) && array_is_list($value) && $value !== [] ? $value[array_key_last($value)] : $value,
            'rest' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 1) : $value,
            'allbutlast' => is_array($value) && array_is_list($value) && $value !== [] ? array_slice($value, 0, -1) : $value,
            'chomp' => $this->pipeChomp($value),
            'nowrap' => $this->pipeNowrap($value),
            'alpha' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeAlphaText($text)),
            'roman' => $this->mapTextualValue($value, fn (string $text): string => $this->pipeRomanText($text)),
            'left', 'right', 'center' => $this->pipeBlock($pipe['name'], $pipe['args'], $value),
            default => throw new \UnexpectedValueException("Unsupported doctemplate pipe {$pipe['name']}"),
        };
    }

    private function mapTextualValue(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->mapTextualValue($item, $callback);
            }

            return $mapped;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $callback((string) $value);
        }

        return $value;
    }

    private function pipeAlphaText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number < 1) {
            return $value;
        }

        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(ord('a') + ($number % 26)) . $label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    private function pipeRomanText(string $value): string
    {
        if (!preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }

        $number = (int) $value;
        if ($number < 1 || $number >= 4000) {
            return $value;
        }

        $roman = '';
        foreach ([
            1000 => 'm',
            900 => 'cm',
            500 => 'd',
            400 => 'cd',
            100 => 'c',
            90 => 'xc',
            50 => 'l',
            40 => 'xl',
            10 => 'x',
            9 => 'ix',
            5 => 'v',
            4 => 'iv',
            1 => 'i',
        ] as $decimal => $glyph) {
            while ($number >= $decimal) {
                $roman .= $glyph;
                $number -= $decimal;
            }
        }

        return $roman;
    }

    /**
     * @param list<int|string> $args
     */
    private function pipeBlock(string $alignment, array $args, mixed $value): mixed
    {
        if (!is_string($value) && !is_int($value) && !is_float($value) && $value !== null) {
            return $value;
        }

        $width = (int) ($args[0] ?? 0);
        if ($width < 1) {
            throw new \UnexpectedValueException("Missing integer parameter for doctemplate pipe {$alignment}");
        }

        $leftBorder = is_string($args[1] ?? null) ? $args[1] : '';
        $rightBorder = is_string($args[2] ?? null) ? $args[2] : '';
        $lines = preg_split('/\r\n|\n|\r/', str_replace(self::BREAKABLE_SPACE_MARKER, ' ', $value === null ? '' : (string) $value));
        if ($lines === false) {
            $lines = [$value === null ? '' : (string) $value];
        }

        $padded = [];
        foreach ($lines as $line) {
            $padded[] = $leftBorder . $this->padBlockLine($line, $width, $alignment) . $rightBorder;
        }

        return implode("\n", $padded);
    }

    private function padBlockLine(string $line, int $width, string $alignment): string
    {
        return UnicodeText::padDisplay($line, $width, $alignment);
    }

    private function pipePairs(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $pairs = [];
        if (array_is_list($value)) {
            foreach ($value as $index => $item) {
                $pairs[] = ['key' => $index + 1, 'value' => $item];
            }

            return $pairs;
        }

        $ordered = $value;
        ksort($ordered, SORT_STRING);

        foreach ($ordered as $key => $item) {
            $pairs[] = ['key' => $key, 'value' => $item];
        }

        return $pairs;
    }

    private function pipeLength(mixed $value): int
    {
        if (is_string($value)) {
            return $this->stringLength($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    private function pipeReverse(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->reverseString($value);
        }

        if (is_array($value) && array_is_list($value)) {
            return array_reverse($value);
        }

        return $value;
    }

    private function pipeChomp(mixed $value): mixed
    {
        if (is_array($value)) {
            $chomped = [];
            foreach ($value as $key => $item) {
                $chomped[$key] = $this->pipeChomp($item);
            }

            return $chomped;
        }

        if (is_string($value)) {
            return rtrim($value, "\r\n" . self::BREAKABLE_SPACE_MARKER);
        }

        return $value;
    }

    private function pipeNowrap(mixed $value): mixed
    {
        if (is_array($value)) {
            $nowrap = [];
            foreach ($value as $key => $item) {
                $nowrap[$key] = $this->pipeNowrap($item);
            }

            return $nowrap;
        }

        if (is_string($value)) {
            return str_replace(self::BREAKABLE_SPACE_MARKER, ' ', $value);
        }

        return $value;
    }

    private function uppercase(string $value): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        $count = preg_match_all('/./us', $value, $matches);
        if ($count !== false) {
            return $count;
        }

        return strlen($value);
    }

    private function reverseString(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return strrev($value);
        }

        return implode('', array_reverse($characters));
    }

    private function renderValue(mixed $value, ?string $separator): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return 'true';
            }

            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->renderValue($item, null);
            }

            return implode($separator ?? '', $parts);
        }

        if (is_bool($value)) {
            return $value ? 'true' : '';
        }

        if (is_string($value)) {
            return $this->stripSingleFinalNewline($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function renderPartialValue(mixed $value, ?string $separator): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $this->renderValue($value, $separator);
    }

    private function stripSingleFinalNewline(string $value): string
    {
        if (str_ends_with($value, "\r\n")) {
            return substr($value, 0, -2);
        }

        if (str_ends_with($value, "\n") || str_ends_with($value, "\r")) {
            return substr($value, 0, -1);
        }

        return $value;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                return true;
            }

            foreach ($value as $item) {
                if ($this->isTruthy($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value !== '';
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private function loopIterations(bool $exists, mixed $value): array
    {
        if (!$exists || $value === null) {
            return [];
        }

        if (is_array($value)) {
            if ($value === []) {
                return [];
            }

            return array_is_list($value) ? $value : [$value];
        }

        return [$value];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function contextForLoopIteration(array $context, string $path, mixed $item, bool $rebindPath): array
    {
        $next = $context;
        $next['it'] = $item;

        $segments = explode('.', $path);
        if (!$rebindPath || $segments[0] === 'it') {
            return $next;
        }

        $cursor = &$next;
        foreach ($segments as $offset => $segment) {
            if ($offset === count($segments) - 1) {
                $cursor[$segment] = $item;
                break;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor);

        return $next;
    }

    private function currentColumn(string $output): int
    {
        $lineStart = $this->lastLineEndingByteOffset($output);
        $line = $lineStart === null ? $output : substr($output, $lineStart + 1);

        return UnicodeText::displayWidth(str_replace("\t", ' ', $line));
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function automaticNestPrefix(array $tokens, int $index, int $end, string $output): ?string
    {
        $lineStart = strrpos($output, "\n");
        $prefix = $lineStart === false ? $output : substr($output, $lineStart + 1);
        if (trim($prefix, " \t") !== '') {
            return null;
        }

        for ($next = $index + 1; $next < $end; $next++) {
            $token = $tokens[$next];
            if ($token['type'] !== 'text') {
                return null;
            }

            $newline = strpos($token['value'], "\n");
            $beforeNewline = $newline === false ? $token['value'] : substr($token['value'], 0, $newline);
            if (trim($beforeNewline, " \t\r") !== '') {
                return null;
            }

            if ($newline !== false) {
                return $prefix;
            }
        }

        return $prefix;
    }

    private function appendRenderedChunk(string &$output, string $chunk, ?int &$pendingNestColumn, bool $templateText = false): void
    {
        if ($pendingNestColumn !== null) {
            if (strpbrk($chunk, "\r\n") !== false) {
                $chunk = $templateText
                    ? $this->nestTemplateTextChunk($chunk, $pendingNestColumn)
                    : $this->nestMultiline($chunk, str_repeat(' ', $pendingNestColumn));
                $pendingNestColumn = null;
            }

            $output .= $chunk;
            return;
        }

        $output .= $chunk;
    }

    private function nestMultiline(string $value, string $indent): string
    {
        if ($indent === '' || strpbrk($value, "\r\n") === false) {
            return $value;
        }

        return preg_replace('/(\r\n|\n|\r)(?!$)/', '$1' . $indent, $value) ?? $value;
    }

    private function nestTemplateTextChunk(string $value, int $column): string
    {
        $indent = str_repeat(' ', $column);
        $output = '';
        $offset = 0;
        $length = strlen($value);

        while ($offset < $length) {
            if (preg_match('/\r\n|\n|\r/', $value, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                $output .= substr($value, $offset);
                break;
            }

            $lineEnding = $matches[0][0];
            $lineEndingStart = $matches[0][1];
            $afterLineEnding = $lineEndingStart + strlen($lineEnding);
            $output .= substr($value, $offset, $afterLineEnding - $offset);

            if ($afterLineEnding >= $length) {
                break;
            }

            $indentEnd = $afterLineEnding;
            while ($indentEnd < $length && ($value[$indentEnd] === ' ' || $value[$indentEnd] === "\t")) {
                $indentEnd++;
            }

            $sourceIndent = substr($value, $afterLineEnding, $indentEnd - $afterLineEnding);
            if ($indentEnd < $length && ($value[$indentEnd] === "\r" || $value[$indentEnd] === "\n")) {
                $offset = $indentEnd;
                continue;
            }

            if (strlen($sourceIndent) < $column) {
                $output .= substr($value, $afterLineEnding);
                break;
            }

            $output .= $indent . $this->dropSourceIndentColumns($sourceIndent, $column);
            $offset = $indentEnd;
        }

        return $output;
    }

    private function dropSourceIndentColumns(string $indent, int $columns): string
    {
        $offset = 0;
        $length = strlen($indent);

        while ($offset < $length && $columns > 0) {
            if ($indent[$offset] !== ' ' && $indent[$offset] !== "\t") {
                break;
            }

            $offset++;
            $columns--;
        }

        return substr($indent, $offset);
    }

    /**
     * @param list<array{type:string, value:string}> $tokens
     */
    private function appendTextToken(array &$tokens, string $text, bool $breakableSpaces = false): void
    {
        if ($text !== '') {
            $tokens[] = ['type' => 'text', 'value' => $text, 'breakable' => $breakableSpaces];
        }
    }

    private function validateLineLength(int $lineLength): void
    {
        if ($lineLength < 1) {
            throw new \InvalidArgumentException('Doctemplate wrapped rendering requires a positive line length');
        }
    }

    private function wrapBreakableSpaces(string $value, int $lineLength): string
    {
        if (!str_contains($value, self::BREAKABLE_SPACE_MARKER)) {
            return $value;
        }

        $parts = explode(self::BREAKABLE_SPACE_MARKER, $value);
        $output = '';
        $column = 0;

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $nextWidth = $this->leadingSegmentWidth($part);
                if ($column > 0 && $column + 1 + $nextWidth > $lineLength) {
                    $output .= "\n";
                    $column = 0;
                } elseif ($column > 0) {
                    $output .= ' ';
                    $column++;
                }
            }

            $this->appendWrappedSegment($output, $column, $part);
        }

        return $output;
    }

    private function leadingSegmentWidth(string $value): int
    {
        if (preg_match('/\r\n|\n|\r/', $value, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $value = substr($value, 0, $matches[0][1]);
        }

        return UnicodeText::displayWidth(str_replace("\t", ' ', $value));
    }

    private function appendWrappedSegment(string &$output, int &$column, string $segment): void
    {
        $offset = 0;
        $length = strlen($segment);

        while ($offset < $length) {
            if (preg_match('/\r\n|\n|\r/', $segment, $matches, PREG_OFFSET_CAPTURE, $offset) !== 1) {
                $chunk = substr($segment, $offset);
                $output .= $chunk;
                $column += UnicodeText::displayWidth(str_replace("\t", ' ', $chunk));
                return;
            }

            $lineEnding = $matches[0][0];
            $lineEndingStart = $matches[0][1];
            $chunk = substr($segment, $offset, $lineEndingStart - $offset);
            $output .= $chunk . $lineEnding;
            $column = 0;
            $offset = $lineEndingStart + strlen($lineEnding);
        }
    }

    private function normalizeBreakableSpaces(string $text, string $replacement = ' '): string
    {
        return preg_replace('/[ \t\r\n]+/', $replacement, $text) ?? $text;
    }
}
