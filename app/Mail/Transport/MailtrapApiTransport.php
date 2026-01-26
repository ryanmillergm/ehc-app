<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use App\Services\MailtrapApiMailer;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class MailtrapApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly MailtrapApiMailer $mailtrap,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $sentMessage): void
    {
        $original = $sentMessage->getOriginalMessage();

        // Laravel/Symfony should give us an Email for normal usage.
        if (! $original instanceof Email) {
            throw new TransportException('MailtrapApiTransport expected Symfony\Component\Mime\Email.');
        }

        $from = $original->getFrom()[0] ?? null;
        $to   = $original->getTo()[0] ?? null;

        if (! $from || ! $to) {
            throw new TransportException('Missing From or To address.');
        }

        $subject = (string) ($original->getSubject() ?? '');

        $html = $original->getHtmlBody();
        $text = $original->getTextBody();

        if ($html === null && $text === null) {
            $text = '(empty email body)';
            $html = '<p>(empty email body)</p>';
        } elseif ($html === null) {
            // If only text exists, generate a basic HTML version.
            $html = '<pre>' . e($text ?? '') . '</pre>';
        }

        try {
            $this->mailtrap->sendHtml(
                fromEmail: $from->getAddress(),
                fromName: $from->getName() ?: null,
                toEmail: $to->getAddress(),
                toName: $to->getName() ?: null,
                subject: $subject,
                html: (string) $html,
                text: $text,
                category: 'App Mail',
            );
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function __toString(): string
    {
        return 'mailtrap+api://default';
    }
}
