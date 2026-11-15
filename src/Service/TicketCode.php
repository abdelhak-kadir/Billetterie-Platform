<?php

declare(strict_types=1);
namespace App\Service;
use App\Entity\Billet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final class TicketCode
{
    public function __construct(#[Autowire('%kernel.secret%')] private readonly string $secret) {}
    public function encode(Billet $ticket): string
    {
        $ref = $ticket->getReference();
        return $ref.'.'.hash_hmac('sha256', 'horizon-ticket-v1:'.$ref, $this->secret);
    }
    public function reference(string $code): ?string
    {
        if (strlen($code) > 140 || !str_contains($code, '.')) { return null; }
        $separator = strrpos($code, '.'); $ref = substr($code, 0, $separator); $signature = substr($code, $separator+1);
        if ($ref === '' || strlen($ref) > 64 || !hash_equals(hash_hmac('sha256', 'horizon-ticket-v1:'.$ref, $this->secret), $signature)) { return null; }
        return $ref;
    }
}
