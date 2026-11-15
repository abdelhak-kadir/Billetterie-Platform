<?php

declare(strict_types=1);
namespace App\Sheets;
final class NativeHttpTransport implements HttpTransport
{
    public function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $context = stream_context_create(['http'=>[
            'method'=>$method, 'header'=>implode("\r\n",$headers), 'content'=>$body ?? '',
            'timeout'=>12, 'ignore_errors'=>true, 'follow_location'=>0,
        ], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) { throw new SyncFailure('Connexion à Google Sheets impossible ou délai dépassé.'); }
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('~^HTTP/\S+ (\d{3})~', $line, $m)) { $status = (int) $m[1]; }
        }
        return ['status'=>$status,'body'=>$content];
    }
}
