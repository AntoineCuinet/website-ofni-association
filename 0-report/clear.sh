files='*.aux *.lof *.log *.out *.toc'
if [ $# = 1 ] && [ $1 = '-a' ]; then
    files=$files' *.pdf'
fi
rm -f $files
echo rm -f $files
