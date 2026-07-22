<?php

declare(strict_types=1);

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Domain\VehicleState;
use APMG\Commerce\Tests\TestCase;

$test->test('status accepts only the commercial state machine values', static function (): void {
    TestCase::same(Status::AVAILABLE, Status::assert(Status::AVAILABLE));
    TestCase::same(Status::RESERVED, Status::assert(Status::RESERVED));
    TestCase::same(Status::SALE_PENDING, Status::assert(Status::SALE_PENDING));
    TestCase::same(Status::SOLD, Status::assert(Status::SOLD));
    TestCase::throws(CommerceException::class, static fn (): string => Status::assert('pending'));
});

$test->test('price selects reserve or full amount without float arithmetic', static function (): void {
    TestCase::same(99, Price::forMode(Price::RESERVE, 24950, 99));
    TestCase::same(24950, Price::forMode(Price::FULL, 24950, 99));
    TestCase::same(99, Price::forMode(Price::RESERVE, 0, 99));
});

$test->test('price rejects invalid commercial amounts and modes', static function (): void {
    TestCase::throws(CommerceException::class, static fn (): int => Price::forMode('instalment', 24950, 99));
    TestCase::throws(CommerceException::class, static fn (): int => Price::forMode(Price::RESERVE, 24950, 0));
    TestCase::throws(CommerceException::class, static fn (): int => Price::forMode(Price::RESERVE, 99, 100));
    TestCase::throws(CommerceException::class, static fn (): int => Price::forMode(Price::FULL, 0, 99));
});

$test->test('POA vehicles are valid inventory with an unknown zero balance', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $state = VehicleState::available(42, 0)->reserve('poa-token', $now->modify('+30 minutes'), 0);

    TestCase::same(0, $state->fullPrice());
    TestCase::same(0, $state->balanceDue());
    TestCase::same(Status::RESERVED, $state->status());
});

$test->test('availability distinguishes live, reserved, expired and sold vehicles', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $available = VehicleState::available(42, 24950);
    $reserved = $available->reserve('checkout-1', $now->modify('+30 minutes'), 24851);
    $expired = $available->reserve('checkout-2', $now, 24851);
    $salePending = $available->markSalePending(900);
    $sold = $available->sell();

    TestCase::true($available->isAvailable($now));
    TestCase::false($reserved->isAvailable($now));
    TestCase::true($expired->isExpired($now));
    TestCase::true($expired->isAvailable($now));
    TestCase::false($salePending->isAvailable($now));
    TestCase::false($sold->isAvailable($now));
});

$test->test('reservation release requires expiry and payment balance never becomes negative', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $reserved = VehicleState::available(42, 24950)->reserve('checkout-1', $now->modify('+30 minutes'), 24851);

    TestCase::throws(CommerceException::class, static fn (): VehicleState => $reserved->release($now));
    TestCase::same(Status::AVAILABLE, $reserved->release($now->modify('+31 minutes'))->status());
    TestCase::same(14851, $reserved->recordBalancePayment(10000)->balanceDue());
    $paid = $reserved->recordBalancePayment(99999);
    TestCase::same(0, $paid->balanceDue());
    TestCase::same(Status::SALE_PENDING, $paid->status());
});

$test->test('persisted state restores reservation and active order metadata', static function (): void {
    $until = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
    $state = VehicleState::restore(42, Status::RESERVED, 24950, 'persisted-token', $until, 24851, 900);

    TestCase::same(Status::RESERVED, $state->status());
    TestCase::same('persisted-token', $state->reservationToken());
    TestCase::same(24851, $state->balanceDue());
    TestCase::same(900, $state->activeOrderId());
    TestCase::same($until->format(DATE_ATOM), $state->reservedUntil()?->format(DATE_ATOM));
});

$test->test('a refunded full payment can reopen sale pending but never a completed sale', static function (): void {
    $pending = VehicleState::available(42, 24950)->reserve('full-token', null, 0)->markSalePending(700);
    TestCase::same(Status::AVAILABLE, $pending->reopenAfterRefund()->status());
    TestCase::throws(CommerceException::class, static fn() => $pending->sell()->reopenAfterRefund());
});
