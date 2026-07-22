<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/inc/reference-importer.php';

function assert_parser_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("%s\nExpected: %s\nActual: %s\n", $message, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

$listing_html = <<<'HTML'
<div class="filter-header"><a><span>Filter Results</span> (50)</a></div>
<div class="used-car">
  <a href="/used-car/alfa-romeo-gtv-357256" class="cb-image"><img src="https://media.example/first.jpg" alt="Alfa Romeo GTV"></a>
  <span class="cb-title">2000 Alfa Romeo GTV</span>
  <span class="cb-subtitle">2.0i LUSSO TWIN SPARK</span>
  <span class="cb-spec"><span>125,000 MI</span>|<span>2000</span>|<span>Manual</span>|<span>Petrol</span></span>
  <div class="cb-main-price"><div>Our Price</div><div>&euro;4,950</div></div>
  <div class="cb-main-price"><div>HP Weekly</div><div class="cb-price-monthly">&euro;19/wk</div></div>
</div>
<div class="used-car"><a href="/used-car/ford-kuga-622011" class="cb-image"></a></div>
<div class="used-car-pager"><a href="/used-cars?page=1">1</a><a href="/used-cars?page=2">2</a></div>
HTML;

$listing = apmg_parse_reference_listing($listing_html, 'https://www.autopointmotorgroup.com');
assert_parser_same(50, $listing['total'], 'Inventory count is parsed');
assert_parser_same(2, $listing['pages'], 'Pagination count is parsed');
assert_parser_same(2, count($listing['vehicles']), 'Every card is parsed');
assert_parser_same('357256', $listing['vehicles'][0]['source_id'], 'Stable DealerHub id is extracted');
assert_parser_same(4950, $listing['vehicles'][0]['price'], 'Euro price is numeric');
assert_parser_same(19, $listing['vehicles'][0]['weekly_price'], 'Weekly finance is numeric');

$detail_html = <<<'HTML'
<section class="used-cars-deatails-page">
  <div class="used-car-images"><div class="swiper-wrapper">
    <div class="swiper-slide"><img src="https://media.example/first.jpg" alt="Alfa Romeo GTV"></div>
    <div class="swiper-slide"><img src="https://media.example/second.jpg" alt="Alfa Romeo GTV"></div>
  </div></div>
  <h1 class="used-car-title">2000 (0) Alfa Romeo GTV</h1>
  <div class="used-car-sub-title">2.0i LUSSO TWIN SPARK</div>
</section>
<div class="used-car-description-title">Vehicle Details</div>
<div>Well maintained example.</div>
<div class="used-car-specs">
  <div class="year"><div><span>Year</span><strong>2000</strong></div></div>
  <div class="transmission"><div><span>Transmission</span><strong>Manual</strong></div></div>
  <div class="mileage"><div><span>Mileage</span><strong>125,000 MI</strong></div></div>
  <div class="engine"><div><span>Engine Size</span><strong>2.0</strong></div></div>
  <div class="bodytype"><div><span>Body Type</span><strong>Coupe</strong></div></div>
  <div class="colour"><div><span>Colour:</span><strong>Blue</strong></div></div>
  <div class="previous"><div><span>Previous Owners</span><strong>2</strong></div></div>
  <div class="tax"><div><span>Road Tax:</span><strong>&euro;710</strong></div></div>
  <div class="doors"><div><span>Access:</span><strong>2</strong></div></div>
</div>
HTML;

$detail = apmg_parse_reference_vehicle($detail_html);
assert_parser_same(2, count($detail['images']), 'All gallery images are parsed');
assert_parser_same('Alfa Romeo', $detail['make'], 'Multi-word make is parsed from the title');
assert_parser_same('GTV', $detail['model'], 'Model is parsed from the title');
assert_parser_same(125000, $detail['mileage'], 'Mileage becomes numeric');
assert_parser_same('MI', $detail['mileage_unit'], 'Mileage unit is retained');
assert_parser_same('Coupe', $detail['body'], 'Body type is parsed');
assert_parser_same(2, $detail['doors'], 'Door count is parsed');

echo "Reference parser passed.\n";
