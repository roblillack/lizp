#!/usr/bin/env sbcl --noinform

(defun fib (n)
  (if (eq n 0) 1
    (if (eq n 1) 1
      (+ (fib (- n 1))
         (fib (- n 2))))))

(format *standard-output* "fib: ~D~%" (fib 15))