(println "Hello, World!")
(println (- (+ (/ 44 2) (* 3 (+ 20 10))) 1))
(println "Bye")
(println "Size of /etc/shells: "
         (strlen (file-get-contents "/etc/shells")))

(dump (if t (println "eins")
        (println "noe")))

(println "liste: "
         (if () "was drin" "leer"))

(println "MySQL-Funktionen existieren: "
         (if (function-exists "mysql_connect") "ja" "nein"))

(p (function-exists "mysql_connectas"))

(when (< 1 2 3)
  (println "< works"))

(when (> (/ 3 2) 1 0 -1)
  (println "> works"))

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

(defun p2 () (quote println))
(defun p3 () (p2))
((p3) "blabla")

(define getprintln (lambda () (quote println)))
((lambda (name) ((getprintln) "huhu, " name "!")) "rob")


(let ((bla "blA") (blub "blubb!"))
  (println bla " -- " blub))

;(defmacro make-adder (a b)
;  '(+ ~a ~b))

;(defmacro three-times (what)
;  '(println ~what ", " ~what ", " ~what "."))

;(defmacro avg (&rest list)
;  `(/ (+ ,@list ,(length list))))

;(defmacro avg1 (&rest list)
;  '(/ (+ ~@list ~(length list))))

(defun count-args (&rest args) (length args))

(defun list (&rest args)
  '(@args))

(defmacro avg2 (&rest list)
  `(/ (+ ~@list) ~(length list)))

;(three-times "bla")

(println "random number: "
         (rand 1 1000))

(println "argcount: " (count-args 10 20 10))

(p (avg2 10 20))

(p (list 10 20 30))

(define mm '(10 20))
(println "rand 10-20: " (rand @mm))

(defun print-random (&rest args)
  (println "random: " (rand @args)))


(print-random 1 100000)