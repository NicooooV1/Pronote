<?php

declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller — Périodes (F7)
 */
class PeriodeController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();
        $this->jsonCached(app('periodes')->getAll(), 600);
    }

    public function show(array $params): void
    {
        $this->authenticate();

        $periode = app('periodes')->getById((int) $params['id']);
        if (!$periode) {
            $this->error('Période not found', 404);
        }

        $this->json($periode);
    }

    public function current(): void
    {
        $this->authenticate();

        $periode = app('periodes')->getCurrent();
        if (!$periode) {
            $this->error('No active period', 404);
        }

        $this->jsonCached($periode, 3600);
    }

    public function store(): void
    {
        $this->authenticate();
        $this->authorize('periodes.manage');

        $data = $this->jsonBody();
        foreach (['nom', 'numero', 'type', 'date_debut', 'date_fin'] as $field) {
            if (empty($data[$field])) {
                $this->error("Field '{$field}' is required", 422);
            }
        }

        $id = app('periodes')->create($data);
        $this->json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        $this->authenticate();
        $this->authorize('periodes.manage');

        $updated = app('periodes')->update((int) $params['id'], $this->jsonBody());
        if (!$updated) {
            $this->error('Période not found or no changes', 404);
        }

        $this->json(['updated' => true]);
    }

    public function destroy(array $params): void
    {
        $this->authenticate();
        $this->authorize('periodes.manage');

        $deleted = app('periodes')->delete((int) $params['id']);
        if (!$deleted) {
            $this->error('Période not found', 404);
        }

        $this->json(['deleted' => true]);
    }

    public function overlaps(): void
    {
        $this->authenticate();
        $this->json(app('periodes')->detectOverlaps());
    }
}
