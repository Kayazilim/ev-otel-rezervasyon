<?php

declare(strict_types=1);

namespace App\Exception;

use App\Support\DateRange;
use DateTimeImmutable;

/**
 * Tarih çakışması (FR-36). Mesaj başka kullanıcının bilgisini içermez (NFR-04);
 * en çok 3 alternatif tarih aralığı ve aralıktaki dolu gecelerin tarihlerini taşır.
 */
class ConflictException extends AppException
{
    /**
     * @param list<DateRange> $alternatives
     * @param list<DateTimeImmutable> $nights aralıktaki dolu geceler (kullanıcıya "neden" olarak gösterilir)
     */
    public function __construct(
        string $message = 'Seçtiğiniz tarihler artık müsait değil.',
        private readonly array $alternatives = [],
        private readonly array $nights = [],
    ) {
        parent::__construct($message);
    }

    /** @return list<DateTimeImmutable> */
    public function nights(): array
    {
        return $this->nights;
    }

    /** @return list<DateRange> */
    public function alternatives(): array
    {
        return $this->alternatives;
    }
}
