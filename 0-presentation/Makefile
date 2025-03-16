ENGINE=pdflatex

pdf: main.tex
	$(ENGINE) main

release: pdf
	$(ENGINE) main

clean:
	rm -f *.aux *.lof *.log *.out *.toc *.vrb *.nav *.snm

mrproper: clean
	rm -f *.pdf
