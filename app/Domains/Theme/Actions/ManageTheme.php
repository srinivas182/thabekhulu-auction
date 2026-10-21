<?php

namespace App\Domains\Theme\Actions;

use App\Domains\Audit\Support\Audit;
use App\Domains\Theme\Models\ThemeVersion;
use App\Domains\Theme\Support\ThemeChecks;
use App\Domains\Theme\Support\ThemePalette;
use App\Domains\Theme\Support\ThemeRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Theme workflow: save a draft, publish it, restore an earlier version or
 * reset to the brand. Nothing reaches the live site unless it passes every
 * contrast check.
 */
class ManageTheme
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function saveDraft(User $actor, string $scope, array $values): ThemeVersion
    {
        $this->ensurePasses($values);

        $draft = $this->draft($scope) ?? new ThemeVersion(['scope' => $scope, 'status' => 'draft', 'created_by' => $actor->id]);
        $draft->values = $values;
        $draft->save();

        return $draft;
    }

    public function publish(User $actor, string $scope): ThemeVersion
    {
        $draft = $this->draft($scope);

        if ($draft === null) {
            throw ValidationException::withMessages(['theme' => __('There is no draft to publish.')]);
        }

        $this->ensurePasses($draft->values);

        return $this->makeLive($actor, $scope, $draft, 'Published theme changes');
    }

    public function restore(User $actor, string $scope, ThemeVersion $version): ThemeVersion
    {
        abort_unless($version->scope === $scope && $version->status !== 'draft', 404);

        $copy = new ThemeVersion([
            'scope' => $scope,
            'status' => 'draft',
            'values' => $version->values,
            'note' => "Restored version {$version->id}",
            'created_by' => $actor->id,
        ]);

        return $this->makeLive($actor, $scope, $copy, "Restored theme version {$version->id}");
    }

    public function reset(User $actor, string $scope): ThemeVersion
    {
        $values = ThemePalette::defaults();

        $version = new ThemeVersion([
            'scope' => $scope,
            'status' => 'draft',
            'values' => $values,
            'note' => 'Reset to brand defaults',
            'created_by' => $actor->id,
        ]);

        return $this->makeLive($actor, $scope, $version, 'Reset theme to brand defaults');
    }

    public function discardDraft(string $scope): void
    {
        $this->draft($scope)?->delete();
    }

    public function draft(string $scope): ?ThemeVersion
    {
        return ThemeVersion::where('scope', $scope)->where('status', 'draft')->latest('id')->first();
    }

    private function makeLive(User $actor, string $scope, ThemeVersion $version, string $description): ThemeVersion
    {
        $before = ThemeRepository::published($scope);

        DB::transaction(function () use ($actor, $scope, $version): void {
            ThemeVersion::where('scope', $scope)->where('status', 'published')->update(['status' => 'archived']);

            // Restoring or resetting replaces any open draft.
            ThemeVersion::where('scope', $scope)->where('status', 'draft')->whereKeyNot($version->id ?? 0)->delete();

            $version->forceFill([
                'status' => 'published',
                'published_by' => $actor->id,
                'published_at' => now(),
            ])->save();
        });

        ThemeRepository::forget($scope);

        Audit::record('theme', 'published', "{$description} ({$this->label($scope)})", $version, $actor, [
            'scope' => $scope,
            'old' => $this->summary($before),
            'attributes' => $this->summary($version->values),
        ]);

        return $version;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function ensurePasses(array $values): void
    {
        $failures = ThemeChecks::failures($values);

        if ($failures !== []) {
            throw ValidationException::withMessages([
                'contrast' => trans_choice(
                    '{1} One colour pair is too hard to read. Fix it before saving.|[2,*] :count colour pairs are too hard to read. Fix them before saving.',
                    count($failures),
                ),
            ]);
        }
    }

    /**
     * Compact before/after for the audit log.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function summary(array $values): array
    {
        return [
            'light' => $values['light'] ?? null,
            'dark' => $values['dark'] ?? null,
            'fonts' => $values['fonts'] ?? null,
            'radius' => $values['radius'] ?? null,
            'logo_light' => $values['logo_light'] ?? null,
            'logo_dark' => $values['logo_dark'] ?? null,
            'favicon' => $values['favicon'] ?? null,
            'favicon_apple' => $values['favicon_apple'] ?? null,
        ];
    }

    private function label(string $scope): string
    {
        return $scope === 'admin' ? 'back office' : 'website';
    }
}
