<?php

namespace App\Providers;

use App\Application\Patients\Ports\IdentityMatchCandidateRepository;
use App\Application\Patients\Ports\LocalPatientRepository;
use App\Application\Patients\Ports\MpiPatientRepository;
use App\Application\Patients\Ports\PatientHospitalLinkRepository;
use App\Application\Patients\Ports\PatientSyncOutboxRepository;
use App\Domain\Patients\Mpi\PatientMatchingPolicy;
use App\Infrastructure\Patients\Eloquent\EloquentIdentityMatchCandidateRepository;
use App\Infrastructure\Patients\Eloquent\EloquentLocalPatientRepository;
use App\Infrastructure\Patients\Eloquent\EloquentMpiPatientRepository;
use App\Infrastructure\Patients\Eloquent\EloquentPatientHospitalLinkRepository;
use App\Infrastructure\Patients\Eloquent\EloquentPatientSyncOutboxRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Ata los puertos (interfaces) del módulo 03 a sus adaptadores PostgreSQL/Eloquent.
 * Los tests de aplicación reemplazan estos bindings por los dobles InMemory
 * (App\Infrastructure\Patients\Fakes\*) usando $this->app->bind(...) en setUp().
 */
class PatientModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LocalPatientRepository::class, EloquentLocalPatientRepository::class);
        $this->app->bind(MpiPatientRepository::class, EloquentMpiPatientRepository::class);
        $this->app->bind(PatientHospitalLinkRepository::class, EloquentPatientHospitalLinkRepository::class);
        $this->app->bind(IdentityMatchCandidateRepository::class, EloquentIdentityMatchCandidateRepository::class);
        $this->app->bind(PatientSyncOutboxRepository::class, EloquentPatientSyncOutboxRepository::class);

        $this->app->singleton(PatientMatchingPolicy::class, function () {
            return new PatientMatchingPolicy(
                autoLinkThreshold: (float) env('PATIENTS_MPI_AUTO_LINK_THRESHOLD', 0.92),
                reviewThreshold: (float) env('PATIENTS_MPI_REVIEW_THRESHOLD', 0.60),
            );
        });
    }
}
