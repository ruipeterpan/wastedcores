#!/bin/bash

rm output/*large.png
cd output/

for FILE in `ls sched-profiler-*`
do
    convert $FILE -sample 3120x23805 ${FILE%.png}_large.png &
done

wait

