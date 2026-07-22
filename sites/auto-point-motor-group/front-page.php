<?php get_header(); ?>
<main>
    <?php get_template_part('template-parts/home-search-hero'); ?>
    <?php get_template_part('template-parts/vehicle-catalog', null, ['action' => home_url('/'), 'per_page' => 12, 'show_filters' => true]); ?>

    <section class="buying-section" aria-labelledby="buying-title">
        <div class="kit-container">
            <header class="section-heading"><span>Why choose us</span><h2 id="buying-title">Buying with Autopoint Motor Group</h2><p>Ireland’s award-winning trusted car dealer, helping drivers find quality vehicles with confidence.</p></header>
            <div class="buying-grid">
                <article><i class="fas fa-undo" aria-hidden="true"></i><h3>7 Day Money Back Guarantee</h3><p>Buy with added peace of mind and a straightforward guarantee.</p></article>
                <article><i class="fas fa-shield-alt" aria-hidden="true"></i><h3>Up to 2 Years Warranty</h3><p>Warranty options are available across our approved used-car range.</p></article>
                <article><i class="fas fa-check-circle" aria-hidden="true"></i><h3>Reserve Online for €99</h3><p>Secure the vehicle you want before visiting our Tralee showroom.</p></article>
                <article><i class="fas fa-truck" aria-hidden="true"></i><h3>Free Nationwide Delivery</h3><p>We make collecting your next car simple, wherever you are in Ireland.</p></article>
            </div>
        </div>
    </section>

    <?php get_template_part('template-parts/google-reviews'); ?>

    <section class="trust-section">
        <div class="kit-container trust-section__grid">
            <div><span>Established expertise</span><h2>Celebrating 30 years in the motor industry</h2><p>Autopoint Motor Group is built on honesty, transparency and customer satisfaction. Every vehicle is carefully inspected and prepared before sale.</p><a href="<?php echo esc_url(home_url('/about/')); ?>">Why Autopoint <i class="fas fa-arrow-right" aria-hidden="true"></i></a></div>
            <aside><strong>Independent Dealer of the Year 2024</strong><p>National award-winning service, flexible finance and a team that puts the customer first.</p></aside>
        </div>
    </section>
</main>
<?php get_footer(); ?>
