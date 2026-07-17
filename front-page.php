<?php
/**
 * Institutional home page.
 *
 * @package E7_Company
 */

$services = [
    ['number' => '01', 'title' => 'Evaluation & Design', 'copy' => 'We turn complex requirements into clear digital products through discovery, UX strategy and interface design.'],
    ['number' => '02', 'title' => 'Custom Software', 'copy' => 'Purpose-built platforms designed around your operations, users and long-term business goals.'],
    ['number' => '03', 'title' => 'Web Development', 'copy' => 'Fast, accessible and scalable web experiences built with modern engineering practices.'],
    ['number' => '04', 'title' => 'Mobile Development', 'copy' => 'Mobile products that feel native, work reliably and create value wherever your customers are.'],
    ['number' => '05', 'title' => 'Maintenance & Support', 'copy' => 'Continuous improvement, monitoring and technical support that keep your product moving.'],
];

$industries = [
    ['title' => 'Logistics', 'image' => 'orizonjpg-01-480.webp', 'copy' => 'Connected operations and real-time visibility.'],
    ['title' => 'Government', 'image' => 'orizonjpg-02-480.webp', 'copy' => 'Reliable services designed for people.'],
    ['title' => 'Healthcare', 'image' => 'orizonjpg-03-480.webp', 'copy' => 'Secure products for critical journeys.'],
];

$cases = [
    ['title' => 'Workflow System Energy', 'image' => 'Porto-06-400.webp', 'tag' => 'Product design'],
    ['title' => 'SaaS for End-to-End Analytics', 'image' => 'Porto-07-400.webp', 'tag' => 'Web platform'],
    ['title' => 'Workload Management', 'image' => 'Porto-08-400.webp', 'tag' => 'Cloud systems'],
    ['title' => 'Qpay E-wallet Mobile', 'image' => 'Porto-09-400.webp', 'tag' => 'Mobile app'],
    ['title' => 'Fitness & Personal Trainers', 'image' => 'Porto-010-400.webp', 'tag' => 'Digital product'],
    ['title' => 'Nutrition Coach & Nutritionist', 'image' => 'Porto-011-400.webp', 'tag' => 'Experience design'],
];

$testimonials = [
    ['name' => 'Leslie Alexander', 'company' => 'VoteMe', 'image' => 'testimonial-img-01-96.webp', 'quote' => 'The team transformed a complex idea into a product that feels simple, fast and ready to scale.'],
    ['name' => 'Kristin Watson', 'company' => 'FIA', 'image' => 'testimonial-img-02-96.webp', 'quote' => 'Clear communication, strong design thinking and a real commitment to the result from day one.'],
    ['name' => 'Robert Fox', 'company' => 'Assessoria Alpha', 'image' => 'testimonial-img-03-96.webp', 'quote' => 'They understood our business before writing code. That made every technical decision stronger.'],
    ['name' => 'Jenny Wilson', 'company' => 'Asaas', 'image' => 'testimonial-img-04-96.webp', 'quote' => 'A reliable partner who brought clarity to the process and quality to every delivery.'],
];

$technology_logos = [
    ['name' => 'React', 'icon' => 'react.svg'],
    ['name' => 'Next.js', 'icon' => 'nextjs.svg'],
    ['name' => 'Node.js', 'icon' => 'nodejs.svg'],
    ['name' => 'TypeScript', 'icon' => 'typescript.svg'],
    ['name' => 'AWS', 'icon' => 'aws.svg'],
    ['name' => 'Cloudflare', 'icon' => 'cloudflare.svg'],
    ['name' => 'Docker', 'icon' => 'docker.svg'],
    ['name' => 'WordPress', 'icon' => 'wordpress.svg'],
];

$technology_categories = [
    [
        'id' => 'web-platform',
        'label' => 'Web Platform',
        'groups' => [
            [
                'title' => 'Front-End',
                'items' => [
                    ['name' => 'React', 'icon' => 'react.svg'],
                    ['name' => 'Next.js', 'icon' => 'nextjs.svg'],
                    ['name' => 'TypeScript', 'icon' => 'typescript.svg'],
                    ['name' => 'Tailwind CSS', 'icon' => 'tailwindcss.svg'],
                    ['name' => 'WordPress', 'icon' => 'wordpress.svg'],
                    ['name' => 'Vue.js', 'icon' => 'vuejs.svg'],
                ],
            ],
            [
                'title' => 'Back-End',
                'items' => [
                    ['name' => 'Node.js', 'icon' => 'nodejs.svg'],
                    ['name' => 'PHP', 'icon' => 'php.svg'],
                    ['name' => 'NestJS', 'icon' => 'nestjs.svg'],
                    ['name' => 'GraphQL', 'icon' => 'graphql.svg'],
                ],
            ],
        ],
    ],
    [
        'id' => 'cloud-devops',
        'label' => 'Cloud & DevOps',
        'groups' => [
            [
                'title' => 'Cloud Infrastructure',
                'items' => [
                    ['name' => 'AWS', 'icon' => 'aws.svg'],
                    ['name' => 'Cloudflare', 'icon' => 'cloudflare.svg'],
                    ['name' => 'Docker', 'icon' => 'docker.svg'],
                ],
            ],
        ],
    ],
    [
        'id' => 'database',
        'label' => 'Database',
        'groups' => [
            [
                'title' => 'Data Layer',
                'items' => [
                    ['name' => 'PostgreSQL', 'icon' => 'postgresql.svg'],
                    ['name' => 'Redis', 'icon' => 'redis.svg'],
                    ['name' => 'GraphQL', 'icon' => 'graphql.svg'],
                ],
            ],
        ],
    ],
    [
        'id' => 'mobile-apps',
        'label' => 'Mobile Apps',
        'groups' => [
            [
                'title' => 'Mobile Development',
                'items' => [
                    ['name' => 'React Native', 'icon' => 'react.svg'],
                    ['name' => 'TypeScript', 'icon' => 'typescript.svg'],
                    ['name' => 'Node.js APIs', 'icon' => 'nodejs.svg'],
                ],
            ],
        ],
    ],
];

$approach_steps = [
    [
        'id' => 'discover',
        'number' => '01',
        'label' => 'Discovery',
        'title' => 'Discovery & Strategy',
        'copy' => 'We align business goals, user context and technical constraints before defining the strongest path forward.',
        'points' => ['Business goals & context', 'User research & journeys', 'Product roadmap'],
    ],
    [
        'id' => 'design',
        'number' => '02',
        'label' => 'Design',
        'title' => 'UI/UX Design',
        'copy' => 'We turn strategy into clear, intuitive experiences through research, prototyping and a purposeful visual system.',
        'points' => ['User-centered research', 'Wireframes & prototypes', 'Visual design & branding'],
    ],
    [
        'id' => 'build',
        'number' => '03',
        'label' => 'Development',
        'title' => 'Product Engineering',
        'copy' => 'We engineer reliable digital products in focused iterations, connecting scalable architecture with polished interfaces.',
        'points' => ['Scalable architecture', 'Front-end & back-end', 'Quality & automation'],
    ],
    [
        'id' => 'evolve',
        'number' => '04',
        'label' => 'Evolution',
        'title' => 'Growth & Support',
        'copy' => 'We learn from real usage, improve continuously and keep the product healthy as the business grows.',
        'points' => ['Monitoring & insights', 'Continuous improvements', 'Technical support'],
    ],
];

get_header();
?>
<main id="main-content" class="overflow-hidden">
    <section class="relative flex min-h-[650px] items-start overflow-hidden bg-neutral-950 pb-8 pt-24 text-white sm:min-h-[720px] sm:items-end sm:pb-20 sm:pt-28 lg:min-h-[min(820px,100svh)] lg:items-center lg:pb-20 lg:pt-28">
        <div class="hero-dot-distortion absolute inset-0" aria-hidden="true">
            <canvas class="h-full w-full" data-hero-dot-canvas></canvas>
        </div>

        <div class="relative z-10 mx-auto w-full max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div data-reveal>
                <div class="mb-8 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.24em] text-brand-300">
                    <span class="h-px w-10 bg-brand-400"></span>
                    Software development & IT solutions
                </div>
                <h1 class="max-w-5xl font-display text-[clamp(3.4rem,8vw,8.6rem)] font-extrabold uppercase leading-[0.82] tracking-[-0.065em]">
                    Customized software
                    <span class="block text-brand-500">solutions</span>
                    for business
                </h1>
                <div class="mt-10 flex flex-col gap-6 sm:flex-row sm:items-center">
                    <a class="button-primary px-8 py-4" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">Start your project</a>
                    <p class="max-w-sm text-sm leading-7 text-white/50">Strategy, design and technology working together to transform ambitious ideas into useful digital products.</p>
                </div>
                <div class="mobile-hero-details mt-5 grid grid-cols-[1fr_auto] gap-3 sm:hidden" aria-label="E7 capabilities">
                    <div class="flex h-16 items-center justify-between rounded-2xl border border-white/10 bg-white/[0.05] px-4 backdrop-blur">
                        <div>
                            <span class="block text-[9px] font-bold uppercase tracking-[0.18em] text-brand-300">Digital systems</span>
                            <span class="mt-1 block text-xs font-semibold text-white/75">Design · Code · Scale</span>
                        </div>
                        <div class="flex items-end gap-1" aria-hidden="true">
                            <span class="h-3 w-1.5 rounded-full bg-brand-800"></span>
                            <span class="h-5 w-1.5 rounded-full bg-brand-600"></span>
                            <span class="h-8 w-1.5 rounded-full bg-brand-400"></span>
                            <span class="h-4 w-1.5 rounded-full bg-brand-700"></span>
                        </div>
                    </div>
                    <div class="grid h-16 w-20 place-items-center rounded-2xl border border-brand-400/25 bg-brand-950/60 text-center">
                        <span><strong class="block font-display text-xl text-brand-400">03</strong><span class="text-[8px] font-bold uppercase tracking-[0.12em] text-white/45">disciplines</span></span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="logo-cloud-mask overflow-hidden border-b border-neutral-200 bg-white py-5" aria-label="Technology partners">
        <div class="logo-cloud-track">
            <?php for ($logo_cloud_copy = 0; $logo_cloud_copy < 2; $logo_cloud_copy++) : ?>
                <div class="flex shrink-0 items-center gap-14 pr-14 sm:gap-20 sm:pr-20" <?php echo 1 === $logo_cloud_copy ? 'aria-hidden="true"' : ''; ?>>
                    <?php foreach ($technology_logos as $technology_logo) : ?>
                        <span class="group flex h-12 shrink-0 items-center gap-3 text-neutral-500 transition hover:text-neutral-950">
                            <img class="h-7 w-7 object-contain grayscale transition group-hover:grayscale-0 sm:h-8 sm:w-8" src="<?php echo esc_url(e7_company_asset('icons/' . $technology_logo['icon'])); ?>" alt="" width="32" height="32">
                            <span class="font-display text-base font-bold tracking-[-0.02em] sm:text-lg"><?php echo esc_html($technology_logo['name']); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section id="services" class="bg-white py-24 sm:py-32 lg:py-40">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="grid gap-14 lg:grid-cols-[.85fr_1.15fr] lg:gap-24">
                <div data-reveal>
                    <p class="eyebrow">What we do</p>
                    <h2 class="section-title">Expertise for every stage of your product.</h2>
                    <p class="mt-7 max-w-lg text-base leading-8 text-neutral-500">From the first conversation to continuous evolution, our team connects business thinking with purposeful technology.</p>
                </div>
                <div class="border-t border-neutral-200" data-reveal>
                    <?php foreach ($services as $service) : ?>
                        <article class="group grid gap-4 border-b border-neutral-200 py-7 sm:grid-cols-[3rem_1fr_1.15fr_auto] sm:items-start">
                            <span class="font-mono text-xs text-brand-600"><?php echo esc_html($service['number']); ?></span>
                            <h3 class="font-display text-2xl font-bold tracking-tight transition group-hover:text-brand-600"><?php echo esc_html($service['title']); ?></h3>
                            <p class="text-sm leading-7 text-neutral-500"><?php echo esc_html($service['copy']); ?></p>
                            <span class="grid h-10 w-10 place-items-center rounded-full border border-neutral-200 text-neutral-500 transition group-hover:border-brand-600 group-hover:bg-brand-600 group-hover:text-white" aria-hidden="true">↗</span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="industries" class="bg-neutral-950 py-24 text-white sm:py-28 lg:rounded-[2.5rem] lg:py-32">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="mb-12 grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
                <div data-reveal>
                    <p class="eyebrow !text-brand-400">Industries</p>
                    <h2 class="font-display text-4xl font-bold leading-none tracking-[-0.045em] sm:text-6xl">Solutions shaped around real industries.</h2>
                </div>
                <p class="max-w-xl text-base leading-8 text-white/50 lg:justify-self-end" data-reveal>We learn the context, constraints and opportunities of each market before designing the technology behind it.</p>
            </div>
            <div class="grid gap-5 lg:grid-cols-3">
                <?php foreach ($industries as $industry) : ?>
                    <article class="group overflow-hidden rounded-3xl border border-white/10 bg-white/[0.06]" data-reveal>
                        <div class="h-56 overflow-hidden">
                            <img class="h-full w-full object-cover grayscale transition duration-700 group-hover:scale-105 group-hover:grayscale-0" src="<?php echo esc_url(e7_company_asset('images/' . $industry['image'])); ?>" alt="" width="480" height="232" loading="lazy">
                        </div>
                        <div class="flex items-start justify-between gap-6 p-7">
                            <div><h3 class="font-display text-2xl font-bold"><?php echo esc_html($industry['title']); ?></h3><p class="mt-3 text-sm leading-6 text-white/60"><?php echo esc_html($industry['copy']); ?></p></div>
                            <span class="text-2xl text-brand-400">↗</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="why-e7" class="bg-white py-24 sm:py-32 lg:py-40">
        <div class="mx-auto grid max-w-[1440px] gap-14 px-5 sm:px-8 lg:grid-cols-[.9fr_1.1fr] lg:items-end lg:px-12">
            <div data-reveal>
                <p class="eyebrow">Why choose us?</p>
                <h2 class="section-title">A product partner focused on meaningful outcomes.</h2>
                <p class="mt-7 max-w-xl text-base leading-8 text-neutral-500">Our multidisciplinary team works as an extension of yours, combining clear processes, senior expertise and honest collaboration.</p>
                <a class="button-secondary mt-9" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">Meet E7 Company</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-[1fr_1.1fr]" data-reveal>
                <img class="h-full min-h-80 w-full rounded-3xl object-cover" src="<?php echo esc_url(e7_company_asset('images/orizonjpg-010-400.webp')); ?>" srcset="<?php echo esc_url(e7_company_asset('images/orizonjpg-010-320.webp')); ?> 320w, <?php echo esc_url(e7_company_asset('images/orizonjpg-010-400.webp')); ?> 400w" sizes="(min-width: 1024px) 300px, 100vw" alt="Team collaborating around a table" width="400" height="570" loading="lazy">
                <div class="grid grid-cols-2 rounded-3xl bg-neutral-50 p-2">
                    <?php foreach ([['04', 'Delivery stages'], ['23+', 'Specialists'], ['150+', 'Projects delivered'], ['2540+', 'Ideas explored']] as $stat) : ?>
                        <div class="border border-white p-5 sm:p-7">
                            <strong class="block font-display text-3xl font-bold tracking-tight text-brand-600 sm:text-4xl"><?php echo esc_html($stat[0]); ?></strong>
                            <span class="mt-2 block text-xs uppercase leading-5 tracking-[0.12em] text-neutral-500"><?php echo esc_html($stat[1]); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="case-studies" class="bg-neutral-50 py-24 sm:py-32 lg:py-40">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="mb-14 flex flex-col gap-7 sm:flex-row sm:items-end sm:justify-between" data-reveal>
                <div><p class="eyebrow">Selected work</p><h2 class="section-title">Case studies</h2></div>
                <a class="button-secondary" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">View all projects</a>
            </div>
            <div class="grid gap-x-6 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($cases as $case) : ?>
                    <article class="group" data-reveal>
                        <div class="aspect-[1.5] overflow-hidden rounded-3xl bg-neutral-200">
                            <img class="h-full w-full object-cover transition duration-700 group-hover:scale-105" src="<?php echo esc_url(e7_company_asset('images/' . $case['image'])); ?>" alt="" width="400" height="266" loading="lazy">
                        </div>
                        <div class="mt-5 flex items-start justify-between gap-5">
                            <div><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand-600"><?php echo esc_html($case['tag']); ?></p><h3 class="mt-2 font-display text-2xl font-bold leading-tight tracking-tight"><?php echo esc_html($case['title']); ?></h3></div>
                            <span class="mt-1 text-xl transition group-hover:-translate-y-1 group-hover:translate-x-1 group-hover:text-brand-600">↗</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="partnership" class="relative overflow-hidden bg-neutral-950 py-24 text-white sm:py-32 lg:py-40">
        <div class="pointer-events-none absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,.08)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.08)_1px,transparent_1px)] [background-size:64px_64px]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full bg-brand-600/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-[1440px] gap-14 px-5 sm:px-8 lg:grid-cols-[1.15fr_.85fr] lg:items-center lg:gap-24 lg:px-12">
            <div data-reveal>
                <p class="eyebrow !text-brand-400">Built for what comes next</p>
                <h2 class="max-w-4xl font-display text-5xl font-bold leading-[.95] tracking-[-0.055em] sm:text-7xl lg:text-8xl">A technical partner for your next big move.</h2>
                <p class="mt-8 max-w-2xl text-base leading-8 text-white/60 sm:text-lg">From the first decision to continuous evolution, we bring product thinking and engineering discipline together to build digital solutions that last.</p>
                <a class="button-primary mt-10" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">Start a conversation</a>
            </div>

            <div class="grid gap-3" data-reveal>
                <?php foreach ([
                    ['01', 'Think beyond the brief', 'We challenge assumptions and connect every technical choice to a meaningful business outcome.'],
                    ['02', 'Build with clarity', 'Transparent decisions, focused execution and a process your team can follow from start to finish.'],
                    ['03', 'Evolve without friction', 'A solid foundation that can adapt as your product, operation and ambition grow.'],
                ] as $principle) : ?>
                    <article class="rounded-2xl border border-white/10 bg-white/[.04] p-6 backdrop-blur-sm transition duration-300 hover:border-brand-400/50 hover:bg-white/[.07] sm:p-8">
                        <div class="flex gap-5 sm:gap-7">
                            <span class="pt-1 font-mono text-xs text-brand-400"><?php echo esc_html($principle[0]); ?></span>
                            <div>
                                <h3 class="font-display text-2xl font-bold tracking-tight"><?php echo esc_html($principle[1]); ?></h3>
                                <p class="mt-3 text-sm leading-7 text-white/55"><?php echo esc_html($principle[2]); ?></p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="technology" class="bg-white py-24 sm:py-32 lg:py-36">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="border-b border-neutral-200 pb-8" data-reveal>
                <p class="eyebrow">Our capabilities</p>
                <h2 class="font-display text-4xl font-bold uppercase tracking-[-0.045em] sm:text-5xl">Technology Stack</h2>
            </div>

            <div class="mt-10 grid min-w-0 gap-10 lg:grid-cols-[17rem_1fr] lg:gap-16" data-reveal>
                <aside class="min-w-0" aria-label="Technology categories">
                    <div class="technology-stack-nav flex gap-3 overflow-x-auto border-b border-neutral-200 pb-4 lg:grid lg:gap-1 lg:border-b-0 lg:pb-0" role="tablist" aria-label="Technology categories">
                        <?php foreach ($technology_categories as $category_index => $category) : ?>
                            <button
                                id="technology-tab-<?php echo esc_attr($category['id']); ?>"
                                class="technology-tab<?php echo 0 === $category_index ? ' is-active' : ''; ?>"
                                type="button"
                                role="tab"
                                aria-selected="<?php echo 0 === $category_index ? 'true' : 'false'; ?>"
                                aria-controls="technology-panel-<?php echo esc_attr($category['id']); ?>"
                                data-technology-tab="<?php echo esc_attr($category['id']); ?>"
                            ><?php echo esc_html($category['label']); ?></button>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <div class="min-h-[24rem] min-w-0">
                    <?php foreach ($technology_categories as $category_index => $category) : ?>
                        <div
                            id="technology-panel-<?php echo esc_attr($category['id']); ?>"
                            class="technology-panel<?php echo 0 === $category_index ? ' is-active' : ''; ?> space-y-10"
                            role="tabpanel"
                            aria-labelledby="technology-tab-<?php echo esc_attr($category['id']); ?>"
                            data-technology-panel="<?php echo esc_attr($category['id']); ?>"
                            <?php echo 0 === $category_index ? '' : 'hidden'; ?>
                        >
                            <?php foreach ($category['groups'] as $stack_group) : ?>
                                <div>
                                    <h3 class="mb-5 font-display text-2xl font-bold tracking-tight"><?php echo esc_html($stack_group['title']); ?></h3>
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                                        <?php foreach ($stack_group['items'] as $technology) : ?>
                                            <div class="technology-card group flex min-h-16 items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50 px-4 text-neutral-950 transition hover:-translate-y-0.5 hover:border-brand-200 hover:bg-brand-50">
                                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white shadow-sm">
                                                    <img class="h-6 w-6 object-contain transition group-hover:scale-110" src="<?php echo esc_url(e7_company_asset('icons/' . $technology['icon'])); ?>" alt="" width="24" height="24" loading="lazy">
                                                </span>
                                                <span class="text-xs font-bold uppercase tracking-[0.1em] text-neutral-700 transition group-hover:text-brand-700"><?php echo esc_html($technology['name']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="approach" class="bg-neutral-950 py-20 text-white sm:py-28 lg:rounded-[2.5rem]">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="grid gap-10 border-b border-white/10 pb-12 lg:grid-cols-[1fr_1fr] lg:items-start lg:gap-24" data-reveal>
                <div>
                    <h2 class="font-display text-4xl font-bold uppercase leading-none tracking-[-0.045em] sm:text-6xl">Our approach</h2>
                    <a class="button-primary mt-6 px-7" href="<?php echo esc_url(e7_company_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">Work with us</a>
                </div>
                <p class="max-w-xl text-sm leading-7 text-white/55 lg:justify-self-end lg:pt-1">From the first strategic conversation to continuous product evolution, our process creates clarity, reduces risk and connects every decision to a meaningful business outcome.</p>
            </div>

            <div class="mt-12 grid gap-12 lg:grid-cols-[13rem_22rem_1fr] lg:items-center lg:gap-10" data-reveal>
                <div class="approach-tabs flex gap-2 overflow-x-auto border-white/10 pb-2 lg:grid lg:border-r lg:pb-0 lg:pr-8" role="tablist" aria-label="Approach stages">
                    <?php foreach ($approach_steps as $step_index => $step) : ?>
                        <button
                            id="approach-tab-<?php echo esc_attr($step['id']); ?>"
                            class="approach-tab<?php echo 0 === $step_index ? ' is-active' : ''; ?>"
                            type="button"
                            role="tab"
                            aria-selected="<?php echo 0 === $step_index ? 'true' : 'false'; ?>"
                            aria-controls="approach-panel-<?php echo esc_attr($step['id']); ?>"
                            data-approach-tab="<?php echo esc_attr($step['id']); ?>"
                        >
                            <span><?php echo esc_html($step['number']); ?></span>
                            <?php echo esc_html($step['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="approach-illustration mx-auto w-full max-w-[22rem]" aria-hidden="true">
                    <svg viewBox="0 0 360 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M95 137C95 84 131 45 180 45C229 45 265 84 265 137" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                        <path d="M180 45L142 160L180 190L218 160L180 45Z" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/>
                        <circle cx="180" cy="45" r="8" fill="#0a0a0a" stroke="currentColor" stroke-width="5"/>
                        <circle cx="180" cy="145" r="13" fill="#3b82f6" stroke="#dbeafe" stroke-width="5"/>
                        <path d="M154 189H206V207H154V189Z" stroke="currentColor" stroke-width="5"/>
                        <path d="M160 207H200V239H160V207Z" stroke="currentColor" stroke-width="5"/>
                        <circle cx="95" cy="137" r="7" fill="#3b82f6"/>
                        <circle cx="265" cy="137" r="7" fill="#0a0a0a" stroke="currentColor" stroke-width="5"/>
                        <path d="M106 91L93 104M93 91L106 104" stroke="#60a5fa" stroke-width="5" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="min-h-[19rem]">
                    <?php foreach ($approach_steps as $step_index => $step) : ?>
                        <div
                            id="approach-panel-<?php echo esc_attr($step['id']); ?>"
                            class="approach-panel<?php echo 0 === $step_index ? ' is-active' : ''; ?>"
                            role="tabpanel"
                            aria-labelledby="approach-tab-<?php echo esc_attr($step['id']); ?>"
                            data-approach-panel="<?php echo esc_attr($step['id']); ?>"
                            <?php echo 0 === $step_index ? '' : 'hidden'; ?>
                        >
                            <p class="font-mono text-xs text-brand-400"><?php echo esc_html($step['number']); ?> /</p>
                            <h3 class="mt-3 font-display text-4xl font-bold tracking-[-0.04em] sm:text-5xl"><?php echo esc_html($step['title']); ?></h3>
                            <p class="mt-6 max-w-lg text-sm leading-7 text-white/55"><?php echo esc_html($step['copy']); ?></p>
                            <ul class="mt-7 grid gap-3">
                                <?php foreach ($step['points'] as $point) : ?>
                                    <li class="flex items-center gap-3 text-sm text-white/75"><span class="grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-[10px] text-white">✓</span><?php echo esc_html($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="testimonials" class="bg-white py-24 sm:py-32 lg:py-40">
        <div class="mx-auto grid max-w-[1440px] gap-14 px-5 sm:px-8 lg:grid-cols-[.7fr_1.3fr] lg:px-12">
            <div data-reveal>
                <p class="eyebrow">Client stories</p>
                <h2 class="section-title">What our clients say.</h2>
                <p class="mt-7 max-w-md text-base leading-8 text-neutral-500">Long-term relationships built on transparency, quality and shared ambition.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <?php foreach ($testimonials as $testimonial) : ?>
                    <article class="rounded-3xl bg-neutral-50 p-7 sm:p-8" data-reveal>
                        <div class="mb-8 text-sm tracking-[0.2em] text-brand-500">★★★★★</div>
                        <blockquote class="font-display text-xl font-semibold leading-8 tracking-tight">“<?php echo esc_html($testimonial['quote']); ?>”</blockquote>
                        <div class="mt-8 flex items-center gap-4">
                            <img class="h-12 w-12 rounded-full object-cover" src="<?php echo esc_url(e7_company_asset('images/' . $testimonial['image'])); ?>" alt="" width="96" height="96" loading="lazy">
                            <div><p class="text-sm font-bold"><?php echo esc_html($testimonial['name']); ?></p><p class="mt-1 text-xs text-neutral-500"><?php echo esc_html($testimonial['company']); ?></p></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="contact" class="bg-brand-600 py-8 text-white sm:py-12">
        <div class="mx-auto max-w-[1440px] px-5 sm:px-8 lg:px-12">
            <div class="relative overflow-hidden rounded-[2rem] bg-neutral-950 px-6 py-14 sm:px-10 lg:px-16 lg:py-20">
                <div class="contact-orb absolute -right-64 -top-64 h-[40rem] w-[40rem] rounded-full"></div>
                <div class="relative grid gap-14 lg:grid-cols-[.8fr_1.2fr]">
                    <div data-reveal>
                        <p class="eyebrow !text-brand-400">Let’s talk</p>
                        <h2 class="font-display text-4xl font-bold leading-none tracking-[-0.045em] sm:text-6xl">Start growing your business with us.</h2>
                        <p class="mt-7 max-w-md text-base leading-8 text-white/60">Tell us what you are building. We will come back with the right questions and a clear next step.</p>
                    </div>
                    <form class="grid gap-5 sm:grid-cols-2" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-reveal>
                        <input type="hidden" name="action" value="e7_company_contact">
                        <?php wp_nonce_field('e7_company_contact'); ?>
                        <label class="grid gap-2 text-xs font-bold uppercase tracking-[0.16em] text-white/50">Your name<input class="border-0 border-b border-white/20 bg-transparent px-0 py-3 text-base font-normal normal-case tracking-normal text-white outline-none transition placeholder:text-white/20 focus:border-brand-400 focus:ring-0" type="text" name="name" autocomplete="name" placeholder="John Smith" required></label>
                        <label class="grid gap-2 text-xs font-bold uppercase tracking-[0.16em] text-white/50">Email address<input class="border-0 border-b border-white/20 bg-transparent px-0 py-3 text-base font-normal normal-case tracking-normal text-white outline-none transition placeholder:text-white/20 focus:border-brand-400 focus:ring-0" type="email" name="email" autocomplete="email" placeholder="john@company.com" required></label>
                        <label class="grid gap-2 text-xs font-bold uppercase tracking-[0.16em] text-white/50 sm:col-span-2">What can we build together?<textarea class="min-h-28 resize-y border-0 border-b border-white/20 bg-transparent px-0 py-3 text-base font-normal normal-case tracking-normal text-white outline-none transition placeholder:text-white/20 focus:border-brand-400 focus:ring-0" name="message" placeholder="Tell us a little about the project" required></textarea></label>
                        <div class="sm:col-span-2"><button class="button-primary mt-3 px-8 py-4" type="submit">Send project details</button></div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php get_footer(); ?>
