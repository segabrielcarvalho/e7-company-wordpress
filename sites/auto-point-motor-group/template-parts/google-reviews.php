<?php
$google_place_id = 'ChIJV0EZKiM1RUgRxOwTQvHY8N0';
$google_profile_url = 'https://www.google.com/maps/search/?api=1&query=Google&query_place_id=' . rawurlencode($google_place_id);
$google_review_url = 'https://search.google.com/local/writereview?placeid=' . rawurlencode($google_place_id);
$review_highlights = [
    [
        'name' => 'Ciara O Gorman',
        'summary' => 'Praised the team, home delivery and the thoughtful finishing touches after purchasing a Volkswagen Tiguan.',
    ],
    [
        'name' => 'Linda Twomey',
        'summary' => 'Highlighted a straightforward Volkswagen Golf purchase and service she would confidently recommend.',
    ],
    [
        'name' => 'Aaron',
        'summary' => 'Recognised clear communication, prompt delivery and convenient collection of his trade-in.',
    ],
];
?>
<section id="google-reviews" class="google-reviews" aria-labelledby="google-reviews-title">
    <div class="kit-container">
        <header class="google-reviews__header">
            <span>Customer feedback</span>
            <h2 id="google-reviews-title">Trusted by drivers across Ireland</h2>
            <p>Real customer experiences from the Autopoint Motor Group Google Business Profile.</p>
        </header>

        <div class="google-reviews__layout">
            <aside class="google-reviews__summary">
                <span class="google-reviews__brand"><i class="fab fa-google" aria-hidden="true"></i> Google Reviews</span>
                <div class="google-reviews__rating"><strong>4.7</strong><span>out of 5</span></div>
                <div class="google-reviews__stars" role="img" aria-label="Rated 4.7 out of 5 stars">
                    <i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star-half-alt" aria-hidden="true"></i>
                </div>
                <p>Based on <strong>300+ Google reviews</strong></p>
                <a href="<?php echo esc_url($google_profile_url); ?>" target="_blank" rel="noopener noreferrer">View Google Business Profile <i class="fas fa-external-link-alt" aria-hidden="true"></i></a>
            </aside>

            <div class="google-reviews__cards">
                <?php foreach ($review_highlights as $review) : ?>
                    <article>
                        <div class="google-reviews__card-stars" role="img" aria-label="5 star review"><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i><i class="fas fa-star" aria-hidden="true"></i></div>
                        <p><?php echo esc_html($review['summary']); ?></p>
                        <footer><strong><?php echo esc_html($review['name']); ?></strong><span>Google review highlight</span></footer>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="google-reviews__action">
            <div><i class="fas fa-comment-dots" aria-hidden="true"></i><span>Already bought from Autopoint?</span><strong>Share your experience with other drivers.</strong></div>
            <a href="<?php echo esc_url($google_review_url); ?>" target="_blank" rel="noopener noreferrer">Write a Google Review <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div>
</section>
