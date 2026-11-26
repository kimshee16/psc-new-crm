<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ApplicationCatalogEntry extends Model
{
    public const LABELS = [
        'actions' => 'Actions',
        'modules' => 'Modules',
        'required_documents' => 'Documents',
    ];

    public const DOCUMENT_TYPES = [
        'Identity', 'Education', 'English', 'Financial', 'Character and health',
        'Employment and skills', 'Relationship',
    ];

    public $timestamps = false;

    protected $fillable = ['catalog', 'type', 'name'];

    public static function catalogs(): Collection
    {
        return self::query()->orderBy('type')->orderBy('name')->get()->groupBy('catalog');
    }

    public function selectionValue(): string
    {
        return $this->catalog === 'required_documents' ? 'document:'.$this->id : $this->name;
    }
}
