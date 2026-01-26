<?php

declare(strict_types=1);

namespace App\Services;

use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

class MailtrapApiMailer
{
    public function sendHtml(
        string $fromEmail,
        ?string $fromName,
        string $toEmail,
        ?string $toName,
        string $subject,
        string $html,
        ?string $text = null,
        ?string $category = null,
    ): void {
        $apiKey = (string) config('services.mailtrap.api_key');

        if ($apiKey === '') {
            throw new \RuntimeException('MAILTRAP_API_KEY is missing.');
        }

        $isBulk = (string) config('services.mailtrap.stream') === 'bulk';

        $client = MailtrapClient::initSendingEmails(apiKey: $apiKey, isBulk: $isBulk);

        $email = (new MailtrapEmail())
            ->from(new Address($fromEmail, $fromName ?? ''))
            ->to(new Address($toEmail, $toName ?? ''))
            ->subject($subject)
            ->html($html);

        if ($text !== null) {
            $email->text($text);
        }

        if ($category) {
            $email->category($category);
        }

        $client->send($email);
    }
}
