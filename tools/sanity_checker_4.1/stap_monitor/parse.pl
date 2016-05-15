#!/usr/bin/perl
use strict;
use warnings;
use List::Util qw(min max);

my @lines = split(/\n/, `cat ../../kernel/sched/fair.c`);
my %seen_lines;
while(<>) {
   if($_ =~ m/line: (\d+)\s+-\s+(\d+)/) {
      $seen_lines{$1} = $2;
   }
}

my $min = min(keys %seen_lines);
my $max = max(keys %seen_lines);
for(my $l = $min - 5; $l <= $max; $l++) {
   if(defined($seen_lines{$l})) {
         printf("%5d - %2d calls - %s\n", $l, $seen_lines{$l}, $lines[$l-1]);
   } else {
         printf("%5d -          - %s\n", $l, $lines[$l-1]);
   }
}
