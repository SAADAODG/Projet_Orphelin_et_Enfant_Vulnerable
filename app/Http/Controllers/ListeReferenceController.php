<?php

namespace App\Http\Controllers;

use App\Models\ListeReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Paramètres > listes de choix de la fiche OEV (groupes de population, qui s'occupe de l'enfant,
 * conditions de vie, situations de vulnérabilité) : ajout, libellé, ordre, activation.
 */
class ListeReferenceController extends Controller
{
    public function index(string $liste): View
    {
        return view('parametres.listes.index', [
            'liste' => $liste,
            'config' => ListeReference::listes()[$liste],
            'elements' => ListeReference::where('liste', $liste)->orderBy('ordre')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, string $liste): RedirectResponse
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:255', Rule::unique('listes_reference', 'libelle')->where('liste', $liste)],
        ], $this->messages());

        // Code enregistré dans les dossiers, tiré du libellé (ex. « Enfant talibé » => enfant_talibe)
        $base = Str::limit(Str::slug($donnees['libelle'], '_'), 50, '') ?: 'choix';
        $code = $base;
        for ($i = 2; ListeReference::where('liste', $liste)->where('code', $code)->exists(); $i++) {
            $code = "{$base}_{$i}";
        }

        ListeReference::create([
            'liste' => $liste,
            'code' => $code,
            'libelle' => $donnees['libelle'],
            'ordre' => (int) ListeReference::where('liste', $liste)->max('ordre') + 1,
            'actif' => true,
            'systeme' => false,
        ]);

        return redirect()->route('parametres.listes.index', $liste)->with('success', "« {$donnees['libelle']} » ajouté à la liste.");
    }

    public function update(Request $request, string $liste, ListeReference $element): RedirectResponse
    {
        abort_unless($element->liste === $liste, 404);

        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'max:255', Rule::unique('listes_reference', 'libelle')->where('liste', $liste)->ignore($element->id)],
            'ordre' => ['required', 'integer', 'min:0', 'max:999'],
            'actif' => ['nullable', 'boolean'],
        ], $this->messages());

        $element->update([
            'libelle' => $donnees['libelle'],
            'ordre' => $donnees['ordre'],
            // Un choix utilisé par les règles de l'application reste proposé
            'actif' => $element->systeme || $request->boolean('actif'),
        ]);

        return redirect()->route('parametres.listes.index', $liste)->with('success', "« {$element->libelle} » mis à jour.");
    }

    public function destroy(string $liste, ListeReference $element): RedirectResponse
    {
        abort_unless($element->liste === $liste, 404);

        if ($element->systeme) {
            return back()->with('error', "« {$element->libelle} » est utilisé par les règles de l’application : il ne peut pas être supprimé (son libellé reste modifiable).");
        }
        if ($nombre = $element->nombreDeDossiers()) {
            return back()->with('error', "« {$element->libelle} » est choisi dans {$nombre} dossier(s) : désactivez-le plutôt, il ne sera plus proposé à la saisie.");
        }

        $element->delete();

        return redirect()->route('parametres.listes.index', $liste)->with('success', "« {$element->libelle} » supprimé de la liste.");
    }

    private function messages(): array
    {
        return [
            'libelle.required' => 'Saisissez le libellé.',
            'libelle.unique' => 'Ce libellé existe déjà dans la liste.',
        ];
    }
}
