<?php
get_header();
the_post();
$vehicle = apmg_vehicle_view(get_the_ID());
$gallery = array_values(array_unique(array_filter((array) get_post_meta(get_the_ID(), 'apmg_gallery_urls', true))));
if ($vehicle['image']) { $gallery = array_values(array_unique(array_filter(array_merge([$vehicle['image']], $gallery)))); }
?>
<main class="vehicle-detail">
    <div class="kit-container vehicle-detail__breadcrumbs"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>/</span><a href="<?php echo esc_url(home_url('/listing/')); ?>">Used Cars</a><span>/</span><strong><?php the_title(); ?></strong></div>
    <section class="kit-container vehicle-detail__hero">
        <div class="vehicle-gallery" data-vehicle-gallery role="region" aria-label="Vehicle photo gallery"<?php if (count($gallery) > 1) : ?> tabindex="0"<?php endif; ?>>
            <div class="vehicle-gallery__stage">
                <?php if ($gallery) : ?><img src="<?php echo esc_url($gallery[0]); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" fetchpriority="high" decoding="async" data-gallery-main><?php else : ?><span><i class="fas fa-car"></i></span><?php endif; ?>
                <?php if (count($gallery) > 1) : ?>
                    <button type="button" class="vehicle-gallery__arrow vehicle-gallery__arrow--previous" data-gallery-prev aria-label="Previous photo"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="vehicle-gallery__arrow vehicle-gallery__arrow--next" data-gallery-next aria-label="Next photo"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                <?php endif; ?>
            </div>
            <?php if (count($gallery) > 1) : ?><div class="vehicle-gallery__thumbs"><?php foreach ($gallery as $index => $image) : ?><button type="button" class="<?php echo $index === 0 ? 'is-active' : ''; ?>" data-gallery-image="<?php echo esc_url($image); ?>" aria-label="View photo <?php echo esc_attr((string) ($index + 1)); ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"><img src="<?php echo esc_url($image); ?>" alt="" loading="lazy"></button><?php endforeach; ?></div><?php endif; ?>
        </div>
        <aside class="vehicle-summary">
            <?php if ($vehicle['status']) : ?><span class="vehicle-summary__status"><?php echo esc_html($vehicle['status']); ?></span><?php endif; ?>
            <p><?php echo esc_html($vehicle['make']); ?></p><h1><?php the_title(); ?></h1><h2><?php echo esc_html($vehicle['subtitle']); ?></h2>
            <div class="vehicle-summary__quick">
                <?php foreach ([['fa-gas-pump', apmg_format_engine($vehicle['engine_size'], $vehicle['fuel'])], ['fa-road', apmg_format_mileage($vehicle['mileage'], $vehicle['mileage_unit'])], ['fa-cog', $vehicle['transmission']], ['fa-euro-sign', $vehicle['road_tax'] ? '€' . $vehicle['road_tax'] . ' road tax' : '']] as [$icon, $value]) : if ($value) : ?><span><i class="fas <?php echo esc_attr($icon); ?>" aria-hidden="true"></i><?php echo esc_html($value); ?></span><?php endif; endforeach; ?>
            </div>
            <div class="vehicle-summary__prices"><div><small>Our Price</small><strong><?php echo esc_html(apmg_format_price($vehicle['price'])); ?></strong></div><?php if ($vehicle['weekly_price']) : ?><div><small>Hire Purchase</small><strong>€<?php echo esc_html(number_format_i18n($vehicle['weekly_price'])); ?>/wk</strong></div><?php endif; ?></div>
            <?php get_template_part('template-parts/vehicle-commercial-actions', null, ['vehicle' => $vehicle]); ?>
            <p class="vehicle-summary__note">Online reservation terms, hold period and balance deduction are shown during checkout.</p>
        </aside>
    </section>
    <section class="kit-container vehicle-information">
        <article><span>Vehicle details</span><h2><?php the_title(); ?></h2><div class="vehicle-information__copy"><?php the_content(); ?></div></article>
        <aside><h2>Specifications</h2><dl><?php foreach ([['Year', $vehicle['year']], ['Make', $vehicle['make']], ['Model', $vehicle['model']], ['Body Type', $vehicle['body']], ['Fuel', $vehicle['fuel']], ['Transmission', $vehicle['transmission']], ['Engine', $vehicle['engine_size'] ? $vehicle['engine_size'] . ' L' : ''], ['Mileage', apmg_format_mileage($vehicle['mileage'], $vehicle['mileage_unit'])], ['Colour', $vehicle['colour']], ['Doors', $vehicle['doors']], ['Seats', $vehicle['seats']], ['Previous Owners', $vehicle['previous_owners']]] as [$label, $value]) : if ($value !== '' && $value !== 0) : ?><div><dt><?php echo esc_html((string) $label); ?></dt><dd><?php echo esc_html((string) $value); ?></dd></div><?php endif; endforeach; ?></dl></aside>
    </section>
    <?php if ($vehicle['features']) : ?><section class="kit-container vehicle-features"><span>Equipment</span><h2>Vehicle features</h2><ul><?php foreach ($vehicle['features'] as $feature) : ?><li><i class="fas fa-check" aria-hidden="true"></i><?php echo esc_html($feature); ?></li><?php endforeach; ?></ul></section><?php endif; ?>
</main>
<?php get_footer(); ?>
