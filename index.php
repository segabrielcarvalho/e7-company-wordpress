<?php
/**
 * Default content template.
 *
 * @package E7_Company
 */

get_header();
?>
<main id="main-content" class="min-h-screen bg-neutral-50 px-5 pb-24 pt-32 sm:px-8 lg:px-12">
    <div class="mx-auto max-w-4xl">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('rounded-3xl bg-white p-8 shadow-sm sm:p-12'); ?>>
                    <h1 class="font-display text-4xl font-bold tracking-tight sm:text-6xl"><?php the_title(); ?></h1>
                    <div class="prose mt-8 max-w-none"><?php the_content(); ?></div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <p><?php esc_html_e('Nothing found.', 'e7-company'); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
