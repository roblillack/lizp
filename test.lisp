(println "Hello, World!")
(println (- (+ (/ 44 2) (* 3 (+ 20 10))) 1))
(println "Bye")
(println "Size of /etc/shells: "
         (strlen (file_get_contents "/etc/shells")))

(dump (if t (println "eins")
        (println "noe")))

(println "liste: "
         (if () "was drin" "leer"))

(println "MySQL-Funktionen existieren: "
         (if (function_exists "mysql_connect") "ja" "nein"))

(p (function_exists "mysql_connectas"))

(define bla "bla")
(define bla "blubber")

(defun blub ()
  (println bla)
  (define bla "innerhalb geaendert"))

(p blub)
(println bla)
(define bla "jetzt in der funktion")
(blub)
(println bla)

(defun fib (n)
  (if (eq? n 0) 1
    (if (eq? n 1) 1
      (+ (fib (- n 1))
         (fib (- n 2))))))

(print "fib: ")
(p fib)
(println (fib 10))

(defun zehn () 10)
(println (zehn))

(defun p2 () println)
(defun p3 () (p2))
((p3) "blabla")

(define getprintln (lambda () (quote println)))
((lambda (name) ((getprintln) "huhu, " name "!")) "rob")


(let ((bla "blA") (blub "blubb!"))
  (println bla " -- " blub))