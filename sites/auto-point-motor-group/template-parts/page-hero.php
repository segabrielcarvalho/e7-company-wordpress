<?php $title = $args['title'] ?? get_the_title(); ?>
<section class="brand-page-hero">
    <div class="kit-container"><p><a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>/</span><?php echo esc_html($title); ?></p><h1><?php echo esc_html($title); ?></h1></div>
</section>
