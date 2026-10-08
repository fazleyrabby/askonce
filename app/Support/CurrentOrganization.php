<?php

namespace App\Support;

use App\Models\Organization;
use LogicException;

class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function restore(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): Organization
    {
        return $this->organization ?? throw new LogicException('Organization context is required.');
    }
}
