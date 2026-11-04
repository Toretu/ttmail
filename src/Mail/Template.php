<?php
declare(strict_types=1);

namespace Mailings\Mail;

/** Placeholder substitution and HTML composition for campaign mail. Contact data is always escaped. */
final class Template
{
    public const PLACEHOLDERS = [
        'first_name' => 'Contact first name',
        'last_name' => 'Contact last name',
        'full_name' => 'Contact full name',
        'company' => 'Company name',
        'campaign' => 'Campaign name',
        'details_link' => 'Personal link to update details and choose products',
    ];

    /** @return list<string> placeholders used in the text that do not exist */
    public static function unknown(string $text): array
    {
        preg_match_all('/\{\{\s*([^{}]*?)\s*\}\}/', $text, $m);
        return array_values(array_unique(array_filter($m[1], static fn (string $name): bool => !isset(self::PLACEHOLDERS[$name]))));
    }

    /** @param array<string,string> $vars */
    public static function subject(string $subject, array $vars): string
    {
        $text = self::replace($subject, $vars, false);
        return trim((string) preg_replace('/[\r\n]+/', ' ', $text));
    }

    /**
     * @param array<string,string> $vars
     * @return string HTML document body (message plus unsubscribe footer)
     */
    public static function html(string $body, array $vars, string $unsubscribeUrl, string $senderName): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $html = nl2br(self::replace($text, $vars, true), false);
        $url = htmlspecialchars($unsubscribeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sender = htmlspecialchars($senderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.5;color:#222">' . $html . '</div>'
            . '<hr style="border:0;border-top:1px solid #ddd;margin:24px 0 8px">'
            . '<p style="font-family:Segoe UI,Arial,sans-serif;font-size:12px;color:#666">'
            . "Du får denne e-posten fra {$sender}. Ønsker du ikke flere henvendelser fra oss? "
            . "<a href=\"{$url}\">Meld deg av her</a>.<br>"
            . "You receive this email from {$sender}. If you do not want to hear from us again, <a href=\"{$url}\">unsubscribe here</a>.</p>";
    }

    /** @param array<string,string> $vars */
    private static function replace(string $text, array $vars, bool $escape): string
    {
        if ($escape) {
            $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return (string) preg_replace_callback('/\{\{\s*([^{}]*?)\s*\}\}/', static function (array $m) use ($vars, $escape): string {
            $value = $vars[$m[1]] ?? '';
            if ($escape && $m[1] === 'details_link' && $value !== '') {
                $url = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return "<a href=\"{$url}\">Oppdater opplysninger og velg produkter / Update your details and choose products</a>";
            }
            return $escape ? htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $text);
    }
}
