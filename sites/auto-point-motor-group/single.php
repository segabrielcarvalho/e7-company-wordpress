<?php get_header(); while (have_posts()) : the_post(); get_template_part('template-parts/page-hero', null, ['title' => get_the_title()]); ?>
<main class="section-pad"><article class="prose prose-lg mx-auto max-w-4xl px-5"><?php if (has_post_thumbnail()) : ?><div class="not-prose mb-10"><?php the_post_thumbnail('full', ['class' => 'w-full']); ?></div><?php endif; ?><p class="text-xs font-bold uppercase tracking-wider text-accent"><?php echo esc_html(get_the_date()); ?> · <?php the_author(); ?></p><?php the_content(); ?></article></main>
<?php endwhile; get_footer();
