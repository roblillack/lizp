; tests the scoping correctness

(define scope "lexical")
(defun read-global () scope)
(defun get-scope () (let ((scope "dynamic")) (read-global)))

(if (eq? (get-scope) scope)
    (println "i can haz lexical scope!1 \\o/")
  (println "#fail"))
