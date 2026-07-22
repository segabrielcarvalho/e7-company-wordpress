<div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach (apmg_team() as $member) : ?>
        <article class="team-card text-center"><div class="team-frame"><img src="<?php echo apmg_image($member[2]); ?>" alt="<?php echo esc_attr($member[0]); ?>" loading="lazy"></div><div class="relative -mt-5 mx-auto w-[85%] bg-accent px-3 py-2 text-white"><h3 class="text-xs font-black uppercase"><?php echo esc_html($member[0]); ?></h3></div><p class="mt-2 text-[10px] uppercase tracking-wider text-gray-400"><?php echo esc_html($member[1]); ?></p></article>
    <?php endforeach; ?>
</div>
