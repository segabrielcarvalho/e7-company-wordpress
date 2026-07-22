<?php get_header(); ?>
<main class="flex min-h-[70vh] items-center justify-center bg-ink px-5 text-center text-white"><div><p class="font-display text-9xl font-black text-accent">404</p><h1 class="mt-3 text-4xl font-black uppercase">Page Not Found</h1><p class="mx-auto mt-4 max-w-lg text-gray-400">The page you are looking for does not exist or has been moved.</p><a class="mt-8 inline-flex bg-accent px-7 py-4 text-xs font-black uppercase tracking-widest" href="<?php echo esc_url(home_url('/')); ?>">Back to home</a></div></main>
<?php get_footer();
