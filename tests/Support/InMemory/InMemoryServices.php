<?php

declare(strict_types=1);

namespace Tests\Support\InMemory;

use App\Service\AuditService;
use App\Service\BookingConflictService;
use App\Service\NotificationService;
use App\Service\PaymentService;
use App\Service\PricingService;
use App\Service\ReservationStateMachine;
use App\Support\FrozenClock;
use App\Support\Logger;
use App\Support\Settings;

/**
 * Çekirdek servisleri bellekte çalışan sahte depolarla kurar (birim testleri için). Hiçbir dosya okunmaz
 * veya yazılmaz; saat sabittir. Servisler yalnızca depo arayüzlerini bildiği için (Repository deseni)
 * gerçek JSON deposunun yerine bu sahte depolar verilebilir.
 */
final class InMemoryServices
{
    public readonly FrozenClock $clock;
    public readonly Settings $settings;
    public readonly InMemoryReservationRepository $reservations;
    public readonly InMemoryCalendarRepository $calendar;
    public readonly InMemoryPaymentRepository $payments;
    public readonly InMemoryNotificationRepository $notifications;
    public readonly InMemoryAuditLogRepository $auditLogs;
    public readonly InMemoryPriceRuleRepository $priceRules;
    public readonly InMemoryPricingRuleRepository $pricingRules;

    public function __construct(string $now = '2026-11-01 10:00:00')
    {
        $this->clock = new FrozenClock($now);
        $this->settings = new Settings();
        $this->reservations = new InMemoryReservationRepository();
        $this->calendar = new InMemoryCalendarRepository();
        $this->payments = new InMemoryPaymentRepository();
        $this->notifications = new InMemoryNotificationRepository();
        $this->auditLogs = new InMemoryAuditLogRepository();
        $this->priceRules = new InMemoryPriceRuleRepository();
        $this->pricingRules = new InMemoryPricingRuleRepository();
    }

    public function pricing(): PricingService
    {
        return new PricingService($this->priceRules, $this->pricingRules, $this->calendar);
    }

    public function conflicts(): BookingConflictService
    {
        $logger = new Logger('', $this->clock); // dosyaya yazmaz
        $audit = new AuditService($this->auditLogs, $this->clock);

        return new BookingConflictService(
            $this->reservations,
            $this->calendar,
            new ReservationStateMachine($this->reservations, $audit, $this->clock, $logger),
            new PaymentService($this->payments, $this->clock),
            new NotificationService($this->notifications, $this->clock),
            $this->clock,
            $this->settings,
            $logger,
        );
    }
}
