<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Veri deposuna ulaşılamıyor (ör. MySQL çalışmıyor veya tablolar kurulmamış).
 * Kullanıcıya 503 ile anlaşılır bir mesaj gösterilir; bağlantı ayrıntısı (parola vb.) mesajda yer almaz.
 */
class StorageUnavailableException extends PersistenceException
{
}
