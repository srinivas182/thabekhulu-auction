<?php

namespace App\Domains\DataRoom\Http\Controllers;

use App\Domains\Audit\Support\Audit;
use App\Domains\DataRoom\Settings\DataRoomSettings;
use App\Domains\Listings\Support\HtmlCleaner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Data-room NDA text and download alert (settings.manage). Changing the text creates a new version. */
class NdaSettingsController extends Controller
{
    public function edit(DataRoomSettings $settings): Response
    {
        return Inertia::render('admin/dataroom/settings', [
            'settings' => ['nda_text' => $settings->nda_text, 'nda_version' => $settings->nda_version, 'daily_download_alert' => $settings->daily_download_alert],
        ]);
    }

    public function update(Request $request, DataRoomSettings $settings, #[CurrentUser] User $actor): RedirectResponse
    {
        $data = $request->validate(['nda_text' => ['required', 'string', 'max:100000'], 'daily_download_alert' => ['required', 'integer', 'min:1', 'max:1000']]);
        $text = (string) HtmlCleaner::clean($data['nda_text']);
        $changed = trim(strip_tags($text)) !== trim(strip_tags($settings->nda_text));

        if ($changed) {
            $settings->nda_text = $text;
            $settings->nda_version++;
        }
        $settings->daily_download_alert = (int) $data['daily_download_alert'];
        $settings->save();

        Audit::record('dataroom', 'nda_updated', $changed ? "Published NDA version {$settings->nda_version}" : 'Updated data room settings', null, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $changed ? __('NDA version :v published. Bidders will accept it again before opening data rooms.', ['v' => $settings->nda_version]) : __('Saved.')]);

        return back();
    }
}
