<?php

declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller — Matières (F6)
 */
class MatiereController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();
        $this->jsonCached(app('matieres')->getAll(), 300);
    }

    public function show(array $params): void
    {
        $this->authenticate();

        $matiere = app('matieres')->getById((int) $params['id']);
        if (!$matiere) {
            $this->error('Matière not found', 404);
        }

        $this->json($matiere);
    }

    public function store(): void
    {
        $this->authenticate();
        $this->authorize('matieres.manage');

        $data = $this->jsonBody();
        foreach (['nom', 'code', 'coefficient', 'couleur'] as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                $this->error("Field '{$field}' is required", 422);
            }
        }

        $id = app('matieres')->create($data);
        $this->json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        $this->authenticate();
        $this->authorize('matieres.manage');

        $id = (int) $params['id'];
        $data = $this->jsonBody();

        $updated = app('matieres')->update($id, $data);
        if (!$updated) {
            $this->error('Matière not found or no changes', 404);
        }

        $this->json(['updated' => true]);
    }

    public function destroy(array $params): void
    {
        $this->authenticate();
        $this->authorize('matieres.manage');

        try {
            $deleted = app('matieres')->delete((int) $params['id']);
            if (!$deleted) {
                $this->error('Matière not found', 404);
            }
            $this->json(['deleted' => true]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage(), 409);
        }
    }
}
