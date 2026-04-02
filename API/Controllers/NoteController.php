<?php

declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller — Notes (F1)
 * GET    /v1/notes          — liste paginée + filtres
 * GET    /v1/notes/:id      — détail
 * POST   /v1/notes          — créer
 * PUT    /v1/notes/:id      — modifier
 * DELETE /v1/notes/:id      — supprimer
 * GET    /v1/notes/stats    — stats par classe/trimestre
 */
class NoteController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();
        $this->authorize('notes.view');

        [$page, $perPage] = $this->pagination();

        $filters = array_filter([
            'classe'        => $this->query('classe'),
            'matiere_id'    => $this->query('matiere_id'),
            'professeur_id' => $this->query('professeur_id'),
            'trimestre'     => $this->query('trimestre'),
            'date_from'     => $this->query('date_from'),
            'date_to'       => $this->query('date_to'),
            'eleve_id'      => $this->query('eleve_id'),
        ]);

        $result = app('notes')->getFiltered($filters, $page, $perPage);

        $this->paginated($result['data'], $result['total'], $page, $perPage);
    }

    public function show(array $params): void
    {
        $this->authenticate();
        $this->authorize('notes.view');

        $note = app('notes')->getById((int) $params['id']);
        if (!$note) {
            $this->error('Note not found', 404);
        }

        $this->json($note);
    }

    public function store(): void
    {
        $this->authenticate();
        $this->authorize('notes.create');

        $data = $this->jsonBody();
        $required = ['id_eleve', 'id_matiere', 'id_professeur', 'note', 'date_note', 'trimestre'];
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                $this->error("Field '{$field}' is required", 422);
            }
        }

        $id = app('notes')->create($data);
        $this->json(['id' => $id], 201);
    }

    public function update(array $params): void
    {
        $this->authenticate();
        $this->authorize('notes.manage');

        $id = (int) $params['id'];
        $data = $this->jsonBody();

        if (empty($data)) {
            $this->error('No data provided', 422);
        }

        $updated = app('notes')->update($id, $data);
        if (!$updated) {
            $this->error('Note not found or no changes', 404);
        }

        $this->json(['updated' => true]);
    }

    public function destroy(array $params): void
    {
        $this->authenticate();
        $this->authorize('notes.manage');

        $deleted = app('notes')->delete((int) $params['id']);
        if (!$deleted) {
            $this->error('Note not found', 404);
        }

        $this->json(['deleted' => true]);
    }

    public function stats(): void
    {
        $this->authenticate();
        $this->authorize('notes.view');

        $classe = $this->query('classe');
        $trimestre = (int) $this->query('trimestre', 1);
        $eleveId = $this->query('eleve_id');

        if ($eleveId) {
            $this->jsonCached(app('notes')->getStatsByEleve((int) $eleveId), 120);
        }

        if ($classe) {
            $this->jsonCached(app('notes')->getStatsByClasse($classe, $trimestre), 120);
        }

        $moyenne = app('notes')->getMoyenneGenerale($trimestre);
        $this->jsonCached(['moyenne_generale' => $moyenne, 'trimestre' => $trimestre], 60);
    }
}
