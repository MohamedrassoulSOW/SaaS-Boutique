<?php

namespace App\Service;

use App\Entity\User;

/** @deprecated Utiliser AppMailer::sendPasswordReset — conservé pour compatibilité. */
class PasswordResetMailer
{
    public function __construct(private AppMailer $appMailer)
    {
    }

    public function sendResetLink(User $user, string $plainToken): void
    {
        $this->appMailer->sendPasswordReset($user, $plainToken);
    }
}
