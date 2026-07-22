<?php
/** @var array $args */
$post_id = (int) ($args['post_id'] ?? get_the_ID());
$vehicle = apmg_vehicle_view($post_id);
$card_optimized = $vehicle['image_id'] ? apmg_performance_image_url($vehicle['image_id'], 'card') : '';
?>
<article class="inventory-card">
    <a class="inventory-card__image" href="<?php echo esc_url(get_permalink($post_id)); ?>">
        <?php if ($vehicle['image_id']) : ?>
            <picture>
                <?php if ($card_optimized) : ?><source srcset="<?php echo esc_url($card_optimized); ?>" type="image/avif"><?php endif; ?>
                <?php echo wp_get_attachment_image($vehicle['image_id'], 'medium_large', false, ['alt' => $vehicle['title'], 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 700px) calc(100vw - 30px), (max-width: 1100px) calc(50vw - 30px), 290px']); ?>
            </picture>
        <?php elseif ($vehicle['image']) : ?>
            <img src="<?php echo esc_url($vehicle['image']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy" decoding="async">
        <?php else : ?>
            <span class="inventory-card__placeholder" aria-hidden="true"><i class="fas fa-car"></i></span>
        <?php endif; ?>
        <?php if ($vehicle['status']) : ?><span class="inventory-card__status"><?php echo esc_html($vehicle['status']); ?></span><?php endif; ?>
    </a>
    <div class="inventory-card__body">
        <p class="inventory-card__make"><?php echo esc_html(trim($vehicle['make'] . ' · ' . $vehicle['year'], ' ·')); ?></p>
        <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html($vehicle['title']); ?></a></h3>
        <?php if ($vehicle['subtitle']) : ?><p class="inventory-card__subtitle"><?php echo esc_html($vehicle['subtitle']); ?></p><?php endif; ?>
        <div class="inventory-card__specs">
            <?php if ($vehicle['mileage']) : ?><span><i class="fas fa-road" aria-hidden="true"></i><?php echo esc_html(apmg_format_mileage($vehicle['mileage'], $vehicle['mileage_unit'])); ?></span><?php endif; ?>
            <?php if ($vehicle['transmission']) : ?><span><i class="fas fa-cog" aria-hidden="true"></i><?php echo esc_html($vehicle['transmission']); ?></span><?php endif; ?>
            <?php if ($vehicle['fuel']) : ?><span><i class="fas fa-gas-pump" aria-hidden="true"></i><?php echo esc_html($vehicle['fuel']); ?></span><?php endif; ?>
        </div>
        <div class="inventory-card__price">
            <div><small>Our Price</small><strong><?php echo esc_html(apmg_format_price($vehicle['price'])); ?></strong></div>
            <?php if ($vehicle['weekly_price']) : ?><div><small>Hire Purchase</small><strong>€<?php echo esc_html(number_format_i18n($vehicle['weekly_price'])); ?><em>/wk</em></strong></div><?php endif; ?>
        </div>
        <a class="inventory-card__cta" href="<?php echo esc_url(get_permalink($post_id)); ?>">View Details <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </div>
</article>
