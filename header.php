<?php
/**
 * Site header.
 *
 * @package E7_Company
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-white text-neutral-950 antialiased'); ?>>
<?php wp_body_open(); ?>
<a class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-full focus:bg-brand-600 focus:px-5 focus:py-3 focus:text-white" href="#main-content">
    <?php esc_html_e('Skip to content', 'e7-company'); ?>
</a>
<header class="site-header fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-neutral-950/75 backdrop-blur-xl transition-all duration-300" data-site-header>
    <div class="mx-auto flex h-20 max-w-[1440px] items-center justify-between px-5 sm:px-8 lg:px-12">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="flex items-center" aria-label="E7 Company home">
            <img class="-my-2 h-24 w-auto object-contain" src="<?php echo esc_url(e7_company_asset('brand/e7-company-logo-transparent.webp')); ?>" alt="E7 Company" width="512" height="238" fetchpriority="high">
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary navigation">
            <a class="nav-link" href="#services">Services</a>
            <a class="nav-link" href="#industries">Industries</a>
            <a class="nav-link" href="#why-e7">About</a>
            <a class="nav-link" href="#case-studies">Case studies</a>
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <?php e7_company_language_switcher(); ?>
            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-white/50">Let’s build</span>
            <a class="button-primary" href="#contact">Start a project</a>
        </div>

        <button class="grid h-11 w-11 place-items-center rounded-full border border-white/20 text-white lg:hidden" type="button" aria-expanded="false" aria-controls="mobile-navigation" data-menu-toggle>
            <span class="sr-only">Open menu</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>
    </div>

    <nav id="mobile-navigation" class="hidden border-t border-white/10 bg-neutral-950/95 px-5 py-5 backdrop-blur-xl lg:hidden" data-mobile-menu aria-label="Mobile navigation">
        <div class="mx-auto grid max-w-[1440px] gap-1">
            <a class="mobile-nav-link" href="#services">Services</a>
            <a class="mobile-nav-link" href="#industries">Industries</a>
            <a class="mobile-nav-link" href="#why-e7">About</a>
            <a class="mobile-nav-link" href="#case-studies">Case studies</a>
            <?php e7_company_language_switcher(); ?>
            <a class="mobile-nav-link text-brand-400" href="#contact">Start a project</a>
        </div>
    </nav>
</header>
