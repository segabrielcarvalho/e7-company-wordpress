<?php
get_header();
while (have_posts()) : the_post();
?>
<main>
    <?php get_template_part('template-parts/page-hero', null, ['title' => get_the_title()]); ?>
    <article class="commercial-page kit-container"><?php the_content(); ?></article>
</main>
<?php endwhile; get_footer();
