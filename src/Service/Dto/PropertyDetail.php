<?php

declare(strict_types=1);

namespace App\Service\Dto;

use App\Model\Amenity;
use App\Model\BookableUnit;
use App\Model\Photo;
use App\Model\Property;
use App\Model\User;

/** İlan detayı (FR-09): açıklama, fotoğraflar, konum, kapasite, olanaklar, fiyat, mod ve iptal koşulu. */
final class PropertyDetail
{
    /**
     * @param list<BookableUnit> $units
     * @param list<Photo> $photos
     * @param list<Amenity> $amenities
     * @param list<array{rating: int, comment: string, author: string, date: \DateTimeImmutable}> $reviews
     */
    public function __construct(
        public readonly Property $property,
        public readonly array $units,
        public readonly array $photos,
        public readonly array $amenities,
        public readonly ?float $rating,
        public readonly int $reviewCount,
        public readonly array $reviews,
        public readonly ?User $host,
        public readonly bool $isFavorite,
        public readonly string $policyText,
        public readonly int $minPrice,
    ) {
    }
}
