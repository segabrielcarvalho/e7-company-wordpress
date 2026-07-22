<?php
/** @var array $args */
$vehicle = (array) ($args['vehicle'] ?? []);
$vehicle_id = absint($vehicle['id'] ?? get_the_ID());
$price = absint($vehicle['price'] ?? 0);
$settings = get_option('apmg_commerce_settings', []);
$reservation_amount = is_array($settings) ? max(1, absint($settings['reservation_amount'] ?? 99)) : 99;
$can_reserve = function_exists('apmg_commerce_can_checkout') && apmg_commerce_can_checkout($vehicle_id, 'reserve');
$can_pay_full = $price > 0 && function_exists('apmg_commerce_can_checkout') && apmg_commerce_can_checkout($vehicle_id, 'full');
$turnstile_site_key = is_array($settings) ? sanitize_text_field((string) ($settings['turnstile_site_key'] ?? '')) : '';
$turnstile_ready = $turnstile_site_key !== '' && defined('APMG_TURNSTILE_SECRET_KEY') && (string) APMG_TURNSTILE_SECRET_KEY !== '';
if (($can_reserve || $can_pay_full) && $turnstile_ready) {
    wp_enqueue_script('cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, ['strategy' => 'defer', 'in_footer' => true]);
}
$form_url = static fn(string $type): string => function_exists('apmg_commerce_form_url')
    ? apmg_commerce_form_url($type, $vehicle_id)
    : add_query_arg('vehicle_id', $vehicle_id, home_url('/contact-us/'));
?>
<div class="vehicle-commerce" data-motion-item>
    <?php if (isset($_GET['commerce_error'])) : ?>
        <p class="vehicle-commerce__message" role="alert">This vehicle cannot enter online checkout right now. Please enquire or call our team.</p>
    <?php endif; ?>

    <?php if ($can_reserve) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="apmg_start_checkout">
            <input type="hidden" name="vehicle_id" value="<?php echo esc_attr((string) $vehicle_id); ?>">
            <input type="hidden" name="payment_mode" value="reserve">
            <?php wp_nonce_field('apmg_start_checkout_' . $vehicle_id); ?>
            <?php if ($turnstile_ready) : ?><div class="cf-turnstile" data-sitekey="<?php echo esc_attr($turnstile_site_key); ?>" data-action="apmg-checkout"></div><?php endif; ?>
            <button class="vehicle-commerce__reserve" type="submit">Reserve Now for €<?php echo esc_html(number_format_i18n($reservation_amount)); ?></button>
        </form>
    <?php else : ?>
        <button class="vehicle-commerce__reserve" type="button" disabled aria-disabled="true">Reserve Now for €<?php echo esc_html(number_format_i18n($reservation_amount)); ?></button>
    <?php endif; ?>

    <div class="vehicle-commerce__secondary">
        <a href="<?php echo esc_url($form_url('enquire')); ?>">Enquire</a>
        <a href="<?php echo esc_url($form_url('finance')); ?>">Get Finance</a>
    </div>
    <a class="vehicle-commerce__exchange" href="<?php echo esc_url($form_url('exchange')); ?>">Exchange My Current Car</a>

    <?php if ($price > 0) : ?>
        <?php if ($can_pay_full) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="apmg_start_checkout">
                <input type="hidden" name="vehicle_id" value="<?php echo esc_attr((string) $vehicle_id); ?>">
                <input type="hidden" name="payment_mode" value="full">
                <?php wp_nonce_field('apmg_start_checkout_' . $vehicle_id); ?>
                <?php if ($turnstile_ready) : ?><div class="cf-turnstile" data-sitekey="<?php echo esc_attr($turnstile_site_key); ?>" data-action="apmg-checkout"></div><?php endif; ?>
                <button class="vehicle-commerce__full" type="submit">Pay in Full Online</button>
            </form>
        <?php else : ?>
            <button class="vehicle-commerce__full" type="button" disabled aria-disabled="true">Pay in Full Online</button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$can_reserve && !$can_pay_full) : ?>
        <p class="vehicle-commerce__offline">Online payments are not active yet. Enquire or call to reserve this vehicle with our team.</p>
    <?php endif; ?>
    <a class="vehicle-commerce__call" href="tel:+353667102545"><i class="fas fa-phone" aria-hidden="true"></i> Call (066) 710 2545</a>
</div>
