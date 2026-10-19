<?php

// The rules this app uses, in Spanish. Filament shows each message under its own field, so they
// do not repeat the field's name. Anything missing falls back to English (APP_FALLBACK_LOCALE).
return [
    'accepted' => 'Hay que aceptarlo.',
    'array' => 'Tiene que ser una lista.',
    'between' => [
        'array' => 'Tiene que tener entre :min y :max elementos.',
        'numeric' => 'Tiene que estar entre :min y :max.',
        'string' => 'Tiene que tener entre :min y :max caracteres.',
    ],
    'boolean' => 'Tiene que ser sí o no.',
    'decimal' => 'Tiene que tener :decimal decimales.',
    'distinct' => 'Hay un valor repetido.',
    'exists' => 'Ese valor no existe.',
    'filled' => 'No puede estar vacío.',
    'gt' => ['numeric' => 'Tiene que ser mayor que :value.'],
    'gte' => ['numeric' => 'Tiene que ser :value o más.'],
    'in' => 'Ese valor no es válido.',
    'integer' => 'Tiene que ser un número entero.',
    'lt' => ['numeric' => 'Tiene que ser menor que :value.'],
    'lte' => ['numeric' => 'Tiene que ser :value o menos.'],
    'max' => [
        'array' => 'No puede tener más de :max elementos.',
        'numeric' => 'No puede ser mayor que :max.',
        'string' => 'No puede tener más de :max caracteres.',
    ],
    'min' => [
        'array' => 'Tiene que tener al menos :min elementos.',
        'numeric' => 'Tiene que ser al menos :min.',
        'string' => 'Tiene que tener al menos :min caracteres.',
    ],
    'numeric' => 'Tiene que ser un número.',
    'regex' => 'El formato no es válido.',
    'required' => 'Este campo es obligatorio.',
    'string' => 'Tiene que ser texto.',
    'unique' => 'Ya existe otro con ese valor.',

    'custom' => [],
    'attributes' => [],
];
