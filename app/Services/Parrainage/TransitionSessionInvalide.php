<?php

namespace App\Services\Parrainage;

use DomainException;

/** Changement d'état de session refusé : le message s'affiche tel quel à l'utilisateur. */
class TransitionSessionInvalide extends DomainException
{
}
