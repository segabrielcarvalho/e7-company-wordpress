<?php
$availability_query = apmg_catalog_query([], 1, 1);
$vehicle_count = (int) $availability_query->found_posts;
$hero_vehicles = get_posts(apply_filters('apmg_catalog_query_args', [
    'post_type' => 'vehicle',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'fields' => 'ids',
    'meta_query' => [
        'relation' => 'AND',
        ['key' => '_thumbnail_id', 'compare' => 'EXISTS'],
    ],
], []));
$hero_vehicle_id = (int) ($hero_vehicles[0] ?? 0);
$hero_thumbnail_id = $hero_vehicle_id ? (int) get_post_thumbnail_id($hero_vehicle_id) : 0;
$hero_optimized = $hero_thumbnail_id ? apmg_performance_image_url($hero_thumbnail_id, 'hero') : '';
?>
<section class="brand-hero" aria-labelledby="brand-hero-title">
    <?php if ($hero_thumbnail_id) : ?>
        <div class="brand-hero__media" aria-hidden="true">
            <picture>
                <?php if ($hero_optimized) : ?><source srcset="<?php echo esc_url($hero_optimized); ?>" type="image/avif"><?php endif; ?>
                <?php echo wp_get_attachment_image($hero_thumbnail_id, 'large', false, ['alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async', 'sizes' => '100vw']); ?>
            </picture>
        </div>
    <?php endif; ?>
    <div class="kit-container brand-hero__inner">
        <div class="brand-hero__copy">
            <p><i class="fas fa-award" aria-hidden="true"></i> Independent Dealer of the Year 2024</p>
            <h1 id="brand-hero-title">Your next car starts here.</h1>
            <span>Quality approved used cars from a trusted Tralee dealer with over 30 years of experience.</span>
        </div>
    </div>
    <div class="kit-container hero-search-wrap">
        <form class="hero-search" action="<?php echo esc_url(home_url('/#inventory')); ?>" method="get" role="search">
            <div class="hero-search__field">
                <button type="submit" aria-label="Search <?php echo esc_attr(number_format_i18n($vehicle_count)); ?> available cars"><i class="fas fa-search" aria-hidden="true"></i></button>
                <input type="search" name="vehicle_search" aria-label="Search the vehicle inventory" placeholder="Search by make, model or version" autocomplete="off">
                <span><strong><?php echo esc_html(number_format_i18n($vehicle_count)); ?></strong> cars available</span>
            </div>
        </form>
    </div>
</section>
