<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Patients\DTO\RegisterPatientInput;
use App\Application\Patients\UseCases\RegisterLocalPatientUseCase;
use App\Application\Patients\UseCases\ReviewMatchCandidateUseCase;
use App\Application\Patients\UseCases\SearchLocalPatientsUseCase;
use App\Domain\Patients\Exceptions\DuplicateDpiException;
use App\Domain\Patients\Exceptions\InvalidMatchCandidateSelectionException;
use App\Domain\Patients\Exceptions\LocalPatientNotFoundException;
use App\Domain\Patients\Exceptions\MatchCandidateAlreadyResolvedException;
use App\Domain\Patients\Exceptions\MatchCandidateNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patients\RegisterLocalPatientRequest;
use App\Http\Requests\Patients\ResolveMatchCandidateRequest;
use App\Http\Requests\Patients\SearchPatientsRequest;
use App\Http\Resources\Patients\LocalPatientResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Presentación: solo recibe la solicitud, valida formato (Form Requests), invoca
 * el caso de uso correspondiente y traduce la respuesta. No contiene SQL ni
 * decide reglas clínicas/operativas (esas viven en Domain/Application).
 */
class PatientController extends Controller
{
    public function __construct(
        private readonly RegisterLocalPatientUseCase $registerPatient,
        private readonly SearchLocalPatientsUseCase $searchPatients,
        private readonly ReviewMatchCandidateUseCase $reviewMatchCandidate,
    ) {
    }

    public function store(RegisterLocalPatientRequest $request): JsonResponse
    {
        $tenant = $this->currentTenant($request);

        try {
            $result = $this->registerPatient->handle(new RegisterPatientInput(
                tenantId: (string) $tenant->id,
                firstName: $request->string('first_name')->toString(),
                lastName: $request->string('last_name')->toString(),
                birthDate: $request->string('birth_date')->toString(),
                gender: $request->string('gender')->toString(),
                dpi: $request->input('dpi'),
            ));
        } catch (DuplicateDpiException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'uuid' => $result->uuid,
            'code' => $result->code,
            'mpi_link_status' => $result->mpiLinkStatus,
            'global_id' => $result->globalId,
            'match_candidate_id' => $result->matchCandidateId,
            'central_available' => $result->centralWasAvailable,
        ], 201);
    }

    public function index(SearchPatientsRequest $request): JsonResponse
    {
        $tenant = $this->currentTenant($request);

        $result = $this->searchPatients->handle(
            tenantId: (string) $tenant->id,
            term: (string) $request->input('q', ''),
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
        );

        return response()->json([
            'data' => LocalPatientResource::collection($result['data']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    public function resolveMatchCandidate(ResolveMatchCandidateRequest $request, string $matchCandidate): JsonResponse
    {
        $reviewedBy = (string) $request->user('api')?->getKey();

        try {
            $globalId = $request->string('decision')->toString() === 'confirm'
                ? $this->reviewMatchCandidate->confirm(
                    $matchCandidate,
                    $request->string('mpi_patient_id')->toString(),
                    $reviewedBy
                )
                : $this->reviewMatchCandidate->reject($matchCandidate, $reviewedBy);
        } catch (MatchCandidateNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (MatchCandidateAlreadyResolvedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (LocalPatientNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (InvalidMatchCandidateSelectionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'match_candidate_id' => $matchCandidate,
            'global_id' => $globalId,
            'mpi_link_status' => 'auto_linked',
        ]);
    }

    private function currentTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
