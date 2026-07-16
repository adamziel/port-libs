(defconst hack--keyword-regex
  ;; Keywords, based on hphp.ll.
  ;; We don't highlight endforeach etc, as they're syntax errors.
  ;; From full_fidelity_lexer.ml.
  (regexp-opt
   '(;; This is the list of keywords from full_fidelity_lexer.ml, but
     ;; removing types (boolean etc) and constants (true, false, null).
     "__halt_compiler" "abstract" "and" "as" "break"
     "case" "catch" "class" "clone" "const" "continue" "declare" "default"
     "die" "do" "echo" "else" "elseif" "empty" "enddeclare" "endfor"
     "endforeach" "endif" "endswitch" "endwhile" "eval" "exit" "extends"
     "final" "finally" "for" "foreach" "function" "global" "goto" "if"
     "implements" "include" "include_once" "instanceof" "insteadof"
     "interface" "isset" "list" "namespace" "new" "or" "parent"
     "print" "private" "protected" "public" "require" "require_once"
     "return" "self" "static" "switch" "throw" "trait"
     "try" "unset" "use" "var" "while"
     "xor" "yield"
     "inout" "using"

     ;; Contextual keywords.
     "async"
     "await"
     "enum"
     "newtype"
     "record"
     "shape"
     "super"
     "tuple"
     "type"
     "where"

     ;; These contextual keywords are also used for literals, so highlight them as keywords.
     "array"
     "darray"
     "dict"
     "keyset"
     "varray"
     "vec"

     ;; XHP keywords
     "attribute"
     "category"
     "children"
     "required"

     ;; Highlight a lambda function as a keyword to make it clear,
     ;; even though users can't shadow this anyway.
     "==>"

     ;; Treat self:: and static:: as keywords.
     "self"
     "parent")
   'symbols))
