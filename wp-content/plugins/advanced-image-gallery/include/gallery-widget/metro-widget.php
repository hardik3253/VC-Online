<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$gallery_class = 'zlfms-image-gallery zlfms-metro-gallery';
$unique_id = 'gallery_' . uniqid();
$pattern = isset($settings['metro_pattern']) ? $settings['metro_pattern'] : 'pattern-1';

$pattern_map = [
    'pattern-1' => ['zlfms-metro-item-large', '', 'zlfms-metro-item-tall', '', 'zlfms-metro-item-wide'],
    'pattern-2' => ['zlfms-metro-item-wide', '', 'zlfms-metro-item-large', '', 'zlfms-metro-item-tall'],
];

$sequence = isset($pattern_map[$pattern]) ? $pattern_map[$pattern] : $pattern_map['pattern-1'];
$sequence_count = count($sequence);

echo '<div id="' . esc_attr($unique_id) . '" class="' . esc_attr($gallery_class) . '">';
echo '<div class="zlfms_gallery">';

$index = 0;
foreach ($settings['image_list'] as $item) {
    $type = isset($item['type']) ? esc_attr($item['type']) : 'image';
    $link_target = isset($item['link']['is_external']) && $item['link']['is_external'] ? '_blank' : '_self';
    $link_target = esc_attr($link_target);
    $link = '';
    $attachment_id = isset($item['image']['id']) ? absint($item['image']['id']) : 0;
    $image_title = '';

    if ($attachment_id) {
        $image_post = get_post($attachment_id);
        if ($image_post) {
            $image_title = esc_html($image_post->post_title);
        }
    }
    if (empty($image_title)) {
        $image_title = esc_html(get_the_title());
    }

    $image_url = !empty($item['image']['url']) ? esc_url($item['image']['url']) : esc_url(\Elementor\Utils::get_placeholder_image_src());

    $image_link_to_type = isset($item['image_link_to_type']) ? $item['image_link_to_type'] : '';

    if ($type === 'image') {
        if ($image_link_to_type === 'custom' && !empty($item['link']['url'])) {
            $link = esc_url($item['link']['url']);
        } elseif ($image_link_to_type === 'file') {
            if ($settings['open_lightbox'] === 'yes') {
                $link = esc_url($image_url);
            } else {
                $link = esc_url(get_attachment_link($attachment_id));
            }
        }
    }

    $item_classes = ['zlfms-gallery-item'];
    if ($type === 'video') {
        $item_classes[] = 'zlfms_video';
    }

    if ($sequence_count > 0) {
        $pattern_class = $sequence[$index % $sequence_count];
        if (!empty($pattern_class)) {
            $item_classes[] = $pattern_class;
        }
    }

    echo '<div class="' . esc_attr(implode(' ', $item_classes)) . '">';

    if ($type === 'image') {
        if (!empty($link)) {
            echo '<a href="' . esc_url($link) . '" target="' . esc_attr($link_target) . '"';
            if ($settings['open_lightbox'] === 'yes') {
                echo ' data-elementor-open-lightbox="yes"';
                if (!empty($image_title)) {
                    echo ' data-elementor-lightbox-title="' . esc_attr($image_title) . '"';
                }
            }
            echo '>';
        }

        echo '<div class="zlfms-gallery-img" style="background-image: url(\'' . esc_url($image_url) . '\');" role="img" aria-label="' . esc_attr($image_title) . '">';

        $overlay_type = isset($settings['overlay_type']) ? esc_attr($settings['overlay_type']) : 'none';
        $animation_class = 'zlfms-overlay-animation-' . esc_attr($settings['overlay_animation']);
        if ($overlay_type === 'text') {
            echo '<div class="zlfms-carousel-image-overlay ' . esc_attr($animation_class) . '">';
            echo esc_html($image_title);
            echo '</div>';
        } elseif ($overlay_type === 'icon') {
            echo '<div class="zlfms-carousel-image-overlay ' . esc_attr($animation_class) . '">';
            $icon = $settings['overlay_icon_choose'];
            if (!empty($icon['value'])) {
                echo '<i class="' . esc_attr($icon['value']) . '"></i>';
            }
            echo '</div>';
        }
        echo '</div>';

        if (!empty($link)) {
            echo '</a>';
        }
    } elseif ($type === 'video') {
        $video_url = isset($item['video']['url']) ? $item['video']['url'] : '';
        $video_data = zlfms_parse_video_url($video_url);
        $embed_url = $video_data ? $video_data['embed_url'] : '';
        $video_type = $video_data ? $video_data['video_type'] : '';

        if (!empty($embed_url) && $settings['open_lightbox'] === 'yes') {
            echo '<div class="zlfms_video_player" data-elementor-open-lightbox="yes" data-elementor-lightbox=\'{"type":"video","videoType":"' . esc_attr($video_type) . '","url":"' . esc_url($embed_url) . '?autoplay=1","modalOptions":{"id":"elementor-lightbox-' . esc_attr($unique_id) . '","videoAspectRatio":"169"}}\'>';
        } else {
            echo '<a class="zlfms_video_player" href="' . esc_url($video_url) . '" target="_blank">';
        }

        echo '<div class="zlfms-gallery-img" style="background-image: url(\'' . esc_url($image_url) . '\');" role="img" aria-label="' . esc_attr($image_title) . '">';
        if ($settings['show_play_icon'] === 'yes') {
            $play_icon = $settings['play_icon'];
            $icon_value = isset($play_icon['value']) ? esc_attr($play_icon['value']) : 'far fa-play-circle';
            $icon_library = isset($play_icon['library']) ? esc_attr($play_icon['library']) : 'fa-solid';
            echo '<div class="zlfms-custom-embed-play"><i aria-hidden="true" class="' . esc_attr($icon_library . ' ' . $icon_value) . '"></i></div>';
        }
        echo '</div>';

        $overlay_type = isset($settings['overlay_type']) ? esc_attr($settings['overlay_type']) : 'none';
        $animation_class = 'zlfms-overlay-animation-' . esc_attr($settings['overlay_animation']);
        if ($overlay_type === 'text') {
            echo '<div class="zlfms-carousel-image-overlay ' . esc_attr($animation_class) . '">';
            echo esc_html($image_title);
            echo '</div>';
        } elseif ($overlay_type === 'icon') {
            echo '<div class="zlfms-carousel-image-overlay ' . esc_attr($animation_class) . '">';
            $icon = $settings['overlay_icon_choose'];
            if (!empty($icon['value'])) {
                echo '<i class="' . esc_attr($icon['value']) . '"></i>';
            }
            echo '</div>';
        }

        echo (!empty($embed_url) && $settings['open_lightbox'] === 'yes') ? '</div>' : '</a>';
    }

    echo '</div>';
    $index++;
}

echo '</div>';
echo '</div>';
