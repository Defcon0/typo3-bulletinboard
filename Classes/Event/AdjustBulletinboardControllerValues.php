<?php

namespace WapplerSystems\WsBulletinboard\Event;

class AdjustBulletinboardEntries
{
  public function __construct(protected array $assignedValues)
  {
  }

  public function getAssignedValues(): array
  {
    return $this->assignedValues;
  }

  public function setAssignedValues(array $assignedValues): void
  {
    $this->assignedValues = $assignedValues;
  }
}
