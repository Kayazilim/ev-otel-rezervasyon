<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Support\ClockInterface;

/**
 * Simüle e-posta kutusu: her ileti storage/mail altına ayrı bir .txt dosyası olarak yazılır.
 * Bu klasör uygulama günlüğü değildir; demo sırasında "gelen kutusu" yerine açılır.
 */
final class FileMailer implements MailerInterface
{
    public function __construct(private readonly string $directory, private readonly ClockInterface $clock)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        $file = sprintf(
            '%s/%s-%s.txt',
            $this->directory,
            $this->clock->now()->format('Ymd-His'),
            bin2hex(random_bytes(3))
        );
        file_put_contents($file, "Kime: {$to}\nKonu: {$subject}\n\n{$body}\n");
    }
}
