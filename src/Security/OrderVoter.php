<?php

declare(strict_types=1);
namespace App\Security;

use App\Entity\{Billet, Commande, User};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class OrderVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'ORDER_ACCESS' && ($subject instanceof Commande || $subject instanceof Billet);
    }
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) { return false; }
        $order = $subject instanceof Billet ? $subject->getCommande() : $subject;
        return array_intersect($user->getRoles(), ['ROLE_ADMIN', 'ROLE_GUICHETIER']) !== []
            || ($order->getOwner()?->getId() !== null && $order->getOwner()->getId() === $user->getId());
    }
}
