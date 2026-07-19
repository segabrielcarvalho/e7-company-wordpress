<?php
/**
 * Site footer.
 *
 * @package E7_Company
 */
?>
<footer class="bg-neutral-950 text-white">
    <div class="mx-auto grid max-w-[1440px] gap-12 px-5 py-14 sm:px-8 lg:grid-cols-[1.2fr_2fr] lg:px-12 lg:py-20">
        <div>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center" aria-label="E7 Company home">
                <img class="-my-7 h-28 w-auto object-contain" src="<?php echo esc_url(e7_company_asset('brand/e7-company-logo-transparent-256.webp')); ?>" alt="E7 Company" width="256" height="119" loading="lazy">
            </a>
            <p class="mt-6 max-w-sm text-sm leading-7 text-white/55">Customized software solutions built to move ambitious businesses forward.</p>
        </div>
        <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
            <div>
                <p class="footer-title">Company</p>
                <a class="footer-link" href="#why-e7">About us</a>
                <a class="footer-link" href="#approach">Our approach</a>
                <a class="footer-link" href="#testimonials">Reviews</a>
            </div>
            <div>
                <p class="footer-title">Services</p>
                <a class="footer-link" href="#services">Web development</a>
                <a class="footer-link" href="#services">Mobile apps</a>
                <a class="footer-link" href="#technology">Cloud & DevOps</a>
            </div>
            <div>
                <p class="footer-title">Connect</p>
                <a class="footer-link" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">Contact us</a>
                <a class="footer-link" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">WhatsApp +55 62 99550-6531</a>
                <span class="footer-link">Brazil · Worldwide</span>
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1440px] flex-col gap-3 px-5 py-6 text-xs text-white/60 sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-12">
            <p>© <?php echo esc_html(wp_date('Y')); ?> E7 Company Tecnologia LTDA. All rights reserved.</p>
            <p>Strategy · Design · Technology</p>
        </div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
