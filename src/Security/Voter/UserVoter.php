<?php

namespace App\Security\Voter;

use ApiPlatform\Core\Api\IriConverterInterface;
use App\Entity\User;
use App\Entity\UserSubjectRelation;
use App\Repository\Interface\UserSubjectRelationRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    private const ROLES = [
        'request_friend' => 'REQUEST_FRIEND_USER',
    ];

    public function __construct(
        private IriConverterInterface $iriConverter,
        private UserSubjectRelationRepositoryInterface $relationRepository
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, self::ROLES)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        if (!$user instanceof UserInterface) {
            return false;
        }

        return match ($attribute) {
            self::ROLES['request_friend'] => $this->canRequestFriend($user, $subject),
            default => false,
        };
    }

    private function canRequestFriend(User $user, User $subject): bool
    {
        $hasRelation = $this->hasRelation();

        return !$hasRelation($user, UserSubjectRelation::REQUEST_FRIEND, $subject) &&
            !$hasRelation($user, UserSubjectRelation::FRIEND, $subject);
    }

    private function hasRelation(): callable
    {
        return fn(User $user, string $relation, User $subject): bool =>
            $this->relationRepository->userHasRelationWith(
                $user,
                $relation,
                $subject,
                false
            );
    }
}