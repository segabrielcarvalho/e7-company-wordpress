<?php get_header(); ?>
<main class="catalog-page">
    <section class="catalog-page__hero"><div class="kit-container"><span>Autopoint Motor Group</span><h1>Used Cars</h1><p>Browse our complete range and combine as many filters as you need.</p></div></section>
    <?php get_template_part('template-parts/vehicle-catalog', null, ['action' => get_permalink(), 'per_page' => 12, 'show_filters' => true]); ?>
</main>
<?php get_footer(); ?>
