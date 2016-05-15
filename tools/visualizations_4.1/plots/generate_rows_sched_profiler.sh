#!/bin/bash

COMPILER=hhvm
INPUT=/tmpfs/group-imbalance.txt

generate_sched_profiler_graphs_all_parallel()
{
    I=0

    START_PADDED=`printf "%03d" $START`
    FILE_BASENAME=$(basename $INPUT)
    FILE_ROOT=${FILE_BASENAME%.*}

    cat $INPUT |                                                               \
        ${COMPILER}                                                            \
        ./parse_rows_sched_profiler.php                                        \
        output/${5}_standard.png                                               \
        $3 60500 -1 0 standard $1 $2 $4 &

    cat $INPUT |                                                               \
        ${COMPILER}                                                            \
        ./parse_rows_sched_profiler.php                                        \
        output/${5}_load.png                                                   \
        $3 60500 -1 0 load $1 $2 $4 &
}

# Generic runqueue and load graphs
generate_sched_profiler_graphs_all_parallel 0 -1 600 nothing generic

# Considered wakeups for core zero
generate_sched_profiler_graphs_all_parallel 201 0 600 nothing wakeups

# Graph with threads movements and "overloaded wakeups"
generate_sched_profiler_graphs_all_parallel 0 -1 600 arrows arrows
generate_sched_profiler_graphs_all_parallel 0 -1 600 bad_wakeups               \
                                                     overloaded_wakeups

wait

