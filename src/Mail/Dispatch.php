<?php
declare(strict_types=1);

namespace Mailings\Mail;

use Mailings\Config;
use Mailings\Graph\GraphClient;
use Mailings\Graph\GraphException;
use Mailings\Http\HttpException;

/** Sends one message through Graph. In development, MAIL_DRY_RUN=1 logs instead of sending. */
final class Dispatch
{
    private static ?string $token = null;

    /** Dry run is honoured only in development so production can never silently skip sending. */
    public static function dryRun(): bool
    {
        return Config::get('APP_ENV', 'production') === 'development' && Config::get('MAIL_DRY_RUN', '0') === '1';
    }

    /** @throws GraphException when Microsoft refuses; @throws HttpException when not configured */
    public static function send(string $fromMailbox, string $to, string $subject, string $html): void
    {
        if (self::dryRun()) {
            error_log("DRY RUN: would send '{$subject}' from {$fromMailbox} to {$to}");
            return;
        }
        if (!GraphClient::status()['ready']) {
            throw new HttpException(409, 'The Microsoft 365 connection is not configured yet. See Settings.');
        }
        $client = new GraphClient();
        self::$token ??= $client->acquireToken()['token'];
        try {
            $client->sendMail(self::$token, $fromMailbox, [
                'subject' => $subject,
                'body' => ['contentType' => 'HTML', 'content' => $html],
                'toRecipients' => [['emailAddress' => ['address' => $to]]],
            ]);
        } catch (GraphException $e) {
            if ($e->httpStatus === 401) {
                self::$token = null;
            }
            throw $e;
        }
    }
}
