<?php

namespace App\Console\Commands;

use App\Application\Patients\UseCases\SyncPendingPatientsUseCase;
use Illuminate\Console\Command;

/**
 * Drena el outbox HOSPITAL -> CENTRAL para los pacientes que quedaron pendientes
 * de vínculo MPI (por ejemplo, porque CENTRAL estuvo caído durante el alta).
 * Idempotente: puede ejecutarse repetidamente (cron, worker) sin duplicar vínculos.
 */
class SyncPendingPatientsCommand extends Command
{
    protected $signature = 'patients:sync-mpi {--limit=50}';

    protected $description = 'Sincroniza con el MPI de CENTRAL los pacientes locales pendientes (outbox).';

    public function handle(SyncPendingPatientsUseCase $useCase): int
    {
        $stats = $useCase->handle((int) $this->option('limit'));

        $this->info(sprintf(
            'Procesados: %d | Vinculados: %d | Pendientes de revisión: %d | CENTRAL aún no disponible: %d',
            $stats['processed'],
            $stats['linked'],
            $stats['pending_review'],
            $stats['still_unavailable'],
        ));

        return self::SUCCESS;
    }
}
