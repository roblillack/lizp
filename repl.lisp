(unless (function_exists "readline")
  (println "Readline missing. Using rudimentary input line!")

  (defun readline (prompt)
    (print prompt)
    (define fp (fopen "php://stdin" "r"))
    (rtrim (fgets fp 1024)))

  (defun readline_add_history (line)))

(while t
  (define input (readline "> "))

  (when (eq? 0 (strlen input))
    (println)
    (println "Goodbye.")
    (exit 0))

  (readline_add_history input)
  (define r (eval input))
  (print "* ")
  (p r))

