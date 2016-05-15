<?php

function string_to_color($str, $image)
{
    $background_color = imagecolorallocate($image, 255, 255, 255);

    $splitted_str = str_split($str, 4);

    $rq_size = $splitted_str[0];
    $last_scheduling_event = $splitted_str[1];

    $color = $background_color;

    if ($rq_size == 0)
    {
        $color = $background_color;
    }
    else if ($rq_size == 1)
    {
        switch ($last_scheduling_event)
        {
            case -1:
            case 0:
            case 2:
            case 1:
//                $color = imagecolorallocate($image, 75, 197, 92);
//                break;
            case 13:
            case 23:
//                $color = imagecolorallocate($image, 75, 197, 92);
//                break;
            case 14:
            case 24:
                $color = imagecolorallocate($image, 0, 255, 0);
                break;
        }
    }
    else if ($rq_size == 2)
    {
        switch ($last_scheduling_event)
        {
            case -1:
            case 0:
            case 2:
            case 1:
//                $color = imagecolorallocate($image, 184, 0, 115);
//                break;
            case 13:
            case 23:
//                $color = imagecolorallocate($image, 115, 184, 0);
//                break;
            case 14:
            case 24:
//                $color = imagecolorallocate($image, 0, 115, 184);
//                break;
                $color = imagecolorallocate($image, 255, 128, 0);
                break;
        }
    }
    else if ($rq_size == 3)
    {
        $color = imagecolorallocate($image, 255, 0, 0);
    }
    else if ($rq_size == 4)
    {
        $color = imagecolorallocate($image, 255, 0, 128);
    }
    else if ($rq_size >= 5)
    {
        $color = imagecolorallocate($image, 255, 0, 255);
    }

    return $color;
}

function string_to_color_load($str, $image)
{
    $sstr = substr($str, 8);

/*
    if ($sstr <= 0) return imagecolorallocate($image, 255, 255, 255);
    if ($sstr > 128) return imagecolorallocate($image, 255, 0, 255);

    return imagecolorallocate($image, trim($sstr) * 2, 255 - trim($sstr) * 2, 255);
*/

    if ($sstr <= 0)
        return imagecolorallocate($image, 255, 255, 255);
    else if ($sstr > 0 && $sstr <= 20)
        return imagecolorallocate($image, 175, 255, 255);
    else if ($sstr > 20 && $sstr <= 40)
        return imagecolorallocate($image, 175, 215, 255);
    else if ($sstr > 40 && $sstr <= 60)
        return imagecolorallocate($image, 175, 175, 255);
    else if ($sstr > 60 && $sstr <= 80)
        return imagecolorallocate($image, 175, 135, 255);
    else if ($sstr > 80 && $sstr <= 100)
        return imagecolorallocate($image, 175, 95, 255);
    else if ($sstr > 100 && $sstr <= 250)
        return imagecolorallocate($image, 175, 0, 255);
    else if ($sstr > 250 && $sstr <= 500)
        return imagecolorallocate($image, 175, 0, 215);
    else if ($sstr > 500 && $sstr <= 750)
        return imagecolorallocate($image, 175, 0, 135);
    else if ($sstr > 750 && $sstr <= 1000)
        return imagecolorallocate($image, 175, 0, 175);
    else if ($sstr > 1000 && $sstr <= 2000)
        return imagecolorallocate($image, 175, 0, 95);
}

?>
