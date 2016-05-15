#!/bin/bash

DATA_DIR=/tmpfs/oracle_sched_profiler_split_q18_v3.19.3_lots_of_events

cat $DATA_DIR/sched_profiler_pinned_nodes_baseline.txt |  \
    hhvm ./parse_sched_profiler.php output/overview-pinned-nodes-standard.png &
cat $DATA_DIR/sched_profiler_pinned_nodes_baseline.txt |  \
    hhvm ./parse_sched_profiler.php output/overview-pinned-nodes-load.png load &

wait

