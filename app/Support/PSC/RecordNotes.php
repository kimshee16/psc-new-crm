<?php

namespace App\Support\PSC;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordNotes
{
    public function version(?string $stored): string
    {
        return hash('sha256', (string) $stored);
    }

    public function all(?string $stored): array
    {
        if (trim((string) $stored) === '') {
            return [];
        }

        $decoded = json_decode($stored, true);
        // Keep a legacy free-text note intact, including paragraph breaks.
        $rows = is_array($decoded) && array_is_list($decoded) ? $decoded : [['body' => $stored]];
        $notes = [];
        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : ['body' => (string) $row];
            if (trim((string) ($row['body'] ?? '')) === '') {
                continue;
            }
            $row['id'] ??= hash('sha256', $index.json_encode($row));
            $row['author'] = trim((string) ($row['author'] ?? '')) ?: 'Author not recorded';
            $row['datetime'] = $row['datetime'] ?? null;
            $notes[] = $row;
        }

        // PHP's stable sort preserves the stored order when timestamps tie.
        usort($notes, fn ($a, $b) => $this->timestamp($b['datetime']) <=> $this->timestamp($a['datetime']));

        return $notes;
    }

    private function timestamp(?string $value): int
    {
        try {
            return $value ? Carbon::parse($value)->getTimestamp() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function displayDate(?string $value): string
    {
        return $this->timestamp($value) ? Carbon::parse($value)->format('d M Y, H:i T') : 'Date not recorded';
    }

    public function mutate(Model $record, User $user, string $version, string $action, ?string $id, ?string $body): Model
    {
        return DB::transaction(function () use ($record, $user, $version, $action, $id, $body) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            abort_unless(hash_equals($this->version($record->notes), $version), 409,
                'Notes changed since you opened this page. Copy your draft, then refresh the page and try again.');
            $notes = $this->all($record->notes);
            if ($action === 'store') {
                if (count($notes) >= 50) {
                    throw ValidationException::withMessages(['body' => 'A record can have up to 50 notes.']);
                }
                array_unshift($notes, [
                    'id' => (string) Str::uuid(), 'body' => trim($body),
                    'author' => $user->name, 'user_id' => $user->id,
                    'datetime' => now()->toIso8601String(),
                ]);
            } else {
                $index = array_search($id, array_column($notes, 'id'), true);
                abort_if($index === false, 404);
                if ($action === 'destroy') {
                    array_splice($notes, $index, 1);
                } else {
                    $notes[$index]['body'] = trim($body);
                    $notes[$index]['edited_by'] = $user->name;
                    $notes[$index]['edited_at'] = now()->toIso8601String();
                }
            }
            $encoded = json_encode($notes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (strlen($encoded) > 60000) {
                throw ValidationException::withMessages(['body' => 'These notes exceed the record storage limit. Shorten the note and try again.']);
            }
            $record->update(['notes' => $encoded]);

            return $record;
        });
    }
}
