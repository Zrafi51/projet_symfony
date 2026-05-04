<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class NoBannedWordsValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoBannedWords) {
            throw new UnexpectedTypeException($constraint, NoBannedWords::class);
        }

        if (null === $value || '' === $value) {
            return;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        // Lower-case via mb_strtolower so accented French chars (é, à, …) match.
        $haystack = mb_strtolower($value, 'UTF-8');

        foreach (NoBannedWords::BANNED_WORDS as $word) {
            // Strict substring match (multi-byte safe) — deliberately catches
            // obfuscation attempts like "fuckfuck", "aaafuckbbb", etc.
            // We accept the occasional false positive in favor of strict blocking.
            if (mb_stripos($haystack, $word, 0, 'UTF-8') !== false) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ word }}', $word)
                    ->addViolation();
                return; // stop at the first hit — the message is clearer that way.
            }
        }
    }
}
