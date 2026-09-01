<?php

/**
 * Media URL helpers.
 *
 * @package NovaStreamThemeHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function novastream_parse_embed_video($url)
{
    if (str_contains($url, 'youtube') || str_contains($url, 'youtu.be')) {
        $video_id = novastream_get_youtube_id($url);

        return $video_id ? "https://www.youtube.com/embed/{$video_id}" : false;
    }

    if (str_contains($url, 'vimeo')) {
        $video_id = novastream_get_vimeo_id($url);

        return $video_id ? "https://player.vimeo.com/video/{$video_id}" : false;
    }

    return false;
}

function novastream_get_youtube_id($url)
{
    $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    preg_match($pattern, $url, $matches);

    return $matches[1] ?? false;
}

function novastream_get_vimeo_id($url)
{
    preg_match('/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/', $url, $matches);

    return $matches[1] ?? false;
}
