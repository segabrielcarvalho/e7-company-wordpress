<?php
/** Default template. */
get_header();
?>
<main class="mx-auto min-h-[60vh] max-w-6xl px-5 py-20">
    <?php if (have_posts()) : ?>
        <div class="grid gap-8 md:grid-cols-3">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('overflow-hidden bg-white shadow-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large', ['class' => 'h-52 w-full object-cover']); ?></a><?php endif; ?>
                    <div class="p-6"><p class="mb-2 text-xs font-bold uppercase tracking-[.2em] text-accent"><?php echo esc_html(get_the_date()); ?></p><h2 class="text-xl font-black uppercase"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="mt-3 text-sm text-gray-500"><?php the_excerpt(); ?></div></div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <h1 class="text-4xl font-black uppercase"><?php esc_html_e('Nothing found', 'auto-point-motor-group'); ?></h1>
    <?php endif; ?>
</main>
<?php get_footer();
