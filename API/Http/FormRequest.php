<?php

declare(strict_types=1);

namespace API\Http;

/**
 * Classe abstraite pour la validation des requêtes API.
 *
 * Chaque endpoint peut étendre cette classe pour déclarer ses règles de validation.
 * La validation utilise le Validator existant du framework.
 *
 * Usage :
 *   class CreateNoteRequest extends FormRequest {
 *       public function rules(): array {
 *           return [
 *               'id_eleve'   => 'required|integer',
 *               'note'       => 'required|numeric',
 *               'date_note'  => 'required|date',
 *           ];
 *       }
 *   }
 *
 *   $data = (new CreateNoteRequest())->validate();
 */
abstract class FormRequest
{
    /**
     * Règles de validation (format Validator).
     * @return array<string, string|array>
     */
    abstract public function rules(): array;

    /**
     * Messages d'erreur personnalisés (optionnel).
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Valide les données de la requête.
     * Retourne les données validées ou arrête avec une 422.
     *
     * @return array Données validées
     */
    public function validate(): array
    {
        $data = $this->resolveInput();

        $validator = app('validator');
        if (!$validator->validate($data, $this->rules())) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode([
                'error'  => 'Validation failed',
                'errors' => $validator->errors(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Return only the fields declared in rules
        return array_intersect_key($data, $this->rules());
    }

    /**
     * Fusionne les sources de données (GET, JSON body, POST).
     */
    protected function resolveInput(): array
    {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        return array_merge($_GET, is_array($json) ? $json : [], $_POST);
    }
}
