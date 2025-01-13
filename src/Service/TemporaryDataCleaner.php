<?php 

namespace App\Service;

use App\Repository\TemporaryDataRepository;

class TemporaryDataCleaner
{
    private TemporaryDataRepository $temporaryDataRepository;

    public function __construct(TemporaryDataRepository $temporaryDataRepository)
    {
        $this->temporaryDataRepository = $temporaryDataRepository;
    }

    public function cleanExpiredData(): int
    {
        return $this->temporaryDataRepository->deleteExpiredData();
    }
}
