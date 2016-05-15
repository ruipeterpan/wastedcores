#!/usr/bin/php
<?php

ini_set('memory_limit', '32G');
include "string_to_color.php";

$n_hw_threads = 64;

$line_width = 2;
$line_sep = 0;
$h_margin = 20;
$v_margin_top = 40;
$v_margin_bottom = 20;
$h_tight_margin = 4;
$v_tight_margin = 4;
$column_1_width = 225;
$column_2_width = 400;
$row_height = 20;
$start_ts = 0;
$resolution = 200;
$font_y_corr = -2;
$small_font_height = 8;
$max_n_samples = 5000 * 15;
$n_hw_threads_per_chip = 8;
$chip_sep = 1;
$adaptive_width = false;
$row_width = 1000;
$row_sep = 20;
$metric = "standard";
$monitored_event = -1;
$core = -1;
$show = "nothing";

// ^ Edit these variables

if ($argc > 2) $resolution = $argv[2];
if ($argc > 3) $max_n_samples = $argv[3];
if ($argc > 4) $first_ts = $argv[4];
if ($argc > 5) $start_ts = $argv[5];
if ($argc > 6) $metric = $argv[6];
if ($argc > 7) $monitored_event = $argv[7];
if ($argc > 8) $core = $argv[8];
if ($argc > 9) $show = $argv[9];

$reached_end = false;
$resolution_s = $resolution / 1000000.0;
$num_rows = ceil($max_n_samples / $row_width);
$width = 2 * $h_margin + $row_width;
$height = $v_margin_top + $v_margin_bottom +
          $num_rows *
          ($n_hw_threads * ($line_width + $line_sep) +
          $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1) + $row_sep);


$image = imagecreatetruecolor($width, $height);


$black = imagecolorallocate($image, 0, 0, 0);
$white = imagecolorallocate($image, 255, 255, 255);
$gray = imagecolorallocate($image, 50, 50, 50);
$dk_gray = imagecolorallocate($image, 15, 15, 15);
$red = imagecolorallocate($image, 255, 0, 0);
$green = imagecolorallocate($image, 0, 255, 0);
$blue = imagecolorallocate($image, 0, 0, 255);
$cyan = imagecolorallocate($image, 0, 255, 255);
$magenta = imagecolorallocate($image, 255, 0, 255);

$background_color = $white;
$text_color = $black;
$sep_color = $black;
$highlight_color = $blue;
$bad_wakeup_outer_color = $black;
$bad_wakeup_inner_color = $magenta;

imagefill($image, 0, 0, $background_color);

for ($row = 0 ; $row < $num_rows ; $row++)
{
    for ($i = 0; $i < $n_hw_threads; $i+=8)
    {
        $y_origin = get_y_origin($i) + $font_y_corr +
                    $row * ($n_hw_threads * ($line_width + $line_sep) +
                    $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1) +
                    $row_sep);

        imagestring($image, 2, $h_tight_margin,
                    $y_origin, sprintf("%2d", $i / 8 + 1), $text_color);
    }
}

$i = -1;
$previous_line = $line = fgets(STDIN);
preg_match ("/(.*?)(\d+) nsecs (.*)/", $line, $matches);
if ($matches[1] != "# ") $last_hw_thread_statss_str = $matches[1];
$ts = $matches[2] / 1000000000.0;
if (!isset($first_ts) || $first_ts <= 0) $first_ts = $ts;
$end_ts = $first_ts + $start_ts + $max_n_samples * $resolution_s;

while ($ts < $first_ts + $start_ts)
{
    $previous_line = $line;
    if(!($line = fgets(STDIN)))
    {
        $reached_end = true;
        goto script_end;
    }

    preg_match ("/(.*?)(\d+) nsecs (.*)/", $line, $matches);
    if ($matches[1] != "# ") $last_hw_thread_statss_str = $matches[1];
    $ts = $matches[2] / 1000000000.0;
}

$affected_cores = array();

$idle_balancing_events = array();
$periodic_rebalancing_events = array();
$wake_up_events = array();
$bad_wakeup_marker_locations = array();

for ($z = 0; $z < 64; $z++) $affected_cores[$z] = false;

for($cur_ts = $first_ts + $start_ts + $resolution_s, $i = 0;
    $cur_ts < $end_ts;
    $cur_ts += $resolution_s, $i++)
{
    while($ts < $cur_ts)
    {
        $previous_line = $line;

        if (isset($matches[3]))
        {
            $params = preg_split('/\s+/', $matches[3]);

            if ($params[0] == "BA")
            {
                if ($params[1] == $monitored_event)
                {
                    if (($monitored_event != 110 && $monitored_event != 111
                        && $monitored_event < 200) || $params[2] == $core)
                    {
                        if ($monitored_event == 110 || $monitored_event >= 200)
                        {
                            $value = hexdec($params[4]);

                            for ($d = 0; $d < 64; $d++)
                            {
                                if ($value & (1 << $d))
                                    $affected_cores[$d] = true;
                            }
                        }
                        else
                        {
                            $affected_cores[$params[2]] = true;
                        }
                    }
                }
            }

            if ($show == "arrows" || $show == "bad_wakeups")
            {
                if ($params[0] == "SC")
                {
                    // Idle balance
                    if ($params[1] == 13 || $params[1] == 23)
                    {
                        $idle_balancing_events[] = array("src" => $params[2],
                                                         "dst" => $params[3]);
                    }
                    // Periodic rebalancing
                    else if ($params[1] == 14 || $params[1] == 24)
                    {
                        $periodic_rebalancing_events[] = array("src" => $params[2],
                                                               "dst" => $params[3]);
                    }
                    // Thread wake up
                    else
                    {
                        if ($show == "bad_wakeups" || ($params[2] != $params[3]))
                        {
                            $wake_up_events[] = array("src" => $params[2],
                                                      "dst" => $params[3]);
                        }
                    }
                }
            }
        }

        if(!($line = fgets(STDIN)))
        {
            $reached_end = true;
            goto script_end;
        }

        preg_match ("/(.*?)(\d+) nsecs (.*)/", $line, $matches);
        if ($matches[1] != "# ") $last_hw_thread_statss_str = $matches[1];
        if (sizeof($matches) < 2) echo "Error with: " . $line;
        $ts = $matches[2] / 1000000000.0;
    }

    preg_match ("/(.*?)\d+ nsecs(.*)/", $previous_line, $matches);
    if ($matches[1] != "# ") $last_hw_thread_statss_str = $matches[1];
    $hw_thread_statss_str = $last_hw_thread_statss_str;
    $hw_thread_statss_str = substr($hw_thread_statss_str, 1);
    $hw_thread_statss = str_split($hw_thread_statss_str, 20);

    if ($i % $row_width == 0)
    {
        $x_origin = $h_margin + $i % $row_width;
        $y_origin = $v_margin_top - $small_font_height + floor($i / $row_width)
                    * ($n_hw_threads * ($line_width + $line_sep) +
                    $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1)
                    + $row_sep);

        imagestring($image, 1, $h_margin, $y_origin - 2,
                    "sched_clock = " . ($cur_ts - $resolution_s) . "s",
                    $text_color);

        if ($core >= 0)
        {
            $x_origin = $h_margin;
            $y_origin = get_y_origin($core) + floor($i / $row_width)
                        * ($n_hw_threads * ($line_width + $line_sep) +
                        $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1)
                        + $row_sep);

            imageline($image,
                      $x_origin - 2,
                      $y_origin,
                      $x_origin - 2,
                      $y_origin + 1,
                      $highlight_color);

            imageline($image,
                      $x_origin - 3,
                      $y_origin - 1,
                      $x_origin - 3,
                      $y_origin + 2,
                      $highlight_color);

            imageline($image,
                      $x_origin - 4,
                      $y_origin - 2,
                      $x_origin - 4,
                      $y_origin + 3,
                      $highlight_color);

        }
    }

    $last_x_origin = $last_y_origin = -1;

    foreach ($hw_thread_statss as $hw_thread_id => $hw_thread_stats)
    {
        if (strlen($hw_thread_stats) < 20) continue;

        $color = $background_color;
        if ($metric == "standard")
            $color = string_to_color($hw_thread_stats, $image);
        else if ($metric == "load")
            $color = string_to_color_load($hw_thread_stats, $image);

        if ($affected_cores[$hw_thread_id])
            $color = $highlight_color;

        $x_origin = $h_margin + $i % $row_width;
        $y_origin = get_y_origin($hw_thread_id) + floor($i / $row_width)
                    * ($n_hw_threads * ($line_width + $line_sep) +
                    $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1)
                    + $row_sep);

        $last_x_origin = $x_origin;
        $last_y_origin = $y_origin;

        if ($hw_thread_id % $n_hw_threads_per_chip == 0)
        {
            imageline($image, $x_origin,
                              $y_origin - 1,
                              $x_origin,
                              $y_origin,
                              $sep_color);
        }

        imageline($image, $x_origin,
                          $y_origin,
                          $x_origin,
                          $y_origin + $line_width - 1,
                          $color);
    }


    if ($show == "arrows")
    {
        $events_arrays = array("idle_balancing" => $idle_balancing_events,
                               "periodic_rebalancing" => $periodic_rebalancing_events,
                               "wake_up" => $wake_up_events);

        $event_colors = array("idle_balancing" => imagecolorallocate($image, 255, 0, 255),
                              "periodic_rebalancing" => imagecolorallocate($image, 0, 0, 255),
                              "wake_up" => imagecolorallocate($image, 0, 128, 255));

        $event_styles = array("idle_balancing" => array($event_colors["idle_balancing"], IMG_COLOR_TRANSPARENT),
                              "periodic_rebalancing" => array($event_colors["periodic_rebalancing"], $event_colors["periodic_rebalancing"],
                                                              IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT),
                              "wake_up" => array($event_colors["wake_up"], IMG_COLOR_TRANSPARENT,
                                                 $event_colors["wake_up"], $event_colors["wake_up"],
                                                 IMG_COLOR_TRANSPARENT));

        foreach ($events_arrays as $key => $events_array)
        {
            foreach ($events_array as $event)
            {
                if ($event["src"] == -1) continue;

                $color = $event_colors[$key];

                $x_origin = $h_margin + $i % $row_width;
                $y_start_origin = get_y_origin($event["src"])
                                  + floor($i / $row_width) * ($n_hw_threads *
                                  ($line_width + $line_sep) + $chip_sep * ($n_hw_threads
                                  / $n_hw_threads_per_chip - 1) + $row_sep);

                $y_end_origin = get_y_origin($event["dst"])
                                  + floor($i / $row_width) * ($n_hw_threads *
                                  ($line_width + $line_sep) + $chip_sep * ($n_hw_threads
                                  / $n_hw_threads_per_chip - 1) + $row_sep);

                imagesetstyle($image, $event_styles[$key]);

                // Arrow going down
                if ($event["dst"] > $event["src"])
                {
                    imageline($image, $x_origin,
                                      $y_start_origin + 1,
                                      $x_origin,
                                      $y_end_origin + $line_width - 2,
                                      IMG_COLOR_STYLED);

                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width - 2,
                                      $x_origin,
                                      $y_end_origin + $line_width - 2,
                                      $color);
                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width - 3,
                                      $x_origin - 1,
                                      $y_end_origin + $line_width - 3,
                                      $color);
                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width - 4,
                                      $x_origin - 2,
                                      $y_end_origin + $line_width - 4,
                                      $color);
                }
                // Arrow going up
                else if ($event["dst"] < $event["src"])
                {
                    imageline($image, $x_origin,
                                      $y_start_origin,
                                      $x_origin,
                                      $y_end_origin + $line_width - 1,
                                      IMG_COLOR_STYLED);

                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width - 1,
                                      $x_origin,
                                      $y_end_origin + $line_width - 1,
                                      $color);
                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width,
                                      $x_origin - 1,
                                      $y_end_origin + $line_width,
                                      $color);
                    imageline($image, $x_origin,
                                      $y_end_origin + $line_width + 1,
                                      $x_origin - 2,
                                      $y_end_origin + $line_width + 1,
                                      $color);
                }
            }
        }
    }
    else if ($show == "bad_wakeups")
    {
        foreach ($wake_up_events as $event)
        {
            $wakeup_core = $event["dst"];
            $hw_thread_stats = $hw_thread_statss[$wakeup_core];

            $splitted_str = str_split($hw_thread_stats, 4);

            $rq_size = $splitted_str[0];
            $last_scheduling_event = $splitted_str[1];

            /* There was a wake up even on this core, and the run queue is
               overloaded... Not very precise, because the two might be
               unrelated, or the run queue size might be updated in the next
               pixel etc. Doesn't really matter. */
            if ($rq_size > 1)
            {
                $x_origin = $h_margin + $i % $row_width;
                $y_origin = get_y_origin($wakeup_core) + floor($i / $row_width)
                            * ($n_hw_threads * ($line_width + $line_sep) +
                            $chip_sep * ($n_hw_threads / $n_hw_threads_per_chip - 1)
                            + $row_sep);

                $bad_wakeup_marker_locations[] = array($x_origin, $y_origin);
            }
        }
    }

    imageline($image, $last_x_origin,
                      $last_y_origin + $line_width,
                      $last_x_origin,
                      $last_y_origin + $line_width,
                      $sep_color);

    for ($z = 0; $z < 64; $z++) $affected_cores[$z] = false;
    $idle_balancing_events = array();
    $periodic_rebalancing_events = array();
    $wake_up_events = array();
}

script_end:

foreach ($bad_wakeup_marker_locations as $location)
{
        $x_origin = $location[0];
        $y_origin = $location[1];

        imagerectangle($image, $x_origin - 1,
                               $y_origin - 1,
                               $x_origin + 1,
                               $y_origin + $line_width,
                               $bad_wakeup_outer_color);

        imageline($image, $x_origin,
                          $y_origin,
                          $x_origin,
                          $y_origin + $line_width - 1,
                          $bad_wakeup_inner_color);
}

if ($reached_end)
{
    $execution_time = $ts - $first_ts - $start_ts;

    $y_origin = $v_tight_margin + $font_y_corr;
    $str = sprintf("1 pixel = %.2fms, [%.2fs->%.2fs]",
                   $resolution / 1000,
                   $start_ts,
                   $start_ts + $execution_time);
    imagestringb($image, 2, $h_margin, $y_origin, $str, $text_color);
}
else
{
    $y_origin = $v_tight_margin + $font_y_corr;
    $str = sprintf("1 pixel = %.2fms, [%.2fs->%.2fs]",
                   $resolution / 1000,
                   $start_ts,
                   $start_ts + $resolution * $max_n_samples / 1000000);
    imagestringb($image, 2, $h_margin, $y_origin, $str, $text_color);
}

/*
$x_origin = $h_margin + $column_1_width;
$y_origin = $v_tight_margin + $font_y_corr;
imagestringb($image, 2, $h_margin, $y_origin, $str, $text_color);
imagestringb($image, 2, $x_origin, $y_origin,
             "rq_size = 0: White for all scheduling events", $text_color);

$x_origin = $h_margin + $column_1_width + $column_2_width;
imagestringb($image, 2, $x_origin, $y_origin,
             "rq_size = 1: ", $text_color);
imagestringb($image, 2, $x_origin, $y_origin,
             "             WAKE UP BAL.",
             string_to_color("   1  -1", $image)); // -1 0 1 2
imagestringb($image, 2, $x_origin, $y_origin,
             "                           IDLE BAL.",
             string_to_color("   1  13", $image));
imagestringb($image, 2, $x_origin, $y_origin,
             "                                      PERIODIC BAL.",
             string_to_color("   1  14", $image));

$x_origin = $h_margin + $column_1_width;
$y_origin += $row_height;
imagestringb($image, 2, $x_origin, $y_origin,
             "rq_size = 2:", $text_color);
imagestringb($image, 2, $x_origin, $y_origin,
             "             WAKE UP BAL.",
             string_to_color("   2  -1", $image));
imagestringb($image, 2, $x_origin, $y_origin,
             "                           IDLE BAL.",
             string_to_color("   2  13", $image));
imagestringb($image, 2, $x_origin, $y_origin,
             "                                      PERIODIC BAL.",
             string_to_color("   2  14", $image));

$x_origin = $h_margin + $column_1_width + $column_2_width;
imagestringb($image, 2, $x_origin, $y_origin,
             "rq_size > 2: Black for all scheduling events", $text_color);
*/

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
    global $v_margin_top, $line_width, $line_sep, $chip_sep,
           $n_hw_threads_per_chip;

    return $v_margin_top + $hw_thread_id * ($line_width + $line_sep) +
           $chip_sep * floor($hw_thread_id / $n_hw_threads_per_chip);
}

?>
