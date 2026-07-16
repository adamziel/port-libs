<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'normalizes pandoc and skylighting language aliases and styles' => static function (TestRunner $t): void {
        $node = new AstNode('code_block', [
            'classes' => ['sourceCode', 'numberLines', 'language-php'],
            'attributes' => [],
            'text' => 'echo "ok";',
        ]);
        $attributeNode = new AstNode('code_block', [
            'classes' => [],
            'attributes' => ['data-language' => 'YML'],
            'text' => 'title: Review',
        ]);
        $lineNumberNode = new AstNode('code_block', [
            'classes' => ['sourceCode', 'number-lines', 'line-anchors', 'php'],
            'attributes' => [],
            'text' => 'echo "ok";',
        ]);

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($node));
        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($lineNumberNode));
        $t->same('haskell', SyntaxHighlighter::normalizeLanguage('lhs'));
        $t->same('haskell', SyntaxHighlighter::normalizeLanguage('literate-haskell'));
        $t->same('idris', SyntaxHighlighter::normalizeLanguage('idris'));
        $t->same('idris', SyntaxHighlighter::normalizeLanguage('idr'));
        $t->same('idris', SyntaxHighlighter::normalizeLanguage('idris2'));
        $t->same('idris', SyntaxHighlighter::normalizeLanguage('language-idris-source'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('agda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('agda2'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('lagda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('literate-agda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('language-agda-source'));
        $t->same('diff', SyntaxHighlighter::normalizeLanguage('patch'));
        $t->same('diff', SyntaxHighlighter::normalizeLanguage('unified-diff'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('Dockerfile'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('Containerfile'));
        $t->same('dockerfile', SyntaxHighlighter::normalizeLanguage('language-docker'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('dart'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('dartlang'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('flutter'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('language-dartlang'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('D'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('dlang'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('d-source'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('language-d-language'));
        $t->same('fish', SyntaxHighlighter::normalizeLanguage('fish'));
        $t->same('fish', SyntaxHighlighter::normalizeLanguage('fish-shell'));
        $t->same('fish', SyntaxHighlighter::normalizeLanguage('language-fish'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('fortran'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('fortran-free'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('fortran-fixed'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('f90'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('language-f08'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('fsharp'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('F#'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('fs'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('fsx'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('language-fsharp-source'));
        $t->same('sed', SyntaxHighlighter::normalizeLanguage('sed'));
        $t->same('sed', SyntaxHighlighter::normalizeLanguage('gsed'));
        $t->same('sed', SyntaxHighlighter::normalizeLanguage('gnu-sed'));
        $t->same('sed', SyntaxHighlighter::normalizeLanguage('stream-editor'));
        $t->same('sed', SyntaxHighlighter::normalizeLanguage('language-sed'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swift'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swiftui'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swift-source'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('language-swift-source'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('dot'));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('elm'));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('language-elm-module'));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('elm.source'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('elixir'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('ex'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('exs'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('language-ex'));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('fennel'));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('fnl'));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('language-fennel-lang'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('graphviz'));
        $t->same('dot', SyntaxHighlighter::normalizeLanguage('gv'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apache'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apacheconf'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apache-config'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('htaccess'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('httpd-conf'));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('asciidoc'));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('adoc'));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('asc'));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('language-asciidoctor'));
        $t->same('awk', SyntaxHighlighter::normalizeLanguage('awk'));
        $t->same('awk', SyntaxHighlighter::normalizeLanguage('gawk'));
        $t->same('awk', SyntaxHighlighter::normalizeLanguage('mawk'));
        $t->same('awk', SyntaxHighlighter::normalizeLanguage('language-awk-script'));
        $t->same('batch', SyntaxHighlighter::normalizeLanguage('bat'));
        $t->same('batch', SyntaxHighlighter::normalizeLanguage('batchfile'));
        $t->same('batch', SyntaxHighlighter::normalizeLanguage('cmd'));
        $t->same('batch', SyntaxHighlighter::normalizeLanguage('cmd.exe'));
        $t->same('batch', SyntaxHighlighter::normalizeLanguage('language-dosbatch'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('bibtex'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('biblatex'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('bib'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('language-biblatex'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rst'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rest'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('reStructuredText'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('language-restructured-text'));
        $t->same('html', SyntaxHighlighter::normalizeLanguage('HTML5'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('mustache'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('handlebars'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('hbs'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('ractive'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('html.mst'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('language-js'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('mjs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('cjs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('node'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('nodejs'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('ecmascript'));
        $t->same('javascript', SyntaxHighlighter::normalizeLanguage('es6'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('jsonc'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('json5'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('json-with-comments'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('language-json.comments'));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('julia'));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('jl'));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('language-julia-source'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kotlin'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kt'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kts'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kotlin-script'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('language-kotlinscript'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('jsx'));
        $t->same('jsx', SyntaxHighlighter::normalizeLanguage('javascript-react'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('c'));
        $t->same('c', SyntaxHighlighter::normalizeLanguage('h'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('c++'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cpp'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('cxx'));
        $t->same('cpp', SyntaxHighlighter::normalizeLanguage('hpp'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('crystal'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('cr'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('crystal-lang'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('language-crystal-source'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('cs'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('csharp'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('C#'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('csx'));
        $t->same('csharp', SyntaxHighlighter::normalizeLanguage('language-cs'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('clojure'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('clj'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('cljc'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('cljs'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('edn'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('language-clj'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('coq'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('coq-script'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('gallina'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('rocq'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('language-rocq-prover'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('common-lisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('commonlisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('lisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('language-cl'));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('scala'));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('sbt'));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('language-scala-sbt'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('cmake'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('CMakeLists.txt'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('language-cmake'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('latex'));
        $t->same('tex', SyntaxHighlighter::normalizeLanguage('TeX'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('ini'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('cfg'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('gitconfig'));
        $t->same('ini', SyntaxHighlighter::normalizeLanguage('editorconfig'));
        $t->same('toml', SyntaxHighlighter::normalizeLanguage('toml'));
        $t->same('toml', SyntaxHighlighter::normalizeLanguage('Cargo.lock'));
        $t->same('yaml', SyntaxHighlighter::normalizeLanguage('yml'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('md'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('pandoc-markdown'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('commonmark'));
        $t->same('markdown', SyntaxHighlighter::normalizeLanguage('gfm'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid-js'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('language-mermaidjs'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('go'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('golang'));
        $t->same('go', SyntaxHighlighter::normalizeLanguage('language-go'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('groovy'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('gvy'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('gradle'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('Jenkinsfile'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('language-gradle-groovy'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('graphql'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('gql'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('graphql-schema'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('language-graphql-query'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('hcl'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('terraform'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('tf'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('language-tfvars'));
        $t->same('liquid', SyntaxHighlighter::normalizeLanguage('liquid'));
        $t->same('liquid', SyntaxHighlighter::normalizeLanguage('shopify'));
        $t->same('liquid', SyntaxHighlighter::normalizeLanguage('liquid-html'));
        $t->same('liquid', SyntaxHighlighter::normalizeLanguage('language-html-liquid'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix-expr'));
        $t->same('nix', SyntaxHighlighter::normalizeLanguage('nix-shell'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginxconf'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx-config'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('language-nginx'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nim'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nimrod'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nims'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('language-nim-source'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('ocaml'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('ml'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('mli'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('reason'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('reasonml'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('language-ocaml-interface'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('make'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('makefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('GNUmakefile'));
        $t->same('makefile', SyntaxHighlighter::normalizeLanguage('mk'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('perl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pl'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('PL'));
        $t->same('perl', SyntaxHighlighter::normalizeLanguage('pm'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('powershell'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('posh'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('ps1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('psd1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('psm1'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('pwsh'));
        $t->same('powershell', SyntaxHighlighter::normalizeLanguage('language-ps1'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('proto'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('protobuf'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('protocol-buffer'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('language-protobuf'));
        $t->same('java', SyntaxHighlighter::normalizeLanguage('java'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('xml'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('svg'));
        $t->same('xml', SyntaxHighlighter::normalizeLanguage('xsd'));
        $t->same('xslt', SyntaxHighlighter::normalizeLanguage('xsl'));
        $t->same('xslt', SyntaxHighlighter::normalizeLanguage('xslt'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rb'));
        $t->same('ruby', SyntaxHighlighter::normalizeLanguage('rake'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rs'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rust'));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('language-rs'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('scheme'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('scm'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('racket'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('rkt'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('language-racket'));
        $t->same('scss', SyntaxHighlighter::normalizeLanguage('scss'));
        $t->same('scss', SyntaxHighlighter::normalizeLanguage('language-scss'));
        $t->same('sass', SyntaxHighlighter::normalizeLanguage('sass'));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('less'));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('less-css'));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('language-lesscss'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('lua'));
        $t->same('lua', SyntaxHighlighter::normalizeLanguage('pandoc-lua'));
        $t->same('matlab', SyntaxHighlighter::normalizeLanguage('matlab'));
        $t->same('matlab', SyntaxHighlighter::normalizeLanguage('octave'));
        $t->same('matlab', SyntaxHighlighter::normalizeLanguage('gnu-octave'));
        $t->same('matlab', SyntaxHighlighter::normalizeLanguage('language-m-file'));
        $t->same('matlab', SyntaxHighlighter::normalizeLanguage('m'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('bash'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('sh'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('shell'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('console'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('language-sh'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('zsh'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('zshrc'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('language-zsh-script'));
        $t->same('shellsession', SyntaxHighlighter::normalizeLanguage('shell-session'));
        $t->same('shellsession', SyntaxHighlighter::normalizeLanguage('shellsession'));
        $t->same('shellsession', SyntaxHighlighter::normalizeLanguage('bash-session'));
        $t->same('shellsession', SyntaxHighlighter::normalizeLanguage('language-console-session'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('py3'));
        $t->same('python', SyntaxHighlighter::normalizeLanguage('python3'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('r'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('Rscript'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('S'));
        $t->same('r', SyntaxHighlighter::normalizeLanguage('language-q'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('raku'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('perl6'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('pl6'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('rakumod'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('language-rakutest'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('ts'));
        $t->same('typescript', SyntaxHighlighter::normalizeLanguage('typescript'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('tsx'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('typescript-react'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('language-tsx'));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('typst'));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('typ'));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('language-typst-source'));
        $t->same('v', SyntaxHighlighter::normalizeLanguage('v'));
        $t->same('v', SyntaxHighlighter::normalizeLanguage('vlang'));
        $t->same('v', SyntaxHighlighter::normalizeLanguage('v-source'));
        $t->same('v', SyntaxHighlighter::normalizeLanguage('language-v-language'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('tcl'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('tclsh'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('Tcl/Tk'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('language-expect'));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('vue'));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('vue-sfc'));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('language-html-vue'));
        $t->same('vim', SyntaxHighlighter::normalizeLanguage('vim'));
        $t->same('vim', SyntaxHighlighter::normalizeLanguage('vimscript'));
        $t->same('vim', SyntaxHighlighter::normalizeLanguage('viml'));
        $t->same('vim', SyntaxHighlighter::normalizeLanguage('language-vim-script'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('sourceCode'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('lineAnchors'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('number-lines'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('tokenTitles'));
        $t->same(null, SyntaxHighlighter::normalizeLanguage('token-titles'));
        $t->same('breezedark', SyntaxHighlighter::normalizeStyle('breezeDark'));
        $t->same('pygments', SyntaxHighlighter::normalizeStyle('unknown-theme'));
        $t->same('yaml', (new SyntaxHighlighter())->highlightCodeBlock($attributeNode)['language']);
    },
    'highlights php code blocks from markdown fixture without invoking pandoc' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[0];
        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');

        $t->same('code_block', $codeBlock->type);
        $t->same('php', $highlighted['language']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode php"><code class="sourceCode php">', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span>', $highlighted['html']);
        $t->contains('<span class="fu">render_title</span>', $highlighted['html']);
        $t->contains('<span class="va">$post</span>', $highlighted['html']);
        $t->contains('<span class="st">&#039;title&#039;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress-safe title</span>', $highlighted['html']);
        $t->contains('.sourceCode .kw', $highlighted['css']);
    },
    'hands highlighted code to wordpress html blocks with style metadata' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['php'],
            'attributes' => [],
            'text' => "<?php\necho esc_html(\$title);",
        ]);
        $block = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');

        $t->contains('<!-- wp:html -->', $block);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $block);
        $t->contains('.sourceCode .kw', $block);
        $t->contains('<pre class="sourceCode php"><code class="sourceCode php">', $block);
        $t->contains('<span class="kw">echo</span>', $block);
        $t->contains('<span class="fu">esc_html</span>', $block);
        $t->contains('<!-- /wp:html -->', $block);
    },
    'renders pandoc numbered source lines and anchors from code block attributes' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'migration-review',
            'classes' => ['php', 'numberLines', 'lineAnchors'],
            'attributes' => ['startFrom' => '42'],
            'text' => "<?php\necho esc_html(\$title);",
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');

        $t->same('php', $highlighted['language']);
        $t->same('kate', $highlighted['style']);
        $t->same([
            'enabled' => true,
            'anchors' => true,
            'start' => 42,
            'lineIdPrefix' => 'migration-review-',
        ], $highlighted['lineNumbering']);
        $t->contains('<div class="sourceCode"><pre class="sourceCode numberSource php numberLines lineAnchors"><code class="sourceCode php" style="counter-reset: source-line 41;">', $highlighted['html']);
        $t->contains('<span id="migration-review-42"><a href="#migration-review-42"></a><span class="pp">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="migration-review-43"><a href="#migration-review-43"></a><span class="kw">echo</span> <span class="fu">esc_html</span>', $highlighted['html']);
        $t->contains('pre.numberSource code > span', $highlighted['css']);
        $t->contains('counter(source-line)', $highlighted['css']);

        $anchorOnly = (new SyntaxHighlighter())->highlight('echo "ok";', 'php', 'pygments', [
            'id' => 'anchor-only',
            'classes' => ['line-anchors'],
        ]);

        $t->same(false, $anchorOnly['lineNumbering']['enabled']);
        $t->same(true, $anchorOnly['lineNumbering']['anchors']);
        $t->contains('<pre class="sourceCode line-anchors"><code class="sourceCode php">', $anchorOnly['html']);
        $t->contains('<span id="anchor-only-1"><a href="#anchor-only-1" aria-hidden="true" tabindex="-1"></a><span class="kw">echo</span>', $anchorOnly['html']);
    },
    'preserves pandoc line highlight ranges for wordpress review packets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[81] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a line-highlighted PHP code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $direct = $highlighter->highlight('echo "draft";' . "\n" . 'echo "publish";', 'php', 'pygments', [
            'id' => 'manual-review',
            'attributes' => ['highlight-lines' => '2'],
        ]);
        $absolute = $highlighter->highlight('first' . "\n" . 'second', 'unknown-review-language', 'pygments', [
            'id' => 'absolute-review',
            'attributes' => [
                'startFrom' => '40',
                'highlight-lines' => '41',
                'highlight-lines-absolute' => 'true',
            ],
        ]);

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('php', $highlighted['language']);
        $t->same(1280, $highlighted['lineNumbering']['start']);
        $t->same([1281, 1283, 1284], $highlighted['highlightLines']);
        $t->contains('<pre class="sourceCode numberSource php numberLines"><code class="sourceCode php" style="counter-reset: source-line 1279;">', $highlighted['html']);
        $t->contains('<span id="line-highlight-review-1280"><a href="#line-highlight-review-1280"></a><span class="pp">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="line-highlight-review-1281" class="highlighted-line" data-pandoc-line-highlight="1281"><a href="#line-highlight-review-1281"></a><span class="va">$title</span>', $highlighted['html']);
        $t->contains('<span id="line-highlight-review-1283" class="highlighted-line" data-pandoc-line-highlight="1283"><a href="#line-highlight-review-1283"></a>    <span class="va">$title</span>', $highlighted['html']);
        $t->contains('<span id="line-highlight-review-1284" class="highlighted-line" data-pandoc-line-highlight="1284"><a href="#line-highlight-review-1284"></a><span class="op">}</span></span>', $highlighted['html']);
        $t->same(false, str_contains($highlighted['html'], 'data-pandoc-line-highlight="1282"'));
        $t->contains('.sourceCode .highlighted-line', $highlighted['css']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('data-pandoc-line-highlight="1283"', $wordpressBlock);
        $t->contains('<span class="fu">esc_html</span>', $wordpressBlock);
        $t->same([2], $direct['highlightLines']);
        $t->same(false, $direct['lineNumbering']['enabled']);
        $t->contains('<pre class="sourceCode"><code class="sourceCode php">', $direct['html']);
        $t->contains('<span id="manual-review-2" class="highlighted-line" data-pandoc-line-highlight="2"><a href="#manual-review-2" aria-hidden="true" tabindex="-1"></a><span class="kw">echo</span> <span class="st">&quot;publish&quot;</span>', $direct['html']);
        $t->same([41], $absolute['highlightLines']);
        $t->contains('<span id="absolute-review-41" class="highlighted-line" data-pandoc-line-highlight="41"><a href="#absolute-review-41" aria-hidden="true" tabindex="-1"></a>second</span>', $absolute['html']);
    },
    'renders opt in token title attributes for reviewer metadata' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[20] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a token-title PHP code block');
        }

        $highlighter = new SyntaxHighlighter();
        $plain = $highlighter->highlight('<?php echo esc_html($title);', 'php');
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $attributeEnabled = $highlighter->highlight(
            'echo esc_html($title);',
            'php',
            'pygments',
            ['attributes' => ['data-token-titles' => 'true']]
        );

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same(false, $plain['tokenTitles']);
        $t->same(false, str_contains($plain['html'], 'title="KeywordTok"'));
        $t->same('php', $highlighted['language']);
        $t->same(true, $highlighted['tokenTitles']);
        $t->same(3, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines tokenTitles"><code class="sourceCode php" style="counter-reset: source-line 2;">', $highlighted['html']);
        $t->contains('<span id="token-title-review-3"><a href="#token-title-review-3"></a><span class="pp" title="PreprocessorTok">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="token-title-review-4"><a href="#token-title-review-4"></a><span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>', $highlighted['html']);
        $t->contains('<span class="va" title="VariableTok">$title</span><span class="op" title="OperatorTok">);</span> <span class="co" title="CommentTok">// reviewer token titles</span>', $highlighted['html']);
        $t->same(true, $attributeEnabled['tokenTitles']);
        $t->contains('<span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>', $attributeEnabled['html']);
    },
    'preserves numbered plain text fallback for unsupported languages' => static function (TestRunner $t): void {
        $highlighted = (new SyntaxHighlighter())->highlight(
            "legacy << token\nsecond line",
            'unknown-review-language',
            'pygments',
            [
                'id' => 'raw-code',
                'classes' => ['number-lines'],
                'attributes' => ['startFrom' => '7'],
            ]
        );

        $t->same('', $highlighted['language']);
        $t->same('unsupported-language', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same(true, $highlighted['lineNumbering']['enabled']);
        $t->same(false, $highlighted['lineNumbering']['anchors']);
        $t->contains('<pre class="sourceCode numberSource number-lines"><code class="sourceCode" style="counter-reset: source-line 6;">', $highlighted['html']);
        $t->contains('<span id="raw-code-7"><a href="#raw-code-7"></a>legacy &lt;&lt; token</span>', $highlighted['html']);
        $t->contains('<span id="raw-code-8"><a href="#raw-code-8"></a>second line</span>', $highlighted['html']);
    },
    'highlights json and yaml keys scalars comments and punctuation' => static function (TestRunner $t): void {
        $highlighter = new SyntaxHighlighter();
        $json = $highlighter->highlight('{"title":"Legacy post","draft":false,"count":2}', 'json');
        $yaml = $highlighter->highlight("---\ntitle: \"Legacy post\"\ndraft: false\n# review note\n", 'yaml');

        $t->same('json', $json['language']);
        $t->contains('<span class="ot">&quot;title&quot;</span><span class="op">:</span><span class="st">&quot;Legacy post&quot;</span>', $json['html']);
        $t->contains('<span class="cn">false</span>', $json['html']);
        $t->contains('<span class="dv">2</span>', $json['html']);
        $t->same('yaml', $yaml['language']);
        $t->contains('<span class="op">---</span>', $yaml['html']);
        $t->contains('<span class="ot">title</span><span class="op">:</span> <span class="st">&quot;Legacy post&quot;</span>', $yaml['html']);
        $t->contains('<span class="co"># review note</span>', $yaml['html']);
    },
    'highlights css block theme snippets with at rules selectors and custom properties' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[21] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a CSS code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directCss = (new SyntaxHighlighter())->highlight(
            '@supports (display: grid) { #site-header::before { content: "Review"; } }',
            'css'
        );

        $t->same('css', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('css', $highlighted['language']);
        $t->same('css', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(70, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource css numberLines"><code class="sourceCode css" style="counter-reset: source-line 69;">', $highlighted['html']);
        $t->contains('<span id="css-review-70"><a href="#css-review-70"></a><span class="co">/* WordPress block style review */</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span> <span class="op">&gt;</span> <span class="dt">a</span><span class="fu">:hover</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span><span class="fu">:focus-visible</span>', $highlighted['html']);
        $t->contains('<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">margin-block</span><span class="op">:</span> <span class="dv">1.5rem</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">)</span> <span class="kw">!important</span>', $highlighted['html']);
        $t->contains('<span class="ot">content</span><span class="op">:</span> <span class="st">&quot;Read more&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="cn">#005cc5</span>', $wordpressBlock);
        $t->same('css', $directCss['language']);
        $t->contains('<span class="kw">@supports</span> <span class="op">(</span><span class="ot">display</span><span class="op">:</span> <span class="kw">grid</span><span class="op">)</span>', $directCss['html']);
        $t->contains('<span class="dt">#site-header</span><span class="fu">::before</span>', $directCss['html']);
    },
    'highlights modern css cascade layer and container review snippets' => static function (TestRunner $t): void {
        $source = '@layer imports { .review:has(a) { display: subgrid; color: color-mix(in oklch, oklch(60% 0.2 240), transparent); overflow-position: safe; } }';
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'css');

        $t->same('css', $highlighted['language']);
        $t->contains('<span class="kw">@layer</span> <span class="dt">imports</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">.review</span><span class="fu">:has</span><span class="op">(</span>a<span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">display</span><span class="op">:</span> <span class="kw">subgrid</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="fu">color-mix</span><span class="op">(</span><span class="dt">in</span> <span class="dt">oklch</span>', $highlighted['html']);
        $t->contains('<span class="fu">oklch</span><span class="op">(</span><span class="dv">60%</span> <span class="dv">0.2</span> <span class="dv">240</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="ot">overflow-position</span><span class="op">:</span> <span class="kw">safe</span><span class="op">;</span>', $highlighted['html']);
    },
    'highlights css percent units as numeric values in review snippets' => static function (TestRunner $t): void {
        $source = implode("\n", [
            ':root { --hero-width: min(100%, 48rem); }',
            '@container (inline-size > 60%) { .wp-block-cover { opacity: .75; } }',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'css');

        $t->same('css', $highlighted['language']);
        $t->contains('<span class="ot">--hero-width</span><span class="op">:</span> <span class="fu">min</span><span class="op">(</span><span class="dv">100%</span><span class="op">,</span> <span class="dv">48rem</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@container</span> <span class="op">(</span><span class="dt">inline-size</span> <span class="op">&gt;</span> <span class="dv">60%</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">opacity</span><span class="op">:</span> <span class="dv">.75</span><span class="op">;</span>', $highlighted['html']);
    },
    'highlights rust review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[22] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Rust code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directRust = (new SyntaxHighlighter())->highlight('let block: Option<&str> = Some(r#"ok"#);', 'rust');

        $t->same('rs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('rust', SyntaxHighlighter::normalizeLanguage('rs'));
        $t->same('rust', $highlighted['language']);
        $t->same('rs', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(88, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource rs numberLines"><code class="sourceCode rust" style="counter-reset: source-line 87;">', $highlighted['html']);
        $t->contains('<span id="rust-review-88"><a href="#rust-review-88"></a><span class="co">// WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">use</span> <span class="va">serde_json</span><span class="op">::</span><span class="dt">Value</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">#[derive(Debug)]</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="va">title</span><span class="op">:</span> <span class="dt">Option</span><span class="op">&lt;&amp;</span><span class="ot">&#039;a</span> <span class="dt">str</span><span class="op">&gt;,</span>', $highlighted['html']);
        $t->contains('<span class="va">source_id</span><span class="op">:</span> <span class="dt">u64</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="kw">impl</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span> <span class="dt">ReviewPacket</span><span class="op">&lt;</span><span class="ot">&#039;a</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="kw">fn</span> <span class="fu">normalized_title</span><span class="op">(&amp;</span><span class="kw">self</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">title</span> <span class="op">=</span> <span class="kw">self</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">unwrap_or</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">title</span><span class="op">.</span><span class="fu">trim</span><span class="op">().</span><span class="fu">is_empty</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">format!</span><span class="op">(</span><span class="st">&quot;import-{}&quot;</span><span class="op">,</span> <span class="kw">self</span><span class="op">.</span><span class="va">source_id</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">.</span><span class="fu">to_string</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="fu">format!</span><span class="op">(</span><span class="st">&quot;import-{}&quot;</span>', $wordpressBlock);
        $t->same('rust', $directRust['language']);
        $t->contains('<span class="kw">let</span> <span class="va">block</span><span class="op">:</span> <span class="dt">Option</span><span class="op">&lt;&amp;</span><span class="dt">str</span><span class="op">&gt;</span> <span class="op">=</span> <span class="cn">Some</span><span class="op">(</span><span class="st">r#&quot;ok&quot;#</span><span class="op">);</span>', $directRust['html']);
    },
    'highlights rust raw identifiers and reserved keyword review snippets' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'pub union RawPacket { r#type: u64 }',
            'let r#type = packet.r#type()?;',
            'try { yield r#type }',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'rust');

        $t->same('rust', $highlighted['language']);
        $t->contains('<span class="kw">pub</span> <span class="kw">union</span> <span class="dt">RawPacket</span>', $highlighted['html']);
        $t->contains('<span class="va">r#type</span><span class="op">:</span> <span class="dt">u64</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">r#type</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">.</span><span class="fu">r#type</span><span class="op">()?;</span>', $highlighted['html']);
        $t->contains('<span class="kw">try</span> <span class="op">{</span> <span class="kw">yield</span> <span class="va">r#type</span>', $highlighted['html']);
    },
    'highlights nix deployment review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[23] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Nix code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directNix = (new SyntaxHighlighter())->highlight('{ lib ? import <nixpkgs/lib> }: rec { enabled = true; }', 'nix-expr');

        $t->same('nix', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('nix', $highlighted['language']);
        $t->same('nix', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(101, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource nix numberLines"><code class="sourceCode nix" style="counter-reset: source-line 100;">', $highlighted['html']);
        $t->contains('<span id="nix-review-101"><a href="#nix-review-101"></a><span class="co"># WordPress deployment expression review</span></span>', $highlighted['html']);
        $t->contains('<span class="op">{</span> <span class="va">pkgs</span> <span class="op">?</span> <span class="fu">import</span> <span class="cn">&lt;nixpkgs&gt;</span> <span class="op">{}</span> <span class="op">}:</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span>', $highlighted['html']);
        $t->contains('<span class="kw">inherit</span> <span class="op">(</span><span class="va">pkgs</span><span class="op">)</span> <span class="va">stdenv</span> <span class="va">writeText</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">pluginSlug</span> <span class="op">=</span> <span class="st">&quot;legacy-import&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">mediaPaths</span> <span class="op">=</span> <span class="op">[</span> <span class="st">./uploads</span> <span class="st">./assets</span> <span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="ot">reviewer</span> <span class="op">=</span> <span class="kw">if</span> <span class="va">stdenv</span><span class="op">.</span><span class="va">isLinux</span> <span class="kw">then</span> <span class="st">&quot;wp-cli&quot;</span> <span class="kw">else</span> <span class="st">&quot;manual&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">in</span>', $highlighted['html']);
        $t->contains('<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span> <span class="st">&quot;${pluginSlug}-review.json&quot;</span> <span class="st">&#039;&#039;', $highlighted['html']);
        $t->contains('<span class="st">  {&quot;reviewer&quot;:&quot;${reviewer}&quot;,&quot;media&quot;:${builtins.toJSON mediaPaths}}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span>', $wordpressBlock);
        $t->same('nix', $directNix['language']);
        $t->same('nix-expr', $directNix['requestedLanguage']);
        $t->contains('<span class="op">{</span> <span class="va">lib</span> <span class="op">?</span> <span class="fu">import</span> <span class="cn">&lt;nixpkgs/lib&gt;</span>', $directNix['html']);
        $t->contains('<span class="kw">rec</span> <span class="op">{</span> <span class="ot">enabled</span> <span class="op">=</span> <span class="cn">true</span><span class="op">;</span>', $directNix['html']);
    },
    'highlights scss block theme snippets with sass aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[24] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an SCSS code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directSass = (new SyntaxHighlighter())->highlight(implode("\n", [
            '@use "sass:color"',
            '$gap: 1rem',
            '.wp-block',
            '  margin: $gap',
        ]), 'sass');

        $t->same('scss', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('scss', $highlighted['language']);
        $t->same('scss', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(120, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource scss numberLines"><code class="sourceCode scss" style="counter-reset: source-line 119;">', $highlighted['html']);
        $t->contains('<span id="scss-review-120"><a href="#scss-review-120"></a><span class="co">// WordPress theme Sass review</span></span>', $highlighted['html']);
        $t->contains('<span class="va">$accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span> <span class="kw">!default</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$breakpoints</span><span class="op">:</span> <span class="op">(</span><span class="st">&quot;desktop&quot;</span><span class="op">:</span> <span class="dv">48rem</span>', $highlighted['html']);
        $t->contains('<span class="kw">@mixin</span> <span class="fu">import-card</span><span class="op">(</span><span class="va">$selector</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">#{</span><span class="va">$selector</span><span class="op">}</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">color</span><span class="op">:</span> <span class="va">$accent-color</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="op">&amp;</span><span class="fu">:hover</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">darken</span><span class="op">(</span><span class="va">$accent-color</span><span class="op">,</span> <span class="dv">10%</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@include</span> <span class="fu">import-card</span><span class="op">(</span><span class="st">&quot;.wp-block-import-card&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="kw">@include</span> <span class="fu">import-card</span>', $wordpressBlock);
        $t->same('sass', $directSass['language']);
        $t->same('sass', $directSass['requestedLanguage']);
        $t->contains('<span class="kw">@use</span> <span class="st">&quot;sass:color&quot;</span>', $directSass['html']);
        $t->contains('<span class="va">$gap</span><span class="op">:</span> <span class="dv">1rem</span>', $directSass['html']);
        $t->contains('<span class="dt">.wp-block</span>', $directSass['html']);
        $t->contains('<span class="ot">margin</span><span class="op">:</span> <span class="va">$gap</span>', $directSass['html']);
    },
    'highlights go review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[25] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Go code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $directGo = (new SyntaxHighlighter())->highlight('go func() { defer close(done); done <- "ok" }()', 'golang');

        $t->same('go', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('go', $highlighted['language']);
        $t->same('go', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(135, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource go numberLines"><code class="sourceCode go" style="counter-reset: source-line 134;">', $highlighted['html']);
        $t->contains('<span id="go-review-135"><a href="#go-review-135"></a><span class="co">// WordPress import packet normalizer</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="va">review</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;context&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">struct</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">Title</span> <span class="dt">string</span> <span class="st">`json:&quot;title&quot;`</span>', $highlighted['html']);
        $t->contains('<span class="dt">Meta</span> <span class="kw">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">any</span>', $highlighted['html']);
        $t->contains('<span class="kw">func</span> <span class="fu">NormalizeTitle</span><span class="op">(</span><span class="va">ctx</span> <span class="va">context</span><span class="op">.</span><span class="dt">Context</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">packet</span> <span class="op">==</span> <span class="cn">nil</span> <span class="op">||</span>', $highlighted['html']);
        $t->contains('<span class="kw">var</span> <span class="va">payload</span> <span class="kw">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">any</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">err</span> <span class="op">:=</span> <span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span><span class="op">([]</span><span class="dt">byte</span>', $highlighted['html']);
        $t->contains('<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span> <span class="op">{</span> <span class="va">_</span> <span class="op">=</span> <span class="va">ctx</span><span class="op">.</span><span class="fu">Err</span><span class="op">()</span> <span class="op">}()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span>', $wordpressBlock);
        $t->same('go', $directGo['language']);
        $t->same('golang', $directGo['requestedLanguage']);
        $t->contains('<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span>', $directGo['html']);
        $t->contains('<span class="kw">defer</span> <span class="fu">close</span><span class="op">(</span><span class="va">done</span><span class="op">);</span>', $directGo['html']);
        $t->contains('<span class="va">done</span> <span class="op">&lt;-</span> <span class="st">&quot;ok&quot;</span>', $directGo['html']);
    },
    'highlights powershell migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[26] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PowerShell code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directPowerShell = (new SyntaxHighlighter())->highlight('Get-Content -LiteralPath $Env:WP_IMPORT | ConvertFrom-Json', 'pwsh');

        $t->same('ps1', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('powershell', $highlighted['language']);
        $t->same('ps1', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(150, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ps1 numberLines"><code class="sourceCode powershell" style="counter-reset: source-line 149;">', $highlighted['html']);
        $t->contains('<span id="powershell-review-150"><a href="#powershell-review-150"></a><span class="co"># WordPress Windows import review</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">[CmdletBinding()]</span>', $highlighted['html']);
        $t->contains('<span class="kw">param</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="dt">[string]</span><span class="va">$SourcePath</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="dt">[switch]</span><span class="va">$DryRun</span>', $highlighted['html']);
        $t->contains('<span class="va">$packet</span> <span class="op">=</span> <span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$SourcePath</span> <span class="op">|</span> <span class="fu">ConvertFrom-Json</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="cn">$null</span> <span class="op">-eq</span> <span class="va">$packet</span><span class="op">.</span><span class="va">title</span> <span class="op">-or</span>', $highlighted['html']);
        $t->contains('<span class="va">$packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">Trim</span><span class="op">()</span> <span class="op">-eq</span> <span class="st">&quot;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">Write-Warning</span> <span class="st">&quot;Missing title in $SourcePath&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$blocks</span> <span class="op">=</span> <span class="op">@(</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;$($packet.title)&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$meta</span> <span class="op">=</span> <span class="op">@{</span>', $highlighted['html']);
        $t->contains('<span class="ot">source</span> <span class="op">=</span> <span class="va">$SourcePath</span>', $highlighted['html']);
        $t->contains('<span class="va">$blocks</span> <span class="op">|</span> <span class="fu">ForEach-Object</span> <span class="op">{</span> <span class="va">$_</span><span class="op">.</span><span class="fu">Trim</span><span class="op">()</span> <span class="op">}</span> <span class="op">|</span> <span class="fu">Set-Content</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">Set-Content</span> <span class="ot">-LiteralPath</span> <span class="st">&quot;.\\review.html&quot;</span>', $wordpressBlock);
        $t->same('powershell', $directPowerShell['language']);
        $t->same('pwsh', $directPowerShell['requestedLanguage']);
        $t->contains('<span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$Env:WP_IMPORT</span> <span class="op">|</span> <span class="fu">ConvertFrom-Json</span>', $directPowerShell['html']);
    },
    'highlights graphviz dot workflow review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[27] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Graphviz DOT code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directGraphviz = (new SyntaxHighlighter())->highlight('graph Review { draft -- published [weight=2]; }', 'graphviz');

        $t->same('dot', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('dot', $highlighted['language']);
        $t->same('dot', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(170, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource dot numberLines"><code class="sourceCode dot" style="counter-reset: source-line 169;">', $highlighted['html']);
        $t->contains('<span id="dot-review-170"><a href="#dot-review-170"></a><span class="co">// WordPress import workflow graph</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">digraph</span> <span class="va">ImportFlow</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">graph</span> <span class="op">[</span><span class="ot">rankdir</span><span class="op">=</span><span class="cn">LR</span><span class="op">,</span> <span class="ot">label</span><span class="op">=</span><span class="st">&quot;Legacy import&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">node</span> <span class="op">[</span><span class="ot">shape</span><span class="op">=</span><span class="cn">box</span><span class="op">,</span> <span class="ot">style</span><span class="op">=</span><span class="st">&quot;rounded,filled&quot;</span><span class="op">,</span> <span class="ot">color</span><span class="op">=</span><span class="st">&quot;#005cc5&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;Reviewer Queue&quot;</span><span class="op">,</span> <span class="ot">URL</span><span class="op">=</span><span class="st">&quot;https://example.test/wp-admin/edit.php&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">ingest</span> <span class="op">-&gt;</span> <span class="va">review</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;normalize&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span> <span class="op">[</span><span class="ot">label</span><span class="op">=</span><span class="st">&quot;approve&quot;</span><span class="op">,</span> <span class="ot">weight</span><span class="op">=</span><span class="dv">2</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">subgraph</span> <span class="va">cluster_media</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span>', $wordpressBlock);
        $t->same('dot', $directGraphviz['language']);
        $t->same('graphviz', $directGraphviz['requestedLanguage']);
        $t->contains('<span class="kw">graph</span> <span class="va">Review</span> <span class="op">{</span>', $directGraphviz['html']);
        $t->contains('<span class="va">draft</span> <span class="op">--</span> <span class="va">published</span> <span class="op">[</span><span class="ot">weight</span><span class="op">=</span><span class="dv">2</span><span class="op">];</span>', $directGraphviz['html']);
    },
    'highlights javascript gutenberg module snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[28] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a JavaScript module code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directNode = (new SyntaxHighlighter())->highlight(
            '#!/usr/bin/env node' . "\n" . 'console.log(JSON.stringify({ ok: true, count: 2n }))',
            'node'
        );
        $directCjs = (new SyntaxHighlighter())->highlight('module.exports = require("@wordpress/scripts");', 'cjs');

        $t->same('mjs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('javascript', $highlighted['language']);
        $t->same('mjs', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(190, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mjs numberLines"><code class="sourceCode javascript" style="counter-reset: source-line 189;">', $highlighted['html']);
        $t->contains('<span id="gutenberg-js-review-190"><a href="#gutenberg-js-review-190"></a><span class="co">// Gutenberg import block registration review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="op">{</span> <span class="va">registerBlockType</span> <span class="op">}</span> <span class="kw">from</span> <span class="st">&quot;@wordpress/blocks&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="va">slugify</span> <span class="op">=</span> <span class="op">(</span><span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span> <span class="op">=&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">replace</span><span class="op">(</span><span class="st">/\\s+/gu</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">registerImportBlock</span><span class="op">(</span><span class="va">sourceId</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">await</span> <span class="fu">apiFetch</span><span class="op">({</span> <span class="ot">path</span><span class="op">:</span> <span class="st">&quot;/wp/v2/posts?per_page=1&quot;</span> <span class="op">});</span>', $highlighted['html']);
        $t->contains('<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span><span class="op">,</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">attributes</span><span class="op">:</span> <span class="op">{</span> <span class="ot">sourceId</span><span class="op">:</span> <span class="op">{</span> <span class="ot">type</span><span class="op">:</span> <span class="st">&quot;string&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span><span class="op">(</span><span class="va">response</span><span class="op">));</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span>', $wordpressBlock);
        $t->same('javascript', $directNode['language']);
        $t->same('node', $directNode['requestedLanguage']);
        $t->contains('<span class="co">#!/usr/bin/env node</span>', $directNode['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span>', $directNode['html']);
        $t->contains('<span class="ot">ok</span><span class="op">:</span> <span class="cn">true</span><span class="op">,</span> <span class="ot">count</span><span class="op">:</span> <span class="dv">2n</span>', $directNode['html']);
        $t->same('javascript', $directCjs['language']);
        $t->same('cjs', $directCjs['requestedLanguage']);
        $t->contains('<span class="va">module</span><span class="op">.</span><span class="va">exports</span> <span class="op">=</span> <span class="fu">require</span><span class="op">(</span><span class="st">&quot;@wordpress/scripts&quot;</span><span class="op">);</span>', $directCjs['html']);
    },
    'highlights csharp aspnet review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[29] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a C# code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $directCsharp = (new SyntaxHighlighter())->highlight('await Console.Out.WriteLineAsync("ok");', 'csharp');

        $t->same('cs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('csharp', $highlighted['language']);
        $t->same('cs', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(210, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cs numberLines"><code class="sourceCode csharp" style="counter-reset: source-line 209;">', $highlighted['html']);
        $t->contains('<span id="csharp-review-210"><a href="#csharp-review-210"></a><span class="co">// ASP.NET legacy import packet review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">using</span> <span class="dt">System</span><span class="op">.</span><span class="dt">Text</span><span class="op">.</span><span class="dt">Json</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">namespace</span> <span class="dt">Legacy</span><span class="op">.</span><span class="dt">Import</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">sealed</span> <span class="kw">record</span> <span class="dt">ReviewPacket</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="ot">[property: JsonPropertyName(&quot;title&quot;)]</span> <span class="dt">string</span><span class="op">?</span> <span class="dt">Title</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">static</span> <span class="kw">async</span> <span class="dt">Task</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">&gt;</span> <span class="fu">RenderAsync</span>', $highlighted['html']);
        $t->contains('<span class="dt">JsonSerializer</span><span class="op">.</span><span class="fu">Deserialize</span><span class="op">&lt;</span><span class="dt">ReviewPacket</span><span class="op">&gt;(</span><span class="va">rawJson</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">?.</span><span class="dt">Title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="dt">string</span><span class="op">.</span><span class="fu">IsNullOrWhiteSpace</span><span class="op">(</span><span class="va">title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">$&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;Import {packet?.SourceId}&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">await</span> <span class="dt">Console</span><span class="op">.</span><span class="dt">Out</span><span class="op">.</span><span class="fu">WriteLineAsync</span><span class="op">(</span><span class="va">title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">title</span><span class="op">.</span><span class="fu">Trim</span><span class="op">();</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="st">$&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;Import {packet?.SourceId}&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span>', $wordpressBlock);
        $t->same('csharp', $directCsharp['language']);
        $t->same('csharp', $directCsharp['requestedLanguage']);
        $t->contains('<span class="kw">await</span> <span class="dt">Console</span><span class="op">.</span><span class="dt">Out</span><span class="op">.</span><span class="fu">WriteLineAsync</span><span class="op">(</span><span class="st">&quot;ok&quot;</span><span class="op">);</span>', $directCsharp['html']);
    },
    'highlights tsx gutenberg typed component snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[36] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a TSX code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directTsx = $highlighter->highlight(
            'type Props = { title?: string }; export const Edit = (props: Props) => <PanelBody title={props.title ?? "Import"} />;',
            'typescript-react'
        );

        $t->same('tsx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('tsx'));
        $t->same('tsx', SyntaxHighlighter::normalizeLanguage('typescript-react'));
        $t->same('tsx', $highlighted['language']);
        $t->same('tsx', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(350, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource tsx numberLines"><code class="sourceCode tsx" style="counter-reset: source-line 349;">', $highlighted['html']);
        $t->contains('<span id="tsx-review-350"><a href="#tsx-review-350"></a><span class="co">// Gutenberg typed block inspector review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="kw">type</span> <span class="op">{</span> <span class="dt">BlockEditProps</span> <span class="op">}</span> <span class="kw">from</span> <span class="st">&quot;@wordpress/blocks&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewAttributes</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">?:</span> <span class="dt">string</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">const</span> <span class="dt">Edit</span>', $highlighted['html']);
        $t->contains('<span class="dt">BlockEditProps</span><span class="op">&lt;</span><span class="dt">ReviewAttributes</span><span class="op">&gt;)</span> <span class="op">=&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;InspectorControls</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;PanelBody</span> <span class="ot">title</span><span class="op">={</span><span class="st">`Import ${attributes.sourceId}`</span><span class="op">}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;TextControl</span>', $highlighted['html']);
        $t->contains('<span class="ot">value</span><span class="op">={</span><span class="va">attributes</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="ot">onChange</span><span class="op">={(</span><span class="va">title</span><span class="op">:</span> <span class="dt">string</span><span class="op">)</span> <span class="op">=&gt;</span> <span class="fu">setAttributes</span><span class="op">({</span> <span class="va">title</span> <span class="op">})}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="fu">&lt;TextControl</span>', $wordpressBlock);
        $t->same('tsx', $directTsx['language']);
        $t->same('typescript-react', $directTsx['requestedLanguage']);
        $t->contains('<span class="kw">type</span> <span class="dt">Props</span> <span class="op">=</span>', $directTsx['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">const</span> <span class="dt">Edit</span>', $directTsx['html']);
        $t->contains('<span class="fu">&lt;PanelBody</span> <span class="ot">title</span><span class="op">={</span><span class="va">props</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Import&quot;</span><span class="op">}</span> <span class="op">/&gt;;</span>', $directTsx['html']);
    },
    'highlights cmake build review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[37] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a CMake code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directCMake = $highlighter->highlight('message(STATUS "review ok")', 'CMakeLists.txt');

        $t->same('cmake', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('cmake'));
        $t->same('cmake', SyntaxHighlighter::normalizeLanguage('CMakeLists.txt'));
        $t->same('cmake', $highlighted['language']);
        $t->same('cmake', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(370, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cmake numberLines"><code class="sourceCode cmake" style="counter-reset: source-line 369;">', $highlighted['html']);
        $t->contains('<span id="cmake-review-370"><a href="#cmake-review-370"></a><span class="co"># WordPress native extension build review</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">cmake_minimum_required</span><span class="op">(</span><span class="kw">VERSION</span> <span class="dv">3.20</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">project</span><span class="op">(</span><span class="va">WPImportReview</span> <span class="kw">VERSION</span> <span class="dv">1.0</span> <span class="kw">LANGUAGES</span> <span class="dt">C</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">set</span><span class="op">(</span><span class="va">PLUGIN_SLUG</span> <span class="st">&quot;legacy-import&quot;</span> <span class="kw">CACHE</span> <span class="dt">STRING</span>', $highlighted['html']);
        $t->contains('<span class="fu">option</span><span class="op">(</span><span class="va">WP_IMPORT_BUILD_SHARED</span> <span class="st">&quot;Build shared review helper&quot;</span> <span class="cn">ON</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">add_library</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="dt">MODULE</span> <span class="va">src</span><span class="op">/</span><span class="va">review</span><span class="op">.</span><span class="dt">c</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">target_compile_definitions</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="kw">PRIVATE</span>', $highlighted['html']);
        $t->contains('<span class="ot">PLUGIN_SLUG</span><span class="op">=</span><span class="st">&quot;${PLUGIN_SLUG}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">$&lt;$&lt;CONFIG:Debug&gt;:WP_IMPORT_DEBUG=1&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">target_include_directories</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="kw">PRIVATE</span> <span class="va">${CMAKE_CURRENT_SOURCE_DIR}</span><span class="op">/</span><span class="va">include</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">install</span><span class="op">(</span><span class="kw">TARGETS</span> <span class="va">wp_import_review</span> <span class="kw">LIBRARY</span> <span class="kw">DESTINATION</span> <span class="va">lib</span><span class="op">/</span><span class="va">wordpress</span><span class="op">/</span><span class="va">plugins</span><span class="op">/</span><span class="va">${PLUGIN_SLUG}</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="fu">target_compile_definitions</span>', $wordpressBlock);
        $t->same('cmake', $directCMake['language']);
        $t->same('CMakeLists.txt', $directCMake['requestedLanguage']);
        $t->contains('<span class="fu">message</span><span class="op">(</span><span class="va">STATUS</span> <span class="st">&quot;review ok&quot;</span><span class="op">)</span>', $directCMake['html']);
    },
    'highlights nginx server review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[38] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Nginx code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directNginx = $highlighter->highlight('location ~ \.php$ { fastcgi_pass unix:/run/php/php-fpm.sock; }', 'nginxconf');

        $t->same('nginx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginxconf'));
        $t->same('nginx', SyntaxHighlighter::normalizeLanguage('nginx-config'));
        $t->same('nginx', $highlighted['language']);
        $t->same('nginx', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(390, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource nginx numberLines"><code class="sourceCode nginx" style="counter-reset: source-line 389;">', $highlighted['html']);
        $t->contains('<span id="nginx-review-390"><a href="#nginx-review-390"></a><span class="co"># WordPress Nginx permalink and PHP-FPM review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">server</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">listen</span> <span class="dv">443</span> <span class="cn">ssl</span> <span class="cn">http2</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">server_name</span> <span class="va">example.test</span> <span class="va">www.example.test</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">root</span> <span class="st">/srv/www/legacy-import</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">location</span> <span class="st">/</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">try_files</span> <span class="va">$uri</span> <span class="va">$uri</span><span class="st">/</span> <span class="st">/index.php?</span><span class="va">$args</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">location</span> <span class="op">~</span> <span class="st">\\.php$</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">include</span> <span class="va">fastcgi_params</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">fastcgi_param</span> <span class="va">SCRIPT_FILENAME</span> <span class="va">$document_root$fastcgi_script_name</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">add_header</span> <span class="va">X-Import-Source</span> <span class="st">&quot;legacy&quot;</span> <span class="cn">always</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">rewrite</span> <span class="st">^</span> <span class="st">/index.php</span> <span class="cn">last</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span>', $wordpressBlock);
        $t->same('nginx', $directNginx['language']);
        $t->same('nginxconf', $directNginx['requestedLanguage']);
        $t->contains('<span class="kw">location</span> <span class="op">~</span> <span class="st">\\.php$</span> <span class="op">{</span> <span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span><span class="op">;</span> <span class="op">}</span>', $directNginx['html']);
    },
    'highlights twig timber template snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[39] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Twig template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directTwig = $highlighter->highlight('{{ post.title|default("Untitled")|e }}', 'html+twig');

        $t->same('twig', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('twig'));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('timber'));
        $t->same('twig', SyntaxHighlighter::normalizeLanguage('html+twig'));
        $t->same('twig', $highlighted['language']);
        $t->same('twig', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(410, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource twig numberLines"><code class="sourceCode twig" style="counter-reset: source-line 409;">', $highlighted['html']);
        $t->contains('<span id="twig-template-review-410"><a href="#twig-template-review-410"></a><span class="co">{# Timber theme template review #}</span></span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">extends</span> <span class="st">&quot;base.twig&quot;</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">set</span> <span class="va">blocks</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">,</span> <span class="st">&quot;core/image&quot;</span><span class="op">]</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">for</span> <span class="va">item</span> <span class="kw">in</span> <span class="va">posts</span> <span class="kw">if</span> <span class="va">item</span><span class="op">.</span><span class="va">status</span> <span class="op">==</span> <span class="st">&quot;publish&quot;</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{{</span> <span class="va">item</span><span class="op">.</span><span class="va">title</span><span class="op">|</span><span class="fu">default</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)|</span><span class="fu">e</span> <span class="op">}}</span><span class="kw">&lt;/h2</span>', $highlighted['html']);
        $t->contains('<span class="op">{{</span> <span class="fu">function</span><span class="op">(</span><span class="st">&quot;wp_kses_post&quot;</span><span class="op">,</span> <span class="va">item</span><span class="op">.</span><span class="va">content</span><span class="op">)|</span><span class="fu">raw</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">else</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{{</span> <span class="fu">include</span><span class="op">(</span><span class="st">&quot;partials/empty.twig&quot;</span><span class="op">,</span> <span class="op">{</span> <span class="ot">source</span><span class="op">:</span> <span class="va">sourceId</span> <span class="op">})</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">endfor</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="st">&quot;wp_kses_post&quot;</span>', $wordpressBlock);
        $t->same('twig', $directTwig['language']);
        $t->same('html+twig', $directTwig['requestedLanguage']);
        $t->contains('<span class="op">{{</span> <span class="va">post</span><span class="op">.</span><span class="va">title</span><span class="op">|</span><span class="fu">default</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)|</span><span class="fu">e</span> <span class="op">}}</span>', $directTwig['html']);
    },
    'highlights mustache handlebars template snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[40] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Handlebars template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directHandlebars = $highlighter->highlight('{{#each posts}}{{title}}{{else}}{{default "Untitled"}}{{/each}}', 'handlebars');

        $t->same('hbs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('hbs'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('handlebars'));
        $t->same('mustache', SyntaxHighlighter::normalizeLanguage('html.mst'));
        $t->same('mustache', $highlighted['language']);
        $t->same('hbs', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(430, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource hbs numberLines"><code class="sourceCode mustache" style="counter-reset: source-line 429;">', $highlighted['html']);
        $t->contains('<span id="handlebars-template-review-430"><a href="#handlebars-template-review-430"></a><span class="co">{{!-- Handlebars theme migration review --}}</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;section</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span> <span class="ot">data-source</span><span class="op">={{</span><span class="va">sourceId</span><span class="op">}}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="op">{{#</span><span class="kw">if</span> <span class="va">title</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{{</span><span class="fu">default</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}}</span><span class="kw">&lt;/h2</span>', $highlighted['html']);
        $t->contains('<span class="op">{{#</span><span class="kw">each</span> <span class="va">media</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;img</span> <span class="ot">src</span><span class="op">={{</span><span class="va">url</span><span class="op">}}</span> <span class="ot">alt</span><span class="op">={{</span><span class="va">alt</span><span class="op">}}</span> <span class="op">/&gt;</span>', $highlighted['html']);
        $t->contains('<span class="op">{{{</span><span class="va">rawBlock</span><span class="op">}}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{{&gt;</span> <span class="va">footer</span> <span class="ot">source</span><span class="op">=</span><span class="va">sourceId</span> <span class="ot">count</span><span class="op">=</span><span class="dv">2</span><span class="op">}}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="op">{{&gt;</span> <span class="va">footer</span>', $wordpressBlock);
        $t->same('mustache', $directHandlebars['language']);
        $t->same('handlebars', $directHandlebars['requestedLanguage']);
        $t->contains('<span class="op">{{#</span><span class="kw">each</span> <span class="va">posts</span><span class="op">}}{{</span><span class="va">title</span>', $directHandlebars['html']);
        $t->contains('<span class="op">}}{{</span><span class="kw">else</span><span class="op">}}{{</span>', $directHandlebars['html']);
        $t->contains('<span class="fu">default</span> <span class="st">&quot;Untitled&quot;</span><span class="op">}}{{/</span><span class="kw">each</span>', $directHandlebars['html']);
    },
    'highlights mermaid diagram review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[41] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Mermaid diagram code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directMermaid = $highlighter->highlight('sequenceDiagram' . "\n" . '  participant Editor' . "\n" . '  Editor->>Queue: approve', 'mermaid-js');

        $t->same('mermaid', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaid-js'));
        $t->same('mermaid', SyntaxHighlighter::normalizeLanguage('mermaidjs'));
        $t->same('mermaid', $highlighted['language']);
        $t->same('mermaid', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(450, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mermaid numberLines"><code class="sourceCode mermaid" style="counter-reset: source-line 449;">', $highlighted['html']);
        $t->contains('<span id="mermaid-review-450"><a href="#mermaid-review-450"></a><span class="co">%% WordPress import workflow diagram review</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">%%{ init: { &quot;theme&quot;: &quot;base&quot; } }%%</span>', $highlighted['html']);
        $t->contains('<span class="kw">flowchart</span> <span class="cn">LR</span>', $highlighted['html']);
        $t->contains('<span class="va">ingest</span><span class="st">[Read WXR]</span> <span class="op">--&gt;</span> <span class="va">normalize</span><span class="st">{Normalize blocks}</span>', $highlighted['html']);
        $t->contains('<span class="va">normalize</span> <span class="op">--&gt;</span><span class="st">|safe HTML|</span> <span class="va">review</span><span class="st">[Reviewer Queue]</span>', $highlighted['html']);
        $t->contains('<span class="va">normalize</span> <span class="op">--</span> <span class="va">media</span> <span class="op">--&gt;</span> <span class="va">media</span><span class="st">[(Attachment Library)]</span>', $highlighted['html']);
        $t->contains('<span class="va">review</span> <span class="op">-.</span> <span class="va">approve</span> <span class="op">.-&gt;</span> <span class="va">publish</span><span class="st">[Publish]</span>', $highlighted['html']);
        $t->contains('<span class="kw">classDef</span> <span class="va">warning</span> <span class="ot">fill</span><span class="op">:#</span><span class="va">fff4ce</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="va">normalize</span> <span class="va">warning</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">flowchart</span> <span class="cn">LR</span>', $wordpressBlock);
        $t->same('mermaid', $directMermaid['language']);
        $t->same('mermaid-js', $directMermaid['requestedLanguage']);
        $t->contains('<span class="kw">sequenceDiagram</span>', $directMermaid['html']);
        $t->contains('<span class="kw">participant</span> <span class="va">Editor</span>', $directMermaid['html']);
        $t->contains('<span class="va">Editor</span><span class="op">-&gt;&gt;</span><span class="ot">Queue</span><span class="op">:</span> <span class="va">approve</span>', $directMermaid['html']);
    },
    'highlights embedded css and javascript inside html review snippets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[42] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an embedded HTML asset code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directHtml = $highlighter->highlight(
            '<style>.wp-block { color: var(--accent-color); }</style>' . "\n"
            . '<script>const block = wp.element.createElement("p", null, "ok");</script>',
            'html'
        );

        $t->same('html', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('html', $highlighted['language']);
        $t->same('html', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(470, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource html numberLines"><code class="sourceCode html" style="counter-reset: source-line 469;">', $highlighted['html']);
        $t->contains('<span id="html-embedded-review-470"><a href="#html-embedded-review-470"></a><span class="co">&lt;!-- WordPress embedded asset review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;style</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">margin-block</span><span class="op">:</span> <span class="dv">1rem</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;script</span> <span class="ot">type</span><span class="op">=</span><span class="st">&quot;module&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="dt">window</span><span class="op">.</span><span class="va">wp</span><span class="op">?.</span><span class="va">data</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span><span class="op">({</span> <span class="ot">ok</span><span class="op">:</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="fu">createElement</span><span class="op">(</span><span class="st">&quot;p&quot;</span>', $wordpressBlock);
        $t->same('html', $directHtml['language']);
        $t->contains('<span class="dt">.wp-block</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">);</span>', $directHtml['html']);
        $t->contains('<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $directHtml['html']);
    },
    'highlights embedded php islands inside html template snippets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[43] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an HTML/PHP template code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directHtml = $highlighter->highlight(
            '<article><?php if (! empty($title)) : ?><h2><?= esc_html($title) ?></h2><?php endif; ?></article>',
            'html'
        );

        $t->same('html', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('html', $highlighted['language']);
        $t->same('html', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(490, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource html numberLines"><code class="sourceCode html" style="counter-reset: source-line 489;">', $highlighted['html']);
        $t->contains('<span id="html-php-template-review-490"><a href="#html-php-template-review-490"></a><span class="co">&lt;!-- WordPress PHP template review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">if</span>', $highlighted['html']);
        $t->contains('<span class="fu">empty</span><span class="op">(</span><span class="va">$post_title</span><span class="op">))</span> <span class="op">:</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?=</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$post_title</span><span class="op">)</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">else</span> <span class="op">:</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">endif</span><span class="op">;</span> <span class="pp">?&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">esc_html</span><span class="op">(</span><span class="va">$post_title</span>', $wordpressBlock);
        $t->same('html', $directHtml['language']);
        $t->contains('<span class="pp">&lt;?php</span> <span class="kw">if</span>', $directHtml['html']);
        $t->contains('<span class="pp">&lt;?=</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">)</span> <span class="pp">?&gt;</span>', $directHtml['html']);
    },
    'highlights graphql wpgraphql review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[44] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a GraphQL code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directGraphql = $highlighter->highlight(
            'fragment MediaFields on MediaItem { sourceUrl altText }',
            'graphql-query'
        );

        $t->same('gql', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('gql'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('graphql-schema'));
        $t->same('graphql', SyntaxHighlighter::normalizeLanguage('language-graphql-query'));
        $t->same('graphql', $highlighted['language']);
        $t->same('gql', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(510, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource gql numberLines"><code class="sourceCode graphql" style="counter-reset: source-line 509;">', $highlighted['html']);
        $t->contains('<span id="graphql-review-510"><a href="#graphql-review-510"></a><span class="co"># WPGraphQL import review query</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">query</span> <span class="dt">ImportReview</span><span class="op">(</span><span class="va">$postId</span><span class="op">:</span> <span class="dt">ID</span><span class="op">!,</span> <span class="va">$includeMedia</span><span class="op">:</span> <span class="dt">Boolean</span> <span class="op">=</span> <span class="cn">true</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">post</span><span class="op">(</span><span class="ot">id</span><span class="op">:</span> <span class="va">$postId</span><span class="op">,</span> <span class="ot">idType</span><span class="op">:</span> <span class="dt">DATABASE_ID</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">media</span><span class="op">:</span> <span class="va">featuredImage</span> <span class="ot">@include</span><span class="op">(</span><span class="ot">if</span><span class="op">:</span> <span class="va">$includeMedia</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">implements</span> <span class="dt">Node</span>', $highlighted['html']);
        $t->contains('<span class="ot">blocks</span><span class="op">:</span> <span class="op">[</span><span class="dt">String</span><span class="op">!]!</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="ot">@include</span><span class="op">(</span><span class="ot">if</span><span class="op">:</span> <span class="va">$includeMedia</span>', $wordpressBlock);
        $t->same('graphql', $directGraphql['language']);
        $t->same('graphql-query', $directGraphql['requestedLanguage']);
        $t->contains('<span class="kw">fragment</span> <span class="dt">MediaFields</span> <span class="kw">on</span> <span class="dt">MediaItem</span>', $directGraphql['html']);
    },
    'highlights php attributes enums and closure types for wordpress plugins' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[45] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PHP attribute code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directAttribute = $highlighter->highlight(
            "#[BlockVariation(name: \"legacy/import\")]\nenum ImportStatus: string { case Draft = \"draft\"; }",
            'php'
        );

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('php', $highlighted['language']);
        $t->same('php', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(530, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines"><code class="sourceCode php" style="counter-reset: source-line 529;">', $highlighted['html']);
        $t->contains('<span id="php-attribute-review-531"><a href="#php-attribute-review-531"></a><span class="ot">#[BlockVariation(name: &#039;legacy/import&#039;, title: &#039;Legacy Import&#039;)]</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">final</span> <span class="kw">readonly</span> <span class="kw">class</span> <span class="dt">ImportBlock</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">function</span> <span class="fu">__construct</span><span class="op">(</span><span class="kw">public</span> <span class="dt">string</span> <span class="va">$title</span> <span class="op">=</span> <span class="st">&#039;Untitled&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">function</span> <span class="fu">status</span><span class="op">():</span> <span class="dt">ImportStatus</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">$this</span><span class="op">-&gt;</span><span class="va">title</span> <span class="op">===</span> <span class="st">&#039;&#039;</span> <span class="op">?</span> <span class="dt">ImportStatus</span><span class="op">::</span><span class="dt">Draft</span>', $highlighted['html']);
        $t->contains('<span class="kw">enum</span> <span class="dt">ImportStatus</span><span class="op">:</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="kw">case</span> <span class="dt">Draft</span> <span class="op">=</span> <span class="st">&#039;draft&#039;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$normalize</span> <span class="op">=</span> <span class="kw">fn</span><span class="op">(</span><span class="dt">array</span> <span class="va">$item</span><span class="op">):</span> <span class="dt">string</span> <span class="op">=&gt;</span>', $highlighted['html']);
        $t->contains('<span class="dt">ImportStatus</span><span class="op">::</span><span class="dt">Draft</span><span class="op">-&gt;</span><span class="va">value</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="ot">#[BlockVariation(name: &#039;legacy/import&#039;, title: &#039;Legacy Import&#039;)]</span>', $wordpressBlock);
        $t->same('php', $directAttribute['language']);
        $t->contains('<span class="ot">#[BlockVariation(name: &quot;legacy/import&quot;)]</span>', $directAttribute['html']);
        $t->contains('<span class="kw">enum</span> <span class="dt">ImportStatus</span><span class="op">:</span> <span class="dt">string</span>', $directAttribute['html']);
        $t->same(false, str_contains($directAttribute['html'], '<span class="co">#[BlockVariation'));
    },
    'highlights asciidoc runbook snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[46] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an AsciiDoc code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directAdoc = $highlighter->highlight('link:https://example.test/import[review] and {source-id}', 'asciidoctor');

        $t->same('adoc', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('adoc'));
        $t->same('asciidoc', SyntaxHighlighter::normalizeLanguage('asciidoctor'));
        $t->same('asciidoc', $highlighted['language']);
        $t->same('adoc', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(550, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource adoc numberLines"><code class="sourceCode asciidoc" style="counter-reset: source-line 549;">', $highlighted['html']);
        $t->contains('<span id="asciidoc-review-550"><a href="#asciidoc-review-550"></a><span class="co">// WordPress importer runbook review</span></span>', $highlighted['html']);
        $t->contains('<span class="re">= Legacy Import Review</span>', $highlighted['html']);
        $t->contains('<span class="ot">:source-id:</span> legacy<span class="dv">-42</span>', $highlighted['html']);
        $t->contains('<span class="ot">:wp-block:</span> core<span class="op">/</span>paragraph', $highlighted['html']);
        $t->contains('<span class="ot">[[review-queue]]</span>', $highlighted['html']);
        $t->contains('<span class="kw">NOTE:</span> Preserve <span class="dt">`legacy_shortcode`</span> blocks before publishing<span class="op">.</span>', $highlighted['html']);
        $t->contains('<span class="fu">image::</span>uploads<span class="op">/</span>hero<span class="op">.</span>jpg<span class="op">[</span>Hero image<span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="fu">link:</span><span class="ot">https://example.test/wp-admin/edit.php</span><span class="op">[</span>Reviewer queue<span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="ot">[source,php]</span>', $highlighted['html']);
        $t->contains('<span class="re">----</span>', $highlighted['html']);
        $t->contains('<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">);</span> <span class="co">// reviewed output &lt;1&gt;</span>', $highlighted['html']);
        $t->same(false, str_contains($highlighted['html'], '<span class="dt">echo esc_html($title); // reviewed output'));
        $t->contains('<span class="cn">&lt;1&gt;</span> Escaped WordPress block title<span class="op">.</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="fu">image::</span>uploads', $wordpressBlock);
        $t->contains('<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span>', $wordpressBlock);
        $t->same('asciidoc', $directAdoc['language']);
        $t->same('asciidoctor', $directAdoc['requestedLanguage']);
        $t->contains('<span class="fu">link:</span><span class="ot">https://example.test/import</span><span class="op">[</span>review<span class="op">]</span>', $directAdoc['html']);
        $t->contains('<span class="va">{source-id}</span>', $directAdoc['html']);
    },
    'delegates asciidoc source listings to declared syntax highlighters' => static function (TestRunner $t): void {
        $highlighter = new SyntaxHighlighter();
        $phpSource = $highlighter->highlight("[source,php]\n----\necho esc_html(\$title); // reviewed output <1>\n----", 'asciidoctor');
        $javascriptSource = $highlighter->highlight("[source,js,linenums]\n----\nconst block = wp.element.createElement(\"p\", null, \"ok\");\n----", 'adoc');
        $unsupportedSource = $highlighter->highlight("[source,legacy-runbook]\n----\nraw << token\n----", 'adoc');

        $t->same('asciidoc', $phpSource['language']);
        $t->contains('<span class="ot">[source,php]</span>', $phpSource['html']);
        $t->contains('<span class="re">----</span>', $phpSource['html']);
        $t->contains('<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">);</span> <span class="co">// reviewed output &lt;1&gt;</span>', $phpSource['html']);
        $t->same(false, str_contains($phpSource['html'], '<span class="dt">echo esc_html($title); // reviewed output'));
        $t->contains('<span class="ot">[source,js,linenums]</span>', $javascriptSource['html']);
        $t->contains('<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>', $javascriptSource['html']);
        $t->contains('<span class="dt">raw &lt;&lt; token</span>', $unsupportedSource['html']);
    },
    'delegates asciidoc source listings through alternate delimited blocks' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '[source,terraform]',
            '====',
            'locals {',
            '  title = trimspace(var.title)',
            '}',
            '====',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'adoc');

        $t->same('asciidoc', $highlighted['language']);
        $t->contains('<span class="ot">[source,terraform]</span>', $highlighted['html']);
        $t->contains('<span class="re">====</span>', $highlighted['html']);
        $t->contains('<span class="kw">locals</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">title</span> <span class="op">=</span> <span class="fu">trimspace</span><span class="op">(</span><span class="va">var.title</span><span class="op">)</span>', $highlighted['html']);
        $t->same(false, str_contains($highlighted['html'], '<span class="dt">locals {'));
    },
    'highlights phpdoc annotations and typed metadata in php review snippets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[47] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PHPDoc code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directDocblock = $highlighter->highlight(implode("\n", [
            '<?php',
            '/**',
            ' * @var class-string<WP_Post> $postClass',
            ' */',
            '$postClass = WP_Post::class;',
        ]), 'php', 'pygments', ['tokenTitles' => true]);

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('php', $highlighted['language']);
        $t->same('php', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(true, $highlighted['tokenTitles']);
        $t->same(570, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines tokenTitles"><code class="sourceCode php" style="counter-reset: source-line 569;">', $highlighted['html']);
        $t->contains('<span id="phpdoc-review-570"><a href="#phpdoc-review-570"></a><span class="pp" title="PreprocessorTok">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="phpdoc-review-572"><a href="#phpdoc-review-572"></a><span class="co" title="CommentTok"> * Builds a review title from a migrated block packet.</span></span>', $highlighted['html']);
        $t->contains('<span class="ot" title="OtherTok">@template</span> <span class="dt" title="DataTypeTok">TPacket</span> <span class="co" title="CommentTok">of</span> <span class="dt" title="DataTypeTok">array</span><span class="op" title="OperatorTok">&lt;</span><span class="dt" title="DataTypeTok">string</span><span class="op" title="OperatorTok">,</span><span class="dt" title="DataTypeTok">mixed</span><span class="op" title="OperatorTok">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="ot" title="OtherTok">@param</span> <span class="dt" title="DataTypeTok">array</span><span class="op" title="OperatorTok">&lt;</span><span class="dt" title="DataTypeTok">string</span><span class="op" title="OperatorTok">,</span><span class="dt" title="DataTypeTok">mixed</span><span class="op" title="OperatorTok">&gt;</span> <span class="va" title="VariableTok">$item</span>', $highlighted['html']);
        $t->contains('<span class="ot" title="OtherTok">@param</span> <span class="dt" title="DataTypeTok">list</span><span class="op" title="OperatorTok">&lt;</span><span class="dt" title="DataTypeTok">WP_Post</span><span class="op" title="OperatorTok">&gt;|</span><span class="dt" title="DataTypeTok">null</span> <span class="va" title="VariableTok">$attachments</span>', $highlighted['html']);
        $t->contains('<span class="ot" title="OtherTok">@return</span> <span class="dt" title="DataTypeTok">non-empty-string</span>', $highlighted['html']);
        $t->contains('<span class="ot" title="OtherTok">@throws</span> <span class="dt" title="DataTypeTok">ImportReviewException</span>', $highlighted['html']);
        $t->contains('<span class="kw" title="KeywordTok">function</span> <span class="fu" title="FunctionTok">normalize_review_title</span><span class="op" title="OperatorTok">(</span><span class="dt" title="DataTypeTok">array</span> <span class="va" title="VariableTok">$item</span><span class="op" title="OperatorTok">,</span> <span class="op" title="OperatorTok">?</span><span class="dt" title="DataTypeTok">array</span> <span class="va" title="VariableTok">$attachments</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="ot" title="OtherTok">@return</span> <span class="dt" title="DataTypeTok">non-empty-string</span>', $wordpressBlock);
        $t->same('php', $directDocblock['language']);
        $t->contains('<span class="ot" title="OtherTok">@var</span> <span class="dt" title="DataTypeTok">class-string</span><span class="op" title="OperatorTok">&lt;</span><span class="dt" title="DataTypeTok">WP_Post</span><span class="op" title="OperatorTok">&gt;</span> <span class="va" title="VariableTok">$postClass</span>', $directDocblock['html']);
        $t->contains('<span class="va" title="VariableTok">$postClass</span> <span class="op" title="OperatorTok">=</span> <span class="dt" title="DataTypeTok">WP_Post</span><span class="op" title="OperatorTok">::</span><span class="kw" title="KeywordTok">class</span>', $directDocblock['html']);
    },
    'highlights terraform hcl infrastructure review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[48] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Terraform HCL code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directTfvars = $highlighter->highlight(
            "source_id = \"legacy-42\"\nenabled = true\nsites = toset(var.sites)",
            'tfvars'
        );

        $t->same('terraform', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('terraform'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('tf'));
        $t->same('hcl', SyntaxHighlighter::normalizeLanguage('tfvars'));
        $t->same('hcl', $highlighted['language']);
        $t->same('terraform', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(590, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource terraform numberLines"><code class="sourceCode hcl" style="counter-reset: source-line 589;">', $highlighted['html']);
        $t->contains('<span id="terraform-review-590"><a href="#terraform-review-590"></a><span class="co"># WordPress import infrastructure review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">terraform</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">required_version</span> <span class="op">=</span> <span class="st">&quot;&gt;= 1.6.0&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">variable</span> <span class="st">&quot;source_id&quot;</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">type</span>    <span class="op">=</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="kw">locals</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">Source</span> <span class="op">=</span> <span class="va">var.source_id</span>', $highlighted['html']);
        $t->contains('<span class="kw">resource</span> <span class="st">&quot;aws_s3_bucket&quot;</span> <span class="st">&quot;media&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">bucket</span> <span class="op">=</span> <span class="st">&quot;wp-${var.source_id}-media&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">tags</span>   <span class="op">=</span> <span class="fu">merge</span><span class="op">(</span><span class="va">local.review_tags</span>', $highlighted['html']);
        $t->contains('<span class="kw">output</span> <span class="st">&quot;review_packet&quot;</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">value</span> <span class="op">=</span> <span class="fu">jsonencode</span><span class="op">({</span>', $highlighted['html']);
        $t->contains('<span class="ot">bucket</span>  <span class="op">=</span> <span class="va">aws_s3_bucket.media.bucket</span>', $highlighted['html']);
        $t->contains('<span class="ot">dry_run</span> <span class="op">=</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="fu">jsonencode</span><span class="op">({</span>', $wordpressBlock);
        $t->same('hcl', $directTfvars['language']);
        $t->same('tfvars', $directTfvars['requestedLanguage']);
        $t->contains('<span class="ot">source_id</span> <span class="op">=</span> <span class="st">&quot;legacy-42&quot;</span>', $directTfvars['html']);
        $t->contains('<span class="ot">enabled</span> <span class="op">=</span> <span class="cn">true</span>', $directTfvars['html']);
        $t->contains('<span class="ot">sites</span> <span class="op">=</span> <span class="fu">toset</span><span class="op">(</span><span class="va">var.sites</span><span class="op">)</span>', $directTfvars['html']);
    },
    'highlights terraform ephemeral values and sensitivity helpers' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'ephemeral "aws_secretsmanager_secret_version" "review" {',
            '  secret_id = var.secret_id',
            '}',
            '',
            'locals {',
            '  safe_title = nonsensitive(ephemeralasnull(var.title))',
            '  network = cidrsubnet(var.vpc_cidr, 8, 3)',
            '  ready = alltrue([var.enabled, !issensitive(local.safe_title)])',
            '}',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'terraform');

        $t->same('hcl', $highlighted['language']);
        $t->same('terraform', $highlighted['requestedLanguage']);
        $t->contains('<span class="kw">ephemeral</span> <span class="st">&quot;aws_secretsmanager_secret_version&quot;</span> <span class="st">&quot;review&quot;</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">secret_id</span> <span class="op">=</span> <span class="va">var.secret_id</span>', $highlighted['html']);
        $t->contains('<span class="kw">locals</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">safe_title</span> <span class="op">=</span> <span class="fu">nonsensitive</span><span class="op">(</span><span class="fu">ephemeralasnull</span><span class="op">(</span><span class="va">var.title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="ot">network</span> <span class="op">=</span> <span class="fu">cidrsubnet</span><span class="op">(</span><span class="va">var.vpc_cidr</span><span class="op">,</span> <span class="dv">8</span><span class="op">,</span> <span class="dv">3</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">ready</span> <span class="op">=</span> <span class="fu">alltrue</span><span class="op">([</span><span class="va">var.enabled</span><span class="op">,</span> <span class="op">!</span><span class="fu">issensitive</span><span class="op">(</span><span class="va">local.safe_title</span><span class="op">)])</span>', $highlighted['html']);
    },
    'highlights terraform validation and string collection helpers' => static function (TestRunner $t): void {
        $source = implode("\n", [
            'variable "slug" {',
            '  type = string',
            '  validation {',
            '    condition = alltrue([',
            '      strcontains(var.slug, "-"),',
            '      startswith(trimspace(var.slug), "wp-"),',
            '      endswith(var.slug, "-review")',
            '    ])',
            '    error_message = join(" ", distinct(["slug", "invalid"]))',
            '  }',
            '}',
            'locals { labels = keys({ for k, v in var.map : k => v if can(v.id) }) }',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'tf');

        $t->same('hcl', $highlighted['language']);
        $t->same('tf', $highlighted['requestedLanguage']);
        $t->contains('<span class="kw">validation</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">condition</span> <span class="op">=</span> <span class="fu">alltrue</span><span class="op">([</span>', $highlighted['html']);
        $t->contains('<span class="fu">strcontains</span><span class="op">(</span><span class="va">var.slug</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="fu">startswith</span><span class="op">(</span><span class="fu">trimspace</span><span class="op">(</span><span class="va">var.slug</span><span class="op">),</span> <span class="st">&quot;wp-&quot;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="fu">endswith</span><span class="op">(</span><span class="va">var.slug</span><span class="op">,</span> <span class="st">&quot;-review&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">error_message</span> <span class="op">=</span> <span class="fu">join</span><span class="op">(</span><span class="st">&quot; &quot;</span><span class="op">,</span> <span class="fu">distinct</span><span class="op">([</span><span class="st">&quot;slug&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">keys</span><span class="op">({</span> <span class="kw">for</span> <span class="va">k</span><span class="op">,</span> <span class="va">v</span> <span class="kw">in</span> <span class="va">var.map</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">can</span><span class="op">(</span><span class="va">v.id</span><span class="op">)</span>', $highlighted['html']);
    },
    'highlights liquid shopify template snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[49] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Liquid code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directLiquid = $highlighter->highlight(
            '{{ product.title | default: "Untitled" | escape }} {% render "badge", source_id: product.id %}',
            'liquid-html'
        );

        $t->same('shopify', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('liquid', $highlighted['language']);
        $t->same('shopify', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(620, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource shopify numberLines"><code class="sourceCode liquid" style="counter-reset: source-line 619;">', $highlighted['html']);
        $t->contains('<span id="liquid-review-620"><a href="#liquid-review-620"></a><span class="co">{%- comment -%} WordPress migration review for Shopify product snippets {%- endcomment -%}</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">assign</span> <span class="ot">title</span> <span class="op">=</span> <span class="va">product.title</span> <span class="op">|</span> <span class="fu">default</span><span class="op">:</span> <span class="st">&quot;Untitled&quot;</span> <span class="op">|</span> <span class="fu">escape</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">if</span> <span class="va">product.available</span> <span class="op">and</span> <span class="va">product.images.size</span> <span class="op">&gt;</span> <span class="dv">0</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<span class="va">product.description</span> <span class="op">|</span> <span class="fu">strip_html</span> <span class="op">|</span> <span class="fu">truncatewords</span><span class="op">:</span> <span class="dv">24</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">render</span> <span class="st">&quot;review-badge&quot;</span><span class="op">,</span> <span class="ot">source_id</span><span class="op">:</span> <span class="va">product.id</span><span class="op">,</span> <span class="ot">status</span><span class="op">:</span> <span class="st">&quot;needs-review&quot;</span> <span class="op">%}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="fu">truncatewords</span><span class="op">:</span> <span class="dv">24</span>', $wordpressBlock);
        $t->same('liquid', $directLiquid['language']);
        $t->same('liquid-html', $directLiquid['requestedLanguage']);
        $t->contains('<span class="op">{{</span> <span class="va">product.title</span> <span class="op">|</span> <span class="fu">default</span><span class="op">:</span> <span class="st">&quot;Untitled&quot;</span> <span class="op">|</span> <span class="fu">escape</span> <span class="op">}}</span>', $directLiquid['html']);
        $t->contains('<span class="op">{%</span> <span class="kw">render</span> <span class="st">&quot;badge&quot;</span><span class="op">,</span> <span class="ot">source_id</span><span class="op">:</span> <span class="va">product.id</span> <span class="op">%}</span>', $directLiquid['html']);
    },
    'highlights elm architecture review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[50] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Elm code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directElm = $highlighter->highlight(
            'view model = Html.text (if True then "Published" else "Draft")',
            'elm-source'
        );

        $t->same('elm', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('elm'));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('elm-module'));
        $t->same('elm', SyntaxHighlighter::normalizeLanguage('language-elm-source'));
        $t->same('elm', $highlighted['language']);
        $t->same('elm', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(640, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource elm numberLines"><code class="sourceCode elm" style="counter-reset: source-line 639;">', $highlighted['html']);
        $t->contains('<span id="elm-review-640"><a href="#elm-review-640"></a><span class="co">{- WordPress import review UI state -}</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">ImportReview</span> <span class="kw">exposing</span> <span class="op">(</span><span class="dt">Model</span><span class="op">,</span> <span class="dt">Msg</span><span class="op">(..),</span> <span class="va">view</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">Html.Attributes</span> <span class="kw">as</span> <span class="dt">Attr</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="kw">alias</span> <span class="dt">Model</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span> <span class="op">:</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="va">published</span> <span class="op">:</span> <span class="dt">Bool</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">Msg</span>', $highlighted['html']);
        $t->contains('<span class="op">=</span> <span class="dt">Approve</span>', $highlighted['html']);
        $t->contains('<span class="va">decoder</span> <span class="op">:</span> <span class="dt">Decode.Decoder</span> <span class="dt">Model</span>', $highlighted['html']);
        $t->contains('<span class="fu">Decode.map3</span> <span class="dt">Model</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="fu">Decode.field</span> <span class="st">&quot;title&quot;</span> <span class="fu">Decode.string</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="fu">Decode.succeed</span> <span class="cn">False</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">Html.div</span> <span class="op">[</span> <span class="fu">Attr.class</span> <span class="st">&quot;wp-block-import-card&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">Attr.attribute</span> <span class="st">&quot;data-source&quot;</span> <span class="op">(</span><span class="fu">String.fromInt</span> <span class="va">model</span><span class="op">.</span><span class="va">sourceId</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">Html.text</span> <span class="op">(</span><span class="kw">if</span> <span class="va">model</span><span class="op">.</span><span class="va">published</span> <span class="kw">then</span> <span class="st">&quot;Published&quot;</span> <span class="kw">else</span> <span class="st">&quot;Needs review&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">Html.div</span> <span class="op">[</span> <span class="fu">Attr.class</span>', $wordpressBlock);
        $t->same('elm', $directElm['language']);
        $t->same('elm-source', $directElm['requestedLanguage']);
        $t->contains('<span class="va">view</span> <span class="va">model</span> <span class="op">=</span> <span class="fu">Html.text</span> <span class="op">(</span><span class="kw">if</span> <span class="cn">True</span> <span class="kw">then</span> <span class="st">&quot;Published&quot;</span> <span class="kw">else</span> <span class="st">&quot;Draft&quot;</span><span class="op">)</span>', $directElm['html']);
    },
    'highlights json with comments review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[51] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a JSON-with-comments code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directJson5 = $highlighter->highlight(
            "// note\n{enabled: true, limit: +10, ratio: .5, value: NaN}\n",
            'json5'
        );

        $t->same('jsonc', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('jsonc'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('json5'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('json-with-comments'));
        $t->same('jsonc', SyntaxHighlighter::normalizeLanguage('language-json.comments'));
        $t->same('jsonc', $highlighted['language']);
        $t->same('jsonc', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(660, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource jsonc numberLines"><code class="sourceCode jsonc" style="counter-reset: source-line 659;">', $highlighted['html']);
        $t->contains('<span id="jsonc-review-660"><a href="#jsonc-review-660"></a><span class="co">// WordPress import review settings</span></span>', $highlighted['html']);
        $t->contains('<span class="co">// Keep unsafe legacy shortcodes visible for editors.</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;source&quot;</span><span class="op">:</span> <span class="st">&quot;legacy-42&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">unlistedBlocks</span><span class="op">:</span> <span class="op">[</span><span class="st">&quot;core/html&quot;</span><span class="op">,</span> <span class="st">&quot;legacy/shortcode&quot;</span><span class="op">],</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;download&quot;</span><span class="op">:</span> <span class="cn">true</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;maxBytes&quot;</span><span class="op">:</span> <span class="dv">1048576</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="co">/* Reviewer-only routing; ignored by strict JSON consumers. */</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;notify&quot;</span><span class="op">:</span> <span class="cn">null</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;dryRun&quot;</span><span class="op">:</span> <span class="cn">false</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="co">/* Reviewer-only routing; ignored by strict JSON consumers. */</span>', $wordpressBlock);
        $t->same('jsonc', $directJson5['language']);
        $t->same('json5', $directJson5['requestedLanguage']);
        $t->contains('<span class="co">// note</span>', $directJson5['html']);
        $t->contains('<span class="ot">enabled</span><span class="op">:</span> <span class="cn">true</span>', $directJson5['html']);
        $t->contains('<span class="ot">limit</span><span class="op">:</span> <span class="dv">+10</span>', $directJson5['html']);
        $t->contains('<span class="ot">ratio</span><span class="op">:</span> <span class="dv">.5</span>', $directJson5['html']);
        $t->contains('<span class="ot">value</span><span class="op">:</span> <span class="cn">NaN</span>', $directJson5['html']);
    },
    'highlights less block theme snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[52] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a LESS code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directLess = $highlighter->highlight('@width: 10px; .card { width: @width * 2; }', 'less-css');

        $t->same('less', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('less'));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('less-css'));
        $t->same('less', SyntaxHighlighter::normalizeLanguage('language-lesscss'));
        $t->same('less', $highlighted['language']);
        $t->same('less', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(680, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource less numberLines"><code class="sourceCode less" style="counter-reset: source-line 679;">', $highlighted['html']);
        $t->contains('<span id="less-review-680"><a href="#less-review-680"></a><span class="co">// WordPress block theme LESS review</span></span>', $highlighted['html']);
        $t->contains('<span class="va">@accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">@spacing</span><span class="op">:</span> <span class="dv">1.5rem</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="dt">.import-card</span><span class="op">(</span><span class="va">@selector</span><span class="op">,</span> <span class="va">@state</span><span class="op">:</span> hover<span class="op">)</span> <span class="kw">when</span>', $highlighted['html']);
        $t->contains('<span class="va">@{selector}</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">--accent-color</span><span class="op">:</span> <span class="va">@accent-color</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">margin-block</span><span class="op">:</span> <span class="va">@spacing</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">color</span><span class="op">:</span> <span class="fu">darken</span><span class="op">(</span><span class="va">@accent-color</span><span class="op">,</span> <span class="dv">10%</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="op">&amp;</span><span class="fu">:hover</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">lighten</span><span class="op">(</span><span class="va">@accent-color</span><span class="op">,</span> <span class="dv">8%</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="fu">lighten</span><span class="op">(</span><span class="va">@accent-color</span>', $wordpressBlock);
        $t->same('less', $directLess['language']);
        $t->same('less-css', $directLess['requestedLanguage']);
        $t->contains('<span class="va">@width</span><span class="op">:</span> <span class="dv">10px</span>', $directLess['html']);
        $t->contains('<span class="dt">.card</span> <span class="op">{</span> <span class="ot">width</span><span class="op">:</span> <span class="va">@width</span> <span class="op">*</span> <span class="dv">2</span><span class="op">;</span>', $directLess['html']);
    },
    'highlights typst review templates with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[53] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Typst code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directTypst = $highlighter->highlight('#let card = rect(fill: rgb("#fff"))', 'typ');

        $t->same('typst', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('typst'));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('typ'));
        $t->same('typst', SyntaxHighlighter::normalizeLanguage('language-typst-source'));
        $t->same('typst', $highlighted['language']);
        $t->same('typst', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(700, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource typst numberLines"><code class="sourceCode typst" style="counter-reset: source-line 699;">', $highlighted['html']);
        $t->contains('<span id="typst-review-700"><a href="#typst-review-700"></a><span class="co">// WordPress import Typst review template</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">#set</span> <span class="dt">page</span><span class="op">(</span><span class="ot">width</span><span class="op">:</span> <span class="dv">8.5in</span>', $highlighted['html']);
        $t->contains('<span class="kw">#set</span> <span class="dt">text</span><span class="op">(</span><span class="ot">font</span><span class="op">:</span> <span class="st">&quot;Source Sans 3&quot;</span><span class="op">,</span> <span class="ot">size</span><span class="op">:</span> <span class="dv">11pt</span>', $highlighted['html']);
        $t->contains('<span class="kw">#let</span> <span class="va">source-id</span> <span class="op">=</span> <span class="st">&quot;legacy-42&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">#let</span> <span class="fu">badge</span><span class="op">(</span><span class="va">body</span><span class="op">)</span> <span class="op">=</span> <span class="dt">rect</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="fu">rgb</span><span class="op">(</span><span class="st">&quot;#005cc5&quot;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="re">=</span> <span class="va">#title</span>', $highlighted['html']);
        $t->contains('<span class="fu">#badge</span><span class="op">([</span><span class="va">Needs</span> <span class="va">review</span><span class="op">])</span>', $highlighted['html']);
        $t->contains('<span class="kw">#show</span> <span class="dt">link</span><span class="op">:</span> <span class="va">it</span> <span class="op">=&gt;</span> <span class="fu">underline</span>', $highlighted['html']);
        $t->contains('<span class="fu">#link</span><span class="op">(</span><span class="st">&quot;https://example.test/wp-admin/post.php?post=#source-id&quot;</span><span class="op">)[</span><span class="va">Review</span> <span class="va">source</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="fu">#table</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="ot">columns</span><span class="op">:</span> <span class="op">(</span><span class="dv">1fr</span><span class="op">,</span> <span class="dv">2fr</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="fu">#table</span><span class="op">(</span>', $wordpressBlock);
        $t->same('typst', $directTypst['language']);
        $t->same('typ', $directTypst['requestedLanguage']);
        $t->contains('<span class="kw">#let</span> <span class="va">card</span> <span class="op">=</span> <span class="dt">rect</span><span class="op">(</span><span class="ot">fill</span><span class="op">:</span> <span class="fu">rgb</span><span class="op">(</span><span class="st">&quot;#fff&quot;</span><span class="op">))</span>', $directTypst['html']);
    },
    'highlights typst dotted helper functions for review templates' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '#let title = "Review"',
            '#assert.eq(title, "Review")',
            '#counter(heading).at(here())',
        ]);
        $highlighted = (new SyntaxHighlighter())->highlight($source, 'typst');

        $t->same('typst', $highlighted['language']);
        $t->contains('<span class="kw">#let</span> <span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Review&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">#assert.eq</span><span class="op">(</span><span class="va">title</span><span class="op">,</span> <span class="st">&quot;Review&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">#counter</span><span class="op">(</span><span class="dt">heading</span><span class="op">).</span><span class="fu">at</span><span class="op">(</span><span class="fu">here</span><span class="op">())</span>', $highlighted['html']);
    },
    'highlights typst import aliases labels references and raw spans' => static function (TestRunner $t): void {
        $source = implode("\n", [
            '/* WordPress Typst package review */',
            '#import "@preview/cetz:0.3.1": canvas as draw',
            '#include "partials/header.typ"',
            '= #title <review-heading>',
            'See @review-heading and `raw shortcode`.',
        ]);

        $highlighted = (new SyntaxHighlighter())->highlight($source, 'typst');

        $t->same('typst', $highlighted['language']);
        $t->contains('<span class="co">/* WordPress Typst package review */</span>', $highlighted['html']);
        $t->contains('<span class="kw">#import</span> <span class="st">&quot;@preview/cetz:0.3.1&quot;</span><span class="op">:</span> <span class="va">canvas</span> <span class="kw">as</span> <span class="va">draw</span>', $highlighted['html']);
        $t->contains('<span class="kw">#include</span> <span class="st">&quot;partials/header.typ&quot;</span>', $highlighted['html']);
        $t->contains('<span class="re">=</span> <span class="va">#title</span> <span class="ot">&lt;review-heading&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">See</span> <span class="ot">@review-heading</span> <span class="va">and</span> <span class="st">`raw shortcode`</span><span class="op">.</span>', $highlighted['html']);
    },
    'hands typst import review packets to wordpress blocks' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'typst-import-review',
            'classes' => ['typ', 'numberLines'],
            'attributes' => ['startFrom' => '720'],
            'text' => "#import \"template.typ\": render as review\n#review([Body]) <review-block>\nSee @review-block",
        ]);

        $block = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');

        $t->contains('<!-- wp:html -->', $block);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $block);
        $t->contains('<pre class="sourceCode numberSource typ numberLines"><code class="sourceCode typst" style="counter-reset: source-line 719;">', $block);
        $t->contains('<span id="typst-import-review-720"><a href="#typst-import-review-720"></a><span class="kw">#import</span> <span class="st">&quot;template.typ&quot;</span><span class="op">:</span> <span class="va">render</span> <span class="kw">as</span> <span class="va">review</span></span>', $block);
        $t->contains('<span class="fu">#review</span><span class="op">([</span><span class="va">Body</span><span class="op">])</span> <span class="ot">&lt;review-block&gt;</span>', $block);
        $t->contains('<span class="va">See</span> <span class="ot">@review-block</span>', $block);
        $t->contains('<!-- /wp:html -->', $block);
    },
    'highlights kotlin android review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[54] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Kotlin code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directKotlin = $highlighter->highlight('val block: String? = null; println(block ?: "Untitled")', 'kotlin-script');

        $t->same('kt', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kt'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kts'));
        $t->same('kotlin', SyntaxHighlighter::normalizeLanguage('kotlin-script'));
        $t->same('kotlin', $highlighted['language']);
        $t->same('kt', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(720, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource kt numberLines"><code class="sourceCode kotlin" style="counter-reset: source-line 719;">', $highlighted['html']);
        $t->contains('<span id="kotlin-review-720"><a href="#kotlin-review-720"></a><span class="co">// Android WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="va">org</span><span class="op">.</span><span class="va">wordpress</span><span class="op">.</span><span class="va">importer</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">kotlinx</span><span class="op">.</span><span class="va">serialization</span><span class="op">.</span><span class="dt">Serializable</span>', $highlighted['html']);
        $t->contains('<span class="ot">@Serializable</span>', $highlighted['html']);
        $t->contains('<span class="kw">data</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="kw">val</span> <span class="va">title</span><span class="op">:</span> <span class="dt">String</span><span class="op">?,</span>', $highlighted['html']);
        $t->contains('<span class="kw">val</span> <span class="va">media</span><span class="op">:</span> <span class="dt">List</span><span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;</span> <span class="op">=</span> <span class="fu">emptyList</span><span class="op">(),</span>', $highlighted['html']);
        $t->contains('<span class="kw">fun</span> <span class="fu">normalizeTitle</span><span class="op">(</span><span class="va">raw</span><span class="op">:</span> <span class="dt">String</span><span class="op">):</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="kw">val</span> <span class="va">packet</span> <span class="op">=</span> <span class="dt">Json</span><span class="op">.</span><span class="fu">decodeFromString</span><span class="op">&lt;</span><span class="dt">ReviewPacket</span><span class="op">&gt;(</span><span class="va">raw</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trim</span><span class="op">()?.</span><span class="fu">ifBlank</span> <span class="op">{</span> <span class="st">&quot;Import ${packet.sourceId}&quot;</span> <span class="op">}</span> <span class="op">?:</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">val</span> <span class="va">blocks</span> <span class="op">=</span> <span class="fu">mapOf</span><span class="op">(</span><span class="st">&quot;core/paragraph&quot;</span> <span class="kw">to</span> <span class="cn">true</span><span class="op">,</span> <span class="st">&quot;core/html&quot;</span> <span class="kw">to</span> <span class="cn">false</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">decodeFromString</span><span class="op">&lt;</span><span class="dt">ReviewPacket</span>', $wordpressBlock);
        $t->same('kotlin', $directKotlin['language']);
        $t->same('kotlin-script', $directKotlin['requestedLanguage']);
        $t->contains('<span class="kw">val</span> <span class="va">block</span><span class="op">:</span> <span class="dt">String</span><span class="op">?</span> <span class="op">=</span> <span class="cn">null</span>', $directKotlin['html']);
        $t->contains('<span class="fu">println</span><span class="op">(</span><span class="va">block</span> <span class="op">?:</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $directKotlin['html']);
    },
    'highlights dart flutter review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[55] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Dart code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directDart = $highlighter->highlight('const blocks = <String>["core/paragraph"];', 'flutter');

        $t->same('dart', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('dart'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('dartlang'));
        $t->same('dart', SyntaxHighlighter::normalizeLanguage('flutter'));
        $t->same('dart', $highlighted['language']);
        $t->same('dart', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(740, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource dart numberLines"><code class="sourceCode dart" style="counter-reset: source-line 739;">', $highlighted['html']);
        $t->contains('<span id="dart-review-740"><a href="#dart-review-740"></a><span class="co">// Flutter WordPress import review card</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="st">&#039;package:flutter/widgets.dart&#039;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">@immutable</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="dt">ReviewPacket</span><span class="op">({</span><span class="kw">required</span> <span class="kw">this</span><span class="op">.</span><span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="kw">final</span> <span class="dt">List</span><span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;</span> <span class="va">media</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="dt">Future</span><span class="op">&lt;</span><span class="dt">Widget</span><span class="op">&gt;</span> <span class="fu">buildCard</span><span class="op">(</span><span class="dt">BuildContext</span> <span class="va">context</span><span class="op">)</span> <span class="kw">async</span>', $highlighted['html']);
        $t->contains('<span class="kw">final</span> <span class="va">safeTitle</span> <span class="op">=</span> <span class="va">title</span><span class="op">.</span><span class="fu">trim</span><span class="op">().</span><span class="va">isEmpty</span> <span class="op">?</span> <span class="st">&#039;Untitled&#039;</span> <span class="op">:</span> <span class="va">title</span><span class="op">.</span><span class="fu">trim</span><span class="op">();</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="dt">Column</span><span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="va">children</span><span class="op">:</span> <span class="op">&lt;</span><span class="dt">Widget</span><span class="op">&gt;[</span>', $highlighted['html']);
        $t->contains('<span class="dt">Text</span><span class="op">(</span><span class="st">&#039;Import $sourceId: $safeTitle&#039;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">media</span><span class="op">.</span><span class="va">isNotEmpty</span><span class="op">)</span> <span class="dt">Text</span><span class="op">(</span><span class="st">&#039;${media.length} attachments&#039;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="fu">buildCard</span><span class="op">(</span><span class="dt">BuildContext</span>', $wordpressBlock);
        $t->same('dart', $directDart['language']);
        $t->same('flutter', $directDart['requestedLanguage']);
        $t->contains('<span class="kw">const</span> <span class="va">blocks</span> <span class="op">=</span> <span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">];</span>', $directDart['html']);
    },
    'highlights swiftui review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[56] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Swift code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directSwift = $highlighter->highlight(
            'let title: String? = nil; Text(title ?? "Untitled")',
            'swift-source'
        );

        $t->same('swift', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swift'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swiftui'));
        $t->same('swift', SyntaxHighlighter::normalizeLanguage('swift-source'));
        $t->same('swift', $highlighted['language']);
        $t->same('swift', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(760, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource swift numberLines"><code class="sourceCode swift" style="counter-reset: source-line 759;">', $highlighted['html']);
        $t->contains('<span id="swift-review-760"><a href="#swift-review-760"></a><span class="co">// SwiftUI WordPress import review card</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">SwiftUI</span>', $highlighted['html']);
        $t->contains('<span class="kw">struct</span> <span class="dt">ReviewPacket</span><span class="op">:</span> <span class="dt">Decodable</span><span class="op">,</span> <span class="dt">Identifiable</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">title</span><span class="op">:</span> <span class="dt">String</span><span class="op">?</span>', $highlighted['html']);
        $t->contains('<span class="ot">@MainActor</span>', $highlighted['html']);
        $t->contains('<span class="kw">final</span> <span class="kw">class</span> <span class="dt">ReviewModel</span><span class="op">:</span> <span class="dt">ObservableObject</span>', $highlighted['html']);
        $t->contains('<span class="ot">@Published</span> <span class="kw">var</span> <span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">?</span>', $highlighted['html']);
        $t->contains('<span class="kw">func</span> <span class="fu">load</span><span class="op">(</span><span class="va">from</span> <span class="va">data</span><span class="op">:</span> <span class="dt">Data</span><span class="op">)</span> <span class="kw">async</span> <span class="kw">throws</span>', $highlighted['html']);
        $t->contains('<span class="kw">try</span> <span class="dt">JSONDecoder</span><span class="op">().</span><span class="fu">decode</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">.</span><span class="kw">self</span>', $highlighted['html']);
        $t->contains('<span class="ot">@StateObject</span> <span class="kw">private</span> <span class="kw">var</span> <span class="va">model</span> <span class="op">=</span> <span class="dt">ReviewModel</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">var</span> <span class="va">body</span><span class="op">:</span> <span class="kw">some</span> <span class="dt">View</span>', $highlighted['html']);
        $t->contains('<span class="dt">Text</span><span class="op">(</span><span class="va">model</span><span class="op">.</span><span class="va">packet</span><span class="op">?.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trimmingCharacters</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="kw">let</span> <span class="va">count</span> <span class="op">=</span> <span class="va">model</span><span class="op">.</span><span class="va">packet</span><span class="op">?.</span><span class="va">media</span><span class="op">.</span><span class="va">count</span>', $highlighted['html']);
        $t->contains('<span class="dt">Button</span><span class="op">(</span><span class="st">&quot;Review in WordPress&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="dt">Task</span> <span class="op">{</span> <span class="kw">try</span><span class="op">?</span> <span class="kw">await</span> <span class="va">model</span><span class="op">.</span><span class="fu">load</span>', $wordpressBlock);
        $t->same('swift', $directSwift['language']);
        $t->same('swift-source', $directSwift['requestedLanguage']);
        $t->contains('<span class="kw">let</span> <span class="va">title</span><span class="op">:</span> <span class="dt">String</span><span class="op">?</span> <span class="op">=</span> <span class="cn">nil</span>', $directSwift['html']);
        $t->contains('<span class="dt">Text</span><span class="op">(</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $directSwift['html']);
    },
    'highlights clojure and edn review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[57] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Clojure code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directEdn = $highlighter->highlight(
            '{:post/title "Imported" :published? true :blocks #{"core/paragraph"}}',
            'edn'
        );

        $t->same('clj', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('clojure'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('clj'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('cljs'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('cljc'));
        $t->same('clojure', SyntaxHighlighter::normalizeLanguage('edn'));
        $t->same('clojure', $highlighted['language']);
        $t->same('clj', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(780, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource clj numberLines"><code class="sourceCode clojure" style="counter-reset: source-line 779;">', $highlighted['html']);
        $t->contains('<span id="clojure-review-780"><a href="#clojure-review-780"></a><span class="co">;; Babashka WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">ns</span> <span class="va">importer.review</span>', $highlighted['html']);
        $t->contains('<span class="ot">:require</span> <span class="op">[</span><span class="va">clojure.edn</span> <span class="ot">:as</span> <span class="va">edn</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">defn</span> <span class="va">normalize-title</span> <span class="op">[</span><span class="va">packet</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="op">[</span><span class="va">title</span> <span class="op">(</span><span class="fu">str/trim</span>', $highlighted['html']);
        $t->contains('<span class="ot">:post/title</span> <span class="va">packet</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">str/blank?</span> <span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">str</span> <span class="st">&quot;Import &quot;</span> <span class="op">(</span><span class="ot">:source/id</span> <span class="va">packet</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="va">review-packet</span>', $highlighted['html']);
        $t->contains('<span class="ot">:source/id</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<span class="ot">:media/items</span> <span class="op">[{</span><span class="ot">:url</span> <span class="st">&quot;uploads/hero.jpg&quot;</span> <span class="ot">:alt</span> <span class="cn">nil</span><span class="op">}]</span>', $highlighted['html']);
        $t->contains('<span class="pp">#_</span><span class="op">(</span><span class="fu">println</span> <span class="st">&quot;discarded debug&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="fu">map</span> <span class="va">normalize-title</span> <span class="op">[</span><span class="va">review-packet</span><span class="op">])</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="fu">map</span> <span class="va">normalize-title</span>', $wordpressBlock);
        $t->same('clojure', $directEdn['language']);
        $t->same('edn', $directEdn['requestedLanguage']);
        $t->contains('<span class="ot">:post/title</span> <span class="st">&quot;Imported&quot;</span>', $directEdn['html']);
        $t->contains('<span class="ot">:published?</span> <span class="cn">true</span>', $directEdn['html']);
        $t->contains('<span class="ot">:blocks</span> <span class="op">#{</span><span class="st">&quot;core/paragraph&quot;</span>', $directEdn['html']);
    },
    'highlights scala review snippets with sbt aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[58] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Scala code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directSbt = $highlighter->highlight(
            'ThisBuild / scalaVersion := "3.5.0"; lazy val importer = project',
            'sbt'
        );

        $t->same('scala', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('scala'));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('sbt'));
        $t->same('scala', SyntaxHighlighter::normalizeLanguage('scala-sbt'));
        $t->same('scala', $highlighted['language']);
        $t->same('scala', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(800, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource scala numberLines"><code class="sourceCode scala" style="counter-reset: source-line 799;">', $highlighted['html']);
        $t->contains('<span id="scala-review-800"><a href="#scala-review-800"></a><span class="co">// Scala WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="va">importer</span><span class="op">.</span><span class="va">review</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">scala</span><span class="op">.</span><span class="va">util</span><span class="op">.</span><span class="dt">Try</span>', $highlighted['html']);
        $t->contains('<span class="kw">final</span> <span class="kw">case</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">:</span> <span class="dt">Option</span><span class="op">[</span><span class="dt">String</span><span class="op">],</span>', $highlighted['html']);
        $t->contains('<span class="va">media</span><span class="op">:</span> <span class="dt">List</span><span class="op">[</span><span class="dt">String</span><span class="op">]</span> <span class="op">=</span> <span class="cn">Nil</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="op">)</span> <span class="kw">derives</span> <span class="dt">CanEqual</span>', $highlighted['html']);
        $t->contains('<span class="kw">object</span> <span class="dt">ReviewPacket</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="kw">private</span> <span class="kw">val</span> <span class="va">defaultTitle</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">normalize</span><span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">):</span> <span class="dt">String</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="kw">val</span> <span class="va">title</span> <span class="op">=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">map</span><span class="op">(</span><span class="va">_</span><span class="op">.</span><span class="va">trim</span><span class="op">).</span><span class="fu">filter</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">title</span><span class="op">.</span><span class="va">isEmpty</span> <span class="kw">then</span> <span class="st">s&quot;Import ${packet.sourceId}&quot;</span> <span class="kw">else</span> <span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="dt">Map</span><span class="op">(</span><span class="st">&quot;core/paragraph&quot;</span> <span class="op">-&gt;</span> <span class="cn">true</span><span class="op">,</span> <span class="st">&quot;core/html&quot;</span> <span class="op">-&gt;</span> <span class="cn">false</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">object</span> <span class="dt">ReviewPacket</span>', $wordpressBlock);
        $t->same('scala', $directSbt['language']);
        $t->same('sbt', $directSbt['requestedLanguage']);
        $t->contains('<span class="dt">ThisBuild</span> <span class="op">/</span> <span class="va">scalaVersion</span> <span class="op">:=</span> <span class="st">&quot;3.5.0&quot;</span>', $directSbt['html']);
        $t->contains('<span class="kw">lazy</span> <span class="kw">val</span> <span class="va">importer</span> <span class="op">=</span> <span class="va">project</span>', $directSbt['html']);
    },
    'highlights elixir phoenix review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[59] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Elixir code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directExs = $highlighter->highlight(
            'defmodule Importer.Script do; @spec run(String.t()) :: :ok; def run(title), do: title |> String.trim(); end',
            'exs'
        );

        $t->same('ex', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('elixir'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('ex'));
        $t->same('elixir', SyntaxHighlighter::normalizeLanguage('exs'));
        $t->same('elixir', $highlighted['language']);
        $t->same('ex', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(820, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ex numberLines"><code class="sourceCode elixir" style="counter-reset: source-line 819;">', $highlighted['html']);
        $t->contains('<span id="elixir-review-820"><a href="#elixir-review-820"></a><span class="co"># Phoenix WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">defmodule</span> <span class="dt">Importer</span><span class="op">.</span><span class="dt">ReviewPacket</span> <span class="kw">do</span>', $highlighted['html']);
        $t->contains('<span class="ot">@derive</span> <span class="dt">Jason</span><span class="op">.</span><span class="dt">Encoder</span>', $highlighted['html']);
        $t->contains('<span class="ot">@enforce_keys</span> <span class="op">[</span><span class="cn">:source_id</span><span class="op">,</span> <span class="cn">:title</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">defstruct</span> <span class="op">[</span><span class="cn">:source_id</span><span class="op">,</span> <span class="cn">:title</span><span class="op">,</span> <span class="ot">media</span><span class="op">:</span> <span class="op">[],</span> <span class="ot">blocks</span><span class="op">:</span> <span class="dt">MapSet</span><span class="op">.</span><span class="fu">new</span><span class="op">()]</span>', $highlighted['html']);
        $t->contains('<span class="ot">@spec</span> <span class="fu">normalize_title</span><span class="op">(%</span><span class="dt">__MODULE__</span><span class="op">{})</span> <span class="op">::</span> <span class="dt">String</span><span class="op">.</span><span class="fu">t</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">normalize_title</span><span class="op">(%</span><span class="dt">__MODULE__</span><span class="op">{</span><span class="ot">title</span><span class="op">:</span> <span class="va">title</span><span class="op">,</span> <span class="ot">source_id</span><span class="op">:</span> <span class="va">source_id</span><span class="op">})</span> <span class="kw">when</span> <span class="fu">is_binary</span><span class="op">(</span><span class="va">title</span><span class="op">)</span> <span class="kw">do</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="op">|&gt;</span> <span class="dt">String</span><span class="op">.</span><span class="fu">trim</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="op">|&gt;</span> <span class="kw">case</span> <span class="kw">do</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;&quot;</span> <span class="op">-&gt;</span> <span class="st">&quot;Import #{source_id}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">with</span> <span class="op">{</span><span class="cn">:ok</span><span class="op">,</span> <span class="va">packet</span><span class="op">}</span> <span class="op">&lt;-</span> <span class="dt">Jason</span><span class="op">.</span><span class="fu">decode</span><span class="op">(</span><span class="va">raw</span><span class="op">,</span> <span class="ot">keys</span><span class="op">:</span> <span class="cn">:atoms</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="cn">:source_id</span>', $highlighted['html']);
        $t->contains('<span class="op">-&gt;</span> <span class="op">{</span><span class="cn">:error</span><span class="op">,</span> <span class="cn">:invalid_packet</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="dt">Jason</span><span class="op">.</span><span class="fu">decode</span>', $wordpressBlock);
        $t->same('elixir', $directExs['language']);
        $t->same('exs', $directExs['requestedLanguage']);
        $t->contains('<span class="kw">defmodule</span> <span class="dt">Importer</span><span class="op">.</span><span class="dt">Script</span> <span class="kw">do</span>', $directExs['html']);
        $t->contains('<span class="ot">@spec</span> <span class="fu">run</span><span class="op">(</span><span class="dt">String</span><span class="op">.</span><span class="fu">t</span><span class="op">())</span> <span class="op">::</span> <span class="cn">:ok</span>', $directExs['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">run</span><span class="op">(</span><span class="va">title</span><span class="op">),</span> <span class="ot">do</span><span class="op">:</span> <span class="va">title</span> <span class="op">|&gt;</span> <span class="dt">String</span><span class="op">.</span><span class="fu">trim</span><span class="op">();</span>', $directExs['html']);
    },
    'highlights vue sfc review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[60] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Vue SFC code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directVue = $highlighter->highlight(
            '<template><ImportCard :title="post.title">{{ post.title ?? "Untitled" }}</ImportCard></template>',
            'html-vue'
        );

        $t->same('vue', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('vue'));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('vuejs'));
        $t->same('vue', SyntaxHighlighter::normalizeLanguage('vue-component'));
        $t->same('vue', $highlighted['language']);
        $t->same('vue', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(840, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource vue numberLines"><code class="sourceCode vue" style="counter-reset: source-line 839;">', $highlighted['html']);
        $t->contains('<span id="vue-sfc-review-840"><a href="#vue-sfc-review-840"></a><span class="co">&lt;!-- Vue WordPress import card review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;template</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;article</span> <span class="ot">class</span><span class="op">=</span><span class="st">&quot;wp-block-import-card&quot;</span> <span class="ot">:data-source</span><span class="op">=</span><span class="st">&quot;packet.sourceId&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="op">&gt;{{</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trim</span><span class="op">()</span> <span class="op">||</span> <span class="st">&quot;Untitled&quot;</span> <span class="op">}}</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;button</span> <span class="ot">v-if</span><span class="op">=</span><span class="st">&quot;packet.reviewUrl&quot;</span> <span class="ot">@click</span><span class="op">=</span><span class="st">&quot;openReview(packet.reviewUrl)&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;script</span> <span class="ot">setup</span> <span class="ot">lang</span><span class="op">=</span><span class="st">&quot;ts&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="op">{</span> <span class="va">computed</span> <span class="op">}</span> <span class="kw">from</span> <span class="st">&quot;vue&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">reviewUrl</span><span class="op">?:</span> <span class="dt">string</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="va">props</span> <span class="op">=</span> <span class="fu">defineProps</span><span class="op">&lt;{</span> <span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">}&gt;();</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="fu">openReview</span><span class="op">(</span><span class="va">url</span><span class="op">:</span> <span class="dt">string</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">window</span><span class="op">.</span><span class="va">location</span><span class="op">.</span><span class="va">href</span> <span class="op">=</span> <span class="va">url</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;style</span> <span class="ot">scoped</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="dt">.wp-block-import-card</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">text-decoration</span><span class="op">:</span> underline<span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="ot">@click</span><span class="op">=</span><span class="st">&quot;openReview(packet.reviewUrl)&quot;</span>', $wordpressBlock);
        $t->same('vue', $directVue['language']);
        $t->same('html-vue', $directVue['requestedLanguage']);
        $t->contains('<span class="fu">&lt;ImportCard</span> <span class="ot">:title</span><span class="op">=</span><span class="st">&quot;post.title&quot;</span><span class="op">&gt;{{</span>', $directVue['html']);
        $t->contains('<span class="op">&gt;{{</span> <span class="va">post</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">&quot;Untitled&quot;</span> <span class="op">}}</span>', $directVue['html']);
    },
    'highlights vue sfc custom block metadata with embedded languages' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[63] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Vue custom-block code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directVue = $highlighter->highlight(
            "<i18n lang=\"yaml\">\nen:\n  title: Imported\n</i18n>\n<docs>\n## Notes\n</docs>",
            'vue-sfc'
        );

        $t->same('vue', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('vue', $highlighted['language']);
        $t->same('vue', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(920, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource vue numberLines"><code class="sourceCode vue" style="counter-reset: source-line 919;">', $highlighted['html']);
        $t->contains('<span id="vue-custom-block-review-920"><a href="#vue-custom-block-review-920"></a><span class="co">&lt;!-- Vue custom metadata blocks for WordPress review --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;i18n</span> <span class="ot">lang</span><span class="op">=</span><span class="st">&quot;json&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;title&quot;</span><span class="op">:</span><span class="st">&quot;Imported&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;review&quot;</span><span class="op">:</span><span class="cn">true</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;route</span> <span class="ot">lang</span><span class="op">=</span><span class="st">&quot;yaml&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="ot">meta</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="ot">requiresReview</span><span class="op">:</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;docs</span> <span class="ot">lang</span><span class="op">=</span><span class="st">&quot;md&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="re">## Import Notes</span>', $highlighted['html']);
        $t->contains('<span class="op">- </span><span class="cn">[x]</span> Review <span class="ot">[queue](https://example.test/wp-admin/edit.php)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="ot">&quot;review&quot;</span><span class="op">:</span><span class="cn">true</span>', $wordpressBlock);
        $t->same('vue', $directVue['language']);
        $t->same('vue-sfc', $directVue['requestedLanguage']);
        $t->contains('<span class="ot">en</span><span class="op">:</span>', $directVue['html']);
        $t->contains('<span class="re">## Notes</span>', $directVue['html']);
    },
    'highlights ocaml review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[61] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an OCaml code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directReason = $highlighter->highlight('let normalizeTitle = "Untitled";', 'reasonml');

        $t->same('ml', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('ml'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('ocaml-interface'));
        $t->same('ocaml', SyntaxHighlighter::normalizeLanguage('reasonml'));
        $t->same('ocaml', $highlighted['language']);
        $t->same('ml', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(880, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ml numberLines"><code class="sourceCode ocaml" style="counter-reset: source-line 879;">', $highlighted['html']);
        $t->contains('<span id="ocaml-review-880"><a href="#ocaml-review-880"></a><span class="co">(* WordPress import review normalizer *)</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">open</span> <span class="dt">Yojson.Safe</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="va">review_packet</span> <span class="op">=</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">source_id</span> <span class="op">:</span> <span class="dt">int</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">title</span> <span class="op">:</span> <span class="dt">string</span> <span class="dt">option</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="fu">normalize_title</span> <span class="op">?(</span><span class="va">fallback</span><span class="op">=</span><span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">|</span> <span class="cn">Some</span> <span class="va">title</span> <span class="kw">when</span> <span class="dt">String</span><span class="op">.</span><span class="fu">trim</span>', $highlighted['html']);
        $t->contains('<span class="dt">Printf</span><span class="op">.</span><span class="fu">sprintf</span> <span class="st">&quot;Import %d&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">Result.Ok</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="st">&quot;core/paragraph&quot;</span><span class="op">;</span> <span class="st">&quot;core/html&quot;</span>', $wordpressBlock);
        $t->same('ocaml', $directReason['language']);
        $t->same('reasonml', $directReason['requestedLanguage']);
        $t->contains('<span class="kw">let</span> <span class="va">normalizeTitle</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">;</span>', $directReason['html']);
    },
    'highlights julia review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[62] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Julia code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directJulia = $highlighter->highlight('function publish!(packet::ReviewPacket); @info "ok" dry_run=false; end', 'julia-source');

        $t->same('jl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('julia'));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('jl'));
        $t->same('julia', SyntaxHighlighter::normalizeLanguage('julia-source'));
        $t->same('julia', $highlighted['language']);
        $t->same('jl', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(900, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource jl numberLines"><code class="sourceCode julia" style="counter-reset: source-line 899;">', $highlighted['html']);
        $t->contains('<span id="julia-review-900"><a href="#julia-review-900"></a><span class="co"># WordPress import review normalizer</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">ImportReview</span>', $highlighted['html']);
        $t->contains('<span class="kw">using</span> <span class="dt">JSON3</span>', $highlighted['html']);
        $t->contains('<span class="dt">Base</span><span class="op">.</span><span class="ot">@kwdef</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="va">source_id</span><span class="op">::</span><span class="dt">Int</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">::</span><span class="dt">Union</span><span class="op">{</span><span class="dt">String</span><span class="op">,</span> <span class="dt">Nothing</span><span class="op">}</span> <span class="op">=</span> <span class="cn">nothing</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">packet</span><span class="op">::</span><span class="dt">ReviewPacket</span><span class="op">)::</span><span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">something</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">,</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">isempty</span><span class="op">(</span><span class="fu">strip</span><span class="op">(</span><span class="va">title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">&quot;Import $(packet.source_id)&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">JSON3</span><span class="op">.</span><span class="fu">read</span><span class="op">(</span><span class="va">raw_json</span><span class="op">,</span> <span class="dt">ReviewPacket</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">@info</span> <span class="st">&quot;review packet&quot;</span> <span class="ot">source</span><span class="op">=</span><span class="va">packet</span><span class="op">.</span><span class="va">source_id</span> <span class="ot">dry_run</span><span class="op">=</span><span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="dt">JSON3</span><span class="op">.</span><span class="fu">read</span>', $wordpressBlock);
        $t->same('julia', $directJulia['language']);
        $t->same('julia-source', $directJulia['requestedLanguage']);
        $t->contains('<span class="kw">function</span> <span class="fu">publish!</span><span class="op">(</span><span class="va">packet</span><span class="op">::</span><span class="dt">ReviewPacket</span><span class="op">);</span>', $directJulia['html']);
        $t->contains('<span class="ot">@info</span> <span class="st">&quot;ok&quot;</span> <span class="ot">dry_run</span><span class="op">=</span><span class="cn">false</span>', $directJulia['html']);
    },
    'highlights html attributes sql keywords and functions for review packets' => static function (TestRunner $t): void {
        $highlighter = new SyntaxHighlighter();
        $html = $highlighter->highlight('<section data-id="42"><code>$post</code></section>', 'html5');
        $sql = $highlighter->highlight("select count(*) from posts where post_status = 'publish'", 'postgresql');

        $t->same('html', $html['language']);
        $t->contains('<span class="kw">&lt;section</span> <span class="ot">data-id</span><span class="op">=</span><span class="st">&quot;42&quot;</span><span class="op">&gt;</span>', $html['html']);
        $t->contains('<span class="kw">&lt;/section</span><span class="op">&gt;</span>', $html['html']);
        $t->same('sql', $sql['language']);
        $t->contains('<span class="kw">select</span> <span class="fu">count</span><span class="op">(*)</span> <span class="kw">from</span>', $sql['html']);
        $t->contains('<span class="st">&#039;publish&#039;</span>', $sql['html']);
    },
    'highlights sql migration snippets with mysql and sqlite aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[30] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a SQL migration code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $sqlite = $highlighter->highlight(
            'WITH posts AS (SELECT 42 AS id, \'Imported\' AS "post_title") SELECT "post_title" FROM posts WHERE id = $1',
            'sqlite3'
        );

        $t->same('mysql', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('mysql'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('mariadb'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('sqlite3'));
        $t->same('sql', $highlighted['language']);
        $t->same('mysql', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(230, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource mysql numberLines"><code class="sourceCode sql" style="counter-reset: source-line 229;">', $highlighted['html']);
        $t->contains('<span id="sql-migration-review-230"><a href="#sql-migration-review-230"></a><span class="co">-- WordPress SQL migration review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">START</span> <span class="kw">TRANSACTION</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="kw">TABLE</span> <span class="ot">`wp_posts`</span>', $highlighted['html']);
        $t->contains('<span class="ot">`ID`</span> <span class="dt">bigint</span><span class="op">(</span><span class="dv">20</span><span class="op">)</span> <span class="kw">unsigned</span> <span class="kw">NOT</span> <span class="cn">NULL</span> <span class="kw">AUTO_INCREMENT</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="kw">PRIMARY</span> <span class="kw">KEY</span> <span class="op">(</span><span class="ot">`ID`</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">ON</span> <span class="kw">DUPLICATE</span> <span class="kw">KEY</span> <span class="kw">UPDATE</span> <span class="ot">`post_title`</span> <span class="op">=</span> <span class="kw">VALUES</span><span class="op">(</span><span class="ot">`post_title`</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">SELECT</span> <span class="fu">JSON_EXTRACT</span><span class="op">(</span><span class="ot">`meta_value`</span><span class="op">,</span> <span class="st">&#039;$.title&#039;</span><span class="op">)</span> <span class="kw">AS</span> <span class="ot">`title`</span>', $highlighted['html']);
        $t->contains('<span class="kw">WHERE</span> <span class="ot">`post_id`</span> <span class="op">=</span> <span class="va">:post_id</span> <span class="kw">AND</span> <span class="ot">`meta_key`</span> <span class="kw">LIKE</span> <span class="st">&#039;review\\_%&#039;</span> <span class="kw">ESCAPE</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="fu">JSON_EXTRACT</span><span class="op">(</span><span class="ot">`meta_value`</span>', $wordpressBlock);
        $t->same('sql', $sqlite['language']);
        $t->same('sqlite3', $sqlite['requestedLanguage']);
        $t->contains('<span class="kw">WITH</span> <span class="va">posts</span> <span class="kw">AS</span> <span class="op">(</span><span class="kw">SELECT</span> <span class="dv">42</span> <span class="kw">AS</span> <span class="va">id</span><span class="op">,</span> <span class="st">&#039;Imported&#039;</span> <span class="kw">AS</span> <span class="ot">&quot;post_title&quot;</span><span class="op">)</span>', $sqlite['html']);
        $t->contains('<span class="kw">WHERE</span> <span class="va">id</span> <span class="op">=</span> <span class="va">$1</span>', $sqlite['html']);
    },
    'highlights postgresql dollar quoted review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[31] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PostgreSQL trigger code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directPgsql = $highlighter->highlight(
            'DO $$ BEGIN RAISE NOTICE \'Imported %\', 42; END $$;',
            'plpgsql'
        );
        $taggedDollar = $highlighter->highlight(
            'SELECT $wp_import$<!-- wp:paragraph --><p>Imported</p><!-- /wp:paragraph -->$wp_import$::text;',
            'pgsql'
        );

        $t->same('pgsql', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('pgsql'));
        $t->same('sql', SyntaxHighlighter::normalizeLanguage('plpgsql'));
        $t->same('sql', $highlighted['language']);
        $t->same('pgsql', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(250, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pgsql numberLines"><code class="sourceCode sql" style="counter-reset: source-line 249;">', $highlighted['html']);
        $t->contains('<span id="postgres-trigger-review-250"><a href="#postgres-trigger-review-250"></a><span class="co">-- PostgreSQL trigger review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="kw">OR</span> <span class="kw">REPLACE</span> <span class="kw">FUNCTION</span> <span class="fu">wp_review_notice</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">RETURNS</span> <span class="dt">trigger</span>', $highlighted['html']);
        $t->contains('<span class="kw">LANGUAGE</span> <span class="dt">plpgsql</span>', $highlighted['html']);
        $t->contains('<span class="kw">AS</span> <span class="st">$review$</span>', $highlighted['html']);
        $t->contains('<span id="postgres-trigger-review-255"><a href="#postgres-trigger-review-255"></a><span class="st">BEGIN</span></span>', $highlighted['html']);
        $t->contains('<span class="st">  RAISE NOTICE &#039;import %&#039;, NEW.post_title;</span>', $highlighted['html']);
        $t->contains('<span class="st">$review$</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">CREATE</span> <span class="dt">TRIGGER</span> <span class="va">wp_review_before_insert</span>', $highlighted['html']);
        $t->contains('<span class="kw">FOR</span> <span class="kw">EACH</span> <span class="kw">ROW</span> <span class="kw">EXECUTE</span> <span class="kw">FUNCTION</span> <span class="fu">wp_review_notice</span><span class="op">();</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="st">$review$</span>', $wordpressBlock);
        $t->same('sql', $directPgsql['language']);
        $t->same('plpgsql', $directPgsql['requestedLanguage']);
        $t->contains('<span class="kw">DO</span> <span class="st">$$ BEGIN RAISE NOTICE &#039;Imported %&#039;, 42; END $$</span><span class="op">;</span>', $directPgsql['html']);
        $t->same('sql', $taggedDollar['language']);
        $t->contains('<span class="kw">SELECT</span> <span class="st">$wp_import$&lt;!-- wp:paragraph --&gt;&lt;p&gt;Imported&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;$wp_import$</span><span class="op">::</span><span class="dt">text</span><span class="op">;</span>', $taggedDollar['html']);
    },
    'highlights apache htaccess rewrite snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[32] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an htaccess rewrite code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directApache = $highlighter->highlight(
            'Header always set X-Frame-Options "SAMEORIGIN"',
            'apacheconf'
        );

        $t->same('htaccess', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('htaccess'));
        $t->same('apache', SyntaxHighlighter::normalizeLanguage('apacheconf'));
        $t->same('apache', $highlighted['language']);
        $t->same('htaccess', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(270, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource htaccess numberLines"><code class="sourceCode apache" style="counter-reset: source-line 269;">', $highlighted['html']);
        $t->contains('<span id="htaccess-review-270"><a href="#htaccess-review-270"></a><span class="co"># WordPress permalink review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;IfModule</span> <span class="dt">mod_rewrite.c</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteEngine</span> <span class="cn">On</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteBase</span> <span class="st">/</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteCond</span> <span class="va">%{REQUEST_FILENAME}</span> <span class="op">!-f</span>', $highlighted['html']);
        $t->contains('<span class="kw">RewriteRule</span> <span class="op">.</span> <span class="st">/index.php</span> <span class="ot">[L]</span>', $highlighted['html']);
        $t->contains('<span class="kw">Header</span> <span class="kw">set</span> <span class="va">X-Import-Source</span> <span class="st">&quot;legacy&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;/IfModule</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">RewriteRule</span> <span class="op">.</span> <span class="st">/index.php</span>', $wordpressBlock);
        $t->same('apache', $directApache['language']);
        $t->same('apacheconf', $directApache['requestedLanguage']);
        $t->contains('<span class="kw">Header</span> <span class="kw">always</span> <span class="kw">set</span> <span class="va">X-Frame-Options</span> <span class="st">&quot;SAMEORIGIN&quot;</span>', $directApache['html']);
    },
    'highlights restructuredtext review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[35] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a reStructuredText code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directRest = $highlighter->highlight('See :ref:`review queue` and https://example.test/import', 'rest');

        $t->same('rst', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('rest'));
        $t->same('rst', SyntaxHighlighter::normalizeLanguage('reStructuredText'));
        $t->same('rst', $highlighted['language']);
        $t->same('rst', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(330, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource rst numberLines"><code class="sourceCode rst" style="counter-reset: source-line 329;">', $highlighted['html']);
        $t->contains('<span id="rst-review-330"><a href="#rst-review-330"></a><span class="co">.. WordPress import review note</span></span>', $highlighted['html']);
        $t->contains('<span class="re">=============</span>', $highlighted['html']);
        $t->contains('<span class="fu">:source:</span> legacy<span class="op">-</span>doc', $highlighted['html']);
        $t->contains('<span class="fu">:status:</span> <span class="kw">**needs review**</span>', $highlighted['html']);
        $t->contains('<span class="dt">.. _review queue: https://example.test/wp-admin/edit.php</span>', $highlighted['html']);
        $t->contains('<span class="dt">.. code-block:: php</span>', $highlighted['html']);
        $t->contains('<span class="dt">   echo esc_html($title);</span>', $highlighted['html']);
        $t->contains('<span class="dt">``legacy_shortcode``</span>', $highlighted['html']);
        $t->contains('<span class="kw">:doc:</span><span class="cn">`media map &lt;uploads&gt;`</span>', $highlighted['html']);
        $t->contains('<span class="ot">`queue link`_</span>', $highlighted['html']);
        $t->contains('<span class="ot">https://example.test/review</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="dt">.. code-block:: php</span>', $wordpressBlock);
        $t->same('rst', $directRest['language']);
        $t->same('rest', $directRest['requestedLanguage']);
        $t->contains('<span class="kw">:ref:</span><span class="cn">`review queue`</span>', $directRest['html']);
        $t->contains('<span class="ot">https://example.test/import</span>', $directRest['html']);
    },
    'highlights haskell and literate haskell review snippets' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['sourceCode', 'literate-haskell'],
            'attributes' => [],
            'text' => implode("\n", [
                '{- migration review -}',
                'module Review.Import where',
                'import Text.Pandoc (Pandoc)',
                'renderBlocks :: Pandoc -> Text',
                'renderBlocks post = writeMarkdown def post',
                'status = Just 42',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');

        $t->same('literate-haskell', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('haskell', $highlighted['language']);
        $t->same('literate-haskell', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode haskell"><code class="sourceCode haskell">', $highlighted['html']);
        $t->contains('<span class="co">{- migration review -}</span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">Review.Import</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">Text.Pandoc</span> <span class="op">(</span><span class="dt">Pandoc</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">renderBlocks</span> <span class="op">::</span> <span class="dt">Pandoc</span> <span class="op">-&gt;</span> <span class="dt">Text</span>', $highlighted['html']);
        $t->contains('<span class="cn">Just</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">import</span> <span class="dt">Text.Pandoc</span>', $wordpressBlock);
    },
    'highlights tex and latex review snippets with pandoc alias handoff' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'classes' => ['sourceCode', 'language-latex'],
            'attributes' => [],
            'text' => implode("\n", [
                '\\documentclass[11pt]{article}',
                '\\usepackage{graphicx}',
                '% WordPress import review note',
                '\\newcommand{\\ReviewTitle}{$title$}',
                '\\begin{document}',
                '\\section{Import 42}',
                '\\includegraphics[width=0.5\\textwidth]{media.png}',
                '\\end{document}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');

        $t->same('latex', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('tex', $highlighted['language']);
        $t->same('latex', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode tex"><code class="sourceCode tex">', $highlighted['html']);
        $t->contains('<span class="kw">\\documentclass</span><span class="op">[</span><span class="dv">11</span><span class="va">pt</span><span class="op">]</span><span class="dt">{article}</span>', $highlighted['html']);
        $t->contains('<span class="co">% WordPress import review note</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\newcommand</span><span class="dt">{\\ReviewTitle}</span><span class="op">{</span><span class="va">$title$</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\begin</span><span class="dt">{document}</span>', $highlighted['html']);
        $t->contains('<span class="fu">\\includegraphics</span><span class="op">[</span><span class="va">width</span><span class="op">=</span><span class="dv">0.5</span><span class="fu">\\textwidth</span><span class="op">]</span><span class="dt">{media.png}</span>', $highlighted['html']);
        $t->contains('<span class="kw">\\end</span><span class="dt">{document}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">\\usepackage</span><span class="dt">{graphicx}</span>', $wordpressBlock);
    },
    'highlights unified diff and patch review snippets' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'source-diff',
            'classes' => ['sourceCode', 'patch', 'numberLines'],
            'attributes' => ['startFrom' => '9'],
            'text' => implode("\n", [
                'diff --git a/content.php b/content.php',
                'index 1111111..2222222 100644',
                '--- a/content.php',
                '+++ b/content.php',
                '@@ -1,3 +1,4 @@',
                '-echo $old_title;',
                '+echo esc_html($new_title);',
                ' context line',
                '\ No newline at end of file',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');

        $t->same('patch', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('diff', $highlighted['language']);
        $t->same('patch', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(9, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource patch numberLines"><code class="sourceCode diff" style="counter-reset: source-line 8;">', $highlighted['html']);
        $t->contains('<span id="source-diff-9"><a href="#source-diff-9"></a><span class="re">diff --git a/content.php b/content.php</span></span>', $highlighted['html']);
        $t->contains('<span id="source-diff-10"><a href="#source-diff-10"></a><span class="ot">index 1111111..2222222 100644</span></span>', $highlighted['html']);
        $t->contains('<span class="re">@@ -1,3 +1,4 @@</span>', $highlighted['html']);
        $t->contains('<span class="al">-echo $old_title;</span>', $highlighted['html']);
        $t->contains('<span class="in">+echo esc_html($new_title);</span>', $highlighted['html']);
        $t->contains('<span class="co">\ No newline at end of file</span>', $highlighted['html']);
        $t->contains('.sourceCode .re', $highlighted['css']);
        $t->contains('.sourceCode .in', $highlighted['css']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="in">+echo esc_html($new_title);</span>', $wordpressBlock);
    },
    'highlights markdown family review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'markdown-review',
            'classes' => ['sourceCode', 'md', 'numberLines'],
            'attributes' => ['startFrom' => '5'],
            'text' => implode("\n", [
                '# Migration Review',
                '',
                '- [x] Preserve [media](uploads/hero.png)',
                '- Keep `legacy_shortcode` visible',
                '> Reviewer note with <https://example.test/post>',
                '',
                '[asset]: uploads/hero.png "Hero image"',
                '',
                '``` {.php}',
                'echo esc_html($title);',
                '```',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');

        $t->same('md', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('markdown', $highlighted['language']);
        $t->same('md', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(5, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource md numberLines"><code class="sourceCode markdown" style="counter-reset: source-line 4;">', $highlighted['html']);
        $t->contains('<span id="markdown-review-5"><a href="#markdown-review-5"></a><span class="re"># Migration Review</span></span>', $highlighted['html']);
        $t->contains('<span class="op">- </span><span class="cn">[x]</span> Preserve <span class="ot">[media](uploads/hero.png)</span>', $highlighted['html']);
        $t->contains('<span class="st">`legacy_shortcode`</span>', $highlighted['html']);
        $t->contains('<span class="op">&gt; </span>Reviewer note with <span class="ot">&lt;https://example.test/post&gt;</span>', $highlighted['html']);
        $t->contains('<span class="ot">[asset]: uploads/hero.png &quot;Hero image&quot;</span>', $highlighted['html']);
        $t->contains('<span class="pp">``` {.php}</span>', $highlighted['html']);
        $t->contains('<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="pp">```</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="re"># Migration Review</span>', $wordpressBlock);
        $t->contains('<span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span>', $wordpressBlock);

        $commonmark = (new SyntaxHighlighter())->highlight('## Imported Notes', 'commonmark');
        $t->same('markdown', $commonmark['language']);
        $t->contains('<pre class="sourceCode markdown"><code class="sourceCode markdown"><span class="re">## Imported Notes</span></code></pre>', $commonmark['html']);
    },
    'delegates markdown fenced code bodies to embedded language tokenizers' => static function (TestRunner $t): void {
        $markdown = implode("\n", [
            '# Import packet',
            '',
            '``` json',
            '{"title":"Imported","draft":false}',
            '```',
            '',
            '~~~ {.php .numberLines}',
            'echo esc_html($title);',
            '~~~',
        ]);

        $highlighted = (new SyntaxHighlighter())->highlight($markdown, 'pandoc-markdown');

        $t->same('markdown', $highlighted['language']);
        $t->same('pandoc-markdown', $highlighted['requestedLanguage']);
        $t->contains('<span class="pp">``` json</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;title&quot;</span><span class="op">:</span><span class="st">&quot;Imported&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">&quot;draft&quot;</span><span class="op">:</span><span class="cn">false</span>', $highlighted['html']);
        $t->contains('<span class="pp">~~~ {.php .numberLines}</span>', $highlighted['html']);
        $t->contains('<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="pp">~~~</span>', $highlighted['html']);
    },
    'highlights ruby and rake review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'ruby-review',
            'classes' => ['sourceCode', 'rb'],
            'attributes' => [],
            'text' => implode("\n", [
                '# WordPress import audit task',
                "require 'json'",
                'module Migration',
                '  class ReviewPacket',
                '    def initialize(path:)',
                '      @path = path',
                '    end',
                '',
                '    def call',
                "      puts JSON.parse(File.read(@path))['title']",
                '    rescue JSON::ParserError => error',
                '      warn "invalid import: #{error.message}"',
                '      nil',
                '    end',
                '  end',
                'end',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $rake = (new SyntaxHighlighter())->highlight("task :import do\n  puts 'ok'\nend", 'rake');

        $t->same('rb', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('ruby', $highlighted['language']);
        $t->same('rb', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<pre class="sourceCode ruby"><code class="sourceCode ruby">', $highlighted['html']);
        $t->contains('<span class="co"># WordPress import audit task</span>', $highlighted['html']);
        $t->contains('<span class="fu">require</span> <span class="st">&#039;json&#039;</span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">Migration</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">initialize</span><span class="op">(</span><span class="ot">path:</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">@path</span> <span class="op">=</span> <span class="va">path</span>', $highlighted['html']);
        $t->contains('<span class="kw">rescue</span> <span class="dt">JSON::ParserError</span> <span class="op">=&gt;</span> <span class="va">error</span>', $highlighted['html']);
        $t->contains('<span class="fu">warn</span> <span class="st">&quot;invalid import: #{error.message}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="cn">nil</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="dt">JSON</span><span class="op">.</span><span class="fu">parse</span>', $wordpressBlock);
        $t->same('ruby', $rake['language']);
        $t->contains('<span class="fu">task</span> <span class="ot">:import</span> <span class="kw">do</span>', $rake['html']);
    },
    'highlights lua filter review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'lua-filter-review',
            'classes' => ['sourceCode', 'pandoc-lua', 'numberLines'],
            'attributes' => ['startFrom' => '3'],
            'text' => implode("\n", [
                '-- WordPress import Lua filter',
                'function Header(el)',
                '  local title = pandoc.utils.stringify(el.content)',
                '  if el.level == 1 then',
                '    return pandoc.Div({el}, {class = "import-title"})',
                '  end',
                '  return nil',
                'end',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directLua = (new SyntaxHighlighter())->highlight('return pandoc.Str("ok")', 'lua');

        $t->same('pandoc-lua', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('lua', $highlighted['language']);
        $t->same('pandoc-lua', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(3, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pandoc-lua numberLines"><code class="sourceCode lua" style="counter-reset: source-line 2;">', $highlighted['html']);
        $t->contains('<span id="lua-filter-review-3"><a href="#lua-filter-review-3"></a><span class="co">-- WordPress import Lua filter</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="fu">Header</span><span class="op">(</span><span class="va">el</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">local</span> <span class="va">title</span> <span class="op">=</span> <span class="dt">pandoc</span><span class="op">.</span><span class="va">utils</span><span class="op">.</span><span class="fu">stringify</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">el</span><span class="op">.</span><span class="va">level</span> <span class="op">==</span> <span class="dv">1</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span><span class="op">({</span><span class="va">el</span><span class="op">},</span> <span class="op">{</span><span class="va">class</span> <span class="op">=</span> <span class="st">&quot;import-title&quot;</span><span class="op">})</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="cn">nil</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span>', $wordpressBlock);
        $t->same('lua', $directLua['language']);
        $t->contains('<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Str</span><span class="op">(</span><span class="st">&quot;ok&quot;</span><span class="op">)</span>', $directLua['html']);
    },
    'highlights typescript review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'ts-review',
            'classes' => ['sourceCode', 'ts', 'numberLines'],
            'attributes' => ['startFrom' => '12'],
            'text' => implode("\n", [
                '// Gutenberg block migration packet',
                'type BlockPayload = {',
                '  title?: string;',
                '  meta: Record<string, unknown>;',
                '};',
                '',
                'export async function migrateBlock(payload: BlockPayload): Promise<void> {',
                '  const title = payload.title ?? `Untitled`;',
                '  if (payload.meta?.sourceId !== undefined) {',
                '    console.log(`import:${payload.meta.sourceId}`);',
                '  }',
                '  return;',
                '}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $directTypescript = (new SyntaxHighlighter())->highlight('interface ReviewBlock { readonly title: string }', 'typescript');

        $t->same('ts', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('typescript', $highlighted['language']);
        $t->same('ts', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(12, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ts numberLines"><code class="sourceCode typescript" style="counter-reset: source-line 11;">', $highlighted['html']);
        $t->contains('<span id="ts-review-12"><a href="#ts-review-12"></a><span class="co">// Gutenberg block migration packet</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">BlockPayload</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">?:</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="va">meta</span><span class="op">:</span> <span class="dt">Record</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">unknown</span><span class="op">&gt;;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">migrateBlock</span>', $highlighted['html']);
        $t->contains('<span class="dt">Promise</span><span class="op">&lt;</span><span class="dt">void</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span><span class="op">.</span><span class="va">title</span> <span class="op">??</span> <span class="st">`Untitled`</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span><span class="op">.</span><span class="va">meta</span><span class="op">?.</span><span class="va">sourceId</span> <span class="op">!==</span> <span class="dt">undefined</span>', $highlighted['html']);
        $t->contains('<span class="va">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="st">`import:${payload.meta.sourceId}`</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span>', $wordpressBlock);
        $t->same('typescript', $directTypescript['language']);
        $t->contains('<span class="kw">interface</span> <span class="dt">ReviewBlock</span>', $directTypescript['html']);
        $t->contains('<span class="kw">readonly</span> <span class="va">title</span><span class="op">:</span> <span class="dt">string</span>', $directTypescript['html']);
    },
    'highlights jsx react review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[12] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a JSX code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directJsx = (new SyntaxHighlighter())->highlight('return <ReviewCard title={post.title} />;', 'javascript-react');

        $t->same('jsx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('jsx', $highlighted['language']);
        $t->same('jsx', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(18, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource jsx numberLines"><code class="sourceCode jsx" style="counter-reset: source-line 17;">', $highlighted['html']);
        $t->contains('<span id="jsx-review-18"><a href="#jsx-review-18"></a><span class="co">// Gutenberg block preview component</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">React</span> <span class="kw">from</span> <span class="st">&#039;react&#039;</span>', $highlighted['html']);
        $t->contains('<span class="kw">export</span> <span class="kw">default</span> <span class="kw">function</span> <span class="fu">ImportPreview</span>', $highlighted['html']);
        $t->contains('<span class="kw">const</span> <span class="op">{</span> <span class="va">title</span><span class="op">,</span> <span class="va">sourceId</span> <span class="op">}</span> <span class="op">=</span> <span class="va">props</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="kw">&lt;section</span> <span class="ot">className</span><span class="op">=</span><span class="st">&quot;wp-block-import&quot;</span> <span class="ot">data-source</span><span class="op">={</span><span class="va">sourceId</span><span class="op">}&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;h2</span><span class="op">&gt;{</span><span class="va">title</span><span class="op">}</span><span class="kw">&lt;/h2</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="fu">&lt;InnerBlocks</span> <span class="ot">allowedBlocks</span><span class="op">={[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">]}</span> <span class="op">/&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;/section</span><span class="op">&gt;;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">&lt;InnerBlocks</span>', $wordpressBlock);
        $t->same('jsx', $directJsx['language']);
        $t->contains('<span class="kw">return</span> <span class="fu">&lt;ReviewCard</span> <span class="ot">title</span><span class="op">={</span><span class="va">post</span><span class="op">.</span><span class="va">title</span><span class="op">}</span> <span class="op">/&gt;;</span>', $directJsx['html']);
    },
    'highlights r script review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[13] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an R code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'espresso');
        $directR = (new SyntaxHighlighter())->highlight('`post title` <- c("Draft", NA)', 'Rscript');

        $t->same('r', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('r', $highlighted['language']);
        $t->same('r', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(27, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource r numberLines"><code class="sourceCode r" style="counter-reset: source-line 26;">', $highlighted['html']);
        $t->contains('<span id="r-review-27"><a href="#r-review-27"></a><span class="co">## WordPress import analysis</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">library</span><span class="op">(</span><span class="va">dplyr</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">scores</span> <span class="ot">&lt;-</span> <span class="fu">data.frame</span>', $highlighted['html']);
        $t->contains('<span class="ot">title</span> <span class="ot">=</span> <span class="fu">c</span><span class="op">(</span><span class="st">&quot;Draft&quot;</span><span class="op">,</span> <span class="st">&quot;Published&quot;</span><span class="op">),</span>', $highlighted['html']);
        $t->contains('<span class="ot">views</span> <span class="ot">=</span> <span class="fu">c</span><span class="op">(</span><span class="dv">10L</span><span class="op">,</span> <span class="cn">NA_integer_</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="va">scores</span> <span class="op">|&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">dplyr</span><span class="op">::</span><span class="fu">filter</span><span class="op">(!</span><span class="fu">is.na</span><span class="op">(</span><span class="va">title</span><span class="op">),</span> <span class="va">views</span> <span class="op">&gt;=</span> <span class="dv">10</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">mutate</span><span class="op">(</span><span class="ot">slug</span> <span class="ot">=</span> <span class="fu">tolower</span><span class="op">(</span><span class="fu">gsub</span><span class="op">(</span><span class="st">&quot;[^a-z0-9]+&quot;</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">,</span> <span class="va">title</span><span class="op">)))</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">any</span><span class="op">(</span><span class="va">scores</span><span class="op">$</span><span class="va">views</span> <span class="op">&gt;</span> <span class="dv">100</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="fu">print</span><span class="op">(</span><span class="st">&quot;popular import&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="va">scores</span> <span class="op">|&gt;</span>', $wordpressBlock);
        $t->same('r', $directR['language']);
        $t->contains('<span class="ot">`post title`</span> <span class="ot">&lt;-</span> <span class="fu">c</span><span class="op">(</span><span class="st">&quot;Draft&quot;</span><span class="op">,</span> <span class="cn">NA</span><span class="op">)</span>', $directR['html']);
    },
    'highlights python3 review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'python-review',
            'classes' => ['sourceCode', 'python3', 'numberLines'],
            'attributes' => ['startFrom' => '20'],
            'text' => implode("\n", [
                '# WordPress import JSON cleanup',
                'from dataclasses import dataclass',
                'from pathlib import Path',
                '@dataclass',
                'class ReviewPacket:',
                '    source_id: int',
                '    title: str | None = None',
                '',
                'def normalize_title(packet: ReviewPacket) -> str:',
                '    payload = Path(packet.source_path).read_bytes()',
                '    if payload.startswith(b"\\xef\\xbb\\xbf"):',
                '        payload = payload.removeprefix(b"\\xef\\xbb\\xbf")',
                '    pattern = rb"legacy-\\d+"',
                '    raw = json.loads(Path(packet.source_path).read_text())["title"]',
                '    if raw is None:',
                '        return "Untitled"',
                '    return raw.strip()',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directPython = (new SyntaxHighlighter())->highlight('async def load(): await fetch()', 'py3');

        $t->same('python3', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('python', $highlighted['language']);
        $t->same('python3', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(20, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource python3 numberLines"><code class="sourceCode python" style="counter-reset: source-line 19;">', $highlighted['html']);
        $t->contains('<span id="python-review-20"><a href="#python-review-20"></a><span class="co"># WordPress import JSON cleanup</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">from</span> <span class="va">dataclasses</span> <span class="kw">import</span> <span class="va">dataclass</span>', $highlighted['html']);
        $t->contains('<span class="kw">from</span> <span class="va">pathlib</span> <span class="kw">import</span> <span class="dt">Path</span>', $highlighted['html']);
        $t->contains('<span class="ot">@dataclass</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="va">source_id</span><span class="op">:</span> <span class="dt">int</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">:</span> <span class="dt">str</span> <span class="op">|</span> <span class="cn">None</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="dt">str</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span> <span class="op">=</span> <span class="dt">Path</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">source_path</span><span class="op">).</span><span class="fu">read_bytes</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">payload</span><span class="op">.</span><span class="fu">startswith</span><span class="op">(</span><span class="st">b&quot;\\xef\\xbb\\xbf&quot;</span><span class="op">):</span>', $highlighted['html']);
        $t->contains('<span class="va">payload</span> <span class="op">=</span> <span class="va">payload</span><span class="op">.</span><span class="fu">removeprefix</span><span class="op">(</span><span class="st">b&quot;\\xef\\xbb\\xbf&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">pattern</span> <span class="op">=</span> <span class="st">rb&quot;legacy-\\d+&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">json</span><span class="op">.</span><span class="fu">loads</span><span class="op">(</span><span class="dt">Path</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">source_path</span><span class="op">).</span><span class="fu">read_text</span><span class="op">())[</span><span class="st">&quot;title&quot;</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">raw</span> <span class="kw">is</span> <span class="cn">None</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">raw</span><span class="op">.</span><span class="fu">strip</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="st">b&quot;\\xef\\xbb\\xbf&quot;</span>', $wordpressBlock);
        $t->contains('<span class="st">rb&quot;legacy-\\d+&quot;</span>', $wordpressBlock);
        $t->contains('<span class="ot">@dataclass</span>', $wordpressBlock);
        $t->same('python', $directPython['language']);
        $t->contains('<span class="kw">async</span> <span class="kw">def</span> <span class="fu">load</span>', $directPython['html']);
        $t->contains('<span class="kw">await</span> <span class="fu">fetch</span><span class="op">()</span>', $directPython['html']);
        $bytesPython = (new SyntaxHighlighter())->highlight('payload = br"data-\d+"; marker = B"WP"', 'py');
        $t->contains('<span class="va">payload</span> <span class="op">=</span> <span class="st">br&quot;data-\\d+&quot;</span><span class="op">;</span> <span class="va">marker</span> <span class="op">=</span> <span class="st">B&quot;WP&quot;</span>', $bytesPython['html']);
    },
    'highlights c and cpp review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'cpp-review',
            'classes' => ['sourceCode', 'cpp', 'numberLines'],
            'attributes' => ['startFrom' => '30'],
            'text' => implode("\n", [
                '#include <string>',
                '#include "wp_import.h"',
                '// WordPress import extension review',
                'namespace Migration {',
                'class ReviewPacket {',
                'public:',
                '    explicit ReviewPacket(std::string title) : title_(std::move(title)) {}',
                '    bool is_draft() const { return title_.empty() || title_ == "Draft"; }',
                'private:',
                '    std::string title_;',
                '};',
                '}',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'pygments');
        $directC = (new SyntaxHighlighter())->highlight('static const char *title = "Draft";', 'h');

        $t->same('cpp', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('cpp', $highlighted['language']);
        $t->same('cpp', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(30, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource cpp numberLines"><code class="sourceCode cpp" style="counter-reset: source-line 29;">', $highlighted['html']);
        $t->contains('<span id="cpp-review-30"><a href="#cpp-review-30"></a><span class="pp">#include &lt;string&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">#include &quot;wp_import.h&quot;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress import extension review</span>', $highlighted['html']);
        $t->contains('<span class="kw">namespace</span> <span class="dt">Migration</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="kw">explicit</span> <span class="dt">ReviewPacket</span><span class="op">(</span><span class="dt">std</span><span class="op">::</span><span class="dt">string</span> <span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">title_</span><span class="op">(</span><span class="dt">std</span><span class="op">::</span><span class="fu">move</span><span class="op">(</span><span class="va">title</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="dt">bool</span> <span class="fu">is_draft</span><span class="op">()</span> <span class="kw">const</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">title_</span><span class="op">.</span><span class="fu">empty</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="va">title_</span> <span class="op">==</span> <span class="st">&quot;Draft&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="dt">std</span><span class="op">::</span><span class="dt">string</span>', $wordpressBlock);
        $t->same('c', $directC['language']);
        $t->contains('<span class="kw">static</span> <span class="kw">const</span> <span class="dt">char</span> <span class="op">*</span><span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Draft&quot;</span><span class="op">;</span>', $directC['html']);
    },
    'highlights dockerfile and containerfile review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'docker-review',
            'classes' => ['sourceCode', 'Dockerfile', 'numberLines'],
            'attributes' => ['startFrom' => '4'],
            'text' => implode("\n", [
                '# syntax=docker/dockerfile:1.7',
                'FROM wordpress:php8.3-apache AS source',
                'ARG WP_ENV=production',
                'ENV WORDPRESS_CONFIG_EXTRA="define(\'WP_DEBUG\', false);"',
                'COPY --from=source /var/www/html /review/html',
                'RUN set -eux; \\',
                '    php -m | grep json',
            ]),
        ]);

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $containerfile = (new SyntaxHighlighter())->highlight('RUN echo "$WP_ENV"', 'Containerfile');

        $t->same('Dockerfile', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('dockerfile', $highlighted['language']);
        $t->same('Dockerfile', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(4, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource Dockerfile numberLines"><code class="sourceCode dockerfile" style="counter-reset: source-line 3;">', $highlighted['html']);
        $t->contains('<span id="docker-review-4"><a href="#docker-review-4"></a><span class="ot"># syntax=docker/dockerfile:1.7</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">FROM</span> wordpress<span class="op">:</span>php<span class="dv">8.3</span><span class="op">-</span>apache <span class="kw">AS</span> source', $highlighted['html']);
        $t->contains('<span class="kw">ARG</span> <span class="ot">WP_ENV</span><span class="op">=</span>production', $highlighted['html']);
        $t->contains('<span class="kw">ENV</span> <span class="ot">WORDPRESS_CONFIG_EXTRA</span><span class="op">=</span><span class="st">&quot;define(&#039;WP_DEBUG&#039;, false);&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">COPY</span> <span class="op">--from=source</span> /var/www/html /review/html', $highlighted['html']);
        $t->contains('<span class="kw">RUN</span> <span class="fu">set</span> <span class="op">-</span>eux<span class="op">;</span> <span class="op">\\</span>', $highlighted['html']);
        $t->contains('<span class="fu">php</span> <span class="op">-</span>m <span class="op">|</span> <span class="fu">grep</span> json', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">FROM</span> wordpress<span class="op">:</span>php', $wordpressBlock);
        $t->same('dockerfile', $containerfile['language']);
        $t->contains('<span class="kw">RUN</span> <span class="fu">echo</span> <span class="st">&quot;$WP_ENV&quot;</span>', $containerfile['html']);
    },
    'highlights makefile review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[11] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Makefile code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directMakefile = (new SyntaxHighlighter())->highlight("include .env\nclean:\n\trm -rf build", 'mk');

        $t->same('Makefile', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('makefile', $highlighted['language']);
        $t->same('Makefile', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(6, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource Makefile numberLines"><code class="sourceCode makefile" style="counter-reset: source-line 5;">', $highlighted['html']);
        $t->contains('<span id="make-review-6"><a href="#make-review-6"></a><span class="co"># WordPress asset build review</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">PLUGIN_VERSION</span> <span class="op">?=</span> <span class="dv">1.2.3</span>', $highlighted['html']);
        $t->contains('<span class="re">assets/build</span><span class="op">:</span> <span class="va">package.json</span> <span class="va">src/block.js</span>', $highlighted['html']);
        $t->contains('<span class="va">$(NPM)</span> <span class="va">run</span> <span class="va">build</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">i18n</span> <span class="va">make-pot</span> <span class="op">.</span> <span class="va">languages/plugin.pot</span>', $highlighted['html']);
        $t->contains('<span class="re">deploy</span><span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="op">@</span><span class="va">$(WP_CLI)</span> <span class="va">plugin</span> <span class="va">update</span> <span class="va">my-plugin</span> <span class="op">--version</span> <span class="va">$(PLUGIN_VERSION)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="re">assets/build</span><span class="op">:</span>', $wordpressBlock);
        $t->same('makefile', $directMakefile['language']);
        $t->contains('<span class="kw">include</span> <span class="va">.env</span>', $directMakefile['html']);
        $t->contains('<span class="re">clean</span><span class="op">:</span>', $directMakefile['html']);
        $t->contains('<span class="fu">rm</span> <span class="op">-rf</span> <span class="va">build</span>', $directMakefile['html']);
    },
    'highlights ini config review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[14] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an INI code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $directCfg = (new SyntaxHighlighter())->highlight("enabled = True\nerror_reporting = ~E_ALL", 'cfg');

        $t->same('ini', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('ini', $highlighted['language']);
        $t->same('ini', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(2, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource ini numberLines"><code class="sourceCode ini" style="counter-reset: source-line 1;">', $highlighted['html']);
        $t->contains('<span id="php-ini-review-2"><a href="#php-ini-review-2"></a><span class="co">; WordPress hosting php.ini review</span></span>', $highlighted['html']);
        $t->contains('<span id="php-ini-review-3"><a href="#php-ini-review-3"></a><span class="kw">[PHP]</span></span>', $highlighted['html']);
        $t->contains('<span class="dt">memory_limit</span> <span class="op">=</span> <span class="st">256M</span>', $highlighted['html']);
        $t->contains('<span class="dt">upload_max_filesize</span> <span class="op">=</span> <span class="st">64M</span>', $highlighted['html']);
        $t->contains('<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>', $highlighted['html']);
        $t->contains('<span class="dt">error_reporting</span> <span class="op">=</span> <span class="kw">E_ALL</span>', $highlighted['html']);
        $t->contains('<span class="kw">[opcache]</span>', $highlighted['html']);
        $t->contains('<span class="dt">opcache.enable</span> <span class="op">=</span> <span class="dv">1</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>', $wordpressBlock);
        $t->same('ini', $directCfg['language']);
        $t->contains('<span class="dt">enabled</span> <span class="op">=</span> <span class="kw">True</span>', $directCfg['html']);
        $t->contains('<span class="dt">error_reporting</span> <span class="op">=</span> <span class="op">~</span><span class="kw">E_ALL</span>', $directCfg['html']);
    },
    'highlights toml configuration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[15] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a TOML code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'kate');
        $cargoLock = (new SyntaxHighlighter())->highlight("[[package]]\nname = \"wp-import\"\nversion = \"1.0.0\"", 'Cargo.lock');

        $t->same('toml', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('toml', $highlighted['language']);
        $t->same('toml', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(11, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource toml numberLines"><code class="sourceCode toml" style="counter-reset: source-line 10;">', $highlighted['html']);
        $t->contains('<span id="toml-review-11"><a href="#toml-review-11"></a><span class="co"># WordPress static export review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">[tool.wordpress-import]</span>', $highlighted['html']);
        $t->contains('<span class="dt">enabled</span> <span class="op">=</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<span class="dt">source</span> <span class="op">=</span> <span class="st">&quot;markdown&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">published_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00Z</span>', $highlighted['html']);
        $t->contains('<span class="dt">max_posts</span> <span class="op">=</span> <span class="dv">250</span>', $highlighted['html']);
        $t->contains('<span class="dt">media_paths</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;uploads&quot;</span><span class="op">,</span> <span class="st">&quot;assets&quot;</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">[theme.variation]</span>', $highlighted['html']);
        $t->contains('<span class="dt">palette</span> <span class="op">=</span> <span class="op">{</span> <span class="dt">primary</span> <span class="op">=</span> <span class="st">&quot;#005cc5&quot;</span><span class="op">,</span> <span class="dt">contrast</span> <span class="op">=</span> <span class="st">&quot;#ffffff&quot;</span> <span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="op">[[</span><span class="dt">theme</span><span class="op">.</span><span class="st">&quot;palette variants&quot;</span><span class="op">]]</span>', $highlighted['html']);
        $t->contains('<span class="dt">created_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00</span> <span class="co"># local review time</span>', $highlighted['html']);
        $t->contains('<span class="dt">review</span><span class="op">.</span><span class="dt">cutoff</span> <span class="op">=</span> <span class="cn">08:40:00.125</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;accent.color&quot;</span> <span class="op">=</span> <span class="st">&quot;#005cc5&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="op">[[</span><span class="dt">theme</span><span class="op">.</span><span class="st">&quot;palette variants&quot;</span><span class="op">]]</span>', $wordpressBlock);
        $t->same('toml', $cargoLock['language']);
        $t->contains('<span class="kw">[[package]]</span>', $cargoLock['html']);
        $t->contains('<span class="dt">name</span> <span class="op">=</span> <span class="st">&quot;wp-import&quot;</span>', $cargoLock['html']);
    },
    'highlights perl migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[16] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Perl code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'zenburn');
        $module = (new SyntaxHighlighter())->highlight("package WP::Import;\nuse utf8;\n1;", 'pm');

        $t->same('pl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('perl', $highlighted['language']);
        $t->same('pl', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(14, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pl numberLines"><code class="sourceCode perl" style="counter-reset: source-line 13;">', $highlighted['html']);
        $t->contains('<span id="perl-review-14"><a href="#perl-review-14"></a><span class="kw">#!/usr/bin/env perl</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">strict</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">warnings</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::ImportReview</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">sub</span> <span class="fu">normalize_title</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">my</span> <span class="op">(</span><span class="va">$packet</span><span class="op">)</span> <span class="op">=</span> <span class="va">@_</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">my</span> <span class="op">(</span><span class="va">$title</span><span class="op">)</span> <span class="op">=</span> <span class="va">$packet</span><span class="op">-&gt;{</span><span class="ot">title</span><span class="op">}</span> <span class="op">//</span> <span class="st">&#039;Untitled&#039;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$title</span> <span class="op">=~</span> <span class="st">s/^\\s+|\\s+$//g</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">$title</span> <span class="op">eq</span> <span class="st">&#039;&#039;</span><span class="op">)</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="fu">warn</span> <span class="st">&quot;empty title for $packet-&gt;{id}&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="cn">undef</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">lc</span> <span class="va">$title</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::ImportReview</span>', $wordpressBlock);
        $t->same('perl', $module['language']);
        $t->contains('<span class="kw">package</span> <span class="dt">WP::Import</span><span class="op">;</span>', $module['html']);
        $t->contains('<span class="fu">use</span> <span class="kw">utf8</span><span class="op">;</span>', $module['html']);
    },
    'highlights java migration review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[17] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Java code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'tango');
        $record = (new SyntaxHighlighter())->highlight('record ImportTask(String title, int count) {}', 'java');

        $t->same('java', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('java', $highlighted['language']);
        $t->same('java', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(21, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource java numberLines"><code class="sourceCode java" style="counter-reset: source-line 20;">', $highlighted['html']);
        $t->contains('<span id="java-review-21"><a href="#java-review-21"></a><span class="kw">package</span> <span class="va">org</span><span class="op">.</span><span class="va">wordpress</span><span class="op">.</span><span class="va">importer</span><span class="op">;</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">java</span><span class="op">.</span><span class="va">nio</span><span class="op">.</span><span class="va">file</span><span class="op">.</span><span class="dt">Files</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="co">// WordPress import review helper</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="kw">final</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">private</span> <span class="kw">final</span> <span class="dt">Path</span> <span class="va">sourcePath</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">this</span><span class="op">.</span><span class="va">sourcePath</span> <span class="op">=</span> <span class="va">sourcePath</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">@Deprecated</span>', $highlighted['html']);
        $t->contains('<span class="kw">public</span> <span class="dt">Optional</span><span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;</span> <span class="fu">title</span><span class="op">()</span> <span class="kw">throws</span> <span class="dt">IOException</span>', $highlighted['html']);
        $t->contains('<span class="kw">var</span> <span class="va">json</span> <span class="op">=</span> <span class="dt">Files</span><span class="op">.</span><span class="fu">readString</span><span class="op">(</span><span class="va">sourcePath</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">json</span><span class="op">.</span><span class="fu">isBlank</span><span class="op">())</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="dt">Optional</span><span class="op">.</span><span class="fu">of</span><span class="op">(</span><span class="st">&quot;Imported&quot;</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="dt">Optional</span><span class="op">.</span><span class="fu">empty</span><span class="op">();</span>', $wordpressBlock);
        $t->same('java', $record['language']);
        $t->contains('<span class="kw">record</span> <span class="dt">ImportTask</span><span class="op">(</span><span class="dt">String</span> <span class="va">title</span><span class="op">,</span> <span class="dt">int</span> <span class="va">count</span><span class="op">)</span>', $record['html']);
    },
    'highlights xml and xslt review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[18] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an XML code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'haddock');
        $xslt = (new SyntaxHighlighter())->highlight(
            "<xsl:template match=\"/rss/channel/item\">\n  <xsl:value-of select=\"normalize-space(title)\"/>\n</xsl:template>",
            'xsl'
        );
        $svg = (new SyntaxHighlighter())->highlight('<svg viewBox="0 0 10 10"><use href="#icon"/></svg>', 'svg');

        $t->same('xml', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('xml', $highlighted['language']);
        $t->same('xml', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(33, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource xml numberLines"><code class="sourceCode xml" style="counter-reset: source-line 32;">', $highlighted['html']);
        $t->contains('<span id="wxr-xml-review-33"><a href="#wxr-xml-review-33"></a><span class="pp">&lt;?xml</span> <span class="ot">version</span><span class="op">=</span><span class="st">&quot;1.0&quot;</span> <span class="ot">encoding</span><span class="op">=</span><span class="st">&quot;UTF-8&quot;</span><span class="op">?&gt;</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">&lt;!DOCTYPE</span> rss <span class="op">[</span><span class="pp">&lt;!ENTITY</span> legacy <span class="st">&quot;Legacy&quot;</span><span class="op">&gt;]&gt;</span>', $highlighted['html']);
        $t->contains('<span class="co">&lt;!-- WordPress WXR media review --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;rss</span> <span class="ot">version</span><span class="op">=</span><span class="st">&quot;2.0&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">xmlns:wp</span><span class="op">=</span><span class="st">&quot;http://wordpress.org/export/1.2/&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;wp:wxr_version</span><span class="op">&gt;</span><span class="dv">1.2</span><span class="kw">&lt;/wp:wxr_version</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;item</span> <span class="ot">data-source</span><span class="op">=</span><span class="st">&quot;legacy-42&quot;</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">&lt;title</span><span class="op">&gt;</span><span class="cn">&amp;legacy;</span> <span class="cn">&amp;amp;</span> Reviewed<span class="kw">&lt;/title</span><span class="op">&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;![CDATA[&lt;!-- wp:paragraph --&gt;&lt;p&gt;Legacy shortcode [gallery]&lt;/p&gt;]]&gt;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">&lt;content:encoded</span><span class="op">&gt;</span>', $wordpressBlock);
        $t->same('xslt', $xslt['language']);
        $t->same('xsl', $xslt['requestedLanguage']);
        $t->contains('<pre class="sourceCode xslt"><code class="sourceCode xslt"><span class="kw">&lt;xsl:template</span> <span class="ot">match</span><span class="op">=</span><span class="st">&quot;/rss/channel/item&quot;</span><span class="op">&gt;</span>', $xslt['html']);
        $t->contains('<span class="kw">&lt;xsl:value-of</span> <span class="ot">select</span><span class="op">=</span><span class="st">&quot;normalize-space(title)&quot;</span><span class="op">/&gt;</span>', $xslt['html']);
        $t->same('xml', $svg['language']);
        $t->contains('<span class="kw">&lt;svg</span> <span class="ot">viewBox</span><span class="op">=</span><span class="st">&quot;0 0 10 10&quot;</span><span class="op">&gt;</span>', $svg['html']);
        $t->contains('<span class="kw">&lt;use</span> <span class="ot">href</span><span class="op">=</span><span class="st">&quot;#icon&quot;</span><span class="op">/&gt;</span>', $svg['html']);
    },
    'highlights bash shell review snippets with heredoc state and pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[19] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Bash shell code block');
        }

        $highlighted = (new SyntaxHighlighter())->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = (new SyntaxHighlighter())->wordpressHtmlBlock($codeBlock, 'pygments');
        $console = (new SyntaxHighlighter())->highlight('printf "%s\n" "$title"', 'console');

        $t->same('sh', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('bash', $highlighted['language']);
        $t->same('sh', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(50, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource sh numberLines"><code class="sourceCode bash" style="counter-reset: source-line 49;">', $highlighted['html']);
        $t->contains('<span id="shell-review-50"><a href="#shell-review-50"></a><span class="kw">#!/usr/bin/env bash</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">set</span> <span class="op">-euo</span> <span class="va">pipefail</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--post_type</span><span class="op">=</span><span class="va">post</span>', $highlighted['html']);
        $t->contains('<span class="kw">while</span> <span class="fu">read</span> <span class="op">-r</span> <span class="va">post_id</span><span class="op">;</span> <span class="kw">do</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">=$(</span><span class="fu">wp</span> <span class="va">post</span> <span class="va">get</span> <span class="st">&quot;$post_id&quot;</span> <span class="ot">--field</span><span class="op">=</span><span class="va">post_title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">[[</span> <span class="op">-z</span> <span class="st">&quot;$title&quot;</span> <span class="op">]];</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="fu">cat</span> <span class="op">&lt;&lt;</span><span class="st">&#039;HTML&#039;</span> <span class="op">&gt;</span> <span class="st">&quot;$TMPDIR/post-$post_id.html&quot;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="re">HTML</span>', $highlighted['html']);
        $t->contains('<span class="kw">done</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;', $wordpressBlock);
        $t->same('bash', $console['language']);
        $t->same('console', $console['requestedLanguage']);
        $t->contains('<span class="fu">printf</span> <span class="st">&quot;%s\\n&quot;</span> <span class="st">&quot;$title&quot;</span>', $console['html']);
    },
    'highlights zsh review snippets through bounded shell handoff' => static function (TestRunner $t): void {
        $codeBlock = new AstNode('code_block', [
            'id' => 'zsh-review',
            'classes' => ['sourceCode', 'zsh', 'numberLines'],
            'attributes' => ['startFrom' => '64'],
            'text' => implode("\n", [
                '#!/usr/bin/env zsh',
                'autoload -Uz colors',
                'title=${1:-Untitled}',
                'print -r "$title"',
                'wp post meta update 42 _review "$title"',
            ]),
        ]);

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $direct = $highlighter->highlight('print -r "$title"', 'language-zsh-script');

        $t->same('zsh', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('zsh'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('zshrc'));
        $t->same('bash', SyntaxHighlighter::normalizeLanguage('language-zsh-script'));
        $t->same('bash', $highlighted['language']);
        $t->same('zsh', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(64, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource zsh numberLines"><code class="sourceCode bash" style="counter-reset: source-line 63;">', $highlighted['html']);
        $t->contains('<span id="zsh-review-64"><a href="#zsh-review-64"></a><span class="kw">#!/usr/bin/env zsh</span></span>', $highlighted['html']);
        $t->contains('<span class="fu">autoload</span> <span class="op">-Uz</span> <span class="va">colors</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">=</span><span class="va">${1:-Untitled}</span>', $highlighted['html']);
        $t->contains('<span class="fu">print</span> <span class="op">-r</span> <span class="st">&quot;$title&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span> <span class="va">update</span> <span class="dv">42</span> <span class="va">_review</span> <span class="st">&quot;$title&quot;</span>', $highlighted['html']);
        $t->same('bash', $direct['language']);
        $t->same('language-zsh-script', $direct['requestedLanguage']);
        $t->contains('<pre class="sourceCode bash"><code class="sourceCode bash"><span class="fu">print</span> <span class="op">-r</span> <span class="st">&quot;$title&quot;</span></code></pre>', $direct['html']);
    },
    'highlights shell session transcripts with prompt and output handoff' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[88] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a shell-session transcript code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directSession = $highlighter->highlight(
            "reviewer@wp:/srv/site$ wp post get 42 --field=post_title\nLegacy Review",
            'console-session'
        );

        $t->same('shell-session', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('shellsession', $highlighted['language']);
        $t->same('shell-session', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1420, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource shell-session numberLines"><code class="sourceCode shellsession" style="counter-reset: source-line 1419;">', $highlighted['html']);
        $t->contains('<span id="shell-session-review-1420"><a href="#shell-session-review-1420"></a><span class="re">$ </span><span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--post_type</span><span class="op">=</span><span class="va">post</span> <span class="ot">--format</span><span class="op">=</span><span class="va">ids</span></span>', $highlighted['html']);
        $t->contains('<span id="shell-session-review-1421"><a href="#shell-session-review-1421"></a><span class="in">42</span></span>', $highlighted['html']);
        $t->contains('<span id="shell-session-review-1422"><a href="#shell-session-review-1422"></a><span class="re">$ </span><span class="va">title</span><span class="op">=$(</span><span class="fu">wp</span> <span class="va">post</span> <span class="va">get</span> <span class="dv">42</span> <span class="ot">--field</span><span class="op">=</span><span class="va">post_title</span><span class="op">)</span></span>', $highlighted['html']);
        $t->contains('<span id="shell-session-review-1423"><a href="#shell-session-review-1423"></a><span class="in">Legacy Review</span></span>', $highlighted['html']);
        $t->contains('<span id="shell-session-review-1424"><a href="#shell-session-review-1424"></a><span class="re">$ </span><span class="fu">printf</span> <span class="st">&#039;%s\\n&#039;</span> <span class="st">&quot;$title&quot;</span></span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="in">Legacy Review</span>', $wordpressBlock);
        $t->same('shellsession', $directSession['language']);
        $t->same('console-session', $directSession['requestedLanguage']);
        $t->contains('<span class="re">reviewer@wp:/srv/site$ </span><span class="fu">wp</span> <span class="va">post</span> <span class="va">get</span> <span class="dv">42</span> <span class="ot">--field</span><span class="op">=</span><span class="va">post_title</span>', $directSession['html']);
        $t->contains('<span class="in">Legacy Review</span>', $directSession['html']);
    },
    'highlights lua long bracket strings and comments for pandoc filters' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[33] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Lua long-bracket code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directLua = $highlighter->highlight("--[==[review note]==]\nlocal html = [==[<p>ok</p>]==]", 'pandoc-lua');

        $t->same('pandoc-lua', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('lua', $highlighted['language']);
        $t->same('pandoc-lua', $highlighted['requestedLanguage']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(290, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pandoc-lua numberLines"><code class="sourceCode lua" style="counter-reset: source-line 289;">', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-290"><a href="#lua-long-bracket-review-290"></a><span class="co">--[=[ WordPress block fixture can contain &lt;!-- comments --&gt; ]=]</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-291"><a href="#lua-long-bracket-review-291"></a><span class="kw">local</span> <span class="va">rawBlock</span> <span class="op">=</span> <span class="st">[=[</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-292"><a href="#lua-long-bracket-review-292"></a><span class="st">&lt;!-- wp:paragraph --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-293"><a href="#lua-long-bracket-review-293"></a><span class="st">&lt;p&gt;Imported ${title}&lt;/p&gt;</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-295"><a href="#lua-long-bracket-review-295"></a><span class="st">]=]</span></span>', $highlighted['html']);
        $t->contains('<span id="lua-long-bracket-review-296"><a href="#lua-long-bracket-review-296"></a><span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">RawBlock</span><span class="op">(</span><span class="st">&quot;html&quot;</span><span class="op">,</span> <span class="va">rawBlock</span><span class="op">)</span></span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="st">&lt;p&gt;Imported ${title}&lt;/p&gt;</span>', $wordpressBlock);
        $t->same('lua', $directLua['language']);
        $t->contains('<span class="co">--[==[review note]==]</span>', $directLua['html']);
        $t->contains('<span class="st">[==[&lt;p&gt;ok&lt;/p&gt;]==]</span>', $directLua['html']);
    },
    'highlights php heredoc and nowdoc wordpress block strings' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[34] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PHP heredoc code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
        $directNowdoc = $highlighter->highlight("<?php\n\$raw = <<<'HTML'\n<!-- wp:shortcode -->\n[gallery]\nHTML;\n", 'php');

        $t->same('php', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('php', $highlighted['language']);
        $t->same('php', $highlighted['requestedLanguage']);
        $t->same('pygments', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(310, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource php numberLines"><code class="sourceCode php" style="counter-reset: source-line 309;">', $highlighted['html']);
        $t->contains('<span id="php-heredoc-review-310"><a href="#php-heredoc-review-310"></a><span class="pp">&lt;?php</span></span>', $highlighted['html']);
        $t->contains('<span id="php-heredoc-review-311"><a href="#php-heredoc-review-311"></a><span class="va">$block</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;HTML</span></span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;p&gt;Imported {$title}&lt;/p&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">HTML;</span>', $highlighted['html']);
        $t->contains('<span class="va">$raw</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;&#039;NOWDOC&#039;</span>', $highlighted['html']);
        $t->contains('<span class="st">&lt;div data-source=&quot;legacy&quot;&gt;raw&lt;/div&gt;</span>', $highlighted['html']);
        $t->contains('<span class="st">NOWDOC;</span>', $highlighted['html']);
        $t->contains('<span class="kw">echo</span> <span class="va">$block</span> <span class="op">.</span> <span class="va">$raw</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="pygments">', $wordpressBlock);
        $t->contains('<span class="st">&lt;!-- wp:html --&gt;</span>', $wordpressBlock);
        $t->same('php', $directNowdoc['language']);
        $t->contains('<span class="va">$raw</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;&#039;HTML&#039;', $directNowdoc['html']);
        $t->contains('&lt;!-- wp:shortcode --&gt;', $directNowdoc['html']);
        $t->contains('[gallery]', $directNowdoc['html']);
        $t->contains('HTML;</span>', $directNowdoc['html']);
    },
    'highlights awk migration filters with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[64] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an AWK review filter code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directAwk = $highlighter->highlight('BEGIN { FS="," } NR > 1 { print $1, tolower($2) }', 'gawk');

        $t->same('awk', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('awk', $highlighted['language']);
        $t->same('awk', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(940, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource awk numberLines"><code class="sourceCode awk" style="counter-reset: source-line 939;">', $highlighted['html']);
        $t->contains('<span id="awk-review-940"><a href="#awk-review-940"></a><span class="co"># AWK WordPress export review</span></span>', $highlighted['html']);
        $t->contains('<span class="re">BEGIN</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="va">FS</span> <span class="op">=</span> <span class="st">&quot;,&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">OFS</span> <span class="op">=</span> <span class="st">&quot;\\t&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">print</span> <span class="st">&quot;source_id&quot;</span><span class="op">,</span> <span class="st">&quot;title&quot;</span><span class="op">,</span> <span class="st">&quot;status&quot;</span>', $highlighted['html']);
        $t->contains('<span class="va">NR</span> <span class="op">&gt;</span> <span class="dv">1</span> <span class="op">&amp;&amp;</span> <span class="va">$3</span> <span class="op">~</span> <span class="st">/publish|draft/</span>', $highlighted['html']);
        $t->contains('<span class="fu">gensub</span><span class="op">(</span><span class="st">/^&quot;|&quot;$/</span><span class="op">,</span> <span class="st">&quot;&quot;</span><span class="op">,</span> <span class="st">&quot;g&quot;</span><span class="op">,</span> <span class="va">$2</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">gsub</span><span class="op">(</span><span class="st">/[[:space:]]+/</span><span class="op">,</span> <span class="st">&quot; &quot;</span><span class="op">,</span> <span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">length</span><span class="op">(</span><span class="va">title</span><span class="op">)</span> <span class="op">==</span> <span class="dv">0</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">printf</span> <span class="st">&quot;%s\\t%s\\t%s\\n&quot;</span><span class="op">,</span> <span class="va">$1</span><span class="op">,</span> <span class="va">title</span><span class="op">,</span> <span class="fu">tolower</span><span class="op">(</span><span class="va">$3</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="re">END</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">print</span> <span class="st">&quot;reviewed&quot;</span><span class="op">,</span> <span class="va">NR</span> <span class="op">-</span> <span class="dv">1</span> <span class="op">&gt;</span> <span class="st">&quot;/dev/stderr&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="fu">gsub</span><span class="op">(</span><span class="st">/[[:space:]]+/</span>', $wordpressBlock);
        $t->same('awk', $directAwk['language']);
        $t->same('gawk', $directAwk['requestedLanguage']);
        $t->contains('<span class="re">BEGIN</span> <span class="op">{</span> <span class="va">FS</span><span class="op">=</span><span class="st">&quot;,&quot;</span>', $directAwk['html']);
        $t->contains('<span class="va">NR</span> <span class="op">&gt;</span> <span class="dv">1</span> <span class="op">{</span> <span class="kw">print</span> <span class="va">$1</span><span class="op">,</span> <span class="fu">tolower</span><span class="op">(</span><span class="va">$2</span><span class="op">)</span>', $directAwk['html']);
    },
    'highlights windows batch import review scripts with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[65] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Windows batch review script code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directCmd = $highlighter->highlight('@if "%WP_ENV%"=="prod" echo ok', 'cmd.exe');

        $t->same('bat', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('batch', $highlighted['language']);
        $t->same('bat', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(960, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource bat numberLines"><code class="sourceCode batch" style="counter-reset: source-line 959;">', $highlighted['html']);
        $t->contains('<span id="batch-review-960"><a href="#batch-review-960"></a><span class="kw">@echo</span> <span class="cn">off</span></span>', $highlighted['html']);
        $t->contains('<span id="batch-review-961"><a href="#batch-review-961"></a><span class="co">REM Windows WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">setlocal</span> <span class="va">EnableExtensions</span> <span class="va">EnableDelayedExpansion</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="st">&quot;SOURCE_DIR=%~dp0exports&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="kw">not</span> <span class="cn">exist</span> <span class="st">&quot;%SOURCE_DIR%\\wxr.xml&quot;</span> <span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="kw">for</span> <span class="va">%%P</span> <span class="kw">in</span> <span class="op">(</span><span class="st">&quot;%SOURCE_DIR%\\*.html&quot;</span><span class="op">)</span> <span class="kw">do</span> <span class="op">(</span>', $highlighted['html']);
        $t->contains('<span class="fu">php</span> <span class="st">&quot;%~dp0tools\\normalize-title.php&quot;</span> <span class="st">&quot;%%~fP&quot;</span> <span class="op">&gt;&gt;</span> <span class="st">&quot;.\\review.log&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">!ERRORLEVEL!</span> <span class="op">NEQ</span> <span class="dv">0</span> <span class="kw">goto</span> <span class="re">:failed</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--format</span><span class="op">=</span><span class="va">ids</span> <span class="op">&gt;</span> <span class="st">&quot;.\\post-ids.txt&quot;</span>', $highlighted['html']);
        $t->contains('<span class="re">:failed</span>', $highlighted['html']);
        $t->contains('<span class="kw">exit</span> <span class="ot">/b</span> <span class="dv">2</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="kw">setlocal</span> <span class="va">EnableExtensions</span>', $wordpressBlock);
        $t->same('batch', $directCmd['language']);
        $t->same('cmd.exe', $directCmd['requestedLanguage']);
        $t->contains('<span class="kw">@if</span> <span class="st">&quot;%WP_ENV%&quot;</span><span class="op">==</span><span class="st">&quot;prod&quot;</span> <span class="kw">echo</span> <span class="va">ok</span>', $directCmd['html']);
    },
    'highlights matlab and octave technical review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[66] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a MATLAB technical review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directOctave = $highlighter->highlight(
            'function y = normalize_title(x); y = strtrim(x); endfunction',
            'octave'
        );

        $t->same('matlab', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('matlab', $highlighted['language']);
        $t->same('matlab', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(980, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource matlab numberLines"><code class="sourceCode matlab" style="counter-reset: source-line 979;">', $highlighted['html']);
        $t->contains('<span id="matlab-review-980"><a href="#matlab-review-980"></a><span class="co">% WordPress technical note scoring review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="op">[</span><span class="va">score</span><span class="op">,</span> <span class="va">slug</span><span class="op">]</span> <span class="op">=</span> <span class="fu">normalizeImport</span><span class="op">(</span><span class="va">packet</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">arguments</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">.</span><span class="va">title</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">.</span><span class="va">views</span> <span class="dt">double</span> <span class="op">=</span> <span class="cn">NaN</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span> <span class="op">=</span> <span class="fu">strtrim</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">strlength</span><span class="op">(</span><span class="va">title</span><span class="op">)</span> <span class="op">==</span> <span class="dv">0</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">slug</span> <span class="op">=</span> <span class="fu">lower</span><span class="op">(</span><span class="fu">regexprep</span><span class="op">(</span><span class="va">title</span><span class="op">,</span> <span class="st">&quot;[^a-z0-9]+&quot;</span><span class="op">,</span> <span class="st">&quot;-&quot;</span><span class="op">));</span>', $highlighted['html']);
        $t->contains('<span class="va">score</span> <span class="op">=</span> <span class="dt">double</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">views</span><span class="op">)</span> <span class="op">./</span> <span class="fu">max</span><span class="op">(</span><span class="dv">1</span><span class="op">,</span> <span class="fu">numel</span><span class="op">(</span><span class="va">title</span><span class="op">));</span>', $highlighted['html']);
        $t->contains('<span class="va">meta</span> <span class="op">=</span> <span class="dt">struct</span><span class="op">(</span><span class="st">&quot;reviewed&quot;</span><span class="op">,</span> <span class="cn">true</span><span class="op">,</span> <span class="st">&quot;slug&quot;</span><span class="op">,</span> <span class="va">slug</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="fu">regexprep</span><span class="op">(</span><span class="va">title</span>', $wordpressBlock);
        $t->same('matlab', $directOctave['language']);
        $t->same('octave', $directOctave['requestedLanguage']);
        $t->contains('<span class="kw">function</span> <span class="va">y</span> <span class="op">=</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">x</span><span class="op">);</span>', $directOctave['html']);
        $t->contains('<span class="va">y</span> <span class="op">=</span> <span class="fu">strtrim</span><span class="op">(</span><span class="va">x</span><span class="op">);</span> <span class="kw">endfunction</span>', $directOctave['html']);
    },
    'highlights fish shell import review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[67] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Fish shell review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directFish = $highlighter->highlight('set -q argv[1]; and echo $argv[1]', 'fish-shell');

        $t->same('fish', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('fish', $highlighted['language']);
        $t->same('fish', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1000, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource fish numberLines"><code class="sourceCode fish" style="counter-reset: source-line 999;">', $highlighted['html']);
        $t->contains('<span id="fish-review-1000"><a href="#fish-review-1000"></a><span class="co"># Fish shell WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="va">normalize_title</span> <span class="ot">--argument-names</span> <span class="va">packet_path</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="ot">-l</span> <span class="va">title</span> <span class="op">(</span><span class="fu">jq</span> <span class="ot">-r</span> <span class="st">&#039;.title // &quot;&quot;&#039;</span> <span class="va">$packet_path</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">string</span> <span class="va">trim</span> <span class="op">--</span> <span class="va">$title</span> <span class="op">|</span> <span class="fu">read</span> <span class="ot">-l</span> <span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">test</span> <span class="ot">-z</span> <span class="st">&quot;$title&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="va">title</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">printf</span> <span class="st">&quot;review:%s\\n&quot;</span> <span class="va">$title</span>', $highlighted['html']);
        $t->contains('<span class="kw">for</span> <span class="va">review_path</span> <span class="kw">in</span> <span class="va">exports</span><span class="op">/*.</span><span class="va">json</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="ot">-l</span> <span class="va">slug</span> <span class="op">(</span><span class="fu">string</span> <span class="va">lower</span> <span class="op">(</span><span class="fu">path</span> <span class="va">basename</span> <span class="va">$review_path</span> <span class="op">.</span><span class="va">json</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span> <span class="va">update</span> <span class="va">$slug</span> <span class="va">import_source</span> <span class="va">$review_path</span><span class="op">;</span> <span class="kw">or</span> <span class="kw">return</span> <span class="dv">1</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span>', $wordpressBlock);
        $t->same('fish', $directFish['language']);
        $t->same('fish-shell', $directFish['requestedLanguage']);
        $t->contains('<span class="kw">set</span> <span class="ot">-q</span> <span class="va">argv</span><span class="op">[</span><span class="dv">1</span><span class="op">];</span> <span class="kw">and</span> <span class="fu">echo</span> <span class="va">$argv[1]</span>', $directFish['html']);
    },
    'highlights sed stream editor review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[68] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Sed stream editor code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directSed = $highlighter->highlight(
            's#<h1>\(.*\)</h1>#<!-- wp:heading -->\1<!-- /wp:heading -->#g',
            'gnu-sed'
        );

        $t->same('sed', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('sed', $highlighted['language']);
        $t->same('sed', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1020, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource sed numberLines"><code class="sourceCode sed" style="counter-reset: source-line 1019;">', $highlighted['html']);
        $t->contains('<span id="sed-review-1020"><a href="#sed-review-1020"></a><span class="co"># sed WordPress block cleanup review</span></span>', $highlighted['html']);
        $t->contains('<span class="dv">1</span><span class="kw">i</span><span class="op">\\</span>', $highlighted['html']);
        $t->contains('<span class="st">/^[[:space:]]*$/</span><span class="kw">d</span>', $highlighted['html']);
        $t->contains('<span class="kw">s</span><span class="st">#&lt;script[^&gt;]*&gt;.*&lt;/script&gt;##</span><span class="ot">g</span>', $highlighted['html']);
        $t->contains('<span class="kw">s</span><span class="st">/\[gallery[^\]]*\]/&lt;!-- wp:shortcode --&gt;[gallery]&lt;!-- \/wp:shortcode --&gt;/</span><span class="ot">g</span>', $highlighted['html']);
        $t->contains('<span class="st">/&lt;!-- wp:html --&gt;/</span><span class="op">,</span><span class="st">/&lt;!-- \/wp:html --&gt;/</span><span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">t</span> <span class="va">normalized</span>', $highlighted['html']);
        $t->contains('<span class="re">:normalized</span>', $highlighted['html']);
        $t->contains('<span id="sed-review-1032"><a href="#sed-review-1032"></a><span class="kw">p</span></span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">s</span><span class="st">#&lt;script', $wordpressBlock);
        $t->same('sed', $directSed['language']);
        $t->same('gnu-sed', $directSed['requestedLanguage']);
        $t->contains('<span class="kw">s</span><span class="st">#&lt;h1&gt;\(.*\)&lt;/h1&gt;#&lt;!-- wp:heading --&gt;\1&lt;!-- /wp:heading --&gt;#</span><span class="ot">g</span>', $directSed['html']);
    },
    'preserves sed append change and insert text payloads for wordpress review packets' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[68] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Sed stream editor code block');
        }

        $highlighter = new SyntaxHighlighter();
        $fixtureHighlight = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $payload = $highlighter->highlight(implode("\n", [
            '2,5c\\',
            '<!-- wp:paragraph -->',
            '/legacy-shortcode/a\\',
            '<!-- wp:shortcode -->[gallery]<!-- /wp:shortcode -->',
            ':reviewed',
            't reviewed',
        ]), 'stream-editor');
        $continuedPayload = $highlighter->highlight(implode("\n", [
            '$i\\',
            '<!-- wp:html -->\\',
            '<div data-source="legacy"></div>',
            'p',
        ]), 'sed');

        $t->same('sed', $fixtureHighlight['language']);
        $t->contains('<span id="sed-review-1021"><a href="#sed-review-1021"></a><span class="dv">1</span><span class="kw">i</span><span class="op">\\</span></span>', $fixtureHighlight['html']);
        $t->contains('<span id="sed-review-1022"><a href="#sed-review-1022"></a><span class="st">&lt;!-- wp:paragraph --&gt;</span></span>', $fixtureHighlight['html']);
        $t->contains('<span id="sed-review-1023"><a href="#sed-review-1023"></a><span class="st">/^[[:space:]]*$/</span><span class="kw">d</span></span>', $fixtureHighlight['html']);
        $t->same('sed', $payload['language']);
        $t->same('stream-editor', $payload['requestedLanguage']);
        $t->contains('<span class="dv">2,5</span><span class="kw">c</span><span class="op">\\</span>', $payload['html']);
        $t->contains('<span class="st">&lt;!-- wp:paragraph --&gt;</span>', $payload['html']);
        $t->contains('<span class="st">/legacy-shortcode/</span><span class="kw">a</span><span class="op">\\</span>', $payload['html']);
        $t->contains('<span class="st">&lt;!-- wp:shortcode --&gt;[gallery]&lt;!-- /wp:shortcode --&gt;</span>', $payload['html']);
        $t->contains('<span class="re">:reviewed</span>', $payload['html']);
        $t->contains('<span class="kw">t</span> <span class="va">reviewed</span>', $payload['html']);
        $t->same([], $payload['diagnostics']);
        $t->contains('<span class="dv">$</span><span class="kw">i</span><span class="op">\\</span>', $continuedPayload['html']);
        $t->contains('<span class="st">&lt;!-- wp:html --&gt;\\</span>', $continuedPayload['html']);
        $t->contains('<span class="st">&lt;div data-source=&quot;legacy&quot;&gt;&lt;/div&gt;</span>', $continuedPayload['html']);
        $t->contains('<span class="kw">p</span>', $continuedPayload['html']);
    },
    'disambiguates sed print commands from substitution flags' => static function (TestRunner $t): void {
        $highlighted = (new SyntaxHighlighter())->highlight(implode("\n", [
            'p',
            '1,3p',
            '/legacy-shortcode/p',
            '1!p',
            's/foo/bar/p',
        ]), 'sed');

        $t->same('sed', $highlighted['language']);
        $t->same([], $highlighted['diagnostics']);
        $t->contains('<span class="kw">p</span>', $highlighted['html']);
        $t->contains('<span class="dv">1,3</span><span class="kw">p</span>', $highlighted['html']);
        $t->contains('<span class="st">/legacy-shortcode/</span><span class="kw">p</span>', $highlighted['html']);
        $t->contains('<span class="dv">1</span><span class="op">!</span><span class="kw">p</span>', $highlighted['html']);
        $t->contains('<span class="kw">s</span><span class="st">/foo/</span><span class="va">bar</span><span class="op">/</span><span class="ot">p</span>', $highlighted['html']);
    },
    'highlights bibtex bibliography review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[69] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a BibTeX review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directBibtex = $highlighter->highlight('@book{review, year = 2025, month = jun, title = wp # " guide"}', 'bib');

        $t->same('biblatex', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('bibtex'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('biblatex'));
        $t->same('bibtex', SyntaxHighlighter::normalizeLanguage('bib'));
        $t->same('bibtex', $highlighted['language']);
        $t->same('biblatex', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1040, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource biblatex numberLines"><code class="sourceCode bibtex" style="counter-reset: source-line 1039;">', $highlighted['html']);
        $t->contains('<span id="bibtex-review-1040"><a href="#bibtex-review-1040"></a><span class="co">% WordPress bibliography review handoff</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">@online</span><span class="op">{</span><span class="va">wp-data-liberation</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">author</span>       <span class="op">=</span> <span class="st">{Doe, Jane and WordPress.org Contributors}</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">date</span>         <span class="op">=</span> <span class="st">{2026-06-08}</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">keywords</span>     <span class="op">=</span> <span class="st">{wordpress, migration, blocks}</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="kw">@string</span><span class="op">{</span><span class="ot">wp</span> <span class="op">=</span> <span class="st">&quot;WordPress&quot;</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="kw">@article</span><span class="op">{</span><span class="va">legacy-shortcode</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">title</span> <span class="op">=</span> <span class="va">wp</span> <span class="op">#</span> <span class="st">&quot; shortcode audit&quot;</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<span class="ot">year</span> <span class="op">=</span> <span class="dv">2025</span><span class="op">,</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">@online</span><span class="op">{</span><span class="va">wp-data-liberation</span>', $wordpressBlock);
        $t->same('bibtex', $directBibtex['language']);
        $t->same('bib', $directBibtex['requestedLanguage']);
        $t->contains('<span class="kw">@book</span><span class="op">{</span><span class="va">review</span><span class="op">,</span> <span class="ot">year</span> <span class="op">=</span> <span class="dv">2025</span><span class="op">,</span> <span class="ot">month</span> <span class="op">=</span> <span class="cn">jun</span>', $directBibtex['html']);
        $t->contains('<span class="ot">title</span> <span class="op">=</span> <span class="va">wp</span> <span class="op">#</span> <span class="st">&quot; guide&quot;</span><span class="op">}</span>', $directBibtex['html']);
    },
    'highlights vimscript import review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[70] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Vimscript review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directVim = $highlighter->highlight('let g:title = trim(a:title) | return v:true', 'vimscript');

        $t->same('vim', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('vim', $highlighted['language']);
        $t->same('vim', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1060, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource vim numberLines"><code class="sourceCode vim" style="counter-reset: source-line 1059;">', $highlighted['html']);
        $t->contains('<span id="vim-review-1060"><a href="#vim-review-1060"></a><span class="co">&quot; Vimscript WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">scriptencoding</span> <span class="va">utf</span><span class="dv">-8</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">g:wp_import_review</span> <span class="op">=</span> <span class="cn">v:true</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">s:source_path</span> <span class="op">=</span> <span class="fu">expand</span><span class="op">(</span><span class="st">&#039;~/exports/wxr.json&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">setlocal</span> <span class="ot">keywordprg</span><span class="op">=:</span><span class="va">help</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span><span class="op">!</span> <span class="va">s:NormalizeTitle</span><span class="op">(</span><span class="va">packet</span><span class="op">)</span> <span class="kw">abort</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">l:title</span> <span class="op">=</span> <span class="fu">trim</span><span class="op">(</span><span class="va">a:packet</span><span class="op">.</span><span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">empty</span><span class="op">(</span><span class="va">l:title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">substitute</span><span class="op">(</span><span class="va">l:title</span><span class="op">,</span> <span class="st">&#039;\s\+&#039;</span><span class="op">,</span> <span class="st">&#039; &#039;</span><span class="op">,</span> <span class="st">&#039;g&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">command</span><span class="op">!</span> <span class="ot">-nargs</span><span class="op">=</span><span class="dv">1</span> <span class="va">ReviewImport</span> <span class="kw">call</span> <span class="va">s:NormalizeTitle</span>', $highlighted['html']);
        $t->contains('<span class="fu">json_decode</span><span class="op">(</span><span class="fu">readfile</span><span class="op">(&lt;</span><span class="va">q</span><span class="ot">-args</span><span class="op">&gt;)[</span><span class="dv">0</span><span class="op">]))</span>', $highlighted['html']);
        $t->contains('<span class="kw">nnoremap</span> <span class="op">&lt;</span><span class="va">leader</span><span class="op">&gt;</span><span class="va">wr</span> <span class="op">:</span><span class="kw">execute</span> <span class="st">&#039;edit &#039;</span> <span class="op">.</span> <span class="fu">fnameescape</span>', $highlighted['html']);
        $t->contains('<span class="kw">syntax</span> <span class="kw">match</span> <span class="va">wpImportSource</span> <span class="st">/\v(import_source|post_title)/</span>', $highlighted['html']);
        $t->contains('<span class="kw">highlight</span> <span class="va">wpImportSource</span> <span class="kw">ctermfg</span><span class="op">=</span><span class="va">Green</span> <span class="kw">guifg</span><span class="op">=</span><span class="cn">#005cc5</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="kw">command</span><span class="op">!</span> <span class="ot">-nargs</span>', $wordpressBlock);
        $t->same('vim', $directVim['language']);
        $t->same('vimscript', $directVim['requestedLanguage']);
        $t->contains('<span class="kw">let</span> <span class="va">g:title</span> <span class="op">=</span> <span class="fu">trim</span><span class="op">(</span><span class="va">a:title</span><span class="op">)</span> <span class="op">|</span> <span class="kw">return</span> <span class="cn">v:true</span>', $directVim['html']);
    },
    'highlights scheme and racket review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[71] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Scheme/Racket review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directScheme = $highlighter->highlight('(define (title raw) (if (string-blank? raw) "Untitled" raw))', 'scm');

        $t->same('racket', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('scheme'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('scm'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('racket'));
        $t->same('scheme', SyntaxHighlighter::normalizeLanguage('rkt'));
        $t->same('scheme', $highlighted['language']);
        $t->same('racket', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1080, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource racket numberLines"><code class="sourceCode scheme" style="counter-reset: source-line 1079;">', $highlighted['html']);
        $t->contains('<span id="scheme-review-1080"><a href="#scheme-review-1080"></a><span class="kw">#lang</span> <span class="dt">racket</span></span>', $highlighted['html']);
        $t->contains('<span class="co">; WordPress import review helper</span>', $highlighted['html']);
        $t->contains('<span class="kw">struct</span> <span class="va">packet</span> <span class="op">(</span><span class="va">source-id</span> <span class="va">title</span> <span class="va">blocks</span><span class="op">)</span> <span class="ot">#:transparent</span>', $highlighted['html']);
        $t->contains('<span class="kw">define</span> <span class="op">(</span><span class="va">normalize-title</span> <span class="va">raw</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">let*</span> <span class="op">([</span><span class="va">trimmed</span> <span class="op">(</span><span class="fu">string-trim</span> <span class="va">raw</span><span class="op">)]</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">string-blank?</span> <span class="va">trimmed</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">match</span> <span class="va">item</span>', $highlighted['html']);
        $t->contains('<span class="kw">for/list</span> <span class="op">([</span><span class="va">block</span> <span class="va">blocks</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="ot">#:when</span> <span class="op">(</span><span class="fu">hash-ref</span> <span class="va">block</span> <span class="cn">&#039;review?</span> <span class="cn">#t</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="fu">hash</span> <span class="cn">&#039;source</span> <span class="va">source-id</span>', $highlighted['html']);
        $t->contains('<span class="cn">&#039;block-name</span> <span class="op">(</span><span class="fu">hash-ref</span> <span class="va">block</span> <span class="cn">&#039;name</span> <span class="st">&quot;core/paragraph&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">provide</span> <span class="va">normalize-title</span> <span class="va">packet-&gt;blocks</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="kw">#lang</span> <span class="dt">racket</span>', $wordpressBlock);
        $t->same('scheme', $directScheme['language']);
        $t->same('scm', $directScheme['requestedLanguage']);
        $t->contains('<span class="kw">define</span> <span class="op">(</span><span class="va">title</span> <span class="va">raw</span><span class="op">)</span>', $directScheme['html']);
        $t->contains('<span class="fu">string-blank?</span>', $directScheme['html']);
        $t->contains('<span class="st">&quot;Untitled&quot;</span>', $directScheme['html']);
    },
    'highlights csv and tsv import review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[72] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a CSV review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directTsv = $highlighter->highlight(
            "source_id\ttitle\tstatus\n42\t\"Legacy export\"\tdraft",
            'tab-separated-values'
        );

        $t->same('csv', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('csv', SyntaxHighlighter::normalizeLanguage('csv'));
        $t->same('csv', SyntaxHighlighter::normalizeLanguage('comma-separated-values'));
        $t->same('tsv', SyntaxHighlighter::normalizeLanguage('tsv'));
        $t->same('tsv', SyntaxHighlighter::normalizeLanguage('tab-separated-values'));
        $t->same('csv', $highlighted['language']);
        $t->same('csv', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1100, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource csv numberLines"><code class="sourceCode csv" style="counter-reset: source-line 1099;">', $highlighted['html']);
        $t->contains('<span id="csv-review-1100"><a href="#csv-review-1100"></a><span class="co"># WordPress CSV import review</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">source_id</span><span class="op">,</span><span class="ot">title</span><span class="op">,</span><span class="ot">status</span><span class="op">,</span><span class="ot">views</span><span class="op">,</span><span class="ot">featured</span>', $highlighted['html']);
        $t->contains('<span class="dv">42</span><span class="op">,</span><span class="st">&quot;Legacy, &quot;&quot;quoted&quot;&quot; title&quot;</span><span class="op">,</span><span class="va">draft</span><span class="op">,</span><span class="dv">120</span><span class="op">,</span><span class="cn">true</span>', $highlighted['html']);
        $t->contains('<span class="dv">43</span><span class="op">,</span><span class="va">Untitled</span><span class="op">,</span><span class="va">publish</span><span class="op">,</span><span class="dv">0</span><span class="op">,</span><span class="cn">false</span>', $highlighted['html']);
        $t->contains('<span class="dv">44</span><span class="op">,</span><span class="st">&quot;Media path: uploads/hero.jpg&quot;</span><span class="op">,</span><span class="va">needs-review</span><span class="op">,,</span><span class="cn">null</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="st">&quot;Legacy, &quot;&quot;quoted&quot;&quot; title&quot;</span>', $wordpressBlock);
        $t->same('tsv', $directTsv['language']);
        $t->same('tab-separated-values', $directTsv['requestedLanguage']);
        $t->contains('<span class="ot">source_id</span><span class="op">	</span><span class="ot">title</span><span class="op">	</span><span class="ot">status</span>', $directTsv['html']);
        $t->contains('<span class="dv">42</span><span class="op">	</span><span class="st">&quot;Legacy export&quot;</span><span class="op">	</span><span class="va">draft</span>', $directTsv['html']);
    },
    'highlights erlang otp review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[73] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Erlang review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directErlang = $highlighter->highlight(
            '-module(review). -export([normalize/1]). normalize(Title) -> string:trim(Title).',
            'erlang-header'
        );

        $t->same('erl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('erlang', SyntaxHighlighter::normalizeLanguage('erlang'));
        $t->same('erlang', SyntaxHighlighter::normalizeLanguage('erl'));
        $t->same('erlang', SyntaxHighlighter::normalizeLanguage('hrl'));
        $t->same('erlang', SyntaxHighlighter::normalizeLanguage('language-erlang-header'));
        $t->same('erlang', $highlighted['language']);
        $t->same('erl', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1120, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource erl numberLines"><code class="sourceCode erlang" style="counter-reset: source-line 1119;">', $highlighted['html']);
        $t->contains('<span id="erlang-review-1120"><a href="#erlang-review-1120"></a><span class="co">%% Erlang WordPress import review worker</span></span>', $highlighted['html']);
        $t->contains('<span class="ot">-module</span><span class="op">(</span><span class="cn">wp_import_review</span><span class="op">).</span>', $highlighted['html']);
        $t->contains('<span class="ot">-behaviour</span><span class="op">(</span><span class="cn">gen_server</span><span class="op">).</span>', $highlighted['html']);
        $t->contains('<span class="ot">-export</span><span class="op">([</span><span class="cn">normalize_title</span><span class="op">/</span><span class="dv">1</span><span class="op">,</span> <span class="cn">handle_call</span><span class="op">/</span><span class="dv">3</span><span class="op">]).</span>', $highlighted['html']);
        $t->contains('<span class="ot">-define</span><span class="op">(</span><span class="va">DEFAULT_TITLE</span><span class="op">,</span> <span class="op">&lt;&lt;</span><span class="st">&quot;Untitled&quot;</span><span class="op">&gt;&gt;).</span>', $highlighted['html']);
        $t->contains('<span class="ot">-record</span><span class="op">(</span><span class="cn">review_packet</span><span class="op">,</span> <span class="op">{</span><span class="ot">source_id</span> <span class="op">::</span> <span class="dt">integer</span><span class="op">(),</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalize_title</span><span class="op">(</span><span class="dt">#review_packet</span><span class="op">{</span><span class="ot">source_id</span> <span class="op">=</span> <span class="va">SourceId</span>', $highlighted['html']);
        $t->contains('<span class="kw">when</span> <span class="fu">is_binary</span><span class="op">(</span><span class="va">Title</span><span class="op">);</span> <span class="fu">is_list</span><span class="op">(</span><span class="va">Title</span><span class="op">)</span> <span class="op">-&gt;</span>', $highlighted['html']);
        $t->contains('<span class="va">Trimmed</span> <span class="op">=</span> <span class="dt">string</span><span class="op">:</span><span class="fu">trim</span><span class="op">(</span><span class="dt">unicode</span><span class="op">:</span><span class="fu">characters_to_list</span><span class="op">(</span><span class="va">Title</span><span class="op">)),</span>', $highlighted['html']);
        $t->contains('<span class="kw">case</span> <span class="va">Trimmed</span> <span class="kw">of</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;&quot;</span> <span class="op">-&gt;</span> <span class="op">&lt;&lt;</span><span class="st">&quot;Untitled&quot;</span><span class="op">&gt;&gt;;</span>', $highlighted['html']);
        $t->contains('<span class="va">HtmlBlocks</span> <span class="op">=</span> <span class="op">[</span><span class="dt">maps</span><span class="op">:</span><span class="fu">get</span><span class="op">(&lt;&lt;</span><span class="st">&quot;blockName&quot;</span><span class="op">&gt;&gt;,</span>', $highlighted['html']);
        $t->contains('<span class="cn">ok</span><span class="op">,</span> <span class="va">HtmlBlocks</span>', $highlighted['html']);
        $t->contains('<!-- wp:html -->', $wordpressBlock);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<pre class="sourceCode numberSource erl numberLines"><code class="sourceCode erlang" style="counter-reset: source-line 1119;">', $wordpressBlock);
        $t->same('erlang', $directErlang['language']);
        $t->same('erlang-header', $directErlang['requestedLanguage']);
        $t->contains('<span class="ot">-module</span><span class="op">(</span><span class="cn">review</span><span class="op">).</span>', $directErlang['html']);
        $t->contains('<span class="fu">normalize</span><span class="op">(</span><span class="va">Title</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="dt">string</span><span class="op">:</span><span class="fu">trim</span><span class="op">(</span><span class="va">Title</span><span class="op">).</span>', $directErlang['html']);
    },
    'highlights objective c review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[74] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Objective-C review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directObjectiveC = $highlighter->highlight(
            '@interface Review : NSObject @property (nonatomic, copy) NSString *title; @end',
            'objective-c++'
        );

        $t->same('objc', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('objectivec', SyntaxHighlighter::normalizeLanguage('objc'));
        $t->same('objectivec', SyntaxHighlighter::normalizeLanguage('obj-c'));
        $t->same('objectivec', SyntaxHighlighter::normalizeLanguage('objective-c'));
        $t->same('objectivec', SyntaxHighlighter::normalizeLanguage('language-objective-c++'));
        $t->same('objectivec', SyntaxHighlighter::normalizeLanguage('mm'));
        $t->same('objectivec', $highlighted['language']);
        $t->same('objc', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1140, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource objc numberLines"><code class="sourceCode objectivec" style="counter-reset: source-line 1139;">', $highlighted['html']);
        $t->contains('<span id="objectivec-review-1140"><a href="#objectivec-review-1140"></a><span class="co">// Objective-C WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="pp">#import &lt;Foundation/Foundation.h&gt;</span>', $highlighted['html']);
        $t->contains('<span class="kw">@interface</span> <span class="dt">WPImportReviewPacket</span> <span class="op">:</span> <span class="dt">NSObject</span>', $highlighted['html']);
        $t->contains('<span class="kw">@property</span> <span class="op">(</span><span class="kw">nonatomic</span><span class="op">,</span> <span class="kw">copy</span><span class="op">,</span> <span class="kw">nullable</span><span class="op">)</span> <span class="dt">NSString</span> <span class="op">*</span><span class="va">title</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="dt">NSString</span> <span class="op">*</span><span class="va">trimmed</span> <span class="op">=</span> <span class="op">[</span><span class="cn">self</span><span class="op">.</span><span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">trimmed</span><span class="op">.</span><span class="va">length</span> <span class="op">==</span> <span class="dv">0</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="op">[</span><span class="dt">NSString</span> <span class="va">stringWithFormat</span><span class="op">:</span><span class="st">@&quot;Import %ld&quot;</span><span class="op">,</span> <span class="op">(</span><span class="dt">long</span><span class="op">)</span><span class="cn">self</span><span class="op">.</span><span class="va">sourceId</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="va">trimmed</span> <span class="op">?:</span> <span class="st">@&quot;Untitled&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">@autoreleasepool</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="fu">NSLog</span><span class="op">(</span><span class="st">@&quot;%@&quot;</span><span class="op">,</span> <span class="op">[</span><span class="va">packet</span> <span class="va">normalizedTitle</span><span class="op">]);</span>', $highlighted['html']);
        $t->contains('<!-- wp:html -->', $wordpressBlock);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<pre class="sourceCode numberSource objc numberLines"><code class="sourceCode objectivec" style="counter-reset: source-line 1139;">', $wordpressBlock);
        $t->same('objectivec', $directObjectiveC['language']);
        $t->same('objective-c++', $directObjectiveC['requestedLanguage']);
        $t->contains('<span class="kw">@interface</span> <span class="dt">Review</span> <span class="op">:</span> <span class="dt">NSObject</span>', $directObjectiveC['html']);
        $t->contains('<span class="kw">@property</span> <span class="op">(</span><span class="kw">nonatomic</span><span class="op">,</span> <span class="kw">copy</span><span class="op">)</span> <span class="dt">NSString</span> <span class="op">*</span><span class="va">title</span><span class="op">;</span>', $directObjectiveC['html']);
    },
    'highlights raku review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[75] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Raku review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directRaku = $highlighter->highlight(
            'unit module Review; sub normalize(Str $title --> Str) is export { say $title.trim }',
            'perl6'
        );

        $t->same('raku', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('raku'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('perl6'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('pl6'));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('rakumod'));
        $t->same('raku', $highlighted['language']);
        $t->same('raku', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1160, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource raku numberLines"><code class="sourceCode raku" style="counter-reset: source-line 1159;">', $highlighted['html']);
        $t->contains('<span id="raku-review-1160"><a href="#raku-review-1160"></a><span class="co"># Raku WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">use</span> <span class="dt">JSON::Fast</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">unit</span> <span class="kw">module</span> <span class="dt">WP::Import::Review</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">has</span> <span class="dt">Int</span> <span class="va">$.source-id</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">has</span> <span class="dt">Str</span> <span class="va">$.title</span> <span class="kw">is</span> <span class="va">rw</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">sub</span> <span class="fu">normalize-title</span><span class="op">(</span><span class="dt">ReviewPacket</span> <span class="va">$packet</span> <span class="op">--&gt;</span> <span class="dt">Str</span><span class="op">)</span> <span class="kw">is</span> <span class="kw">export</span>', $highlighted['html']);
        $t->contains('<span class="kw">my</span> <span class="dt">Str</span> <span class="va">$title</span> <span class="op">=</span> <span class="va">$packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="va">trim</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">&quot;Untitled&quot;</span> <span class="kw">if</span> <span class="va">$title</span> <span class="op">eq</span> <span class="st">&quot;&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">$title</span><span class="op">.</span><span class="fu">subst</span><span class="op">(</span><span class="st">/\\s+/</span><span class="op">,</span> <span class="st">&quot; &quot;</span><span class="op">,</span> <span class="ot">:g</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">multi</span> <span class="kw">sub</span> <span class="fu">blocks-to-html</span><span class="op">(</span><span class="dt">ReviewPacket</span> <span class="va">$packet</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">gather</span> <span class="kw">for</span> <span class="va">$packet</span><span class="op">.</span><span class="va">blocks</span> <span class="op">-&gt;</span> <span class="va">%block</span>', $highlighted['html']);
        $t->contains('<span class="kw">take</span> <span class="st">&quot;&lt;!-- wp:{%block&lt;name&gt;} --&gt;{%block&lt;content&gt;}&lt;!-- /wp:{%block&lt;name&gt;} --&gt;&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="fu">say</span> <span class="fu">normalize-title</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">.</span><span class="fu">new</span><span class="op">(</span><span class="ot">source-id</span> <span class="op">=&gt;</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<!-- wp:html -->', $wordpressBlock);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<pre class="sourceCode numberSource raku numberLines"><code class="sourceCode raku" style="counter-reset: source-line 1159;">', $wordpressBlock);
        $t->same('raku', $directRaku['language']);
        $t->same('perl6', $directRaku['requestedLanguage']);
        $t->contains('<span class="kw">unit</span> <span class="kw">module</span> <span class="dt">Review</span><span class="op">;</span>', $directRaku['html']);
        $t->contains('<span class="kw">sub</span> <span class="fu">normalize</span><span class="op">(</span><span class="dt">Str</span> <span class="va">$title</span> <span class="op">--&gt;</span> <span class="dt">Str</span><span class="op">)</span> <span class="kw">is</span> <span class="kw">export</span>', $directRaku['html']);
        $t->contains('<span class="fu">say</span> <span class="va">$title</span><span class="op">.</span><span class="va">trim</span>', $directRaku['html']);
    },
    'preserves raku pod block boundaries and heredoc quote forms' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[96] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Raku POD quote review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directRakuQuote = $highlighter->highlight(
            "=begin pod\n=head1 Direct\n=end pod\nmy \$block = qq:to/HTML/;\n<p>{{title}}</p>\nHTML\nsay \$block;",
            'rakudoc'
        );

        $t->same('rakudoc', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('raku', SyntaxHighlighter::normalizeLanguage('rakudoc'));
        $t->same('raku', $highlighted['language']);
        $t->same('rakudoc', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1610, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource rakudoc numberLines"><code class="sourceCode raku" style="counter-reset: source-line 1609;">', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1610"><a href="#raku-pod-quote-review-1610"></a><span class="co">=begin pod</span></span>', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1614"><a href="#raku-pod-quote-review-1614"></a><span class="co">=end pod</span></span>', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1616"><a href="#raku-pod-quote-review-1616"></a><span class="kw">my</span> <span class="va">$title</span> <span class="op">=</span> <span class="st">q:to/END/;</span></span>', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1617"><a href="#raku-pod-quote-review-1617"></a><span class="st">Legacy shortcode [gallery]</span></span>', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1621"><a href="#raku-pod-quote-review-1621"></a><span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;$title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span></span>', $highlighted['html']);
        $t->contains('<span id="raku-pod-quote-review-1624"><a href="#raku-pod-quote-review-1624"></a><span class="fu">say</span> <span class="va">$title</span><span class="op">.</span><span class="va">trim</span><span class="op">;</span></span>', $highlighted['html']);
        $t->contains('<!-- wp:html -->', $wordpressBlock);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<pre class="sourceCode numberSource rakudoc numberLines"><code class="sourceCode raku" style="counter-reset: source-line 1609;">', $wordpressBlock);
        $t->same('raku', $directRakuQuote['language']);
        $t->same('rakudoc', $directRakuQuote['requestedLanguage']);
        $t->contains("=end pod</span>\n<span class=\"kw\">my</span>", $directRakuQuote['html']);
        $t->contains('<span class="kw">my</span> <span class="va">$block</span> <span class="op">=</span> <span class="st">qq:to/HTML/;', $directRakuQuote['html']);
        $t->contains("qq:to/HTML/;\n&lt;p&gt;{{title}}&lt;/p&gt;\nHTML</span>", $directRakuQuote['html']);
        $t->contains('<span class="fu">say</span> <span class="va">$block</span><span class="op">;</span>', $directRakuQuote['html']);
    },
    'highlights fennel review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[76] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Fennel review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directFennel = $highlighter->highlight('(fn review [packet] (print (or packet.title "Untitled")))', 'fennel-lang');

        $t->same('fnl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('fennel'));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('fnl'));
        $t->same('fennel', SyntaxHighlighter::normalizeLanguage('language-fennel-lang'));
        $t->same('fennel', $highlighted['language']);
        $t->same('fnl', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1180, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource fnl numberLines"><code class="sourceCode fennel" style="counter-reset: source-line 1179;">', $highlighted['html']);
        $t->contains('<span id="fennel-review-1180"><a href="#fennel-review-1180"></a><span class="co">; Fennel WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">local</span> <span class="va">json</span> <span class="op">(</span><span class="fu">require</span> <span class="ot">:json</span><span class="op">))</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">fn</span> <span class="fu">normalize-title</span> <span class="op">[</span><span class="va">packet</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">let</span> <span class="op">[</span><span class="va">title</span> <span class="op">(</span><span class="kw">or</span> <span class="va">packet.title</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="va">trimmed</span> <span class="op">(</span><span class="fu">string.gsub</span> <span class="va">title</span> <span class="st">&quot;^%s*(.-)%s*$&quot;</span> <span class="st">&quot;%1&quot;</span><span class="op">)]</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">if</span> <span class="op">(=</span> <span class="va">trimmed</span> <span class="st">&quot;&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">collect</span> <span class="op">[</span><span class="va">_</span> <span class="va">block</span> <span class="op">(</span><span class="fu">ipairs</span> <span class="va">packet.blocks</span><span class="op">)]</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">when</span> <span class="op">(not=</span> <span class="va">block.name</span> <span class="cn">nil</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">:source-id</span> <span class="va">packet.source_id</span>', $highlighted['html']);
        $t->contains('<span class="ot">:html</span> <span class="op">(</span><span class="fu">string.format</span> <span class="st">&quot;&lt;!-- wp:%s --&gt;%s&lt;!-- /wp:%s --&gt;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="fu">print</span> <span class="op">(</span><span class="va">normalize-title</span> <span class="op">{</span><span class="ot">:title</span> <span class="st">&quot; Legacy &quot;</span> <span class="ot">:source_id</span> <span class="dv">42</span><span class="op">}))</span>', $highlighted['html']);
        $t->contains('<!-- wp:html -->', $wordpressBlock);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<pre class="sourceCode numberSource fnl numberLines"><code class="sourceCode fennel" style="counter-reset: source-line 1179;">', $wordpressBlock);
        $t->same('fennel', $directFennel['language']);
        $t->same('fennel-lang', $directFennel['requestedLanguage']);
        $t->contains('<span class="op">(</span><span class="kw">fn</span> <span class="fu">review</span> <span class="op">[</span><span class="va">packet</span><span class="op">]</span>', $directFennel['html']);
        $t->contains('<span class="fu">print</span> <span class="op">(</span><span class="kw">or</span> <span class="va">packet.title</span> <span class="st">&quot;Untitled&quot;</span><span class="op">)))</span>', $directFennel['html']);
    },
    'highlights meson and justfile build review snippets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $mesonCodeBlock = $document->children[77] ?? null;
        if (!$mesonCodeBlock instanceof AstNode || $mesonCodeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Meson build review code block');
        }
        $justCodeBlock = $document->children[78] ?? null;
        if (!$justCodeBlock instanceof AstNode || $justCodeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Justfile review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $meson = $highlighter->highlightCodeBlock($mesonCodeBlock, 'monochrome');
        $mesonWordpressBlock = $highlighter->wordpressHtmlBlock($mesonCodeBlock, 'monochrome');
        $just = $highlighter->highlightCodeBlock($justCodeBlock, 'haddock');
        $justWordpressBlock = $highlighter->wordpressHtmlBlock($justCodeBlock, 'haddock');
        $directMeson = $highlighter->highlight('project("wp-import-review", "c", version: "1.0")', 'meson.build');
        $directJust = $highlighter->highlight('default source_id="legacy-42": @just review {{source_id}}', 'justfile');

        $t->same('meson', SyntaxHighlighter::languageFromCodeBlock($mesonCodeBlock));
        $t->same('meson', SyntaxHighlighter::normalizeLanguage('meson'));
        $t->same('meson', SyntaxHighlighter::normalizeLanguage('meson.build'));
        $t->same('meson', $meson['language']);
        $t->same('meson', $meson['requestedLanguage']);
        $t->same('monochrome', $meson['style']);
        $t->same([], $meson['diagnostics']);
        $t->same(1200, $meson['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource meson numberLines"><code class="sourceCode meson" style="counter-reset: source-line 1199;">', $meson['html']);
        $t->contains('<span id="meson-review-1200"><a href="#meson-review-1200"></a><span class="co"># Meson WordPress native helper review</span></span>', $meson['html']);
        $t->contains('<span class="fu">project</span><span class="op">(</span><span class="st">&#039;wp-import-review&#039;</span><span class="op">,</span> <span class="st">&#039;c&#039;</span><span class="op">,</span> <span class="ot">version</span><span class="op">:</span> <span class="st">&#039;1.0&#039;</span><span class="op">)</span>', $meson['html']);
        $t->contains('<span class="va">review_sources</span> <span class="op">=</span> <span class="fu">files</span><span class="op">(</span><span class="st">&#039;review.c&#039;</span><span class="op">,</span> <span class="st">&#039;audit.c&#039;</span><span class="op">)</span>', $meson['html']);
        $t->contains('<span class="va">wp_cli</span> <span class="op">=</span> <span class="fu">find_program</span><span class="op">(</span><span class="st">&#039;wp&#039;</span><span class="op">,</span> <span class="ot">required</span><span class="op">:</span> <span class="cn">false</span><span class="op">)</span>', $meson['html']);
        $t->contains('<span class="va">config</span><span class="op">.</span><span class="fu">set</span><span class="op">(</span><span class="st">&#039;PLUGIN_SLUG&#039;</span><span class="op">,</span> <span class="va">plugin_slug</span><span class="op">)</span>', $meson['html']);
        $t->contains('<span class="ot">c_args</span><span class="op">:</span> <span class="op">[</span><span class="st">&#039;-DWP_IMPORT_REVIEW=1&#039;</span><span class="op">],</span>', $meson['html']);
        $t->contains('<span class="kw">if</span> <span class="fu">get_option</span><span class="op">(</span><span class="st">&#039;review_tools&#039;</span><span class="op">)</span>', $meson['html']);
        $t->contains('<span class="fu">executable</span><span class="op">(</span><span class="st">&#039;wp-import-review&#039;</span><span class="op">,</span> <span class="st">&#039;review.c&#039;</span><span class="op">,</span> <span class="ot">dependencies</span><span class="op">:</span> <span class="fu">dependency</span><span class="op">(</span><span class="st">&#039;json-c&#039;</span>', $meson['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $mesonWordpressBlock);
        $t->contains('<span class="fu">configuration_data</span><span class="op">()</span>', $mesonWordpressBlock);
        $t->same('meson', $directMeson['language']);
        $t->same('meson.build', $directMeson['requestedLanguage']);
        $t->contains('<span class="fu">project</span><span class="op">(</span><span class="st">&quot;wp-import-review&quot;</span><span class="op">,</span> <span class="st">&quot;c&quot;</span><span class="op">,</span> <span class="ot">version</span><span class="op">:</span> <span class="st">&quot;1.0&quot;</span><span class="op">)</span>', $directMeson['html']);

        $t->same('Justfile', SyntaxHighlighter::languageFromCodeBlock($justCodeBlock));
        $t->same('just', SyntaxHighlighter::normalizeLanguage('just'));
        $t->same('just', SyntaxHighlighter::normalizeLanguage('Justfile'));
        $t->same('just', SyntaxHighlighter::normalizeLanguage('language-just-file'));
        $t->same('just', $just['language']);
        $t->same('Justfile', $just['requestedLanguage']);
        $t->same('haddock', $just['style']);
        $t->same([], $just['diagnostics']);
        $t->same(1220, $just['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource Justfile numberLines"><code class="sourceCode just" style="counter-reset: source-line 1219;">', $just['html']);
        $t->contains('<span id="just-review-1220"><a href="#just-review-1220"></a><span class="co"># Justfile WordPress import review tasks</span></span>', $just['html']);
        $t->contains('<span class="kw">set</span> <span class="ot">shell</span> <span class="op">:=</span> <span class="op">[</span><span class="st">&quot;bash&quot;</span><span class="op">,</span> <span class="st">&quot;-uc&quot;</span><span class="op">]</span>', $just['html']);
        $t->contains('<span class="kw">export</span> <span class="ot">WP_IMPORT_SOURCE</span> <span class="op">:=</span> <span class="st">&quot;legacy-42&quot;</span>', $just['html']);
        $t->contains('<span class="re">default source_id=&quot;legacy-42&quot;</span><span class="op">:</span>', $just['html']);
        $t->contains('<span class="op">@</span><span class="fu">just</span> <span class="va">review</span> <span class="va">{{source_id}}</span>', $just['html']);
        $t->contains('<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="op">--</span><span class="ot">meta_key</span><span class="op">=</span><span class="va">source_id</span> <span class="op">--</span><span class="ot">meta_value</span><span class="op">=</span><span class="va">{{source_id}}</span>', $just['html']);
        $t->contains('<span class="kw">if</span> <span class="op">[</span> <span class="st">&quot;{{dry_run}}&quot;</span> <span class="op">=</span> <span class="st">&quot;true&quot;</span> <span class="op">];</span> <span class="kw">then</span> <span class="fu">echo</span> <span class="st">&quot;dry run&quot;</span>', $just['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $justWordpressBlock);
        $t->contains('<span class="re">publish source_id dry_run=&quot;true&quot;</span><span class="op">:</span>', $justWordpressBlock);
        $t->same('just', $directJust['language']);
        $t->same('justfile', $directJust['requestedLanguage']);
        $t->contains('<span class="re">default source_id=&quot;legacy-42&quot;</span><span class="op">:</span> <span class="op">@</span><span class="fu">just</span> <span class="va">review</span> <span class="va">{{source_id}}</span>', $directJust['html']);
    },
    'highlights protobuf review schemas with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[79] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Protobuf review schema code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directProto = $highlighter->highlight(
            'message ImportReview { optional string title = 1 [default = "Untitled"]; }',
            'protocol-buffer'
        );

        $t->same('proto', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('proto'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('protobuf'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('protocol-buffer'));
        $t->same('protobuf', SyntaxHighlighter::normalizeLanguage('protocol-buffers'));
        $t->same('protobuf', $highlighted['language']);
        $t->same('proto', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1240, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource proto numberLines"><code class="sourceCode protobuf" style="counter-reset: source-line 1239;">', $highlighted['html']);
        $t->contains('<span id="protobuf-review-1240"><a href="#protobuf-review-1240"></a><span class="co">// Protobuf WordPress import review schema</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">syntax</span> <span class="op">=</span> <span class="st">&quot;proto3&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="va">wordpress</span><span class="op">.</span><span class="va">import</span><span class="op">.</span><span class="va">v1</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="st">&quot;google/protobuf/timestamp.proto&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">option</span> <span class="ot">php_namespace</span> <span class="op">=</span> <span class="st">&quot;WordPress\\\\Import\\\\Review&quot;</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">message</span> <span class="dt">ReviewPacket</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">reserved</span> <span class="dv">12</span> <span class="kw">to</span> <span class="dv">15</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="dt">string</span> <span class="ot">source_id</span> <span class="op">=</span> <span class="dv">1</span> <span class="op">[</span><span class="ot">json_name</span> <span class="op">=</span> <span class="st">&quot;sourceId&quot;</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="kw">optional</span> <span class="dt">string</span> <span class="ot">title</span> <span class="op">=</span> <span class="dv">2</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">repeated</span> <span class="dt">Block</span> <span class="ot">blocks</span> <span class="op">=</span> <span class="dv">3</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="dt">map</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">string</span><span class="op">&gt;</span> <span class="ot">metadata</span> <span class="op">=</span> <span class="dv">4</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">oneof</span> <span class="va">publish_target</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">bool</span> <span class="ot">dry_run</span> <span class="op">=</span> <span class="dv">6</span> <span class="op">[</span><span class="kw">default</span> <span class="op">=</span> <span class="cn">true</span><span class="op">];</span>', $highlighted['html']);
        $t->contains('<span class="dt">bytes</span> <span class="ot">raw_html</span> <span class="op">=</span> <span class="dv">2</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">service</span> <span class="dt">ImportReview</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">rpc</span> <span class="fu">Queue</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">)</span> <span class="kw">returns</span> <span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">service</span> <span class="dt">ImportReview</span>', $wordpressBlock);
        $t->same('protobuf', $directProto['language']);
        $t->same('protocol-buffer', $directProto['requestedLanguage']);
        $t->contains('<span class="kw">message</span> <span class="dt">ImportReview</span> <span class="op">{</span> <span class="kw">optional</span> <span class="dt">string</span> <span class="ot">title</span> <span class="op">=</span> <span class="dv">1</span>', $directProto['html']);
        $t->contains('<span class="op">[</span><span class="kw">default</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span><span class="op">];</span>', $directProto['html']);
    },
    'highlights tcl import review scripts with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[80] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Tcl import review script code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'breezedark');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'breezedark');
        $directTcl = $highlighter->highlight(
            'if {$title ne ""} { puts [string trim $title] }',
            'tclsh'
        );

        $t->same('tcl', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('tcl'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('tclsh'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('Tcl/Tk'));
        $t->same('tcl', SyntaxHighlighter::normalizeLanguage('expect'));
        $t->same('tcl', $highlighted['language']);
        $t->same('tcl', $highlighted['requestedLanguage']);
        $t->same('breezedark', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1260, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource tcl numberLines"><code class="sourceCode tcl" style="counter-reset: source-line 1259;">', $highlighted['html']);
        $t->contains('<span id="tcl-review-1260"><a href="#tcl-review-1260"></a><span class="co"># Tcl WordPress import review script</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">package</span> <span class="kw">require</span> <span class="va">json</span>', $highlighted['html']);
        $t->contains('<span class="kw">proc</span> <span class="fu">normalize_title</span> <span class="op">{</span><span class="va">packet</span><span class="op">}</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="va">title</span> <span class="op">[</span><span class="kw">dict</span> <span class="va">get</span> <span class="va">$packet</span> <span class="va">title</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">{</span><span class="va">$title</span> <span class="op">eq</span> <span class="st">&quot;&quot;</span><span class="op">}</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="op">[</span><span class="fu">string</span> <span class="va">trim</span> <span class="va">$title</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="va">source_id</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<span class="kw">set</span> <span class="va">packet</span> <span class="op">[</span><span class="kw">dict</span> <span class="va">create</span> <span class="va">title</span> <span class="st">&quot; Legacy &quot;</span> <span class="va">source_id</span> <span class="va">$source_id</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="fu">exec</span> <span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span> <span class="fu">update</span> <span class="va">$source_id</span> <span class="va">import_title</span> <span class="va">$title</span>', $highlighted['html']);
        $t->contains('<span class="fu">puts</span> <span class="op">[</span><span class="va">json::write</span> <span class="va">object</span> <span class="va">title</span> <span class="va">$title</span> <span class="va">source_id</span> <span class="va">$source_id</span> <span class="va">dry_run</span> <span class="cn">true</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="breezedark">', $wordpressBlock);
        $t->contains('<span class="fu">exec</span> <span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span>', $wordpressBlock);
        $t->same('tcl', $directTcl['language']);
        $t->same('tclsh', $directTcl['requestedLanguage']);
        $t->contains('<span class="kw">if</span> <span class="op">{</span><span class="va">$title</span> <span class="op">ne</span> <span class="st">&quot;&quot;</span><span class="op">}</span>', $directTcl['html']);
        $t->contains('<span class="fu">puts</span> <span class="op">[</span><span class="fu">string</span> <span class="va">trim</span> <span class="va">$title</span><span class="op">]</span>', $directTcl['html']);
    },
    'highlights fortran review modules with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[82] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Fortran review module code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directFortran = $highlighter->highlight(
            'subroutine queue(packet); call normalize_title(packet); end subroutine queue',
            'fortran-free'
        );

        $t->same('f90', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('f90'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('f77'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('fortran-free'));
        $t->same('fortran', SyntaxHighlighter::normalizeLanguage('language-fortran-fixed'));
        $t->same('fortran', $highlighted['language']);
        $t->same('f90', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1300, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource f90 numberLines"><code class="sourceCode fortran" style="counter-reset: source-line 1299;">', $highlighted['html']);
        $t->contains('<span id="fortran-review-1300"><a href="#fortran-review-1300"></a><span class="co">! Fortran WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="va">wp_import_review</span>', $highlighted['html']);
        $t->contains('<span class="kw">implicit</span> <span class="kw">none</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="op">::</span> <span class="va">review_packet</span>', $highlighted['html']);
        $t->contains('<span class="dt">integer</span> <span class="op">::</span> <span class="va">source_id</span>', $highlighted['html']);
        $t->contains('<span class="dt">character</span><span class="op">(</span><span class="ot">len</span><span class="op">=:),</span> <span class="ot">allocatable</span> <span class="op">::</span> <span class="va">title</span>', $highlighted['html']);
        $t->contains('<span class="kw">pure</span> <span class="kw">function</span> <span class="fu">normalized_title</span><span class="op">(</span><span class="va">packet</span><span class="op">)</span> <span class="kw">result</span><span class="op">(</span><span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span><span class="op">(</span><span class="va">review_packet</span><span class="op">),</span> <span class="ot">intent</span><span class="op">(</span><span class="kw">in</span><span class="op">)</span> <span class="op">::</span> <span class="va">packet</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span> <span class="op">=</span> <span class="fu">trim</span><span class="op">(</span><span class="va">packet</span><span class="op">%</span><span class="va">title</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">len_trim</span><span class="op">(</span><span class="va">title</span><span class="op">)</span> <span class="op">==</span> <span class="dv">0</span><span class="op">)</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="fu">write</span><span class="op">(</span><span class="va">title</span><span class="op">,</span> <span class="st">&#039;(A,I0)&#039;</span><span class="op">)</span> <span class="st">&#039;Import &#039;</span><span class="op">,</span> <span class="va">packet</span><span class="op">%</span><span class="va">source_id</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="kw">end</span> <span class="kw">module</span> <span class="va">wp_import_review</span>', $wordpressBlock);
        $t->same('fortran', $directFortran['language']);
        $t->same('fortran-free', $directFortran['requestedLanguage']);
        $t->contains('<span class="kw">subroutine</span> <span class="fu">queue</span><span class="op">(</span><span class="va">packet</span><span class="op">);</span> <span class="kw">call</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">packet</span><span class="op">);</span> <span class="kw">end</span> <span class="kw">subroutine</span> <span class="va">queue</span>', $directFortran['html']);
    },
    'highlights d review modules with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[83] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a D review module code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directD = $highlighter->highlight(
            '@safe string queue(ReviewPacket packet) { return format!"Import %s"(packet.sourceId); }',
            'dlang'
        );

        $t->same('d', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('d'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('dlang'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('d-source'));
        $t->same('d', SyntaxHighlighter::normalizeLanguage('language-d-language'));
        $t->same('d', $highlighted['language']);
        $t->same('d', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1320, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource d numberLines"><code class="sourceCode d" style="counter-reset: source-line 1319;">', $highlighted['html']);
        $t->contains('<span id="d-review-1320"><a href="#d-review-1320"></a><span class="co">// D WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="va">wp</span><span class="op">.</span><span class="va">review</span><span class="op">.</span><span class="va">packet</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">std</span><span class="op">.</span><span class="va">algorithm</span> <span class="op">:</span> <span class="va">strip</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="ot">@safe</span> <span class="kw">pure</span> <span class="dt">string</span> <span class="fu">normalizedTitle</span><span class="op">(</span><span class="dt">ReviewPacket</span> <span class="va">packet</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">immutable</span> <span class="va">title</span> <span class="op">=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="va">strip</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="va">title</span><span class="op">.</span><span class="va">length</span> <span class="op">==</span> <span class="dv">0</span><span class="op">)</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">format</span><span class="op">!</span><span class="st">&quot;Import %s&quot;</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">sourceId</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">struct</span> <span class="dt">ReviewPacket</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">ulong</span> <span class="va">sourceId</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">struct</span> <span class="dt">ReviewPacket</span>', $wordpressBlock);
        $t->same('d', $directD['language']);
        $t->same('dlang', $directD['requestedLanguage']);
        $t->contains('<span class="ot">@safe</span> <span class="dt">string</span> <span class="fu">queue</span><span class="op">(</span><span class="dt">ReviewPacket</span> <span class="va">packet</span><span class="op">)</span>', $directD['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">format</span><span class="op">!</span><span class="st">&quot;Import %s&quot;</span><span class="op">(</span><span class="va">packet</span><span class="op">.</span><span class="va">sourceId</span><span class="op">);</span>', $directD['html']);
    },
    'highlights common lisp review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[84] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Common Lisp review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directCommonLisp = $highlighter->highlight(
            '(loop for block in blocks collect (normalized-title block))',
            'cl'
        );

        $t->same('common-lisp', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('common-lisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('commonlisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('lisp'));
        $t->same('commonlisp', SyntaxHighlighter::normalizeLanguage('language-cl'));
        $t->same('commonlisp', $highlighted['language']);
        $t->same('common-lisp', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1340, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource common-lisp numberLines"><code class="sourceCode commonlisp" style="counter-reset: source-line 1339;">', $highlighted['html']);
        $t->contains('<span id="common-lisp-review-1340"><a href="#common-lisp-review-1340"></a><span class="co">;;;; Common Lisp WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">defpackage</span> <span class="cn">#:wp-import.review</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="ot">:use</span> <span class="cn">#:cl</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">in-package</span> <span class="cn">#:wp-import.review</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">defstruct</span> <span class="va">review-packet</span>', $highlighted['html']);
        $t->contains('<span class="op">(</span><span class="kw">defun</span> <span class="va">normalized-title</span> <span class="op">(</span><span class="va">packet</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">let*</span> <span class="op">((</span><span class="va">title</span> <span class="op">(</span><span class="fu">string-trim</span> <span class="st">&quot; &quot;</span> <span class="op">(</span><span class="fu">review-packet-title</span> <span class="va">packet</span><span class="op">))))</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="op">(</span><span class="fu">string=</span> <span class="va">title</span> <span class="st">&quot;&quot;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">format</span> <span class="cn">nil</span> <span class="st">&quot;Import ~A&quot;</span>', $highlighted['html']);
        $t->contains('<span class="dt">list</span> <span class="ot">:source-id</span> <span class="op">(</span><span class="fu">review-packet-source-id</span> <span class="va">packet</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="ot">:blocks</span> <span class="op">(</span><span class="fu">remove-if-not</span> <span class="op">#&#039;</span><span class="fu">identity</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="kw">defun</span> <span class="va">queue-review-packet</span>', $wordpressBlock);
        $t->same('commonlisp', $directCommonLisp['language']);
        $t->same('cl', $directCommonLisp['requestedLanguage']);
        $t->contains('<span class="kw">loop</span> <span class="kw">for</span> <span class="kw">block</span> <span class="kw">in</span> <span class="va">blocks</span> <span class="kw">collect</span>', $directCommonLisp['html']);
    },
    'highlights pascal and delphi review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[85] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Pascal review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directDelphi = $highlighter->highlight(
            'function Queue(const Packet: TReviewPacket): string; begin WriteLn(Packet.SourceId); end;',
            'delphi'
        );

        $t->same('pascal', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('pas'));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('pascal'));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('delphi'));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('object-pascal'));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('objectpascal'));
        $t->same('pascal', SyntaxHighlighter::normalizeLanguage('language-pp'));
        $t->same('pascal', $highlighted['language']);
        $t->same('pascal', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1360, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource pascal numberLines"><code class="sourceCode pascal" style="counter-reset: source-line 1359;">', $highlighted['html']);
        $t->contains('<span id="pascal-review-1360"><a href="#pascal-review-1360"></a><span class="co">// Pascal WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">program</span> <span class="va">WPImportReview</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="pp">{$mode objfpc}{$H+}</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span>', $highlighted['html']);
        $t->contains('<span class="dt">TReviewPacket</span> <span class="op">=</span> <span class="kw">record</span>', $highlighted['html']);
        $t->contains('<span class="va">SourceId</span><span class="op">:</span> <span class="dt">Integer</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="va">Title</span><span class="op">:</span> <span class="dt">string</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">function</span> <span class="fu">NormalizedTitle</span><span class="op">(</span><span class="kw">const</span> <span class="va">Packet</span><span class="op">:</span> <span class="dt">TReviewPacket</span><span class="op">):</span> <span class="dt">string</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">Result</span> <span class="op">:=</span> <span class="fu">Trim</span><span class="op">(</span><span class="va">Packet</span><span class="op">.</span><span class="va">Title</span><span class="op">);</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="kw">Result</span> <span class="op">=</span> <span class="st">&#039;&#039;</span> <span class="kw">then</span>', $highlighted['html']);
        $t->contains('<span class="kw">Result</span> <span class="op">:=</span> <span class="fu">Format</span><span class="op">(</span><span class="st">&#039;Import %d&#039;</span><span class="op">,</span> <span class="op">[</span><span class="va">Packet</span><span class="op">.</span><span class="va">SourceId</span><span class="op">]);</span>', $highlighted['html']);
        $t->contains('<span class="va">Packet</span><span class="op">.</span><span class="va">SourceId</span> <span class="op">:=</span> <span class="dv">42</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="fu">WriteLn</span><span class="op">(</span><span class="fu">NormalizedTitle</span><span class="op">(</span><span class="va">Packet</span><span class="op">));</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="fu">Format</span><span class="op">(</span><span class="st">&#039;Import %d&#039;</span>', $wordpressBlock);
        $t->same('pascal', $directDelphi['language']);
        $t->same('delphi', $directDelphi['requestedLanguage']);
        $t->contains('<span class="kw">function</span> <span class="fu">Queue</span><span class="op">(</span><span class="kw">const</span> <span class="va">Packet</span><span class="op">:</span> <span class="dt">TReviewPacket</span><span class="op">):</span> <span class="dt">string</span><span class="op">;</span>', $directDelphi['html']);
        $t->contains('<span class="fu">WriteLn</span><span class="op">(</span><span class="va">Packet</span><span class="op">.</span><span class="va">SourceId</span><span class="op">);</span>', $directDelphi['html']);
    },
    'highlights groovy gradle and jenkins review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[86] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Groovy/Gradle review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directJenkinsfile = $highlighter->highlight(
            'node { stage("Review") { sh "wp post list --post_type=post" } }',
            'Jenkinsfile'
        );

        $t->same('gradle', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('groovy'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('gradle'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('gvy'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('jenkinsfile'));
        $t->same('groovy', SyntaxHighlighter::normalizeLanguage('language-groovy-script'));
        $t->same('groovy', $highlighted['language']);
        $t->same('gradle', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1380, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource gradle numberLines"><code class="sourceCode groovy" style="counter-reset: source-line 1379;">', $highlighted['html']);
        $t->contains('<span id="groovy-review-1380"><a href="#groovy-review-1380"></a><span class="co">// Groovy Gradle/Jenkins WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">groovy</span><span class="op">.</span><span class="va">json</span><span class="op">.</span><span class="dt">JsonSlurper</span>', $highlighted['html']);
        $t->contains('<span class="ot">@Grab</span><span class="op">(</span><span class="st">&#039;org.codehaus.groovy:groovy-json:3.0.21&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">class</span> <span class="dt">ReviewPacket</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="dt">Long</span> <span class="va">sourceId</span>', $highlighted['html']);
        $t->contains('<span class="dt">List</span><span class="op">&lt;</span><span class="dt">String</span><span class="op">&gt;</span> <span class="va">blocks</span> <span class="op">=</span> <span class="op">[]</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="va">packet</span> <span class="op">=</span> <span class="kw">new</span> <span class="dt">JsonSlurper</span><span class="op">().</span><span class="fu">parseText</span>', $highlighted['html']);
        $t->contains('<span class="kw">as</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="va">normalizedTitle</span> <span class="op">=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trim</span><span class="op">()</span> <span class="op">?:</span> <span class="st">&quot;Import ${packet.sourceId}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">pipeline</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="fu">stage</span><span class="op">(</span><span class="st">&#039;WordPress review&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="fu">sh</span> <span class="st">&quot;wp post meta update ${packet.sourceId} import_title &#039;${normalizedTitle}&#039;&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">writeJSON</span> <span class="ot">file</span><span class="op">:</span> <span class="st">&#039;review.json&#039;</span><span class="op">,</span> <span class="ot">json</span><span class="op">:</span> <span class="op">[</span><span class="ot">title</span><span class="op">:</span> <span class="va">normalizedTitle</span><span class="op">,</span> <span class="ot">dryRun</span><span class="op">:</span> <span class="cn">true</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="fu">writeJSON</span> <span class="ot">file</span><span class="op">:</span> <span class="st">&#039;review.json&#039;</span>', $wordpressBlock);
        $t->same('groovy', $directJenkinsfile['language']);
        $t->same('Jenkinsfile', $directJenkinsfile['requestedLanguage']);
        $t->contains('<span class="fu">node</span> <span class="op">{</span> <span class="fu">stage</span><span class="op">(</span><span class="st">&quot;Review&quot;</span><span class="op">)</span>', $directJenkinsfile['html']);
        $t->contains('<span class="fu">sh</span> <span class="st">&quot;wp post list --post_type=post&quot;</span>', $directJenkinsfile['html']);
    },
    'highlights crystal review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[87] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Crystal review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directCrystal = $highlighter->highlight('@[Link("wp-review")] struct Packet; property title : String?; end', 'cr');

        $t->same('crystal', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('cr'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('crystal-lang'));
        $t->same('crystal', SyntaxHighlighter::normalizeLanguage('crystal-source'));
        $t->same('crystal', $highlighted['language']);
        $t->same('crystal', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1400, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource crystal numberLines"><code class="sourceCode crystal" style="counter-reset: source-line 1399;">', $highlighted['html']);
        $t->contains('<span id="crystal-review-1400"><a href="#crystal-review-1400"></a><span class="co"># Crystal WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">require</span> <span class="st">&quot;json&quot;</span>', $highlighted['html']);
        $t->contains('<span class="ot">@[Link(&quot;wp-review&quot;)]</span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">WPImport</span>', $highlighted['html']);
        $t->contains('<span class="kw">struct</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">include</span> <span class="dt">JSON</span><span class="op">::</span><span class="dt">Serializable</span>', $highlighted['html']);
        $t->contains('<span class="kw">property</span> <span class="va">source_id</span> <span class="op">:</span> <span class="dt">Int32</span>', $highlighted['html']);
        $t->contains('<span class="kw">property</span> <span class="va">title</span> <span class="op">:</span> <span class="dt">String</span><span class="op">?</span>', $highlighted['html']);
        $t->contains('<span class="kw">def</span> <span class="kw">self</span><span class="op">.</span><span class="fu">normalized_title</span><span class="op">(</span><span class="va">packet</span> <span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">:</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">try</span><span class="op">(&amp;.</span><span class="fu">strip</span><span class="op">)</span> <span class="op">||</span> <span class="st">&quot;Import #{packet.source_id}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">if</span> <span class="va">title</span><span class="op">.</span><span class="fu">empty?</span>', $highlighted['html']);
        $t->contains('<span class="kw">rescue</span> <span class="va">ex</span> <span class="op">:</span> <span class="dt">JSON</span><span class="op">::</span><span class="dt">ParseException</span>', $highlighted['html']);
        $t->contains('<span class="cn">STDERR</span><span class="op">.</span><span class="fu">puts</span> <span class="st">&quot;invalid review packet: #{ex.message}&quot;</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="dt">JSON</span><span class="op">::</span><span class="dt">Serializable</span>', $wordpressBlock);
        $t->same('crystal', $directCrystal['language']);
        $t->same('cr', $directCrystal['requestedLanguage']);
        $t->contains('<span class="ot">@[Link(&quot;wp-review&quot;)]</span> <span class="kw">struct</span> <span class="dt">Packet</span>', $directCrystal['html']);
        $t->contains('<span class="kw">property</span> <span class="va">title</span> <span class="op">:</span> <span class="dt">String</span><span class="op">?;</span>', $directCrystal['html']);
    },
    'highlights nim review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[89] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Nim review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'monochrome');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'monochrome');
        $directNim = $highlighter->highlight(
            'proc queue*(title: Option[string]): string = title.get("Untitled")',
            'nimrod'
        );

        $t->same('nim', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nim'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nimrod'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nims'));
        $t->same('nim', SyntaxHighlighter::normalizeLanguage('nimscript'));
        $t->same('nim', $highlighted['language']);
        $t->same('nim', $highlighted['requestedLanguage']);
        $t->same('monochrome', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1440, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource nim numberLines"><code class="sourceCode nim" style="counter-reset: source-line 1439;">', $highlighted['html']);
        $t->contains('<span id="nim-review-1440"><a href="#nim-review-1440"></a><span class="co"># Nim WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">std</span><span class="op">/[</span><span class="va">json</span><span class="op">,</span> <span class="va">options</span><span class="op">,</span> <span class="va">strutils</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="dt">ReviewPacket</span><span class="op">*</span> <span class="op">=</span> <span class="kw">object</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span><span class="op">*:</span> <span class="dt">Option</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span>', $highlighted['html']);
        $t->contains('<span class="kw">proc</span> <span class="fu">normalizeTitle*</span><span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">):</span> <span class="dt">string</span> <span class="ot">{.raises: [ValueError].}</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">title</span> <span class="op">=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">.</span><span class="fu">get</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">).</span><span class="fu">strip</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="st">&quot;Import &quot;</span> <span class="op">&amp;</span> <span class="op">$</span><span class="va">packet</span><span class="op">.</span><span class="va">sourceId</span>', $highlighted['html']);
        $t->contains('<span class="kw">proc</span> <span class="fu">queueReview*</span><span class="op">(</span><span class="va">raw</span><span class="op">:</span> <span class="dt">string</span><span class="op">):</span> <span class="dt">JsonNode</span>', $highlighted['html']);
        $t->contains('<span class="va">raw</span><span class="op">.</span><span class="fu">parseJson</span><span class="op">().</span><span class="fu">to</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="op">%*{</span>', $highlighted['html']);
        $t->contains('<span class="st">&quot;dryRun&quot;</span><span class="op">:</span> <span class="cn">true</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="monochrome">', $wordpressBlock);
        $t->contains('<span class="fu">normalizeTitle</span><span class="op">(</span><span class="va">packet</span><span class="op">),</span>', $wordpressBlock);
        $t->same('nim', $directNim['language']);
        $t->same('nimrod', $directNim['requestedLanguage']);
        $t->contains('<span class="kw">proc</span> <span class="fu">queue*</span><span class="op">(</span><span class="va">title</span><span class="op">:</span> <span class="dt">Option</span><span class="op">[</span><span class="dt">string</span><span class="op">]):</span> <span class="dt">string</span>', $directNim['html']);
        $t->contains('<span class="va">title</span><span class="op">.</span><span class="fu">get</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)</span>', $directNim['html']);
    },
    'highlights v review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[90] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a V review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'haddock');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'haddock');
        $directV = $highlighter->highlight("module review\npub fn main() !map[string]string { return {'dryRun': 'true'} }", 'vlang');

        $t->same('v', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('v', $highlighted['language']);
        $t->same('v', $highlighted['requestedLanguage']);
        $t->same('haddock', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1460, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource v numberLines"><code class="sourceCode v" style="counter-reset: source-line 1459;">', $highlighted['html']);
        $t->contains('<span id="v-review-1460"><a href="#v-review-1460"></a><span class="co">// V WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="va">review</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="va">json</span>', $highlighted['html']);
        $t->contains('<span class="ot">[json: source_id]</span>', $highlighted['html']);
        $t->contains('<span class="kw">struct</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="va">title</span> <span class="op">?</span><span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="va">blocks</span> <span class="op">[]</span><span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="kw">pub</span> <span class="kw">fn</span> <span class="fu">normalize_title</span><span class="op">(</span><span class="va">packet</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">!</span><span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="kw">mut</span> <span class="va">title</span> <span class="op">:=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span> <span class="kw">or</span> <span class="op">{</span> <span class="st">&#039;Untitled&#039;</span> <span class="op">}</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="fu">error</span><span class="op">(</span><span class="st">&#039;missing title for ${packet.source_id}&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">$if</span> <span class="va">debug</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="fu">println</span><span class="op">(</span><span class="st">&#039;review ${packet.source_id}&#039;</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="dt">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span> <span class="op">:=</span> <span class="va">json</span><span class="op">.</span><span class="fu">decode</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">,</span> <span class="va">raw</span><span class="op">)!</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="op">{</span><span class="st">&#039;title&#039;</span><span class="op">:</span> <span class="va">title</span><span class="op">,</span> <span class="st">&#039;dryRun&#039;</span><span class="op">:</span> <span class="st">&#039;true&#039;</span><span class="op">}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="haddock">', $wordpressBlock);
        $t->contains('<span class="kw">$if</span> <span class="va">debug</span>', $wordpressBlock);
        $t->same('v', $directV['language']);
        $t->same('vlang', $directV['requestedLanguage']);
        $t->contains('<span class="kw">pub</span> <span class="kw">fn</span> <span class="fu">main</span><span class="op">()</span> <span class="op">!</span><span class="dt">map</span><span class="op">[</span><span class="dt">string</span><span class="op">]</span><span class="dt">string</span>', $directV['html']);
        $t->contains('<span class="kw">return</span> <span class="op">{</span><span class="st">&#039;dryRun&#039;</span><span class="op">:</span> <span class="st">&#039;true&#039;</span><span class="op">}</span>', $directV['html']);
    },
    'highlights idris review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[91] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Idris review packet code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'zenburn');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'zenburn');
        $directIdris = $highlighter->highlight(
            'normalizeTitle : ReviewPacket -> String' . "\n" . 'normalizeTitle packet = Right packet',
            'idris2'
        );

        $t->same('idris', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('idris', $highlighted['language']);
        $t->same('idris', $highlighted['requestedLanguage']);
        $t->same('zenburn', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1480, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource idris numberLines"><code class="sourceCode idris" style="counter-reset: source-line 1479;">', $highlighted['html']);
        $t->contains('<span id="idris-review-1480"><a href="#idris-review-1480"></a><span class="co">-- Idris WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">WP.Import.Review</span>', $highlighted['html']);
        $t->contains('<span class="pp">%default</span> <span class="kw">total</span>', $highlighted['html']);
        $t->contains('<span class="pp">%language</span> <span class="dt">ElabReflection</span>', $highlighted['html']);
        $t->contains('<span class="kw">record</span> <span class="dt">ReviewPacket</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">constructor</span> <span class="dt">MkReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="fu">sourceId</span> <span class="op">:</span> <span class="dt">Nat</span>', $highlighted['html']);
        $t->contains('<span class="fu">title</span> <span class="op">:</span> <span class="dt">Maybe</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="kw">case</span> <span class="fu">title</span> <span class="va">packet</span> <span class="kw">of</span>', $highlighted['html']);
        $t->contains('<span class="cn">Just</span> <span class="va">raw</span> <span class="op">=&gt;</span> <span class="kw">if</span> <span class="fu">length</span> <span class="va">raw</span> <span class="op">==</span> <span class="dv">0</span>', $highlighted['html']);
        $t->contains('<span class="cn">Nothing</span> <span class="op">=&gt;</span> <span class="st">&quot;Import &quot;</span> <span class="op">++</span> <span class="fu">show</span>', $highlighted['html']);
        $t->contains('<span class="fu">queueReview</span> <span class="op">:</span> <span class="dt">String</span> <span class="op">-&gt;</span> <span class="dt">Either</span> <span class="dt">String</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="va">packet</span> <span class="op">=</span> <span class="dt">MkReviewPacket</span> <span class="dv">42</span> <span class="op">(</span><span class="cn">Just</span> <span class="va">raw</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="zenburn">', $wordpressBlock);
        $t->contains('<span class="cn">Right</span> <span class="va">packet</span>', $wordpressBlock);
        $t->same('idris', $directIdris['language']);
        $t->same('idris2', $directIdris['requestedLanguage']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $directIdris['html']);
        $t->contains('<span class="cn">Right</span> <span class="va">packet</span>', $directIdris['html']);
    },
    'highlights coq proof review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[92] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a Coq proof review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'tango');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'tango');
        $directCoq = $highlighter->highlight(
            "Theorem review_title : forall title : string, title = title.\nProof. intros title; reflexivity. Qed.\n",
            'rocq'
        );

        $t->same('coq', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('coq'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('gallina'));
        $t->same('coq', SyntaxHighlighter::normalizeLanguage('rocq-prover'));
        $t->same('coq', $highlighted['language']);
        $t->same('coq', $highlighted['requestedLanguage']);
        $t->same('tango', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1510, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource coq numberLines"><code class="sourceCode coq" style="counter-reset: source-line 1509;">', $highlighted['html']);
        $t->contains('<span id="coq-review-1510"><a href="#coq-review-1510"></a><span class="co">(* Coq WordPress import proof review *)</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">From</span> <span class="dt">Coq</span> <span class="kw">Require</span> <span class="kw">Import</span> <span class="dt">Strings.String</span> <span class="dt">Lists.List</span><span class="op">.</span>', $highlighted['html']);
        $t->contains('<span class="kw">Record</span> <span class="fu">review_packet</span> <span class="op">:=</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="ot">source_id</span> <span class="op">:</span> <span class="dt">nat</span><span class="op">;</span>', $highlighted['html']);
        $t->contains('<span class="kw">Definition</span> <span class="fu">normalize_title</span> <span class="op">(</span><span class="ot">packet</span> <span class="op">:</span> <span class="va">review_packet</span><span class="op">)</span> <span class="op">:</span> <span class="dt">string</span> <span class="op">:=</span>', $highlighted['html']);
        $t->contains('<span class="kw">match</span> <span class="dt">String</span><span class="op">.</span><span class="fu">length</span> <span class="op">(</span><span class="va">title</span> <span class="va">packet</span><span class="op">)</span> <span class="kw">with</span>', $highlighted['html']);
        $t->contains('<span class="op">|</span> <span class="cn">O</span> <span class="op">=&gt;</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">Theorem</span> <span class="ot">normalize_title_idempotent</span> <span class="op">:</span>', $highlighted['html']);
        $t->contains('<span class="fu">intros</span> <span class="va">packet</span><span class="op">.</span>', $highlighted['html']);
        $t->contains('<span class="fu">reflexivity</span><span class="op">.</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="tango">', $wordpressBlock);
        $t->contains('<span class="kw">Qed</span><span class="op">.</span>', $wordpressBlock);
        $t->same('coq', $directCoq['language']);
        $t->same('rocq', $directCoq['requestedLanguage']);
        $t->contains('<span class="kw">Theorem</span> <span class="ot">review_title</span> <span class="op">:</span> <span class="kw">forall</span> <span class="ot">title</span> <span class="op">:</span> <span class="dt">string</span>', $directCoq['html']);
        $t->contains('<span class="kw">Proof</span><span class="op">.</span> <span class="fu">intros</span> <span class="va">title</span><span class="op">;</span> <span class="fu">reflexivity</span><span class="op">.</span> <span class="kw">Qed</span><span class="op">.</span>', $directCoq['html']);
    },
    'highlights agda proof review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[93] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an Agda proof review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directAgda = $highlighter->highlight(
            'module Review where' . "\n" . 'postulate normalizeTitle : ReviewPacket -> String',
            'lagda'
        );

        $t->same('agda', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('agda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('agda2'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('lagda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('literate-agda'));
        $t->same('agda', SyntaxHighlighter::normalizeLanguage('language-agda-source'));
        $t->same('agda', $highlighted['language']);
        $t->same('agda', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1535, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource agda numberLines"><code class="sourceCode agda" style="counter-reset: source-line 1534;">', $highlighted['html']);
        $t->contains('<span id="agda-review-1535"><a href="#agda-review-1535"></a><span class="co">-- Agda WordPress import proof review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">WP.Import.Review</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="pp">{-# OPTIONS --safe #-}</span>', $highlighted['html']);
        $t->contains('<span class="kw">open</span> <span class="kw">import</span> <span class="dt">Agda.Builtin.Nat</span> <span class="kw">using</span> <span class="op">(</span><span class="dt">Nat</span><span class="op">;</span> <span class="cn">zero</span><span class="op">;</span> <span class="cn">suc</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">record</span> <span class="dt">ReviewPacket</span> <span class="op">:</span> <span class="dt">Set</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">constructor</span> <span class="fu">mkReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="kw">field</span>', $highlighted['html']);
        $t->contains('<span class="fu">sourceId</span> <span class="op">:</span> <span class="dt">Nat</span>', $highlighted['html']);
        $t->contains('<span class="fu">title</span> <span class="op">:</span> <span class="dt">Maybe</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="va">packet</span> <span class="kw">with</span> <span class="dt">ReviewPacket</span><span class="op">.</span><span class="fu">title</span> <span class="va">packet</span>', $highlighted['html']);
        $t->contains('<span class="op">...</span> <span class="op">|</span> <span class="cn">just</span> <span class="va">raw</span> <span class="op">=</span> <span class="va">raw</span>', $highlighted['html']);
        $t->contains('<span class="op">...</span> <span class="op">|</span> <span class="cn">nothing</span> <span class="op">=</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">postulate</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalizeTitleIdempotent</span> <span class="op">:</span> <span class="op">(</span><span class="fu">packet</span> <span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="fu">normalizeTitle</span> <span class="va">packet</span> <span class="op">==</span> <span class="fu">normalizeTitle</span> <span class="va">packet</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="fu">normalizeTitleIdempotent</span>', $wordpressBlock);
        $t->same('agda', $directAgda['language']);
        $t->same('lagda', $directAgda['requestedLanguage']);
        $t->contains('<span class="kw">module</span> <span class="dt">Review</span> <span class="kw">where</span>', $directAgda['html']);
        $t->contains('<span class="kw">postulate</span> <span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $directAgda['html']);
    },
    'highlights purescript review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[94] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include a PureScript review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'espresso');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'espresso');
        $directPureScript = $highlighter->highlight(
            'normalizeTitle :: ReviewPacket -> String' . "\n" . 'normalizeTitle packet = fromMaybe "Untitled" packet.title',
            'purescript'
        );

        $t->same('purs', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('purescript', SyntaxHighlighter::normalizeLanguage('purescript'));
        $t->same('purescript', SyntaxHighlighter::normalizeLanguage('purs'));
        $t->same('purescript', SyntaxHighlighter::normalizeLanguage('language-purescript-source'));
        $t->same('purescript', $highlighted['language']);
        $t->same('purs', $highlighted['requestedLanguage']);
        $t->same('espresso', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1565, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource purs numberLines"><code class="sourceCode purescript" style="counter-reset: source-line 1564;">', $highlighted['html']);
        $t->contains('<span id="purescript-review-1565"><a href="#purescript-review-1565"></a><span class="co">-- PureScript WordPress import review</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">WP.Import.Review</span> <span class="kw">where</span>', $highlighted['html']);
        $t->contains('<span class="kw">import</span> <span class="dt">Effect</span> <span class="op">(</span><span class="dt">Effect</span><span class="op">)</span>', $highlighted['html']);
        $t->contains('<span class="kw">newtype</span> <span class="dt">ReviewPacket</span> <span class="op">=</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="fu">sourceId</span> <span class="op">::</span> <span class="dt">Int</span>', $highlighted['html']);
        $t->contains('<span class="fu">title</span> <span class="op">::</span> <span class="dt">Maybe</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="op">::</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $highlighted['html']);
        $t->contains('<span class="kw">case</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span> <span class="kw">of</span>', $highlighted['html']);
        $t->contains('<span class="cn">Just</span> <span class="va">raw</span> <span class="op">-&gt;</span> <span class="fu">raw</span>', $highlighted['html']);
        $t->contains('<span class="cn">Nothing</span> <span class="op">-&gt;</span> <span class="st">&quot;Untitled&quot;</span>', $highlighted['html']);
        $t->contains('<span class="fu">queueReview</span> <span class="op">::</span> <span class="dt">String</span> <span class="op">-&gt;</span> <span class="dt">Effect</span> <span class="dt">ReviewPacket</span>', $highlighted['html']);
        $t->contains('<span class="ot">sourceId</span><span class="op">:</span> <span class="dv">42</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="espresso">', $wordpressBlock);
        $t->contains('<span class="ot">blocks</span><span class="op">:</span> <span class="op">[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">]</span>', $wordpressBlock);
        $t->same('purescript', $directPureScript['language']);
        $t->same('purescript', $directPureScript['requestedLanguage']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="op">::</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>', $directPureScript['html']);
        $t->contains('<span class="fu">normalizeTitle</span> <span class="va">packet</span> <span class="op">=</span> <span class="fu">fromMaybe</span> <span class="st">&quot;Untitled&quot;</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span>', $directPureScript['html']);
    },
    'highlights fsharp review packets with pandoc aliases' => static function (TestRunner $t): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
        if (!is_string($fixture)) {
            throw new RuntimeException('Unable to read syntax highlight fixture');
        }

        $document = (new MarkdownReader())->read($fixture);
        $codeBlock = $document->children[95] ?? null;
        if (!$codeBlock instanceof AstNode || $codeBlock->type !== 'code_block') {
            throw new RuntimeException('Expected syntax highlight fixture to include an F# review code block');
        }

        $highlighter = new SyntaxHighlighter();
        $highlighted = $highlighter->highlightCodeBlock($codeBlock, 'kate');
        $wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'kate');
        $directFSharp = $highlighter->highlight(
            'let normalizeTitle (packet: ReviewPacket) = defaultArg packet.Title "Untitled"',
            'F#'
        );

        $t->same('fsx', SyntaxHighlighter::languageFromCodeBlock($codeBlock));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('F#'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('fsx'));
        $t->same('fsharp', SyntaxHighlighter::normalizeLanguage('language-fsharp-source'));
        $t->same('fsharp', $highlighted['language']);
        $t->same('fsx', $highlighted['requestedLanguage']);
        $t->same('kate', $highlighted['style']);
        $t->same([], $highlighted['diagnostics']);
        $t->same(1585, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource fsx numberLines"><code class="sourceCode fsharp" style="counter-reset: source-line 1584;">', $highlighted['html']);
        $t->contains('<span id="fsharp-review-1585"><a href="#fsharp-review-1585"></a><span class="co">// F# WordPress import review helper</span></span>', $highlighted['html']);
        $t->contains('<span class="kw">module</span> <span class="dt">WP</span><span class="op">.</span><span class="dt">Import</span><span class="op">.</span><span class="dt">Review</span>', $highlighted['html']);
        $t->contains('<span class="kw">open</span> <span class="dt">System</span><span class="op">.</span><span class="dt">Text</span><span class="op">.</span><span class="dt">Json</span>', $highlighted['html']);
        $t->contains('<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="ot">SourceId</span><span class="op">:</span> <span class="dt">int</span>', $highlighted['html']);
        $t->contains('<span class="ot">Title</span><span class="op">:</span> <span class="dt">string</span> <span class="dt">option</span>', $highlighted['html']);
        $t->contains('<span class="ot">[&lt;RequireQualifiedAccess&gt;]</span>', $highlighted['html']);
        $t->contains('<span class="op">|</span> <span class="dt">Publish</span> <span class="kw">of</span> <span class="va">slug</span><span class="op">:</span> <span class="dt">string</span>', $highlighted['html']);
        $t->contains('<span class="kw">let</span> <span class="fu">normalizeTitle</span> <span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span> <span class="op">=</span>', $highlighted['html']);
        $t->contains('<span class="kw">match</span> <span class="va">packet</span><span class="op">.</span><span class="dt">Title</span> <span class="kw">with</span>', $highlighted['html']);
        $t->contains('<span class="op">|</span> <span class="cn">Some</span> <span class="fu">title</span> <span class="kw">when</span> <span class="kw">not</span> <span class="op">(</span><span class="dt">String</span><span class="op">.</span><span class="dt">IsNullOrWhiteSpace</span> <span class="va">title</span><span class="op">)</span> <span class="op">-&gt;</span> <span class="va">title</span><span class="op">.</span><span class="dt">Trim</span><span class="op">()</span>', $highlighted['html']);
        $t->contains('<span class="op">|</span> <span class="va">_</span> <span class="op">-&gt;</span> <span class="st">$&quot;Import {packet.SourceId}&quot;</span>', $highlighted['html']);
        $t->contains('<span class="kw">async</span> <span class="op">{</span>', $highlighted['html']);
        $t->contains('<span class="va">packet</span><span class="op">.</span><span class="dt">Blocks</span> <span class="op">|&gt;</span> <span class="dt">List</span><span class="op">.</span><span class="fu">filter</span>', $highlighted['html']);
        $t->contains('<span class="kw">return</span> <span class="op">{|</span> <span class="va">title</span> <span class="op">=</span> <span class="fu">normalizeTitle</span> <span class="va">packet</span><span class="op">;</span> <span class="va">blockCount</span> <span class="op">=</span> <span class="va">blocks</span><span class="op">.</span><span class="dt">Length</span> <span class="op">|}</span>', $highlighted['html']);
        $t->contains('<style data-pandoc-highlight-style="kate">', $wordpressBlock);
        $t->contains('<span class="kw">let</span> <span class="fu">queueReview</span>', $wordpressBlock);
        $t->same('fsharp', $directFSharp['language']);
        $t->same('F#', $directFSharp['requestedLanguage']);
        $t->contains('<span class="kw">let</span> <span class="fu">normalizeTitle</span> <span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span>', $directFSharp['html']);
        $t->contains('<span class="fu">defaultArg</span> <span class="va">packet</span><span class="op">.</span><span class="dt">Title</span> <span class="st">&quot;Untitled&quot;</span>', $directFSharp['html']);
    },
    'parses pandoc json theme files for custom syntax highlight css' => static function (TestRunner $t): void {
        $themeJson = json_encode([
            'name' => 'Review Import',
            'text-color' => '#F8F8F2',
            'background-color' => '#101820',
            'line-number-color' => '#8F9AAE',
            'line-number-background-color' => '#202A35',
            'token-styles' => [
                'Normal' => ['text-color' => '#F8F8F2'],
                'KeywordTok' => ['text-color' => '#FFCC00', 'bold' => true],
                'StringTok' => ['text-color' => '#7BD88F'],
                'CommentTok' => ['text-color' => '#7F8C8D', 'italic' => true],
                'FunctionTok' => ['text-color' => '#80DFFF', 'underline' => true],
                'VariableTok' => ['text-color' => '#FF9F43'],
                'OperatorTok' => ['text-color' => '#FF6B6B'],
                'AttributeTok' => ['text-color' => '#C3E88D'],
                'AlertTok' => ['text-color' => '#FF5555', 'bold' => true],
                'FutureTok' => ['text-color' => '#ABCDEF'],
            ],
        ], JSON_THROW_ON_ERROR);

        $parsed = SyntaxHighlighter::parseThemeJson($themeJson);
        $css = SyntaxHighlighter::stylesheetFromThemeJson($themeJson);

        $t->same('review-import', $parsed['name']);
        $t->same('#101820', $parsed['colors']['background']);
        $t->same('#f8f8f2', $parsed['colors']['text']);
        $t->same('#ffcc00', $parsed['colors']['keyword']);
        $t->same('#ff5555', $parsed['colors']['warning']);
        $t->same(1, count($parsed['diagnostics']));
        $t->same('unsupported-theme-token', $parsed['diagnostics'][0]['code'] ?? null);
        $t->contains('.sourceCode { background: #101820; color: #f8f8f2; }', $css);
        $t->contains('.sourceCode .kw { color: #ffcc00; font-weight: 700; }', $css);
        $t->contains('.sourceCode .fu { color: #80dfff; text-decoration: underline; }', $css);
        $t->contains('.sourceCode .co { color: #7f8c8d; font-style: italic; }', $css);
        $t->contains('.sourceCode .al { color: #ff5555; font-weight: 700; }', $css);
        $t->contains('color: #8f9aae; background-color: #202a35;', $css);

        $highlighted = (new SyntaxHighlighter())->highlight(
            'echo esc_html($title); // review',
            'php',
            'pygments',
            [
                'themeJson' => $themeJson,
                'id' => 'custom-theme',
                'classes' => ['numberLines'],
                'attributes' => ['startFrom' => '10'],
            ]
        );

        $t->same('review-import', $highlighted['style']);
        $t->same('unsupported-theme-token', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same(10, $highlighted['lineNumbering']['start']);
        $t->contains('<pre class="sourceCode numberSource numberLines"><code class="sourceCode php" style="counter-reset: source-line 9;">', $highlighted['html']);
        $t->contains('<span id="custom-theme-10"><a href="#custom-theme-10"></a><span class="kw">echo</span> <span class="fu">esc_html</span>', $highlighted['html']);
        $t->contains('.sourceCode .va { color: #ff9f43; }', $highlighted['css']);

        $block = (new SyntaxHighlighter())->wordpressHtmlBlock(new AstNode('code_block', [
            'id' => 'custom-theme',
            'classes' => ['php', 'numberLines'],
            'attributes' => ['startFrom' => '10'],
            'text' => 'echo esc_html($title); // review',
        ]), 'pygments', ['themeJson' => $themeJson]);

        $t->contains('<style data-pandoc-highlight-style="review-import">', $block);
        $t->contains('.sourceCode .kw { color: #ffcc00; font-weight: 700; }', $block);
        $t->contains('<span id="custom-theme-10"><a href="#custom-theme-10"></a><span class="kw">echo</span>', $block);
    },
    'rejects invalid pandoc json theme payloads' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson("\xEF\xBB\xBF{}"));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"token-styles": [{"KeywordTok": {"text-color": "#ffffff"}}]}'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"text-color": "#12"}'));
        $t->throws(InvalidArgumentException::class, static fn (): array => SyntaxHighlighter::parseThemeJson('{"token-styles": {"KeywordTok": {"bold": "sometimes"}}}'));
    },
    'writes highlighted wordpress blocks through writer opt in' => static function (TestRunner $t): void {
        $document = (new MarkdownReader())->read(implode("\n", [
            '``` {.php #migration-review .numberLines .lineAnchors startFrom=42}',
            '<?php',
            'function render_title($post) {',
            "    return esc_html(\$post['title']);",
            '}',
            '```',
        ]));

        $plainBlocks = (new WordPressBlockWriter())->write($document);
        $highlightedBlocks = (new WordPressBlockWriter([
            'highlightCodeBlocks' => true,
            'highlightStyle' => 'kate',
        ]))->write($document);

        $t->contains('<!-- wp:code -->', $plainBlocks);
        $t->contains('<pre class="wp-block-code php numberLines lineAnchors" id="migration-review"><code class="language-php">&lt;?php', $plainBlocks);
        $t->contains('<!-- wp:html -->', $highlightedBlocks);
        $t->contains('<style data-pandoc-highlight-style="kate">', $highlightedBlocks);
        $t->contains('.sourceCode .kw', $highlightedBlocks);
        $t->contains('<div class="sourceCode"><pre class="sourceCode numberSource php numberLines lineAnchors"><code class="sourceCode php" style="counter-reset: source-line 41;">', $highlightedBlocks);
        $t->contains('<span id="migration-review-42"><a href="#migration-review-42"></a><span class="pp">&lt;?php</span></span>', $highlightedBlocks);
        $t->contains('<span id="migration-review-43"><a href="#migration-review-43"></a><span class="kw">function</span> <span class="fu">render_title</span>', $highlightedBlocks);
        $t->contains('<span class="va">$post</span><span class="op">[</span><span class="st">&#039;title&#039;</span><span class="op">]);</span>', $highlightedBlocks);
        $t->same(false, str_contains($highlightedBlocks, '<!-- wp:code -->'));
    },
    'falls back safely for unsupported languages' => static function (TestRunner $t): void {
        $highlighted = (new SyntaxHighlighter())->highlight('<danger>& text', 'brainfuck');

        $t->same('', $highlighted['language']);
        $t->same('brainfuck', $highlighted['requestedLanguage']);
        $t->same('unsupported-language', $highlighted['diagnostics'][0]['code'] ?? null);
        $t->same([['type' => 'text', 'text' => '<danger>& text', 'class' => '']], $highlighted['tokens']);
        $t->contains('<pre class="sourceCode"><code class="sourceCode">&lt;danger&gt;&amp; text</code></pre>', $highlighted['html']);
    },
];
