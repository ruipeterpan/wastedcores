#!/usr/bin/php
<?php

// Non-pinned events:     9720094 -  756.951584 -> 1260.727326 = 503.775742
// Pinned events:         6605579 - 1889.061938 -> 2331.292383 = 442.230445
// Pinned to node events: 7500567 - 2980.826650 -> 3486.077738 = 505.251088

include "string_to_color.php";

$n_hw_threads = 64;

$line_width = 7;
$line_sep = 0;
$h_margin = 20;
$v_margin = 20;
$h_tight_margin = 4;
$v_tight_margin = 4;
$column_width = 200;
$start_ts = 0;
$resolution = 50000;
$font_y_corr = -2;
$max_n_samples = 5000;
$n_hw_threads_per_chip = 8;
$chip_sep = 5;
$adaptive_width = true;

// ^ Edit these variables

$reached_end = false;
$resolution_s = $resolution / 1000000;
$width = 2 * $h_margin + $max_n_samples;
$height = 2 * $v_margin + $n_hw_threads * ($line_width + $line_sep) +
          $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1);
$image = imagecreatetruecolor($width, $height);

$black = imagecolorallocate($image, 0, 0, 0);
$white = imagecolorallocate($image, 255, 255, 255);

$gray = imagecolorallocate($image, 50, 50, 50);
$dk_gray = imagecolorallocate($image, 15, 15, 15);
$red = imagecolorallocate($image, 255, 0, 0);
$green = imagecolorallocate($image, 0, 255, 0);

$metric = "standard";
if ($argc > 2) $metric = $argv[2];

imagefill($image, 0, 0, $black);

for ($i = 0; $i < $n_hw_threads; $i++)
{
    imagestring($image, 1, $h_tight_margin,
                get_y_origin($i) + $font_y_corr + 2,
                sprintf("%2d", $i), $white);
}

$i = -1;
$previous_line = $line = fgets(STDIN);
preg_match ("/.*?(\d+) nsecs.*/", $line, $matches);
$ts = $first_ts = $matches[1] / 1000000000.0;

$end_ts = $first_ts + $start_ts + $max_n_samples * $resolution_s;

echo "Starts at: " . ($first_ts + $start_ts + $resolution_s) . "s\n";
echo "Ends at: " . $end_ts . "s\n";

while ($ts < $first_ts + $start_ts)
{
    $previous_line = $line;
    if(!($line = fgets(STDIN)))
    {
        $reached_end = true;
        goto script_end;
    }

    preg_match ("/.*?(\d+) nsecs.*/", $line, $matches);
    $ts = $matches[1] / 1000000000.0;
}

for($cur_ts = $first_ts + $start_ts + $resolution_s, $i = 0;
    $cur_ts < $end_ts;
    $cur_ts += $resolution_s, $i++)
{
    while($ts < $cur_ts)
    {
        $previous_line = $line;
        if(!($line = fgets(STDIN)))
        {
            $reached_end = true;
            goto script_end;
        }

        preg_match ("/.*?(\d+) nsecs.*/", $line, $matches);
        $ts = $matches[1] / 1000000000.0;
    }

    preg_match ("/(.*?)\d+ nsecs.*/", $previous_line, $matches);
    $hw_thread_statss_str = $matches[1];
    $hw_thread_statss_str = substr($hw_thread_statss_str, 1);
    $hw_thread_statss = str_split($hw_thread_statss_str, 20);

    foreach ($hw_thread_statss as $hw_thread_id => $hw_thread_stats)
    {
        if (strlen($hw_thread_stats) < 20) continue;

        $color = $black;

        if ($metric == "standard")
            $color = string_to_color($hw_thread_stats, $image);
        else if ($metric == "load")
            $color = string_to_color_load($hw_thread_stats, $image);

        $x_origin = $h_margin + $i;
        $y_origin = get_y_origin($hw_thread_id);

        imageline($image, $x_origin,
                          $y_origin,
                          $x_origin,
                          $y_origin + $line_width - 1,
                          $color);
    }
}

script_end:

if ($reached_end)
{
    $execution_time = $ts - $first_ts - $start_ts;

    $y_origin = $v_tight_margin + $font_y_corr;
    $str = sprintf("Total execution time = %.2fs (1 pixel = %.2fs)",
                   $execution_time, $resolution_s);
    imagestringb($image, 2, $h_margin, $y_origin, $str, $white);
}

$output_file = "output.png";
if ($argc > 1) $output_file = $argv[1];

if ($adaptive_width)
{
    $fixed_width = max(2 * $h_margin + $i, 350);
    $fixed_image = imagecreatetruecolor($fixed_width, $height);
    imagecopyresized ($fixed_image, $image, 0, 0, 0, 0,
                      $width, $height, $width, $height);
    imagepng($fixed_image, $output_file);
}
else
{
    imagepng($image, $output_file);
}

/******************************************************************************/
/* Functions                                                                  */
/******************************************************************************/
function imagestringb($image, $font, $x, $y, $string, $color)
{
    imagestring($image, $font, $x, $y, $string, $color);
    return imagestring($image, $font, $x + 1, $y, $string, $color);
}

function get_y_origin($hw_thread_id)
{
    global $v_margin, $line_width, $line_sep, $chip_sep, $n_hw_threads_per_chip;

    return $v_margin + $hw_thread_id * ($line_width + $line_sep) +
           $chip_sep * floor($hw_thread_id / $n_hw_threads_per_chip);
}

?>
