<?php

namespace App\Http\Controllers;

use App\Models\NatureAppui;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paramètres > natures d'appui du parrainage (santé, alimentaire…) : ajout, libellé, ordre, activation.
 * Les natures « système » (scolaire, formation professionnelle, autre) restent toujours proposées.
 */
class NatureAppuiController extends Controller
{
    public function index(): View
    {
        return view('parametres.natures-appui.index', [
            'natures' => NatureAppui::orderBy('ordre')->orderBy('libelle')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:100', Rule::unique('natures_appui', 'libelle')],
        ], $this->messages());

        // Code repris dans les imports d'appuis, tiré du libellé (ex. « Kit d'hygiène » => kit_d_hygiene)
        $base = Str::limit(Str::slug($donnees['libelle'], '_'), 35, '') ?: 'nature';
        $code = $base;
        for ($i = 2; NatureAppui::where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        NatureAppui::create([
            'code' => $code,
            'libelle' => $donnees['libelle'],
            'scolaire' => false,
            'ordre' => (int) NatureAppui::max('ordre') + 1,
            'actif' => true,
        ]);

        return redirect()->route('parametres.natures-appui.index')->with('success', "Nature « {$donnees['libelle']} » ajoutée.");
    }

    public function update(Request $request, NatureAppui $nature): RedirectResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:100', Rule::unique('natures_appui', 'libelle')->ignore($nature->id)],
            'ordre' => ['required', 'integer', 'min:0', 'max:999'],
            'actif' => ['nullable', 'boolean'],
        ], $this->messages());

        $nature->update([
            // Libellé des types d'appui des sessions : repris tel quel
            'libelle' => in_array($nature->code, NatureAppui::LIBELLE_FIXE, true) ? $nature->libelle : $donnees['libelle'],
            'ordre' => $donnees['ordre'],
            'actif' => $nature->estSysteme() || $request->boolean('actif'),
        ]);

        return redirect()->route('parametres.natures-appui.index')->with('success', "Nature « {$nature->libelle} » mise à jour.");
    }

    public function destroy(NatureAppui $nature): RedirectResponse
    {
        if ($nature->estSysteme()) {
            return back()->with('error', "« {$nature->libelle} » est utilisée par les règles de l’application : elle ne peut pas être supprimée.");
        }
        if ($nombre = $nature->nombreUtilisations()) {
            return back()->with('error', "« {$nature->libelle} » est utilisée par {$nombre} appui(s) ou engagement(s) : désactivez-la plutôt, elle ne sera plus proposée.");
        }

        $nature->delete();

        return redirect()->route('parametres.natures-appui.index')->with('success', "Nature « {$nature->libelle} » supprimée.");
    }

    private function messages(): array
    {
        return [
            'libelle.required' => 'Saisissez le libellé.',
            'libelle.unique' => 'Cette nature d’appui existe déjà.',
        ];
    }
}
