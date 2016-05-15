#!/bin/bash

DATA_PATH=/tmpfs/oracle_sched_profiler_split_q18_v3.19.3_lots_of_events


for FILE in `ls $DATA_PATH/*.txt`
do

    echo $FILE

    START="`head $FILE -n 1 | awk '{printf("%d", $129)}'`"
    END="`tail $FILE -n 1 | awk '{printf("%d", $129)}'`"
    DIFF=$(((END-START)/1000000000))

    I=000

    while [[ $I -lt $DIFF ]]
    do
        I_PADDED=`printf "%03d" $I`
        ./fast_split_input_files.php $((START+I*1000000000))                   \
            $((START+(I+15)*1000000000)) $FILE                                 \
            /tmpfs/$(basename $FILE)-$I_PADDED &
        I=$(($I+15))
    done

done

wait

