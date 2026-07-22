<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('bg-white text-ink antialiased'); ?>><?php wp_body_open(); ?>
<header class="brand-header">
    <div class="brand-header__main kit-container">
        <a class="brand-header__logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo apmg_image('logo.svg'); ?>" alt="Autopoint Motor Group" width="156" height="85"></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav"><span aria-hidden="true">☰</span><span class="sr-only">Menu</span></button>
        <nav id="primary-nav" class="primary-nav" aria-label="Primary navigation">
            <ul>
                <li><a href="<?php echo esc_url(home_url('/')); ?>">Home</a></li>
                <li><a href="<?php echo esc_url(home_url('/listing/')); ?>">Used Cars</a></li>
                <li><a href="<?php echo esc_url(home_url('/about/')); ?>">About Us</a></li>
                <li><a href="<?php echo esc_url(home_url('/finance/')); ?>">Finance</a></li>
                <li><a href="<?php echo esc_url(home_url('/exchange/')); ?>">We Buy Cars</a></li>
                <li><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Service</a></li>
                <li><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a></li>
            </ul>
        </nav>
        <a class="brand-header__call" href="tel:+353667102545"><i class="fas fa-phone-alt" aria-hidden="true"></i><span>Call us now</span><strong>(066) 710 2545</strong></a>
    </div>
    <div class="brand-benefits">
        <div class="kit-container">
            <span><i class="fas fa-undo" aria-hidden="true"></i>7 Day Money Back Guarantee</span>
            <span><i class="fas fa-shield-alt" aria-hidden="true"></i>Up to 2 Years Warranty Available</span>
            <span><i class="fas fa-check-circle" aria-hidden="true"></i>Reserve your next car for just €99</span>
            <span><i class="fas fa-truck" aria-hidden="true"></i>Free Nationwide Delivery</span>
        </div>
    </div>
</header>
