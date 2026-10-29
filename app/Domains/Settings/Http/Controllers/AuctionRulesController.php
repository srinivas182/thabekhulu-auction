<?php

namespace App\Domains\Settings\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\Bidding\Support\StepUp;
use App\Domains\Settings\Settings\AuctionSettings;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Auction rules: bid confirmation codes, increment bands, soft close and the closing-soon reminder. */
class AuctionRulesController extends Controller
{
    public function edit(AuctionSettings $s): Response
    {
        return Inertia::render('admin/settings/auction-rules', ['rules' => [
            'step_up_mode' => StepUp::mode(), 'step_up_threshold' => $s->step_up_threshold,
            'increment_bands' => array_map(fn (array $b) => ['upTo' => $b[0], 'step' => $b[1]], $s->increment_bands),
            'soft_close_enabled' => $s->soft_close_enabled, 'soft_close_window_minutes' => $s->soft_close_window_minutes,
            'soft_close_extension_minutes' => $s->soft_close_extension_minutes, 'closing_soon_hours' => $s->closing_soon_hours,
        ]]);
    }

    public function update(Request $request, AuctionSettings $s, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate([
            'step_up_mode' => ['required', Rule::in(StepUp::MODES)],
            'step_up_threshold' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'increment_bands' => ['required', 'array', 'min:1', 'max:12'],
            'increment_bands.*.upTo' => ['nullable', 'integer', 'min:1'],
            'increment_bands.*.step' => ['required', 'integer', 'min:1', 'max:100000000'],
            'soft_close_enabled' => ['required', 'boolean'],
            'soft_close_window_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'soft_close_extension_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'closing_soon_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);

        // Bands go up in order; only the last is open-ended ("and above").
        $bands = array_values($data['increment_bands']);
        $previous = 0;
        foreach ($bands as $i => $band) {
            $last = $i === count($bands) - 1;
            if ($last !== ($band['upTo'] === null) || (! $last && (int) $band['upTo'] <= $previous)) {
                throw ValidationException::withMessages(['increment_bands' => __('Each band must go up from the one before, and only the last band is “and above”.')]);
            }
            $previous = (int) ($band['upTo'] ?? $previous);
        }

        $before = ['mode' => StepUp::mode(), 'bands' => $s->increment_bands];
        $s->step_up_mode = $data['step_up_mode'];
        $s->step_up_threshold = (int) $data['step_up_threshold'];
        $s->increment_bands = array_map(fn (array $b) => [$b['upTo'] === null ? null : (int) $b['upTo'], (int) $b['step']], $bands);
        $s->soft_close_enabled = (bool) $data['soft_close_enabled'];
        $s->soft_close_window_minutes = (int) $data['soft_close_window_minutes'];
        $s->soft_close_extension_minutes = (int) $data['soft_close_extension_minutes'];
        $s->closing_soon_hours = (int) $data['closing_soon_hours'];
        $s->save();

        Audit::record('settings', 'auction_rules_updated', 'Updated the auction rules', null, $actor, ['before' => $before, 'after' => ['mode' => $s->step_up_mode, 'bands' => $s->increment_bands]]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Auction rules saved. They apply to bids from now on.')]);

        return back();
    }
}
