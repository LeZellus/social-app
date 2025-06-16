<?php

namespace App\Security;

use App\Entity\SocialAccount;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SocialAccountVoter extends Voter
{
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW])
            && $subject instanceof SocialAccount;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var SocialAccount $socialAccount */
        $socialAccount = $subject;

        return match($attribute) {
            self::VIEW, self::EDIT, self::DELETE => $this->canAccess($socialAccount, $user),
            default => false,
        };
    }

    private function canAccess(SocialAccount $socialAccount, User $user): bool
    {
        return $socialAccount->getUser() === $user;
    }
}