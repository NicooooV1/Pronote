<?php

declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller — Absences & Retards (F2)
 */
class AbsenceController extends BaseController
{
    public function indexAbsences(): void
    {
        $this->authenticate();
        $this->authorize('absences.view');

        [$page, $perPage] = $this->pagination();

        $filters = array_filter([
            'classe'   => $this->query('classe'),
            'eleve_id' => $this->query('eleve_id'),
            'justifie' => $this->query('justifie'),
            'date_from' => $this->query('date_from'),
            'date_to'   => $this->query('date_to'),
        ], fn($v) => $v !== null && $v !== '');

        $result = app('absences')->getAbsences($filters, $page, $perPage);
        $this->paginated($result['data'], $result['total'], $page, $perPage);
    }

    public function indexRetards(): void
    {
        $this->authenticate();
        $this->authorize('absences.view');

        [$page, $perPage] = $this->pagination();

        $filters = array_filter([
            'classe'   => $this->query('classe'),
            'eleve_id' => $this->query('eleve_id'),
            'justifie' => $this->query('justifie'),
            'date_from' => $this->query('date_from'),
            'date_to'   => $this->query('date_to'),
        ], fn($v) => $v !== null && $v !== '');

        $result = app('absences')->getRetards($filters, $page, $perPage);
        $this->paginated($result['data'], $result['total'], $page, $perPage);
    }

    public function storeAbsence(): void
    {
        $this->authenticate();
        $this->authorize('absences.create');

        $data = $this->jsonBody();
        $id = app('absences')->createAbsence($data);
        $this->json(['id' => $id], 201);
    }

    public function storeRetard(): void
    {
        $this->authenticate();
        $this->authorize('absences.create');

        $data = $this->jsonBody();
        $id = app('absences')->createRetard($data);
        $this->json(['id' => $id], 201);
    }

    public function deleteAbsence(array $params): void
    {
        $this->authenticate();
        $this->authorize('absences.manage');

        $deleted = app('absences')->deleteAbsence((int) $params['id']);
        if (!$deleted) {
            $this->error('Absence not found', 404);
        }
        $this->json(['deleted' => true]);
    }

    public function deleteRetard(array $params): void
    {
        $this->authenticate();
        $this->authorize('absences.manage');

        $deleted = app('absences')->deleteRetard((int) $params['id']);
        if (!$deleted) {
            $this->error('Retard not found', 404);
        }
        $this->json(['deleted' => true]);
    }

    public function statsToday(): void
    {
        $this->authenticate();
        $this->authorize('absences.view');

        $this->jsonCached(app('absences')->getStatsToday(), 30);
    }
}
