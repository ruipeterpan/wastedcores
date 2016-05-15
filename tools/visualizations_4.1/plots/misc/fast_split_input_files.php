#!/usr/bin/php
<?php

$block_size = 52428800; // 50MB

if ($argc < 5)
{
    echo "Error: four parameters needed.\n";
    exit(-1);
}

$start = $argv[1];
$end = $argv[2];
$input_filename=$argv[3];
$output_filename=$argv[4];

$start -= 1000000;
$end += 1000000;

$input_file = fopen($input_filename, "r") or die("Error: fopen failed");
$output_file = fopen($output_filename, "w") or die("Error: fopen failed");

$plus = 0;
$position = 0;

$last_position = ftell($input_file);

do
{
    $line_beginning = ftell($input_file);
    $line = fgets($input_file);
    preg_match ("/.*?(\d+) nsecs.*/", $line, $matches);
    $ts = $matches[1];

    if ($ts != "" && $ts < $start) {
        $last_position = $line_beginning;
        fseek($input_file, $block_size, SEEK_CUR);
        fgets($input_file);
    }
    else break;
} while ($line);

fclose($input_file);
$input_file = fopen($input_filename, "r") or die("Error: fopen failed");

fseek($input_file, $last_position, SEEK_SET);

while (($line = fgets($input_file)) !== false)
{
    preg_match ("/.*?(\d+) nsecs.*/", $line, $matches);
    $ts = $matches[1];
    if ($ts < $start) continue;
    if ($ts > $end) break;

    fwrite($output_file, $line);
}

fclose($input_file);
fclose($output_file);

echo "Written file $output_filename.\n";

?>
