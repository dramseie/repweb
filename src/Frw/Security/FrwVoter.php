<?php
// src/Frw/Security/FrwVoter.php
namespace App\Frw\Security;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class FrwVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    { return in_array($attribute, ['FRW_VIEW','FRW_EDIT','FRW_ADMIN'], true); }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user || !method_exists($user, 'getRoles')) return false;
        $roles = $user->getRoles();
        return match ($attribute) {
            'FRW_VIEW' => in_array('ROLE_FRW_VIEW', $roles, true),
            'FRW_EDIT' => in_array('ROLE_FRW_EDIT', $roles, true),
            'FRW_ADMIN' => in_array('ROLE_FRW_ADMIN', $roles, true),
            default => false,
        };
    }
}