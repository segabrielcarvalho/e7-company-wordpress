<?php
/** @var array $args */
$filters = apmg_catalog_request_filters();
$requested_page = $_GET['vehicle_page'] ?? 1;
$page = max(1, is_scalar($requested_page) ? absint($requested_page) : 1);
$per_page = absint($args['per_page'] ?? 12) ?: 12;
$query = apmg_catalog_query($filters, $page, $per_page);
$form_action = (string) ($args['action'] ?? home_url('/'));
$show_filters = (bool) ($args['show_filters'] ?? true);
update_post_thumbnail_cache($query);
?>
<section id="inventory" class="inventory-section" aria-labelledby="inventory-title">
    <div class="kit-container">
        <header class="inventory-heading">
            <div><span>Quality used cars in Tralee</span><h2 id="inventory-title">Explore our inventory</h2></div>
            <p><strong><?php echo esc_html(number_format_i18n((int) $query->found_posts)); ?></strong> <?php echo esc_html(_n('car available', 'cars available', (int) $query->found_posts, 'auto-point-motor-group')); ?></p>
        </header>
        <?php if ($show_filters) { get_template_part('template-parts/vehicle-filters', null, ['filters' => $filters, 'action' => $form_action]); } ?>
        <div class="inventory-toolbar"><p><?php echo $filters ? esc_html__('Filtered results', 'auto-point-motor-group') : esc_html__('Latest arrivals', 'auto-point-motor-group'); ?></p><form id="inventory-sort" class="inventory-sort-form" action="<?php echo esc_url($form_action); ?>" method="get"><?php foreach ($filters as $key => $value) : if ($key === 'orderby') { continue; } foreach ((array) $value as $item) : ?><input type="hidden" name="<?php echo esc_attr($key . (is_array($value) ? '[]' : '')); ?>" value="<?php echo esc_attr((string) $item); ?>"><?php endforeach; endforeach; ?><label>Sort by <select name="orderby" data-inventory-sort><option value="latest" <?php selected(($filters['orderby'] ?? 'latest'), 'latest'); ?>>Latest First</option><option value="price_asc" <?php selected(($filters['orderby'] ?? ''), 'price_asc'); ?>>Price: Low to High</option><option value="price_desc" <?php selected(($filters['orderby'] ?? ''), 'price_desc'); ?>>Price: High to Low</option><option value="year_desc" <?php selected(($filters['orderby'] ?? ''), 'year_desc'); ?>>Newest Year</option><option value="title_asc" <?php selected(($filters['orderby'] ?? ''), 'title_asc'); ?>>Make / Model A–Z</option></select></label><button type="submit">Apply</button></form></div>
        <?php if ($query->have_posts()) : ?>
            <div class="inventory-grid">
                <?php while ($query->have_posts()) : $query->the_post(); get_template_part('template-parts/vehicle-card', null, ['post_id' => get_the_ID()]); endwhile; wp_reset_postdata(); ?>
            </div>
            <?php if ($query->max_num_pages > 1) : ?>
                <nav class="inventory-pagination" aria-label="Vehicle pages">
                    <?php
                    $base_args = $filters;
                    for ($number = 1; $number <= $query->max_num_pages; ++$number) {
                        $url = add_query_arg(array_merge($base_args, ['vehicle_page' => $number]), $form_action) . '#inventory';
                        printf('<a href="%s"%s>%d</a>', esc_url($url), $number === $page ? ' aria-current="page"' : '', $number);
                    }
                    ?>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="inventory-empty"><i class="fas fa-car" aria-hidden="true"></i><h3>No vehicles match those filters</h3><p>Try a broader make, price range or year.</p><a href="<?php echo esc_url($form_action); ?>">View all cars</a></div>
        <?php endif; ?>
    </div>
</section>
