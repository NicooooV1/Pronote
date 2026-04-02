<?php

declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller — Événements (F5)
 */
class EvenementController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();

        [$page, $perPage] = $this->pagination();

        $filters = array_filter([
            'type'   => $this->query('type'),
            'status' => $this->query('status'),
        ]);

        $result = app('evenements')->getFiltered($filters, $page, $perPage);
        $this->paginated($result['data'], $result['total'], $page, $perPage);
    }

    public function show(array $params): void
    {
        $this->authenticate();

        $event = app('evenements')->getById((int) $params['id']);
        if (!$event) {
            $this->error('Événement not found', 404);
        }
        $this->json($event);
    }

    public function store(): void
    {
        $this->authenticate();
        $this->authorize('evenements.create');

        $data = $this->jsonBody();
        if (empty($data['titre']) || empty($data['date_debut']) || empty($data['date_fin']) || empty($data['type_evenement'])) {
            $this->error('Fields titre, date_debut, date_fin, type_evenement are required', 422);
        }

        $id = app('evenements')->create($data);
        $this->json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        $this->authenticate();
        $this->authorize('evenements.manage');

        $updated = app('evenements')->update((int) $params['id'], $this->jsonBody());
        if (!$updated) {
            $this->error('Événement not found or no changes', 404);
        }
        $this->json(['updated' => true]);
    }

    public function destroy(array $params): void
    {
        $this->authenticate();
        $this->authorize('evenements.manage');

        $deleted = app('evenements')->delete((int) $params['id']);
        if (!$deleted) {
            $this->error('Événement not found', 404);
        }
        $this->json(['deleted' => true]);
    }

    public function toggleStatus(array $params): void
    {
        $this->authenticate();
        $this->authorize('evenements.manage');

        $toggled = app('evenements')->toggleStatus((int) $params['id']);
        if (!$toggled) {
            $this->error('Événement not found', 404);
        }
        $this->json(['toggled' => true]);
    }

    public function types(): void
    {
        $this->authenticate();
        $this->jsonCached(app('evenements')->getDistinctTypes(), 300);
    }
}
