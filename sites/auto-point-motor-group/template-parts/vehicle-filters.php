<?php
/** @var array $args */
$filters = (array) ($args['filters'] ?? []);
$form_action = (string) ($args['action'] ?? home_url('/listing/'));
$terms = [];
foreach (['make', 'model', 'fuel', 'transmission', 'body', 'colour'] as $key) {
    $terms[$key] = apmg_catalog_terms(apmg_vehicle_filter_schema()[$key]['taxonomy']);
}
$selected_values = static fn(string $key): array => array_map('strtolower', (array) ($filters[$key] ?? []));
?>
<form class="catalog-filters" action="<?php echo esc_url($form_action); ?>" method="get">
    <div class="catalog-filters__top">
        <label class="catalog-field catalog-field--search"><span>Search inventory</span><input type="search" name="vehicle_search" value="<?php echo esc_attr((string) ($filters['vehicle_search'] ?? '')); ?>" placeholder="Make, model or version"></label>
        <?php foreach (['make' => 'Make', 'model' => 'Model', 'body' => 'Body type', 'colour' => 'Colour'] as $key => $label) : ?>
            <label class="catalog-field"><span><?php echo esc_html($label); ?></span><select name="<?php echo esc_attr($key); ?>"><option value="">Any</option><?php foreach ($terms[$key] as $term) : ?><option value="<?php echo esc_attr($term->name); ?>" <?php selected((string) ($filters[$key] ?? ''), $term->name); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label>
        <?php endforeach; ?>
    </div>

    <details class="catalog-filters__advanced" <?php echo apmg_has_advanced_vehicle_filters($filters) ? 'open' : ''; ?>>
        <summary>More filters <i class="fas fa-sliders-h" aria-hidden="true"></i></summary>
        <div class="catalog-filters__advanced-grid">
            <fieldset><legend>Fuel type</legend><div class="catalog-checks"><?php foreach ($terms['fuel'] as $term) : ?><label><input type="checkbox" name="fuel[]" value="<?php echo esc_attr($term->name); ?>" <?php checked(in_array(strtolower($term->name), $selected_values('fuel'), true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div></fieldset>
            <fieldset><legend>Transmission</legend><div class="catalog-checks"><?php foreach ($terms['transmission'] as $term) : ?><label><input type="checkbox" name="transmission[]" value="<?php echo esc_attr($term->name); ?>" <?php checked(in_array(strtolower($term->name), $selected_values('transmission'), true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div></fieldset>
            <div class="catalog-range"><span>Price</span><label>From <input type="number" min="0" step="1000" name="price_min" value="<?php echo esc_attr((string) ($filters['price_min'] ?? '')); ?>" placeholder="€0"></label><label>To <input type="number" min="0" step="1000" name="price_max" value="<?php echo esc_attr((string) ($filters['price_max'] ?? '')); ?>" placeholder="Any"></label></div>
            <div class="catalog-range"><span>Vehicle year</span><label>From <input type="number" min="1950" max="2100" name="year_min" value="<?php echo esc_attr((string) ($filters['year_min'] ?? '')); ?>" placeholder="Any"></label><label>To <input type="number" min="1950" max="2100" name="year_max" value="<?php echo esc_attr((string) ($filters['year_max'] ?? '')); ?>" placeholder="Any"></label></div>
            <label class="catalog-field"><span>Maximum mileage</span><input type="number" min="0" step="1000" name="mileage_max" value="<?php echo esc_attr((string) ($filters['mileage_max'] ?? '')); ?>" placeholder="Any"></label>
            <label class="catalog-field"><span>Minimum engine size</span><input type="number" min="0" max="10" step="0.1" name="engine_min" value="<?php echo esc_attr((string) ($filters['engine_min'] ?? '')); ?>" placeholder="Any"></label>
            <label class="catalog-field"><span>Doors</span><select name="doors"><option value="">Any</option><?php foreach ([2, 3, 4, 5] as $number) : ?><option value="<?php echo esc_attr((string) $number); ?>" <?php selected((int) ($filters['doors'] ?? 0), $number); ?>><?php echo esc_html((string) $number); ?></option><?php endforeach; ?></select></label>
        </div>
    </details>
    <div class="catalog-filters__actions"><button type="submit">Search Cars <i class="fas fa-arrow-right" aria-hidden="true"></i></button><a href="<?php echo esc_url($form_action); ?>">Clear filters</a></div>
</form>
