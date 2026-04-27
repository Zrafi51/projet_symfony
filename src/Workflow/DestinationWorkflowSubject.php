<?php

namespace App\Workflow;

final class DestinationWorkflowSubject
{
    public function __construct(private string $currentPlace = 'draft')
    {
        if ($this->currentPlace === '') {
            $this->currentPlace = 'draft';
        }
    }

    public function getCurrentPlace(): string
    {
        return $this->currentPlace;
    }

    public function setCurrentPlace(?string $currentPlace): void
    {
        $currentPlace = trim((string) $currentPlace);
        $this->currentPlace = $currentPlace !== '' ? $currentPlace : 'draft';
    }
}
