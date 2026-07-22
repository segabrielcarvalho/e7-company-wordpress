<?php
get_header();
get_template_part('template-parts/page-hero', null, ['title' => 'Blog Page']);
?>
<main class="section-pad bg-[#fafafa]">
    <div class="container-site">
        <h2 class="ribbon-title">Car Buying Advice</h2>
        <div class="mt-14 grid gap-7 sm:grid-cols-2 lg:grid-cols-3">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <article class="blog-card">
                        <?php if (has_post_thumbnail()) { the_post_thumbnail('large'); } ?>
                        <div class="p-5">
                            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <p><?php echo esc_html(get_the_date()); ?> · Autopoint Motor Group</p>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php else : ?>
                <p>No articles published yet.</p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php get_footer();
