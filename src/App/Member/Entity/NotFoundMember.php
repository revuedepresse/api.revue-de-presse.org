<?php

namespace App\Member\Entity;

use App\Member\MemberInterface;
use WTW\UserBundle\Entity\User;

class NotFoundMember implements MemberInterface
{
    use MemberTrait;

    /**
     * @param $notFound
     * @return MemberInterface
     */
    public function setNotFound(bool $notFound): MemberInterface
    {
        return $this;
    }

    /**
     * @return boolean
     */
    public function hasBeenDeclaredAsNotFound(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function hasNotBeenDeclaredAsNotFound(): bool
    {
        return false;
    }

    /**
     * @param string $screenName
     * @return MemberInterface
     */
    public function make(string $screenName): MemberInterface
    {
        $member = new User();
        $member->setTwitterUsername($screenName);
        $member->setEmail('@'.$screenName);
        $member->setNotFound(true);

        return $member;
    }
}
