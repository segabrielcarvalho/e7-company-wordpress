<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Domain\VehicleState;
use DateTimeImmutable;
use Throwable;

final class WordPressVehicleRepository implements VehicleRepository
{
    public const STATUS_META = 'apmg_commerce_status';
    public const TOKEN_META = '_apmg_reservation_token';
    public const EXPIRES_META = 'apmg_reservation_expires_at';
    public const BALANCE_META = '_apmg_balance_due';
    public const ACTIVE_ORDER_META = 'apmg_active_order_id';
    public const PRICE_META = 'apmg_price';

    public function isAvailable(): bool
    {
        return function_exists('get_post_meta')
            && function_exists('update_post_meta')
            && function_exists('add_post_meta')
            && function_exists('delete_post_meta')
            && function_exists('get_post_type')
            && function_exists('get_posts');
    }

    public function get(int $vehicleId): ?VehicleState
    {
        $this->guard();
        if (get_post_type($vehicleId) !== 'vehicle') {
            return null;
        }

        $status = (string) get_post_meta($vehicleId, self::STATUS_META, true);
        $status = $status !== '' ? $status : Status::AVAILABLE;
        $token = (string) get_post_meta($vehicleId, self::TOKEN_META, true);
        $expires = $this->date((string) get_post_meta($vehicleId, self::EXPIRES_META, true));
        $activeOrderId = (int) get_post_meta($vehicleId, self::ACTIVE_ORDER_META, true);

        return VehicleState::restore(
            $vehicleId,
            $status,
            max(0, (int) get_post_meta($vehicleId, self::PRICE_META, true)),
            $token !== '' ? $token : null,
            $expires,
            max(0, (int) get_post_meta($vehicleId, self::BALANCE_META, true)),
            $activeOrderId > 0 ? $activeOrderId : null
        );
    }

    public function reserveIfAvailable(
        int $vehicleId,
        string $token,
        ?DateTimeImmutable $reservedUntil,
        int $balanceDue,
        DateTimeImmutable $now
    ): ?VehicleState {
        $state = $this->get($vehicleId);
        if (!$state || !$state->isAvailable($now)) {
            return null;
        }

        if ($state->status() === Status::RESERVED) {
            $released = $this->releaseReservation($vehicleId, (string) $state->reservationToken());
            if (!$released) {
                return null;
            }
            $state = $released;
        }

        $this->ensureStatusMeta($vehicleId);
        if (!update_post_meta($vehicleId, self::STATUS_META, Status::RESERVED, Status::AVAILABLE)) {
            return null;
        }

        update_post_meta($vehicleId, self::TOKEN_META, $token);
        update_post_meta($vehicleId, self::EXPIRES_META, $reservedUntil?->format(DATE_ATOM) ?? '');
        update_post_meta($vehicleId, self::BALANCE_META, $balanceDue);
        delete_post_meta($vehicleId, self::ACTIVE_ORDER_META);

        return $this->get($vehicleId);
    }

    public function save(VehicleState $state): void
    {
        $this->guard();
        update_post_meta($state->vehicleId(), self::STATUS_META, $state->status());
        update_post_meta($state->vehicleId(), self::TOKEN_META, $state->reservationToken() ?? '');
        update_post_meta($state->vehicleId(), self::EXPIRES_META, $state->reservedUntil()?->format(DATE_ATOM) ?? '');
        update_post_meta($state->vehicleId(), self::BALANCE_META, $state->balanceDue());
        update_post_meta($state->vehicleId(), self::ACTIVE_ORDER_META, $state->activeOrderId() ?? 0);
    }

    public function releaseReservation(int $vehicleId, string $token): ?VehicleState
    {
        $state = $this->get($vehicleId);
        if (!$state || $state->status() !== Status::RESERVED || $state->reservationToken() !== $token) {
            return null;
        }

        if (!update_post_meta($vehicleId, self::STATUS_META, Status::AVAILABLE, Status::RESERVED)) {
            return null;
        }

        delete_post_meta($vehicleId, self::TOKEN_META);
        delete_post_meta($vehicleId, self::EXPIRES_META);
        delete_post_meta($vehicleId, self::BALANCE_META);
        delete_post_meta($vehicleId, self::ACTIVE_ORDER_META);

        return $this->get($vehicleId);
    }

    public function expired(DateTimeImmutable $now): iterable
    {
        $this->guard();
        $ids = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_key' => self::STATUS_META,
            'meta_value' => Status::RESERVED,
        ]);

        $expired = [];
        foreach ($ids as $vehicleId) {
            $state = $this->get((int) $vehicleId);
            if ($state?->isExpired($now)) {
                $expired[] = $state;
            }
        }

        return $expired;
    }

    private function ensureStatusMeta(int $vehicleId): void
    {
        if ((string) get_post_meta($vehicleId, self::STATUS_META, true) === '') {
            add_post_meta($vehicleId, self::STATUS_META, Status::AVAILABLE, true);
        }
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function guard(): void
    {
        if (!$this->isAvailable()) {
            throw new CommerceException('WordPress vehicle storage is not available.');
        }
    }
}
