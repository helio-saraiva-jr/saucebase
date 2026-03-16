<?php

namespace Modules\Megacombo\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Megacombo\Http\Requests\Api\PriceSimulationRequest;
use Modules\Megacombo\Http\Requests\Api\ProbabilitySimulationRequest;
use Modules\Megacombo\Http\Requests\Api\StoreFinancialSimulationRequest;
use Modules\Megacombo\Http\Requests\Api\TriageRequest;
use Modules\Megacombo\Models\FinancialSimulation;
use Modules\Megacombo\Services\FinancialSimulationService;

class SimulationController
{
    public function __construct(private readonly FinancialSimulationService $service) {}

    public function price(PriceSimulationRequest $request): JsonResponse
    {
        return response()->json($this->service->simulatePrice($request->validated()));
    }

    public function probability(ProbabilitySimulationRequest $request): JsonResponse
    {
        return response()->json($this->service->simulateProbability($request->validated()));
    }

    public function triage(TriageRequest $request): JsonResponse
    {
        return response()->json($this->service->triageLead($request->validated()));
    }

    public function storeFinancialSimulation(StoreFinancialSimulationRequest $request): JsonResponse
    {
        $simulation = FinancialSimulation::query()->create([
            'user_id' => (int) $request->user()->id,
            'title' => $request->validated('title'),
            'inputs' => $request->validated('inputs'),
            'result_snapshot' => $request->validated('result_snapshot'),
        ]);

        return response()->json([
            'message' => 'Simulacao salva com sucesso.',
            'simulation' => $simulation,
        ], 201);
    }

    public function listFinancialSimulations(): JsonResponse
    {
        $items = FinancialSimulation::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get(['id', 'title', 'inputs', 'result_snapshot', 'created_at']);

        return response()->json([
            'items' => $items,
        ]);
    }
}
